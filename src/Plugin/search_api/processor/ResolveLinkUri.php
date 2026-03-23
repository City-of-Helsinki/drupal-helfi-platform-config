<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\processor;

use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * Resolves link field URIs into absolute URLs at index time.
 */
#[SearchApiProcessor(
  id: 'helfi_resolve_link_uri',
  label: new TranslatableMarkup('Resolve link URI'),
  description: new TranslatableMarkup('Resolves link field URIs (e.g., entity:node/16) into absolute URLs.'),
  stages: [
    'preprocess_index' => 0,
  ],
)]
final class ResolveLinkUri extends FieldsProcessorPluginBase {

  /**
   * Resolves link value to string.
   */
  protected function processLinkValue(&$value, ItemInterface $item): void {
    try {
      $url = Url::fromUri($value);

      $object = $item->getOriginalObject()?->getValue();

      if ($object instanceof TranslatableInterface) {
        $language = $object->language();
        $url->setOption('language', $language);
      }

      $value = $url->toString();
    }
    catch (\Exception) {
      // Leave value unchanged.
    }
  }

  /**
   * {@inheritDoc}
   */
  public function preprocessIndexItems(array $items): void {
    foreach ($items as $item) {
      foreach ($item->getFields() as $name => $field) {
        if ($this->testField($name, $field)) {
          $values = $field->getValues();

          foreach ($values as &$value) {
            $this->processLinkValue($value, $item);
          }

          $field->setValues(array_values($values));
        }
      }
    }

  }

}
