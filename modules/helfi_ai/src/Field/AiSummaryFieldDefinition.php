<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Field;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Builds the shared 'ai_summary' base field definition.
 */
final class AiSummaryFieldDefinition {

  /**
   * Builds a new 'ai_summary' base field definition.
   *
   * @return array<string, \Drupal\Core\Field\BaseFieldDefinition>
   *   The field definition, keyed by field name.
   */
  public static function create(): array {
    return [
      'ai_summary' => BaseFieldDefinition::create('text_long')
        ->setLabel(new TranslatableMarkup('AI summary', [], ['context' => 'Helfi AI']))
        ->setDescription(new TranslatableMarkup('AI-generated content summary as a bullet list. Edit before accepting.', [], ['context' => 'Helfi AI']))
        ->setRevisionable(TRUE)
        ->setTranslatable(TRUE)
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE),
    ];
  }

}
