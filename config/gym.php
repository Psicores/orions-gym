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
    | Sin GYM_CHECKIN_TOKEN se usa un valor de desarrollo que está en el código.
    | En producción ese valor no sirve: hay que poner una cadena al azar de 16
    | caracteres o más; hasta entonces el ingreso por QR queda deshabilitado
    | (ver Attendance::receptionToken()).
    |
    */

    'checkin_token' => env('GYM_CHECKIN_TOKEN', 'orions-ingreso-sede-1'),

    /*
    |--------------------------------------------------------------------------
    | Datos de ejemplo
    |--------------------------------------------------------------------------
    |
    | Con true, "php artisan db:seed" carga también los clientes y anuncios de
    | ejemplo, en cualquier entorno. Sirve para probar el sistema. Viene
    | apagado: los clientes de ejemplo tienen DNI conocidos (están en el
    | código) y cualquiera podría entrar al portal con ellos. Para borrar los
    | que ya estén cargados: "php artisan demo:remove".
    |
    */

    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', false),

];
