<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\helfi_navigation\Plugin\Menu\ExternalMenuLink;
use function GuzzleHttp\json_decode;
use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use Psr\Log\LoggerInterface;

/**
 * Helper class for external menu tree actions.
 */
class ExternalMenuTreeFactory {

  /**
   * The JSON schema.
   *
   * @var object
   */
  protected object $schema;

  /**
   * The JSON validator.
   *
   * @var \JsonSchema\Validator
   */
  protected Validator $validator;

  /**
   * Constructs a tree instance from supplied JSON.
   *
   * @param \JsonSchema\SchemaStorage $schemaStorage
   *   JSON Schema storage.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    protected SchemaStorage $schemaStorage,
    protected LoggerInterface $logger
  ) {
    $this->schema = json_decode(file_get_contents(__DIR__ . '/../assets/schema.json'));
    $this->schemaStorage->addSchema('file://schema', $this->schema);
    $this->validator = new Validator(new Factory($this->schemaStorage));
  }

  /**
   * Form and return a menu tree instance from json input.
   *
   * @param string $json
   *   The JSON string.
   * @param int $max_depth
   *   Determines how deep of an array is returned.
   *
   * @return \Drupal\helfi_navigation\ExternalMenuTree|null
   *   The resulting menu tree instance.
   *
   * @throws \Exception
   *   Throws exception.
   */
  public function fromJson(string $json, int $max_depth = 10):? ExternalMenuTree {
    $data = (array) json_decode($json);
    $valid = $this->validate($data);

    if (!$valid) {
      throw new \Exception('Invalid JSON input');
    }

    $tree = $this->transformItems($data, $max_depth);

    if (!empty($tree)) {
      return new ExternalMenuTree($tree);
    }

    return NULL;
  }

  /**
   * Validates JSON against the schema.
   *
   * @param array $json
   *   The json string to validate.
   */
  protected function validate(array $json): bool {
    $this->validator->validate($json, $this->schema);

    if ($this->validator->isValid()) {
      return TRUE;
    }
    else {
      $error_string = '';
      foreach ($this->validator->getErrors() as $error) {
        $error_string .= sprintf('[%s] %s \n', $error['property'], $error['message']);
      }

      $this->logger->notice('Validation failed for external menu. Violations: \n' . $error_string);
      return FALSE;
    }
  }

  /**
   * Create menu link instances from json elements.
   *
   * @param array $items
   *   Provided JSON input.
   * @param int $max_depth
   *   Determines how deep the function recurses.
   * @param string $name
   *   Menu name.
   * @param int $depth
   *   Defines how deep into recursion the function is already.
   *
   * @return array
   *   Resulting array of menu links.
   */
  protected function transformItems(array $items, int $max_depth, string $name = '', int $depth = 0): array {
    $transformed_items = [];

    foreach ($items as $key => $item) {
      $menu_name = $name ?? $item->name;

      $link_definition = [
        'menu_name' => $menu_name,
        'options' => [],
        'title' => $item->name,
      ];

      if (isset($item->description)) {
        $link_definition['description'] = $item->description;
      }

      if (isset($item->weight)) {
        $link_definition['weight'] = $item->weight;
      }

      $transformed_item = [
        'attributes' => new Attribute(),
        'title' => $item->name,
        'original_link' => new ExternalMenuLink([], $item->id, $link_definition),
        'url' => Url::fromUri($item->url),
      ];

      if (isset($item->menu_tree) && $depth <= $max_depth) {
        $transformed_item['below'] = $this->transformItems($item->menu_tree, $max_depth, $menu_name, $depth + 1);
      }
      else {
        $transformed_item['below'] = [];
      }

      $transformed_items[] = $transformed_item;
    }

    usort($transformed_items, function ($a, $b) {
      return $a['original_link']->getWeight() - $b['original_link']->getWeight();
    });

    return $transformed_items;
  }

}
