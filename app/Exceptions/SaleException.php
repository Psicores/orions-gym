<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una venta del kiosco no se pudo hacer por un motivo que entiende quien vende (carrito vacío,
 * producto apagado, sin stock en modo estricto, cobros que no suman el total...).
 *
 * El mensaje está en castellano y se puede mostrar tal cual en la pantalla de venta.
 */
class SaleException extends RuntimeException {}
