<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A problem the user can fix by editing rift.yaml, .env or their checkout.
 */
final class ConfigException extends RuntimeException {}
