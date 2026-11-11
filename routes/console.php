<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vencimientos diarios: a las 00:05 (hora argentina, la zona de la app) pausa las membresías vencidas que todavía dejan entrar.
Schedule::command('memberships:check-expired')
    // Todos los días a las 00:05.
    ->dailyAt('00:05')
    // Si el servidor corre varios schedulers a la vez, que no se pise consigo mismo.
    ->withoutOverlapping();
