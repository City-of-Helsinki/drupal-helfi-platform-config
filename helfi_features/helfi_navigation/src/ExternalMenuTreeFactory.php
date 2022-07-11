<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Template\Attribute;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Link\UrlHelper;
use Drupal\helfi_navigation\Plugin\Menu\ExternalMenuLink;
use Drupal\helfi_navigation\Service\GlobalNavigationService;
use function GuzzleHttp\json_decode;
use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use Psr\Log\LoggerInterface;
use Drupal\helfi_api_base\Link\InternalDomainResolver;

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
   * @param \Drupal\helfi_api_base\Link\InternalDomainResolver $domainResolver
   *   Internal domain resolver.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolver $environmentResolver
   *   EnvironmentResolver helper class.
   * @param \Drupal\helfi_navigation\Service\GlobalNavigationService $globalNavigationService
   *   Global navigation service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   UUID service.
   */
  public function __construct(
    protected SchemaStorage $schemaStorage,
    protected LoggerInterface $logger,
    protected InternalDomainResolver $domainResolver,
    protected EnvironmentResolver $environmentResolver,
    protected GlobalNavigationService $globalNavigationService,
    protected UuidInterface $uuidService,
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

    if (!$this->validate($data)) {
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
      $menu_name = $name ?: $item->menu_type;

      // Convert site to menu link item.
      if (isset($item->project) && isset($item->menu_tree)) {
        $item = $item->menu_tree;
      }

      $transformed_item = $this->createLink($item, $menu_name);

      $transformed_item['below'] = (isset($item->sub_tree) && $depth <= $max_depth)
        ? $this->transformItems($item->sub_tree, $max_depth, $menu_name, $depth + 1)
        : [];

      $transformed_items[] = $transformed_item;
    }

    usort($transformed_items, function ($a, $b) {
      return $a['original_link']->getWeight() - $b['original_link']->getWeight();
    });

    return $transformed_items;
  }

  /**
   * Create link from menu tree item.
   *
   * @param $item
   *   Menu tree item.
   * @param $menu_name
   *   Menu name.
   *
   * @return array
   *   Returns a menu link.
   */
  protected function createLink(object $item, string $menu_name): array {
    $link_definition = [
      'menu_name' => $menu_name,
      'options' => [],
      'title' => $item->name,
    ];

    // Parse the URL.
    $item->url = UrlHelper::parse($item->url);

    if (!isset($item->id)) {
      $item->id = 'menu_link_content:' . $this->uuidService->generate();
    }

    if (!isset($item->external)) {
      $item->external = $this->domainResolver->isExternal($item->url);
    }

    if (isset($item->description)) {
      $link_definition['description'] = $item->description;
    }

    if (isset($item->weight)) {
      $link_definition['weight'] = $item->weight;
    }

    return [
      'attributes' => new Attribute(),
      'title' => $item->name,
      'original_link' => new ExternalMenuLink([], $item->id, $link_definition),
      'external' => $item->external,
      'url' => $item->url,
    ];
  }

}
