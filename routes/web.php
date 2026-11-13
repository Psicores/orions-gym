<?php

use App\Http\Controllers\MemberSessionController;
use App\Http\Middleware\EnsureMemberHasOwnPassword;
use App\Livewire\MemberAccount;
use App\Livewire\MemberDashboard;
use App\Livewire\MemberHistory;
use App\Livewire\MemberScan;
use App\Models\Announcement;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

// Página pública del gimnasio: muestra los planes vigentes (no dados de baja), del más barato al más caro,
// y las novedades: los anuncios activos y dentro de sus fechas, del más nuevo al más viejo.
Route::get('/', fn () => view('welcome', [
    'planes' => Plan::query()->orderBy('precio')->get(),
    'anuncios' => Announcement::query()->active()->latest()->latest('id')->get(),
]))->name('inicio');

// Ingreso único para socios y personal. Quien ya tiene sesión es redirigido a su lugar:
// el socio a /mi-perfil y el personal a /admin (ver redirectUsersTo en bootstrap/app.php).
Route::middleware('guest:client,web')->group(function (): void {
    // Formulario de ingreso.
    Route::get('/ingresar', [MemberSessionController::class, 'create'])->name('login');
    // Envío del formulario.
    Route::post('/ingresar', [MemberSessionController::class, 'store'])->name('login.store');
});

// Portal del socio: solo con sesión de socio (guard "client"); sin ella redirige a /ingresar.
Route::middleware('auth:client')->group(function (): void {
    // Estas pantallas piden además que el socio ya haya elegido una contraseña propia: mientras
    // siga siendo su DNI, lo mandan a "Mi cuenta" a cambiarla.
    Route::middleware(EnsureMemberHasOwnPassword::class)->group(function (): void {
        // Inicio: estado de la membresía, vencimiento y créditos.
        Route::livewire('/mi-perfil', MemberDashboard::class)->name('member.dashboard');
        // Historial: pagos y asistencias.
        Route::livewire('/mi-perfil/historial', MemberHistory::class)->name('member.history');
        // Escanear el QR de ingreso.
        Route::livewire('/mi-perfil/escanear', MemberScan::class)->name('member.scan');
    });
    // Mi cuenta: datos, cambio de contraseña y salir.
    Route::livewire('/mi-perfil/cuenta', MemberAccount::class)->name('member.account');
    // Cierra la sesión del socio.
    Route::post('/salir', [MemberSessionController::class, 'destroy'])->name('logout');
});
