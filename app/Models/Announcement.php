<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Anuncio o novedad que el dueño publica para los socios.
 *
 * Es flexible: puede tener solo texto, solo imagen (banner) o las dos cosas. Se deja de mostrar
 * de dos maneras: sola, al pasar su fecha de fin, o a mano, apagando is_active. Cada socio puede
 * además cerrarlo en su portal; eso se anota en la tabla announcement_client (ver clients()).
 */
#[Fillable([
    'titulo',
    'contenido',
    'imagen_path',
    'is_active',
    'starts_at',
    'ends_at',
])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    /**
     * Define cómo se convierten las columnas al leerlas y escribirlas desde el modelo.
     *
     * Precondiciones:
     * - is_active guarda 0 o 1; starts_at y ends_at guardan una fecha con hora o null.
     *
     * Postcondiciones:
     * - $announcement->is_active devuelve true o false.
     * - $announcement->starts_at y ->ends_at devuelven un objeto Carbon (o null si están vacías).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Devuelve el mapa "columna => tipo" que Eloquent aplica automáticamente.
        return [
            // Encendido o apagado a mano.
            'is_active' => 'boolean',
            // Desde cuándo se muestra.
            'starts_at' => 'datetime',
            // Hasta cuándo se muestra.
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Filtra los anuncios que se están mostrando en este momento.
     *
     * Se usa como Announcement::active() o $query->active().
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - La consulta queda limitada a los anuncios con is_active = true, cuyo starts_at está vacío
     *   o ya llegó, y cuyo ends_at está vacío o todavía no pasó. Es la misma regla de isVisibleNow().
     * - Un anuncio sin fechas y encendido se muestra siempre.
     * - No ejecuta la consulta ni modifica datos.
     *
     * @param  Builder<Announcement>  $query  Consulta de anuncios.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        // Solo los encendidos.
        $query->where('is_active', true)
            // Que ya empezaron (o no tienen fecha de inicio).
            ->where(fn (Builder $query): Builder => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            // Y que todavía no terminaron (o no tienen fecha de fin).
            ->where(fn (Builder $query): Builder => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /**
     * Filtra los anuncios que un socio todavía no cerró.
     *
     * Se usa junto con active(): Announcement::active()->unreadBy($client).
     *
     * Precondiciones:
     * - $client existe en la base.
     *
     * Postcondiciones:
     * - La consulta queda limitada a los anuncios sin marca de ese cliente en announcement_client.
     * - No ejecuta la consulta ni modifica datos.
     *
     * @param  Builder<Announcement>  $query  Consulta de anuncios.
     * @param  Client  $client  Socio que mira su portal.
     */
    #[Scope]
    protected function unreadBy(Builder $query, Client $client): void
    {
        // Deja afuera los que ese cliente ya cerró.
        $query->whereDoesntHave('clients', fn (Builder $query): Builder => $query->whereKey($client->getKey()));
    }

    /**
     * Devuelve la dirección web de la imagen del anuncio.
     *
     * Precondiciones:
     * - Si hay imagen, está en el disco "public" y se corrió php artisan storage:link.
     *
     * Postcondiciones:
     * - Devuelve la URL pública de la imagen, o null si el anuncio no tiene.
     * - No modifica nada.
     *
     * @return string|null URL de la imagen.
     */
    public function imageUrl(): ?string
    {
        // Sin imagen no hay URL.
        return $this->imagen_path === null ? null : Storage::disk('public')->url($this->imagen_path);
    }

    /**
     * Devuelve el contenido listo para mostrarle al socio, sin nada peligroso.
     *
     * Precondiciones:
     * - contenido es el HTML que guardó el editor del panel, o null.
     *
     * Postcondiciones:
     * - Devuelve el mismo HTML sin scripts, eventos (onclick, etc.) ni etiquetas no permitidas;
     *   conserva el formato básico (negrita, cursiva, listas, enlaces).
     * - Devuelve null si no hay contenido o si queda vacío (por ejemplo, un párrafo en blanco).
     * - No modifica nada.
     *
     * @return HtmlString|null HTML seguro para imprimir sin escapar.
     */
    public function safeContent(): ?HtmlString
    {
        // Limpia el HTML del editor.
        $html = Str::sanitizeHtml((string) $this->contenido);

        // Si no queda texto visible, no hay nada que mostrar.
        return trim(strip_tags($html)) === '' ? null : new HtmlString($html);
    }

    /**
     * Indica si el anuncio se está mostrando en este momento.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve true solo si is_active es true, starts_at está vacío o ya llegó, y ends_at está
     *   vacío o todavía no pasó. Es la misma regla del scope active().
     * - No modifica nada.
     *
     * @return bool true si los socios lo están viendo.
     */
    public function isVisibleNow(): bool
    {
        // Encendido, ya empezado y todavía no terminado.
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo(now()))
            && ($this->ends_at === null || $this->ends_at->greaterThanOrEqualTo(now()));
    }

    /**
     * Describe en una palabra en qué situación está el anuncio, para el listado del panel.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve "Apagado" si is_active es false (aunque las fechas lo permitirían).
     * - Si no, "Programado" si starts_at todavía no llegó, "Finalizado" si ends_at ya pasó y
     *   "Visible" en cualquier otro caso.
     * - No modifica nada.
     *
     * @return string Una de: Apagado, Programado, Finalizado, Visible.
     */
    public function situacion(): string
    {
        // Elige el texto según la primera condición que se cumple.
        return match (true) {
            // Dado de baja a mano.
            ! $this->is_active => 'Apagado',
            // Todavía no empezó.
            $this->starts_at !== null && $this->starts_at->isFuture() => 'Programado',
            // Ya terminó solo.
            $this->ends_at !== null && $this->ends_at->isPast() => 'Finalizado',
            // Se está mostrando.
            default => 'Visible',
        };
    }

    /**
     * Socios que ya cerraron (leyeron) el anuncio en su portal.
     *
     * Precondiciones:
     * - Existe la tabla announcement_client con announcement_id, client_id y read_at.
     *
     * Postcondiciones:
     * - Devuelve la relación "un anuncio fue cerrado por muchos clientes", sin ejecutar la consulta.
     * - Cada cliente trae en ->pivot->read_at cuándo lo cerró.
     * - No modifica datos.
     *
     * @return BelongsToMany<Client, $this>
     */
    public function clients(): BelongsToMany
    {
        // Une anuncios y clientes a través de announcement_client, con la fecha de lectura.
        return $this->belongsToMany(Client::class)->withPivot('read_at');
    }
}
