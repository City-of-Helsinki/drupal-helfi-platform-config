<?php

declare(strict_types=1);

namespace Drupal\helfi_entity_task;

/**
 * The status of an entity task.
 */
enum EntityTaskStatus: string {

  case Pending = 'pending';
  case Done = 'done';

}
