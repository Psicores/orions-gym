<?php

namespace App\Rules;

use App\Models\Client;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regla de validación: el valor tiene que ser un celular argentino válido, escrito como sea
 * ("280 15 412-3456", "02804123456", "+54 9 11 2345-6789"...). Ver Client::normalizeCel().
 *
 * Un valor vacío pasa: si el campo es obligatorio, eso lo decide la regla "required".
 */
class ArgentineMobile implements ValidationRule
{
    /**
     * Verifica que el valor sea un celular argentino.
     *
     * Precondiciones:
     * - Ninguna: acepta cualquier valor.
     *
     * Postcondiciones:
     * - Si el valor está vacío o es un celular argentino válido, no hace nada.
     * - Si no, llama a $fail con un mensaje que explica cómo escribirlo.
     *
     * @param  string  $attribute  Nombre del campo.
     * @param  mixed  $value  Lo que escribió la persona.
     * @param  Closure(string): mixed  $fail  Marca el error.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Vacío no es asunto de esta regla.
        if (blank($value)) {
            return;
        }

        // Si no se puede llevar a un celular argentino, es un error.
        if (! is_string($value) || Client::normalizeCel($value) === null) {
            // Mensaje con un ejemplo de cómo escribirlo.
            $fail('Escribí un celular válido, con la característica. Por ejemplo: 11 2345-6789 o 280 15 412-3456.');
        }
    }
}
