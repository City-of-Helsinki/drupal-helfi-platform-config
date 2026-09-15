<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

/**
 * The processing state of a document.
 */
enum DocumentState: string {

  // The document needs a pipeline run.
  case Pending = 'pending';

  // The document is queued.
  case Embedding = 'embedding';

  // Every chunk of the document has a vector.
  case Ready = 'ready';

  // Processing failed too many times in a row.
  case Failed = 'failed';

  // The document produces nothing to embed.
  case Skipped = 'skipped';

}
