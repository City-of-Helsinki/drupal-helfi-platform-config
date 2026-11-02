<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormState;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\Plugin\Field\FieldFormatter\MultisiteImageFormatter;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the multisite image formatter.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class MultisiteImageFormatterTest extends KernelTestBase {

  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'file',
    'image',
    'breakpoint',
    'responsive_image',
  ];

  /**
   * The formatter under test.
   */
  private MultisiteImageFormatter $formatter;

  /**
   * Public files directory used in file URLs.
   */
  private string $publicDirectory;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Prod);
    $this->container->get('config.storage')->write('helfi_proxy.settings', [
      'asset_path' => 'assets',
    ]);
    $this->container->get('config.factory')->reset('helfi_proxy.settings');

    $wrapper = $this->container->get('stream_wrapper_manager')->getViaScheme('public');
    $this->assertInstanceOf(LocalStream::class, $wrapper);
    $this->publicDirectory = trim($wrapper->getDirectoryPath(), '/');
    $this->assertNotFalse(file_put_contents('public://image.jpg', 'image'));

    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    foreach (['instance', 'field_image_path'] as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => 'page',
        'label' => $field_name,
      ])->save();
    }

    $formatter = $this->container->get('plugin.manager.field.formatter')->createInstance('multisite_image', [
      'field_definition' => FieldConfig::loadByName('node', 'page', 'field_image_path'),
      'settings' => [
        'responsive_image_style' => 'card_teaser',
      ],
      'label' => 'hidden',
      'view_mode' => 'teaser',
      'third_party_settings' => [],
    ]);
    $this->assertInstanceOf(MultisiteImageFormatter::class, $formatter);
    $this->formatter = $formatter;
  }

  /**
   * The formatter applies only when both image modules are enabled.
   */
  public function testIsApplicableRequiresBothImageModules() : void {
    $field = FieldConfig::loadByName('node', 'page', 'field_image_path');
    $this->assertNotNull($field);
    $this->assertFalse(MultisiteImageFormatter::isApplicable($field));

    $this->enableModules(['imagecache_external']);
    $this->assertTrue(MultisiteImageFormatter::isApplicable($field));
  }

  /**
   * The settings form offers only styles that have image style mappings.
   */
  public function testSettingsFormListsStylesWithMappings() : void {
    $this->createResponsiveImageStyle('empty', 'Empty');
    $this->createResponsiveImageStyle('card_teaser', 'Card teaser', TRUE);

    $form = $this->formatter->settingsForm([], new FormState());

    $this->assertSame('select', $form['responsive_image_style']['#type']);
    $this->assertTrue($form['responsive_image_style']['#required']);
    $this->assertSame('card_teaser', $form['responsive_image_style']['#default_value']);
    $this->assertSame(['card_teaser' => 'Card teaser'], $form['responsive_image_style']['#options']);
  }

  /**
   * The summary names the selected style, or asks for one.
   */
  public function testSettingsSummary() : void {
    $this->assertSame('Select a responsive image style.', (string) $this->formatter->settingsSummary()[0]);

    $this->createResponsiveImageStyle('card_teaser', 'Card teaser', TRUE);
    $this->assertSame('Responsive image style: Card teaser', (string) $this->formatter->settingsSummary()[0]);
  }

  /**
   * A file on the current instance uses the local responsive image theme.
   */
  public function testCurrentInstanceExistingFileUsesResponsiveImage() : void {
    $elements = $this->elements(Project::ETUSIVU, $this->path('image.jpg'));

    $this->assertSame('responsive_image', $elements[0]['#theme']);
    $this->assertSame('public://image.jpg', $elements[0]['#uri']);
    $this->assertSame('card_teaser', $elements[0]['#responsive_image_style_id']);
  }

  /**
   * Another instance uses an absolute imagecache external image.
   */
  public function testOtherInstanceUsesExternalImage() : void {
    $path = $this->path('image.jpg');
    $elements = $this->elements(Project::ASUMINEN, $path);

    $this->assertSame('imagecache_external_responsive', $elements[0]['#theme']);
    $this->assertSame($this->internalUrl(Project::ASUMINEN, $path), $elements[0]['#uri']);
  }

  /**
   * A missing local file falls back to the current instance's internal URL.
   */
  public function testMissingLocalFileUsesExternalImage() : void {
    $path = $this->path('missing.jpg');
    $elements = $this->elements(Project::ETUSIVU, $path);

    $this->assertSame('imagecache_external_responsive', $elements[0]['#theme']);
    $this->assertSame($this->internalUrl(Project::ETUSIVU, $path), $elements[0]['#uri']);
  }

  /**
   * An empty path renders nothing.
   */
  public function testEmptyPathRendersNothing() : void {
    $this->assertSame([], $this->elements(Project::ETUSIVU, ''));
  }

  /**
   * An unknown instance name renders nothing.
   */
  public function testUnknownInstanceRendersNothing() : void {
    $this->assertSame([], $this->elements('missing', $this->path('image.jpg')));
  }

  /**
   * Renders one image path for an instance.
   *
   * @return array<int, array<int|string, mixed>>
   *   The formatter elements.
   */
  private function elements(string $instance, string $path) : array {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Image',
      'instance' => $instance,
      'field_image_path' => $path,
    ]);
    $node->save();

    return $this->formatter->viewElements($node->get('field_image_path'), 'fi');
  }

  /**
   * Creates a responsive image style, optionally with one image style mapping.
   */
  private function createResponsiveImageStyle(string $id, string $label, bool $mapped = FALSE) : void {
    $values = [
      'id' => $id,
      'label' => $label,
      'breakpoint_group' => 'responsive_image',
      'fallback_image_style' => '',
    ];
    if ($mapped) {
      $values['image_style_mappings'] = [
        [
          'breakpoint_id' => 'responsive_image.viewport_sizing',
          'multiplier' => '1x',
          'image_mapping_type' => 'image_style',
          'image_mapping' => 'thumbnail',
        ],
      ];
    }
    ResponsiveImageStyle::create($values)->save();
  }

  /**
   * Builds a proxied public file path.
   */
  private function path(string $filename) : string {
    return '/assets/' . $this->publicDirectory . '/' . $filename;
  }

  /**
   * Builds the internal URL the formatter should prefix onto a path.
   */
  private function internalUrl(string $instance, string $path) : string {
    $environment = $this->container->get(EnvironmentResolverInterface::class)
      ->getEnvironment($instance, EnvironmentEnum::Prod->value);
    return rtrim($environment->getInternalBaseUrl(), '/') . '/' . ltrim($path, '/');
  }

}
