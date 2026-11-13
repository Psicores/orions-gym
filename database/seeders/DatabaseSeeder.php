<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Punto único de carga de datos: "php artisan db:seed" deja la base lista en cualquier
     * entorno (en producción, "php artisan db:seed --force").
     *
     * Carga, en este orden: las cuentas del personal, los planes base y los datos de ejemplo
     * (clientes con sus membresías y pagos, y anuncios). No hay que correr ningún otro seeder.
     *
     * Precondiciones:
     * - Las migraciones están corridas.
     * - Para crear el administrador, ADMIN_NAME, ADMIN_EMAIL y ADMIN_PASSWORD están en .env.
     *
     * Postcondiciones:
     * - Existe un usuario con el email del administrador (si las variables ADMIN_* están
     *   completas; si falta alguna, lo avisa y sigue con el resto) y la cuenta de prueba
     *   compartida, los dos con email verificado (pueden entrar al panel /admin).
     * - Existen los tres planes base (ver PlanSeeder).
     * - Si config('gym.seed_demo_data') es true (el valor por defecto; se apaga con
     *   SEED_DEMO_DATA=false en .env), existen además los clientes de ejemplo (ver
     *   DemoClientSeeder) y los anuncios de ejemplo (ver DemoAnnouncementSeeder), también en producción.
     * - Intenta crear el enlace public/storage si falta, para que se vean las imágenes; si el
     *   servidor no lo permite, lo avisa y sigue.
     * - Es seguro correrlo las veces que haga falta: no duplica nada, y los planes, clientes y
     *   anuncios que ya existen no se modifican ni se borran. Lo único que se vuelve a escribir
     *   son el nombre y la contraseña de las dos cuentas del personal.
     */
    public function run(): void
    {
        // Crea o actualiza la cuenta del administrador con los datos de .env (si están).
        $this->seedAdministrator();
        // Crea o actualiza la cuenta de prueba compartida.
        $this->seedTestUser();
        // Carga los planes base del catálogo.
        $this->call(PlanSeeder::class);

        // Datos de ejemplo: se cargan salvo que se hayan apagado con SEED_DEMO_DATA=false.
        if (config('gym.seed_demo_data')) {
            // Clientes de ejemplo que cubren todos los estados.
            $this->call(DemoClientSeeder::class);
            // Anuncios de ejemplo que cubren todas las combinaciones y situaciones.
            $this->call(DemoAnnouncementSeeder::class);
        }

        // Deja a la vista las imágenes subidas (anuncios).
        $this->linkStorage();
    }

    /**
     * Crea el enlace public/storage -> storage/app/public si todavía no existe.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Si el enlace ya existía, no hace nada.
     * - Si no existía, lo crea (equivale a "php artisan storage:link").
     * - Si el servidor no permite crearlo, muestra un aviso con el comando a correr a mano y no
     *   interrumpe la carga de datos.
     */
    private function linkStorage(): void
    {
        // Con el enlace (o una carpeta) ya en su lugar no hay nada que hacer.
        if (file_exists(public_path('storage'))) {
            // Sale sin cambios.
            return;
        }

        // Algunos hostings compartidos no permiten crear enlaces: no debe frenar el seed.
        try {
            // Crea el enlace.
            Artisan::call('storage:link');
        } catch (Throwable $exception) {
            // Avisa cómo resolverlo a mano.
            $this->command?->warn('No se pudo crear el enlace public/storage ('.$exception->getMessage().'). Corré "php artisan storage:link" o creá el enlace desde el panel del hosting para ver las imágenes.');
        }
    }

    /**
     * Crea o actualiza el administrador por defecto a partir de las variables ADMIN_* de .env.
     *
     * Precondiciones:
     * - config('app.admin') tiene name, email y password (vienen de ADMIN_NAME, ADMIN_EMAIL y
     *   ADMIN_PASSWORD en .env).
     *
     * Postcondiciones:
     * - Hay un único usuario con ese email, con el nombre y la contraseña de .env (guardada
     *   hasheada por el cast del modelo) y el email marcado como verificado.
     * - Si falta alguna variable, no toca la base, muestra un aviso y deja seguir el resto de la carga.
     */
    private function seedAdministrator(): void
    {
        /** @var array{name: ?string, email: ?string, password: ?string} $admin */
        $admin = config('app.admin');

        // Sin los tres datos no se puede crear la cuenta.
        if (blank($admin['name']) || blank($admin['email']) || blank($admin['password'])) {
            // Avisa y sigue: el resto de los datos no depende de esta cuenta.
            $this->command?->warn('No se creó el administrador: completá ADMIN_NAME, ADMIN_EMAIL y ADMIN_PASSWORD en .env y volvé a correr "php artisan db:seed".');

            // No crea nada.
            return;
        }

        // Busca por email; si existe lo actualiza, si no lo crea.
        User::updateOrCreate(
            // Clave de búsqueda.
            ['email' => $admin['email']],
            [
                // Nombre a mostrar.
                'name' => $admin['name'],
                // Contraseña en texto plano; el cast 'hashed' del modelo User la guarda hasheada.
                'password' => $admin['password'],
                // Marca el email como verificado, requisito para entrar al panel (User::canAccessPanel).
                'email_verified_at' => now(),
            ],
        );
    }

    /**
     * Crea o actualiza la cuenta de prueba compartida (test@test.com).
     *
     * Precondiciones:
     * - Existe la tabla users.
     *
     * Postcondiciones:
     * - Hay un único usuario test@test.com, con nombre "test", su contraseña hasheada
     *   y el email verificado.
     */
    private function seedTestUser(): void
    {
        // Busca el usuario de prueba por email: si existe lo actualiza, si no lo crea.
        User::updateOrCreate(
            // Criterio de búsqueda: el email de la cuenta de prueba.
            ['email' => 'pandu@pandu.com'],
            [
                // Nombre visible de la cuenta de prueba.
                'name' => 'Pandu',
                // Contraseña en texto plano; el cast 'hashed' del modelo User la guarda hasheada.
                'password' => 'orions40pirulos',
                // Marca el email como verificado para que pueda entrar al panel.
                'email_verified_at' => now(),
            ],
        );
    }
}
