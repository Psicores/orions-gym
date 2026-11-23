<?php

namespace App\Livewire;

use App\Models\AccessPoint;
use App\Models\Attendance;
use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ingreso con la cámara del celular: la pantalla que abre el QR de un punto de acceso
 * (/ingreso/{token}).
 *
 * Abrirla nunca registra nada (una vista previa de WhatsApp o un enlace reenviado no descuentan
 * créditos): el socio confirma con el botón y recién ahí se registra el ingreso. El resultado se
 * muestra a pantalla completa, para que se lea desde recepción.
 */
#[Layout('layouts::member')]
#[Title('Ingreso')]
class MemberCheckIn extends Component
{
    /** Confirmaciones por minuto que acepta de un mismo socio. */
    public const int MAX_PER_MINUTE = 10;

    // Código del punto de acceso (viene en la dirección; el socio no lo puede cambiar).
    #[Locked]
    public string $token = '';

    // Resultado: null (falta confirmar), "ok", "repetido" o "error".
    public ?string $resultado = null;

    // Texto que acompaña al resultado.
    public ?string $mensaje = null;

    // Créditos que le quedan después del ingreso.
    public ?int $creditos = null;

    // Hora del ingreso registrado (HH:MM).
    public ?string $hora = null;

    /**
     * Recibe el código del QR.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - Guarda el código. No registra nada.
     *
     * @param  string  $token  Código del punto de acceso.
     */
    public function mount(string $token): void
    {
        // Código de la dirección.
        $this->token = $token;
    }

    /**
     * Registra el ingreso por este punto de acceso.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - Si el punto no existe o está apagado: resultado "error", sin tocar nada.
     * - Si el socio ya confirmó MAX_PER_MINUTE veces en el último minuto: "error", sin tocar nada.
     * - Si Client::registerAttendance() lo deja entrar: "ok" (ingreso nuevo, descontó un crédito)
     *   o "repetido" (ya había entrado hace minutos; no descontó otro), con la hora y los créditos.
     * - Si no lo deja entrar (sin membresía o cuota vencida): "error" con el motivo.
     */
    public function confirmar(): void
    {
        /** @var Client $client Socio con la sesión iniciada. */
        $client = Auth::guard('client')->user();

        // Límite por socio (no por conexión: en el gimnasio todos comparten el wifi).
        $key = 'check-in:'.$client->id;

        // Demasiados intentos seguidos: corta sin tocar la base.
        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_MINUTE)) {
            $this->fallar('Demasiados intentos seguidos. Esperá un minuto y probá de nuevo.');

            return;
        }

        // Cuenta este intento durante un minuto.
        RateLimiter::hit($key, 60);

        // Punto de acceso activo con este código.
        $accessPoint = AccessPoint::findActiveFromQr($this->token);

        // Código viejo o punto apagado.
        if ($accessPoint === null) {
            $this->fallar('Este QR ya no está habilitado. Avisá en recepción.');

            return;
        }

        // Intenta registrar el ingreso; el modelo valida la membresía.
        try {
            // Ingreso nuevo, o el de hace minutos si es repetido.
            $attendance = $client->registerAttendance($accessPoint);
        } catch (InvalidArgumentException $exception) {
            // Sin membresía o con la cuota vencida.
            $this->fallar($exception->getMessage());

            return;
        }

        // Muestra el resultado.
        $this->mostrar($client, $attendance);
    }

    /**
     * Carga el resultado de un ingreso aceptado.
     *
     * @param  Client  $client  Socio que entró.
     * @param  Attendance  $attendance  Ingreso registrado (o el reciente, si era repetido).
     */
    private function mostrar(Client $client, Attendance $attendance): void
    {
        // Nuevo o repetido.
        $this->resultado = $attendance->wasRecentlyCreated ? 'ok' : 'repetido';
        // Saludo o aclaración.
        $this->mensaje = $attendance->wasRecentlyCreated
            ? '¡Buen entrenamiento, '.$client->nombre.'!'
            : 'Tu ingreso ya estaba registrado. No se descontó otro crédito.';
        // Hora del ingreso.
        $this->hora = $attendance->fecha_hora->format('H:i');
        // Saldo releído de la base.
        $this->creditos = $attendance->membership()->value('creditos_disponibles');
    }

    /**
     * Deja la pantalla en estado de error, con el motivo.
     *
     * @param  string  $mensaje  Motivo que ve el socio.
     */
    private function fallar(string $mensaje): void
    {
        // Error, sin datos de ingreso.
        $this->resultado = 'error';
        $this->mensaje = $mensaje;
        $this->creditos = null;
        $this->hora = null;
    }

    /**
     * Arma la pantalla.
     *
     * Precondiciones:
     * - Hay un socio con sesión en el guard "client" (lo garantiza la ruta).
     *
     * Postcondiciones:
     * - La vista recibe el socio y el punto de acceso (null si el código no sirve). No modifica nada.
     *
     * @return View La vista livewire.member-check-in.
     */
    public function render(): View
    {
        return view('livewire.member-check-in', [
            // Socio.
            'client' => Auth::guard('client')->user(),
            // Punto de acceso activo (null si el QR no sirve).
            'accessPoint' => AccessPoint::findActiveFromQr($this->token),
        ]);
    }
}
