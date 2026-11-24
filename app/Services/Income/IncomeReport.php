<?php

namespace App\Services\Income;

use App\Enums\PaymentMethodEnum;
use App\Enums\SalePaymentStatusEnum;
use App\Enums\SaleStatusEnum;
use App\Models\Payment;
use App\Models\SalePayment;
use App\Services\Kiosk\SaleQuote;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Ingresos consolidados: cuotas y kiosco juntos, para rendir la caja sin abrirla ni cerrarla.
 *
 * Las tablas siguen separadas (payments para cuotas, sale_payments para el kiosco); este reporte
 * las lee juntas. Del kiosco cuenta solo lo aprobado de ventas no anuladas.
 */
class IncomeReport
{
    /**
     * Ingresos de un período, del más nuevo al más viejo.
     *
     * Precondiciones:
     * - $from y $to son fechas; se toman los días completos (desde las 00:00 de $from hasta las
     *   23:59:59 de $to).
     *
     * Postcondiciones:
     * - Devuelve los pagos de cuotas (por fecha_pago) y los cobros aprobados del kiosco de ventas
     *   no anuladas (por pagado_at), de todos o solo de $userId. No modifica nada.
     *
     * @param  CarbonInterface  $from  Primer día.
     * @param  CarbonInterface  $to  Último día.
     * @param  int|null  $userId  Solo lo que cobró esta persona (null = todos).
     * @return Collection<int, IncomeEntry>
     */
    public function entries(CarbonInterface $from, CarbonInterface $to, ?int $userId = null): Collection
    {
        // Días completos.
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        // Cuotas.
        $cuotas = Payment::query()
            ->with(['client', 'registradoPor'])
            ->whereBetween('fecha_pago', [$start, $end])
            ->when($userId !== null, fn (Builder $query): Builder => $query->where('registrado_por_user_id', $userId))
            ->get()
            ->map(fn (Payment $payment): IncomeEntry => new IncomeEntry(
                fecha: $payment->fecha_pago,
                origen: IncomeEntry::CUOTA,
                metodo: $payment->metodo_pago,
                cents: SaleQuote::toCents($payment->monto),
                userId: $payment->registrado_por_user_id,
                userName: $payment->registradoPor?->name ?? '—',
                detalle: $payment->client ? "{$payment->client->apellido}, {$payment->client->nombre}" : 'Cuota',
            ));

        // Kiosco: cobros aprobados de ventas vigentes.
        $kiosco = SalePayment::query()
            ->with(['sale.client', 'registradoPor'])
            ->where('estado', SalePaymentStatusEnum::Aprobado)
            ->whereHas('sale', fn (Builder $query): Builder => $query->where('estado', '!=', SaleStatusEnum::Anulada))
            ->whereBetween('pagado_at', [$start, $end])
            ->when($userId !== null, fn (Builder $query): Builder => $query->where('registrado_por_user_id', $userId))
            ->get()
            ->map(fn (SalePayment $payment): IncomeEntry => new IncomeEntry(
                fecha: $payment->pagado_at,
                origen: IncomeEntry::KIOSCO,
                metodo: $payment->metodo,
                cents: SaleQuote::toCents($payment->monto),
                userId: $payment->registrado_por_user_id,
                userName: $payment->registradoPor?->name ?? 'Pasarela',
                detalle: "Venta #{$payment->sale_id}".($payment->sale?->client ? " · {$payment->sale->client->apellido}" : ''),
            ));

        // Juntos, lo más nuevo primero.
        return $cuotas->concat($kiosco)
            ->sortByDesc(fn (IncomeEntry $entry): int => $entry->fecha->getTimestamp())
            ->values();
    }

    /**
     * Totales en centavos por origen y medio de pago.
     *
     * Postcondiciones:
     * - Devuelve [origen => [valor del medio => centavos]] con todos los orígenes y medios (en 0 si
     *   no hubo), más 'total' por medio y el total general en ['total']['total']. No modifica nada.
     *
     * @param  Collection<int, IncomeEntry>  $entries  Ingresos.
     * @return array<string, array<string, int>>
     */
    public function byOriginAndMethod(Collection $entries): array
    {
        // Columnas: cada medio y el total.
        $columns = [...array_map(fn (PaymentMethodEnum $metodo): string => $metodo->value, PaymentMethodEnum::cases()), 'total'];
        // Filas en cero.
        $totals = [];
        foreach ([IncomeEntry::CUOTA, IncomeEntry::KIOSCO, 'total'] as $row) {
            $totals[$row] = array_fill_keys($columns, 0);
        }

        // Suma cada ingreso en su fila, su columna y los totales.
        foreach ($entries as $entry) {
            foreach ([$entry->origen, 'total'] as $row) {
                $totals[$row][$entry->metodo->value] += $entry->cents;
                $totals[$row]['total'] += $entry->cents;
            }
        }

        return $totals;
    }

    /**
     * Totales en centavos por persona y medio de pago.
     *
     * Postcondiciones:
     * - Devuelve una fila por persona, ordenadas por nombre: ['name', 'cuota', 'kiosco', valor de
     *   cada medio, 'total']. No modifica nada.
     *
     * @param  Collection<int, IncomeEntry>  $entries  Ingresos.
     * @return list<array<string, int|string>>
     */
    public function byUser(Collection $entries): array
    {
        return $this->groupedTotals($entries, fn (IncomeEntry $entry): string => (string) ($entry->userId ?? 0), fn (IncomeEntry $entry): string => $entry->userName, 'name');
    }

    /**
     * Totales en centavos por día y medio de pago.
     *
     * Postcondiciones:
     * - Devuelve una fila por día con ingresos, del más nuevo al más viejo: ['day' (Y-m-d),
     *   'cuota', 'kiosco', valor de cada medio, 'total']. No modifica nada.
     *
     * @param  Collection<int, IncomeEntry>  $entries  Ingresos.
     * @return list<array<string, int|string>>
     */
    public function byDay(Collection $entries): array
    {
        // Agrupa por fecha y ordena del más nuevo al más viejo.
        $rows = $this->groupedTotals($entries, fn (IncomeEntry $entry): string => $entry->fecha->format('Y-m-d'), fn (IncomeEntry $entry): string => $entry->fecha->format('Y-m-d'), 'day');
        usort($rows, fn (array $a, array $b): int => strcmp((string) $b['day'], (string) $a['day']));

        return $rows;
    }

    /**
     * Agrupa ingresos y suma por origen y por medio.
     *
     * @param  Collection<int, IncomeEntry>  $entries  Ingresos.
     * @param  callable(IncomeEntry): string  $key  Clave del grupo.
     * @param  callable(IncomeEntry): string  $label  Texto del grupo.
     * @param  string  $labelColumn  Nombre de la columna del texto.
     * @return list<array<string, int|string>>
     */
    private function groupedTotals(Collection $entries, callable $key, callable $label, string $labelColumn): array
    {
        // Fila vacía.
        $empty = [IncomeEntry::CUOTA => 0, IncomeEntry::KIOSCO => 0, ...array_fill_keys(array_map(fn (PaymentMethodEnum $metodo): string => $metodo->value, PaymentMethodEnum::cases()), 0), 'total' => 0];
        $rows = [];

        foreach ($entries as $entry) {
            // Grupo del ingreso.
            $group = $key($entry);
            $rows[$group] ??= [$labelColumn => $label($entry), ...$empty];
            // Suma por origen, por medio y total.
            $rows[$group][$entry->origen] += $entry->cents;
            $rows[$group][$entry->metodo->value] += $entry->cents;
            $rows[$group]['total'] += $entry->cents;
        }

        // Por texto.
        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b): int => strcmp((string) $a[$labelColumn], (string) $b[$labelColumn]));

        return $rows;
    }
}
