<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\helfi_platform_config\MultisiteContentId;

/**
 * Adds the external URL to multisite content autocomplete labels.
 *
 * The autocomplete matcher copies this label into both the dropdown and the
 * stored value, so a selected item is saved as "Title (url) (id)".
 */
#[EntityReferenceSelection(
  id: 'default:helfi_multisite_content',
  label: new TranslatableMarkup('Multisite content selection'),
  entity_types: [MultisiteContentId::ENTITY_TYPE_ID],
  group: 'default',
  weight: 1,
)]
final class MultisiteContentSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<string, array<int|string, string>>
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->getConfiguration()['target_type'];

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();
    if (empty($result) || !is_array($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $entity = $this->entityRepository->getTranslationFromContext($entity);
      $options[$bundle][$entity_id] = $this->suggestionLabel($entity);
    }

    return $options;
  }

  /**
   * Builds an escaped label, with the external URL when one exists.
   */
  private function suggestionLabel(EntityInterface $entity) : string {
    $label = Html::escape((string) ($entity->label() ?? ''));
    if (!method_exists($entity, 'getExternalUrl')) {
      return $label;
    }
    $url = $entity->getExternalUrl();
    if (!$url instanceof Url) {
      return $label;
    }

    return $label . ' (' . Html::escape($url->toString()) . ')';
  }

}
