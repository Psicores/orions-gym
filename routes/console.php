<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vencimientos diarios: a las 00:05 hora argentina pausa las membresías vencidas que todavía dejan entrar.
Schedule::command('memberships:check-expired')
    // Todos los días a las 00:05.
    ->dailyAt('00:05')
    // En hora argentina (la app guarda en UTC; a esa hora ambas zonas están en el mismo día).
    ->timezone('America/Argentina/Buenos_Aires')
    // Si el servidor corre varios schedulers a la vez, que no se pise consigo mismo.
    ->withoutOverlapping();
