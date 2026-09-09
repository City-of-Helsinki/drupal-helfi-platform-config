<?php

declare(strict_types=1);

namespace Drupal\helfi_ai;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Builds the shared 'ai_summary' base field definition.
 *
 * Any entity type wanting the AI summary feature adds a base field with
 * this definition, named 'ai_summary'. The name is what
 * \Drupal\helfi_ai\Plugin\Field\FieldWidget\AiSummaryWidget::isApplicable()
 * matches on, so it must stay exactly 'ai_summary'.
 */
final class AiSummaryFieldDefinition {

  /**
   * Builds a new 'ai_summary' base field definition.
   *
   * A fresh instance is required per entity type/bundle since
   * BaseFieldDefinition is mutable and gets bound to its target.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition
   *   The field definition.
   */
  public static function create(): BaseFieldDefinition {
    return BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('AI summary', [], ['context' => 'Helfi AI']))
      ->setDescription(new TranslatableMarkup('AI-generated content summary as a bullet list. Edit before accepting.', [], ['context' => 'Helfi AI']))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
  }

}
