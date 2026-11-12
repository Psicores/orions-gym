<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Carga los datos iniciales: usuarios base, planes del catálogo y, fuera de producción,
     * clientes de ejemplo.
     *
     * Precondiciones:
     * - Las migraciones están corridas.
     * - ADMIN_NAME, ADMIN_EMAIL y ADMIN_PASSWORD están definidos en .env.
     *
     * Postcondiciones:
     * - Existen exactamente un usuario con el email del administrador y uno con test@test.com,
     *   ambos con email verificado (por lo tanto pueden entrar al panel /admin).
     * - Existen los tres planes base (ver PlanSeeder).
     * - Si la app no está en producción (APP_ENV distinto de production), existen además los
     *   clientes de ejemplo con sus membresías y pagos (ver DemoClientSeeder). En producción
     *   no se carga ningún cliente.
     * - Correrlo varias veces no duplica nada: los usuarios se actualizan, y los planes y
     *   clientes que ya existen no se tocan.
     *
     * @throws RuntimeException Si falta alguna variable ADMIN_* en .env.
     */
    public function run(): void
    {
        // Crea o actualiza la cuenta del administrador con los datos de .env.
        $this->seedAdministrator();
        // Crea o actualiza la cuenta de prueba compartida.
        $this->seedTestUser();
        // Carga los planes base del catálogo.
        $this->call(PlanSeeder::class);

        // Los datos de ejemplo son solo para desarrollo y pruebas.
        if (! app()->isProduction()) {
            // Carga clientes de ejemplo que cubren todos los estados.
            $this->call(DemoClientSeeder::class);
        }
    }

    /**
     * Crea o actualiza el administrador por defecto a partir de las variables ADMIN_* de .env.
     *
     * Precondiciones:
     * - config('app.admin') tiene name, email y password no vacíos (vienen de ADMIN_NAME,
     *   ADMIN_EMAIL y ADMIN_PASSWORD en .env).
     *
     * Postcondiciones:
     * - Hay un único usuario con ese email, con el nombre y la contraseña de .env (guardada
     *   hasheada por el cast del modelo) y el email marcado como verificado.
     * - Si faltaba alguna variable, no se tocó la base y se lanzó la excepción.
     *
     * @throws RuntimeException Si falta alguna de las variables ADMIN_*.
     */
    private function seedAdministrator(): void
    {
        /** @var array{name: ?string, email: ?string, password: ?string} $admin */
        // Lee las credenciales del administrador desde config/app.php (que a su vez las toma de .env).
        $admin = config('app.admin');

        // Si alguna de las tres credenciales está vacía, corta antes de escribir en la base.
        if (blank($admin['name']) || blank($admin['email']) || blank($admin['password'])) {
            // Avisa qué variables hay que completar en .env.
            throw new RuntimeException('Completá ADMIN_NAME, ADMIN_EMAIL y ADMIN_PASSWORD en .env antes de cargar los datos iniciales.');
        }

        // Busca el usuario por email: si existe lo actualiza, si no lo crea (evita duplicados).
        User::updateOrCreate(
            // Criterio de búsqueda: el email del administrador.
            ['email' => $admin['email']],
            [
                // Nombre visible del administrador.
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
            ['email' => 'test@test.com'],
            [
                // Nombre visible de la cuenta de prueba.
                'name' => 'test',
                // Contraseña en texto plano; el cast 'hashed' del modelo User la guarda hasheada.
                'password' => 'orions40pirulos',
                // Marca el email como verificado para que pueda entrar al panel.
                'email_verified_at' => now(),
            ],
        );
    }
}
