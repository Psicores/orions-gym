<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Payment;
use Database\Seeders\DemoAnnouncementSeeder;
use Database\Seeders\DemoClientSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('demo:remove {--force : No pide confirmación}')]
#[Description('Borra definitivamente los clientes y anuncios de ejemplo que carga el seed')]
class RemoveDemoData extends Command
{
    /**
     * Borra los datos de ejemplo antes de empezar a operar con socios reales.
     *
     * Los clientes de ejemplo tienen DNI conocidos (están en el código), así que mientras existan
     * cualquiera puede entrar al portal con ellos. Se corre a mano: "php artisan demo:remove".
     *
     * Precondiciones:
     * - Las migraciones están corridas.
     *
     * Postcondiciones:
     * - Ya no existen los clientes de ejemplo (ver DemoClientSeeder::DNIS), tampoco los dados de
     *   baja, ni sus membresías, pagos, asistencias y anuncios cerrados. Solo se borra un cliente
     *   si conserva el DNI y el email de ejemplo (demo<DNI>@example.com): si alguien le cambió el
     *   email desde el panel, se lo considera real y no se toca.
     * - Ya no existen los anuncios de ejemplo (ver DemoAnnouncementSeeder::TITLES) ni sus dos banners.
     * - No toca ningún otro cliente, anuncio, plan ni cuenta del personal.
     * - Sin --force pide confirmación; si se responde que no, no borra nada.
     * - Para que un seed posterior no los vuelva a crear, SEED_DEMO_DATA no tiene que estar en
     *   true en el .env (ver config/gym.php).
     *
     * @return int Command::SUCCESS.
     */
    public function handle(): int
    {
        // Es un borrado definitivo: pide confirmación salvo que venga --force.
        if (! $this->option('force') && ! $this->confirm('Se van a borrar definitivamente los clientes y anuncios de ejemplo. ¿Seguir?')) {
            // Sale sin tocar nada.
            $this->components->info('No se borró nada.');

            return self::SUCCESS;
        }

        // Ids de los clientes de ejemplo, incluidos los dados de baja.
        $clientIds = Client::withTrashed()
            // Solo los DNI que carga el seeder.
            ->whereIn('dni', DemoClientSeeder::DNIS)
            ->get()
            // Y solo si conservan el email de ejemplo.
            ->filter(fn (Client $client): bool => $client->email === DemoClientSeeder::emailFor($client->dni))
            ->modelKeys();

        // Todo o nada: si algo falla no queda un cliente a medio borrar.
        $announcements = DB::transaction(function () use ($clientIds): int {
            // Primero lo que depende del cliente, en el orden que exigen las claves foráneas.
            Attendance::query()->whereIn('client_id', $clientIds)->delete();
            Payment::query()->whereIn('client_id', $clientIds)->delete();
            Membership::query()->whereIn('client_id', $clientIds)->delete();
            // Después los clientes, de forma definitiva (no como baja).
            Client::withTrashed()->whereIn('id', $clientIds)->forceDelete();

            // Anuncios de ejemplo, por título.
            return Announcement::query()->whereIn('titulo', DemoAnnouncementSeeder::TITLES)->delete();
        });

        // Banners de ejemplo copiados al disco público.
        Storage::disk('public')->delete(array_map(
            fn (string $file): string => 'announcements/'.$file,
            DemoAnnouncementSeeder::BANNERS,
        ));

        // Informa cuánto borró.
        $this->components->info('Clientes de ejemplo borrados: '.count($clientIds).'. Anuncios de ejemplo borrados: '.$announcements.'.');

        return self::SUCCESS;
    }
}
