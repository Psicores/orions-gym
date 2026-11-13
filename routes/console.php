<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Piloto automático de la madrugada (hora argentina, la zona de la app). Para que corra en el
// servidor hace falta la entrada de cron que ejecuta "php artisan schedule:run" (ver README).

// 01:00 — Cierre de ciclos: pasa a En pausa las membresías Entrenando cuya fecha ya pasó.
Schedule::command('memberships:update-completed-cycles')
    // Todos los días a la 01:00.
    ->dailyAt('01:00')
    // Si el servidor corre varios schedulers a la vez, que no se pise consigo mismo.
    ->withoutOverlapping();

// 01:05 — Recarga mensual: inicia el mes nuevo en las membresías pagadas que quedaron sin renovar.
Schedule::command('memberships:renew-credits')
    // Todos los días a la 01:05, después del cierre de ciclos.
    ->dailyAt('01:05')
    // Si el servidor corre varios schedulers a la vez, que no se pise consigo mismo.
    ->withoutOverlapping();
