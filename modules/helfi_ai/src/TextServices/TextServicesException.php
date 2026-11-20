<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\TextServices;

/**
 * Thrown when a Helfi Text Services request fails.
 *
 * The exception code is the HTTP status of an error response, or 0 otherwise.
 */
class TextServicesException extends \Exception {
}
