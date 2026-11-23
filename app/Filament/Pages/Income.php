<?php

namespace App\Filament\Pages;

use App\Enums\PaymentMethodEnum;
use App\Models\User;
use App\Services\Income\IncomeEntry;
use App\Services\Income\IncomeReport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Ingresos: cuotas y kiosco juntos, por medio de pago, por persona y por día, con el detalle de
 * cada cobro. Sirve para rendir la caja sin abrirla ni cerrarla.
 *
 * Un administrador ve lo de todos y puede filtrar por persona; un profe ve solo lo que cobró él.
 * Del kiosco cuentan solo los cobros aprobados de ventas no anuladas (ver IncomeReport).
 */
class Income extends Page
{
    protected string $view = 'filament.pages.income';

    // URL en castellano: /admin/ingresos.
    protected static ?string $slug = 'ingresos';

    protected static ?string $title = 'Ingresos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    // Después de Clientes (1) y QR de ingreso (2).
    protected static ?int $navigationSort = 3;

    /** Detalle: cuántos cobros muestra como máximo. */
    public const int DETAIL_LIMIT = 300;

    /** Período más largo que se puede pedir, en días. */
    public const int MAX_DAYS = 366;

    /** Primer día (Y-m-d); vacío = hoy. */
    #[Url]
    public string $desde = '';

    /** Último día (Y-m-d); vacío = hoy. */
    #[Url]
    public string $hasta = '';

    /** Persona (solo administradores; null = todos). */
    #[Url]
    public ?int $persona = null;

    /**
     * Arranca en hoy si la dirección no trae fechas.
     *
     * Postcondiciones:
     * - desde y hasta quedan con una fecha válida (las que vinieron, corregidas, o hoy).
     */
    public function mount(): void
    {
        // Normaliza lo que vino en la dirección.
        [$from, $to] = $this->range();
        $this->desde = $from->toDateString();
        $this->hasta = $to->toDateString();
    }

    /**
     * Atajo de período: hoy, ayer, esta semana o este mes.
     *
     * Postcondiciones:
     * - Deja desde y hasta en el período pedido; cualquier otro valor deja hoy.
     *
     * @param  string  $periodo  "hoy", "ayer", "semana" o "mes".
     */
    public function period(string $periodo): void
    {
        // Hoy, en la zona horaria de la app.
        $today = CarbonImmutable::today();

        [$from, $to] = match ($periodo) {
            'ayer' => [$today->subDay(), $today->subDay()],
            'semana' => [$today->startOfWeek(), $today],
            'mes' => [$today->startOfMonth(), $today],
            default => [$today, $today],
        };

        $this->desde = $from->toDateString();
        $this->hasta = $to->toDateString();
    }

    /**
     * Indica si quien mira es administrador (ve lo de todos).
     */
    public function isAdmin(): bool
    {
        // Sesión del panel.
        $user = Auth::guard('web')->user();

        return $user instanceof User && $user->isSiteAdmin();
    }

    /**
     * Período pedido, válido.
     *
     * Postcondiciones:
     * - Fechas vacías o inválidas valen hoy; si están al revés se dan vuelta; si el período pasa de
     *   MAX_DAYS se acorta desde el último día. No modifica las propiedades.
     *
     * @return array{CarbonImmutable, CarbonImmutable} Primer y último día.
     */
    public function range(): array
    {
        // Cada fecha, o hoy.
        $from = $this->parseDate($this->desde);
        $to = $this->parseDate($this->hasta);

        // Al revés: se dan vuelta.
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        // Tope de días.
        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
        }

        return [$from, $to];
    }

    /**
     * Datos para la vista.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        // Período y persona (un profe, siempre él mismo).
        [$from, $to] = $this->range();
        $userId = $this->isAdmin() ? $this->persona : Auth::guard('web')->id();

        // Ingresos del período.
        $report = app(IncomeReport::class);
        $entries = $report->entries($from, $to, $userId);

        return [
            'from' => $from,
            'to' => $to,
            'entries' => $entries->take(self::DETAIL_LIMIT),
            'hiddenEntries' => max(0, $entries->count() - self::DETAIL_LIMIT),
            'totals' => $report->byOriginAndMethod($entries),
            'byUser' => $this->isAdmin() && $this->persona === null ? $report->byUser($entries) : [],
            'byDay' => $from->equalTo($to) ? [] : $report->byDay($entries),
            'methods' => PaymentMethodEnum::cases(),
            'people' => $this->isAdmin() ? $this->people() : new Collection,
        ];
    }

    /**
     * Importe para mostrar: "$ 1.500" o "$ 1.234,50".
     *
     * @param  int  $cents  Centavos.
     * @return string Importe con formato argentino.
     */
    public function money(int $cents): string
    {
        // Sin decimales si son ",00".
        $decimals = $cents % 100 === 0 ? 0 : 2;

        return '$ '.number_format($cents / 100, $decimals, ',', '.');
    }

    /**
     * Nombre de un origen para mostrar.
     */
    public function originLabel(string $origen): string
    {
        return $origen === IncomeEntry::KIOSCO ? 'Kiosco' : 'Cuotas';
    }

    /**
     * Personal para el filtro (incluye cuentas dadas de baja, que pueden tener cobros viejos).
     *
     * @return Collection<int, User>
     */
    private function people(): Collection
    {
        return User::withTrashed()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Lee una fecha Y-m-d; si está vacía o es inválida, hoy.
     */
    private function parseDate(string $value): CarbonImmutable
    {
        // Vacío: hoy.
        if ($value === '') {
            return CarbonImmutable::today();
        }

        try {
            // Solo el formato del campo de fecha; si no se puede leer, hoy.
            return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: CarbonImmutable::today();
        } catch (Throwable) {
            return CarbonImmutable::today();
        }
    }
}
