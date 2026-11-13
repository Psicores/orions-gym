<?php

namespace App\Livewire;

use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pantalla "Escanear QR" del portal del socio: registra el ingreso al gimnasio.
 *
 * La cámara y la lectura del código corren en el celular (Alpine + html5-qrcode, ver
 * resources/js/app.js). Cuando lee un QR, el navegador llama a procesarIngreso() con su texto.
 */
#[Layout('layouts::member')]
#[Title('Escanear QR')]
class MemberScan extends Component
{
    // Resultado del último escaneo: null (todavía nada), "ok", "repetido" o "error".
    public ?string $resultado = null;

    // Texto que se le muestra al socio con el resultado.
    public string $mensaje = '';

    // Créditos que le quedan después de un ingreso correcto (puede ser negativo).
    public ?int $creditos = null;

    /**
     * Valida el QR escaneado y, si corresponde, registra el ingreso del socio.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     * - $qrString es el texto leído del código QR.
     *
     * Postcondiciones:
     * - Si $qrString no es el código del gimnasio (config gym.checkin_token): resultado "error",
     *   no guarda nada.
     * - Si el socio no tiene membresía vigente o está En pausa: resultado "error" con el motivo
     *   ("Cuota vencida. Pasá por recepción." si está en pausa), no guarda nada.
     * - Si está Entrenando o Activo flex: guarda la asistencia y le descuenta un crédito, aunque
     *   quede en negativo (ver Client::registerAttendance()); resultado "ok" y $creditos con el saldo.
     * - Si ya había registrado un ingreso hace minutos: resultado "repetido", sin descontar de nuevo.
     * - Avisa al navegador con el evento "ingreso-registrado" (suena y se ve verde) o
     *   "ingreso-rechazado" (se ve rojo).
     *
     * @param  string  $qrString  Texto del QR escaneado.
     */
    public function procesarIngreso(string $qrString): void
    {
        // Borra el resultado del escaneo anterior.
        $this->reset('resultado', 'mensaje', 'creditos');

        // El QR tiene que ser el impreso en recepción (comparación en tiempo constante).
        if (! hash_equals((string) config('gym.checkin_token'), trim($qrString))) {
            // Rechaza sin tocar la base.
            $this->rechazar('Ese código no es el del gimnasio. Escaneá el QR de recepción.');

            return;
        }

        /** @var Client $client Socio con la sesión iniciada. */
        $client = Auth::guard('client')->user();

        // Intenta registrar el ingreso; el modelo valida la membresía.
        try {
            // Crea la asistencia y descuenta el crédito (o devuelve la reciente, si es repetido).
            $attendance = $client->registerAttendance();
        } catch (InvalidArgumentException $exception) {
            // Sin membresía o con la cuota vencida: muestra el motivo.
            $this->rechazar($exception->getMessage());

            return;
        }

        // Saldo de créditos de la membresía usada, releído de la base.
        $this->creditos = $attendance->membership()->value('creditos_disponibles');

        // Ingreso nuevo: descontó un crédito.
        if ($attendance->wasRecentlyCreated) {
            // Verde, con saludo.
            $this->resultado = 'ok';
            $this->mensaje = '¡Buen entrenamiento, '.$client->nombre.'!';
        } else {
            // Mismo ingreso escaneado otra vez: no descontó nada.
            $this->resultado = 'repetido';
            $this->mensaje = 'Tu ingreso ya estaba registrado a las '.$attendance->fecha_hora->format('H:i').'. No se descontó otro crédito.';
        }

        // Le avisa al celular para el sonido de éxito.
        $this->dispatch('ingreso-registrado');
    }

    /**
     * Deja la pantalla en estado de error.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - resultado queda en "error" y mensaje con el motivo recibido.
     * - Se despacha el evento "ingreso-rechazado" al navegador.
     * - No guarda nada en la base.
     *
     * @param  string  $mensaje  Motivo que ve el socio.
     */
    private function rechazar(string $mensaje): void
    {
        // Rojo, con el motivo.
        $this->resultado = 'error';
        $this->mensaje = $mensaje;

        // Le avisa al celular para el sonido y la vibración de error.
        $this->dispatch('ingreso-rechazado');
    }

    /**
     * Muestra la pantalla del escáner.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - Devuelve la vista. No lee ni modifica datos.
     *
     * @return View La vista livewire.member-scan.
     */
    public function render(): View
    {
        // La vista usa las propiedades públicas del componente.
        return view('livewire.member-scan');
    }
}
