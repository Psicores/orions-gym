<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Código del QR de ingreso
    |--------------------------------------------------------------------------
    |
    | Texto que lleva el código QR impreso en el mostrador de recepción. El socio
    | lo escanea desde su portal para registrar su ingreso. Es el mismo para
    | todos y no cambia solo: para invalidar el QR impreso (por ejemplo, si
    | alguien le sacó una foto), se cambia este valor y se imprime uno nuevo
    | desde /admin/qr-de-ingreso.
    |
    */

    'checkin_token' => env('GYM_CHECKIN_TOKEN', 'orions-ingreso-sede-1'),

    /*
    |--------------------------------------------------------------------------
    | Datos de ejemplo
    |--------------------------------------------------------------------------
    |
    | Con true, "php artisan db:seed" carga también los clientes y anuncios de
    | ejemplo, en cualquier entorno. Sirve para probar el sistema recién
    | desplegado. Cuando el gimnasio empiece a operar con socios reales,
    | poner SEED_DEMO_DATA=false en el .env para que un seed posterior no
    | vuelva a crear los de ejemplo que se hayan borrado.
    |
    */

    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', true),

];
