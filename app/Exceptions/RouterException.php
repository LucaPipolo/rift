<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A failure to set up or drive the shared router.
 */
class RouterException extends RuntimeException {}
