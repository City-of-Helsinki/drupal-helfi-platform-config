<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_content_cards\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Defers multisite content cards to a follow-up HTMX request.
 *
 * @extends \Drupal\Core\Field\FormatterBase<\Drupal\Core\Field\EntityReferenceFieldItemListInterface<\Drupal\Core\Entity\EntityInterface>>
 */
#[FieldFormatter(
  id: 'content_cards_htmx',
  label: new TranslatableMarkup('Content cards (HTMX)'),
  field_types: [
    'entity_reference',
  ],
)]
final class ContentCardsHtmxFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) : bool {
    return $field_definition->getTargetEntityTypeId() === 'paragraph'
      && $field_definition->getName() === 'field_content_cards_content_ext';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param \Drupal\Core\Field\EntityReferenceFieldItemListInterface<\Drupal\Core\Entity\EntityInterface> $items
   */
  public function viewElements(FieldItemListInterface $items, $langcode) : array {
    if ($items->isEmpty()) {
      return [];
    }
    $entity = $items->getEntity();
    $id = $entity->id();
    if (!$entity instanceof ParagraphInterface || $id === NULL || $id === '') {
      return [];
    }

    $htmx = new Htmx();
    $htmx->get(Url::fromRoute('helfi_paragraphs_content_cards.content_cards_htmx', [
      'paragraph' => $id,
    ]))
      ->trigger('load')
      ->swap('innerHTML');

    $build = [
      '#theme' => 'helfi_htmx_preview__content_cards',
      '#num_items' => count($items),
      '#message' => $this->t('Loading content', [], [
        'context' => 'Content cards loading message',
      ]),
      '#wrapper' => 'div',
      '#attributes' => [
        'class' => ['content-cards'],
      ],
    ];
    $htmx->applyTo($build);

    return [$build];
  }

}
