<?php

use App\Models\Plan;
use Illuminate\Support\Facades\Route;

// Página pública del gimnasio: muestra los planes vigentes (no dados de baja), del más barato al más caro.
Route::get('/', fn () => view('welcome', [
    'planes' => Plan::query()->orderBy('precio')->get(),
]))->name('inicio');
