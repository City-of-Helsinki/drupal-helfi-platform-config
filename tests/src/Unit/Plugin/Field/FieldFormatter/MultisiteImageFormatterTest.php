<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\Field\FieldFormatter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\helfi_api_base\Environment\Address;
use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverException;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ProjectMetadata;
use Drupal\helfi_platform_config\Plugin\Field\FieldFormatter\MultisiteImageFormatter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests the multisite image formatter.
 */
#[Group('helfi_platform_config')]
class MultisiteImageFormatterTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The environment resolver prophecy.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\helfi_api_base\Environment\EnvironmentResolverInterface>
   */
  private ObjectProphecy $environmentResolver;

  /**
   * The formatter under test.
   */
  private MultisiteImageFormatter $formatter;

  /**
   * Whether this test registered the public stream wrapper.
   */
  private bool $registeredWrapper = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    if (!in_array('public', stream_get_wrappers(), TRUE)) {
      stream_wrapper_register('public', ExistingPublicFileWrapper::class);
      $this->registeredWrapper = TRUE;
    }

    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('asset_path')->willReturn('assets');
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('helfi_proxy.settings')->willReturn($config->reveal());

    $wrapper = $this->prophesize(LocalStream::class);
    $wrapper->getDirectoryPath()->willReturn('sites/default/files');
    $stream_wrapper_manager = $this->prophesize(StreamWrapperManagerInterface::class);
    $stream_wrapper_manager->getViaScheme('public')->willReturn($wrapper->reveal());

    $project = new Project(
      'etusivu',
      new ProjectMetadata('https://github.com/City-of-Helsinki/drupal-helfi-etusivu'),
    );
    $this->environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $this->environmentResolver->getActiveProject()->willReturn($project);
    $this->environmentResolver->getActiveEnvironmentName()->willReturn('prod');

    $this->formatter = new MultisiteImageFormatter(
      'multisite_image',
      [],
      $this->prophesize(FieldDefinitionInterface::class)->reveal(),
      ['responsive_image_style' => 'card_teaser'],
      'hidden',
      'teaser',
      [],
    );
    $this->setProperty('environmentResolver', $this->environmentResolver->reveal());
    $this->setProperty('configFactory', $config_factory->reveal());
    $this->setProperty('streamWrapperManager', $stream_wrapper_manager->reveal());
    $this->setProperty('responsiveImageStyleStorage', $this->prophesize(EntityStorageInterface::class)->reveal());
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() : void {
    if ($this->registeredWrapper) {
      stream_wrapper_unregister('public');
    }
    parent::tearDown();
  }

  /**
   * A file on the current instance uses the local responsive image theme.
   */
  public function testCurrentInstanceExistingFileUsesResponsiveImage() : void {
    $elements = $this->formatter->viewElements(
      $this->items('etusivu', '/assets/sites/default/files/image.jpg'),
      'fi',
    );

    $this->assertSame('responsive_image', $elements[0]['#theme']);
    $this->assertSame('public://image.jpg', $elements[0]['#uri']);
    $this->assertSame('card_teaser', $elements[0]['#responsive_image_style_id']);
  }

  /**
   * Another instance uses an absolute imagecache external image.
   */
  public function testOtherInstanceUsesExternalImage() : void {
    $this->environmentResolver->getEnvironment('asuminen', 'prod')->willReturn($this->environment(
      'nginx-asuminen',
    ));

    $elements = $this->formatter->viewElements(
      $this->items('asuminen', '/assets/sites/default/files/image.jpg'),
      'fi',
    );

    $this->assertSame('imagecache_external_responsive', $elements[0]['#theme']);
    $this->assertSame(
      'http://nginx-asuminen:8080/assets/sites/default/files/image.jpg',
      $elements[0]['#uri'],
    );
  }

  /**
   * A missing local file falls back to the current instance's internal URL.
   */
  public function testMissingLocalFileUsesExternalImage() : void {
    $this->environmentResolver->getEnvironment('etusivu', 'prod')->willReturn($this->environment(
      'nginx-etusivu',
    ));

    $elements = $this->formatter->viewElements(
      $this->items('etusivu', '/assets/sites/default/files/missing.jpg'),
      'fi',
    );

    $this->assertSame('imagecache_external_responsive', $elements[0]['#theme']);
    $this->assertSame(
      'http://nginx-etusivu:8080/assets/sites/default/files/missing.jpg',
      $elements[0]['#uri'],
    );
  }

  /**
   * An empty path renders nothing.
   */
  public function testEmptyPathRendersNothing() : void {
    $elements = $this->formatter->viewElements($this->items('etusivu', ''), 'fi');

    $this->assertSame([], $elements);
  }

  /**
   * An unknown instance name renders nothing.
   */
  public function testUnknownInstanceRendersNothing() : void {
    $this->environmentResolver->getEnvironment('missing', 'prod')
      ->willThrow(new EnvironmentResolverException('Unknown project.'));

    $elements = $this->formatter->viewElements(
      $this->items('missing', '/assets/sites/default/files/image.jpg'),
      'fi',
    );

    $this->assertSame([], $elements);
  }

  /**
   * Sets a private formatter property.
   */
  private function setProperty(string $name, mixed $value) : void {
    $property = new \ReflectionProperty(MultisiteImageFormatter::class, $name);
    $property->setValue($this->formatter, $value);
  }

  /**
   * Builds a field item list with an instance and one path.
   *
   * @phpstan-return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>
   */
  private function items(string $instance, string $path) : FieldItemListInterface {
    $instance_field = new \stdClass();
    $instance_field->value = $instance;

    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->hasField('instance')->willReturn(TRUE);
    $entity->get('instance')->willReturn($instance_field);

    $item = new \stdClass();
    $item->value = $path;

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->willImplement(\IteratorAggregate::class);
    $items->getEntity()->willReturn($entity->reveal());
    $items->getIterator()->willReturn(new \ArrayIterator([0 => $item]));
    return $items->reveal();
  }

  /**
   * Builds an environment with the given internal domain.
   */
  private function environment(string $internal_domain) : Environment {
    return new Environment(
      new Address('www.hel.fi'),
      new Address($internal_domain, 'http', 8080),
      ['fi' => '/'],
      EnvironmentEnum::Prod,
    );
  }

}

/**
 * Stream wrapper that reports one public file as existing.
 */
class ExistingPublicFileWrapper {

  /**
   * Stream context resource.
   *
   * @var resource
   */
  public $context;

  /**
   * Reports whether the public file exists.
   *
   * @phpstan-return array<int|string, int>|false
   */
  // phpcs:disable
  public function url_stat(string $path, int $flags) : array|false {
    return $path === 'public://image.jpg' ? stat(__FILE__) : FALSE;
  }
  // phpcs:enable

}
