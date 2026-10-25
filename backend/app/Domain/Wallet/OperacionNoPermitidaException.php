<?php

namespace App\Domain\Wallet;

use RuntimeException;

/** La cuenta no está habilitada para esta operación de dinero. */
class OperacionNoPermitidaException extends RuntimeException {}
