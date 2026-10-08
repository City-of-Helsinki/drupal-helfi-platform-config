<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\TextServices;

/**
 * Helfi Text Services API services, as paths relative to the API base URL.
 */
enum Service: string {

  // Rewrites content in the hel.fi tone of voice.
  case Tone = 'v1/tone';

  // Summarizes content into key points.
  // @todo Switch to v1 when the summarize service is published there.
  case Summarize = 'dev/summarize';

}
