<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Código del QR de ingreso
    |--------------------------------------------------------------------------
    |
    | Ya no se usa para el ingreso: cada punto de acceso tiene su propio código y
    | se maneja desde el panel (QR de ingreso). Solo lo lee una vez la migración
    | create_access_points_table, para que el primer punto ("Recepción") conserve
    | el código del QR ya impreso si era seguro (16 caracteres o más y no el de
    | desarrollo). Después de migrar se puede borrar del .env.
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
