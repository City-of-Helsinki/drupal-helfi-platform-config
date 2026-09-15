<?php

declare(strict_types=1);

namespace Drupal\helfi_ai;

/**
 * The model tiers AI features can ask for.
 *
 * Features pick a tier by capability rather than by model name, so the actual
 * deployment behind each tier can differ per environment. Tiers are mapped to
 * providers in 'helfi_ai.settings:model_tiers'. A tier that is not configured
 * falls back to Default, and an unconfigured Default falls back to the
 * site-wide provider in 'ai.settings'.
 */
enum ModelTier: string {

  case Default = 'default';
  case Low = 'low';
  case High = 'high';

}
