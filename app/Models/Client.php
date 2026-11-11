<?php

namespace App\Models;

use App\Enums\ClientStatusEnum;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'dni',
    'nombre',
    'apellido',
    'fecha_nacimiento',
    'cel',
    'email',
    'foto_path',
    'certificado_salud_path',
    'observaciones_dolencias',
    'estado',
    'fecha_vencimiento',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Define cómo se convierten las columnas al leerlas y escribirlas desde el modelo.
     *
     * Precondiciones:
     * - Las columnas fecha_nacimiento y fecha_vencimiento guardan fechas (Y-m-d) o null.
     * - La columna estado guarda uno de los valores de ClientStatusEnum
     *   (entrenando, en_pausa, activo_temporal, inactivo).
     *
     * Postcondiciones:
     * - $client->fecha_nacimiento y $client->fecha_vencimiento devuelven un objeto Carbon
     *   (o null si la columna está vacía).
     * - $client->estado devuelve un caso de ClientStatusEnum, y al asignarle un caso del enum
     *   se guarda su valor de texto.
     * - Si la base tiene un estado que no existe en el enum, leerlo lanza un ValueError.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Devuelve el mapa "columna => tipo" que Eloquent aplica automáticamente.
        return [
            // La fecha de nacimiento se maneja como fecha (Carbon) en lugar de texto.
            'fecha_nacimiento' => 'date',
            // El estado se maneja como el enum ClientStatusEnum en lugar de texto suelto.
            'estado' => ClientStatusEnum::class,
            // La fecha de vencimiento de la cuota se maneja como fecha (Carbon).
            'fecha_vencimiento' => 'date',
        ];
    }

    /**
     * Membresías (contratos con un plan) que tuvo y tiene el cliente.
     *
     * Precondiciones:
     * - Existe la tabla memberships con la columna client_id.
     *
     * Postcondiciones:
     * - Devuelve la relación "un cliente tiene muchas membresías", sin ejecutar la consulta.
     * - $client->memberships devuelve una colección (vacía si no tiene ninguna).
     * - No modifica datos.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        // Busca en memberships las filas cuyo client_id es el id de este cliente.
        return $this->hasMany(Membership::class);
    }
}
