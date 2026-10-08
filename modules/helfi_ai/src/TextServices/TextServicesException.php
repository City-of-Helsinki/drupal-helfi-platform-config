<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\TextServices;

/**
 * Thrown when a Helfi Text Services request fails.
 *
 * The exception code is the HTTP status of the failed response, or 0 when
 * there was no usable response.
 */
class TextServicesException extends \Exception {
}
