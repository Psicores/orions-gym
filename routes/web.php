<?php

use App\Enums\SiteSectionEnum;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\MemberSessionController;
use App\Http\Middleware\EnsureMemberHasOwnPassword;
use App\Http\Middleware\EnsureMemberSessionIsCurrent;
use App\Livewire\MemberAccount;
use App\Livewire\MemberCheckIn;
use App\Livewire\MemberDashboard;
use App\Livewire\MemberHistory;
use App\Livewire\MemberScan;
use App\Models\Announcement;
use App\Models\Plan;
use App\Models\SiteContent;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// Página pública del gimnasio: muestra los planes vigentes (no dados de baja), del más barato al más caro,
// y las novedades: los anuncios activos y dentro de sus fechas, del más nuevo al más viejo.
Route::get('/', fn () => view('welcome', [
    'planes' => Plan::query()->orderBy('precio')->get(),
    'anuncios' => Announcement::query()->active()->latest()->latest('id')->get(),
    // Horarios, dirección y mapa cargados desde el panel (vacíos si no hay nada).
    'ubicacion' => SiteContent::dataFor(SiteSectionEnum::Horarios),
    // Video de fondo, textos de bienvenida y datos para buscadores cargados desde el panel.
    'portada' => SiteContent::dataFor(SiteSectionEnum::Portada),
    // Textos, video y fotos de Comunidad cargados desde el panel.
    'comunidad' => SiteContent::dataFor(SiteSectionEnum::Comunidad),
    // Reseñas cargadas desde el panel (lista vacía si no hay).
    'opiniones' => SiteContent::dataFor(SiteSectionEnum::Opiniones)['opiniones'],
]))->name('inicio');

// Avisos del navegador sobre la política de contenido (ver SecurityHeaders). No lleva token CSRF
// porque el navegador no lo manda; tiene un límite de 20 avisos por minuto por IP.
Route::post('/csp-reportes', CspReportController::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->middleware('throttle:20,1')
    ->name('csp.report');

// Curev Hue (futura escuela de windsurf): por ahora, una pantalla con su video y el aviso "Próximamente".
Route::view('/curev-hue', 'curev-hue')->name('curev-hue');

// Ingreso único para socios y personal. Quien ya tiene sesión es redirigido a su lugar:
// el socio a /mi-perfil y el personal a /admin (ver redirectUsersTo en bootstrap/app.php).
Route::middleware('guest:client,web')->group(function (): void {
    // Formulario de ingreso.
    Route::get('/ingresar', [MemberSessionController::class, 'create'])->name('login');
    // Envío del formulario.
    Route::post('/ingresar', [MemberSessionController::class, 'store'])->name('login.store');
    // Segundo paso del personal con verificación en dos pasos: pide el código de la app.
    Route::get('/ingresar/codigo', [MemberSessionController::class, 'createCode'])->name('login.code');
    // Envío del código.
    Route::post('/ingresar/codigo', [MemberSessionController::class, 'storeCode'])->name('login.code.store');
});

// El panel ya no tiene login propio: la dirección vieja (favoritos, enlaces guardados) lleva al ingreso único.
Route::redirect('/admin/login', '/ingresar')->name('admin.login');

// Portal del socio: solo con sesión de socio (guard "client"); sin ella redirige a /ingresar.
// Si la contraseña cambió después de abrir la sesión (desde otro dispositivo o por un
// restablecimiento del personal), la sesión se cierra y tiene que volver a ingresar.
Route::middleware(['auth:client', EnsureMemberSessionIsCurrent::class])->group(function (): void {
    // Estas pantallas piden además que el socio ya haya elegido una contraseña propia: mientras
    // siga siendo su DNI, lo mandan a "Mi cuenta" a cambiarla.
    Route::middleware(EnsureMemberHasOwnPassword::class)->group(function (): void {
        // Inicio: estado de la membresía, vencimiento y créditos.
        Route::livewire('/mi-perfil', MemberDashboard::class)->name('member.dashboard');
        // Historial: pagos y asistencias.
        Route::livewire('/mi-perfil/historial', MemberHistory::class)->name('member.history');
        // Escanear el QR de ingreso.
        Route::livewire('/mi-perfil/escanear', MemberScan::class)->name('member.scan');
        // Lo que abre el QR de un punto de acceso con la cámara del celular: confirma el ingreso.
        Route::livewire('/ingreso/{token}', MemberCheckIn::class)->name('check-in.show');
    });
    // Mi cuenta: datos, cambio de contraseña y salir.
    Route::livewire('/mi-perfil/cuenta', MemberAccount::class)->name('member.account');
    // Cierra la sesión del socio.
    Route::post('/salir', [MemberSessionController::class, 'destroy'])->name('logout');
});
