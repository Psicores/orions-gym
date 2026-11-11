<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum ClientStatusEnum: string implements HasColor, HasDescription, HasLabel
{
    case Entrenando = 'entrenando';
    case EnPausa = 'en_pausa';
    case ActivoTemporal = 'activo_temporal';
    case Inactivo = 'inactivo';

    /**
     * Human-readable name shown in the admin panel.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Entrenando => 'Entrenando',
            self::EnPausa => 'En pausa',
            self::ActivoTemporal => 'Activo temporal',
            self::Inactivo => 'Inactivo',
        };
    }

    /**
     * What the status means in terms of the client's payments.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Entrenando => 'Cuota al día',
            self::EnPausa => 'Debe abonar',
            self::ActivoTemporal => 'Acuerdo de pago atrasado',
            self::Inactivo => 'Sin actividad en el gimnasio',
        };
    }

    /**
     * Filament color used for badges of this status.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Entrenando => 'success',
            self::EnPausa => 'danger',
            self::ActivoTemporal => 'warning',
            self::Inactivo => 'gray',
        };
    }
}
