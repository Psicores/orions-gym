<?php

namespace App\Models;

use App\Enums\ClientStatusEnum;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'estado' => ClientStatusEnum::class,
            'fecha_vencimiento' => 'date',
        ];
    }
}
