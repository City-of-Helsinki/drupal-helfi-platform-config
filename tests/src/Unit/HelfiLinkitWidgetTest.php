<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\helfi_platform_config\Plugin\Field\FieldWidget\HelfiLinkitWidget;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the HelfiLinkitWidget.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Field\FieldWidget\HelfiLinkitWidget
 * @group helfi_platform_config
 */
class HelfiLinkitWidgetTest extends UnitTestCase {
  /**
   * Instance of the field widget.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Field\FieldWidget\HelfiLinkitWidget
   */
  protected HelfiLinkitWidget $widget;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $request_stack = $this->prophesize(RequestStack::class);
    $current_request = $this->prophesize(Request::class);
    $current_request->getSchemeAndHttpHost()->willReturn('https://helfi-etusivu.docker.so');
    $current_request->reveal();
    $request_stack->getCurrentRequest()->willReturn($current_request);

    $language_manager = $this->prophesize(LanguageManagerInterface::class);
    $language_manager->getLanguages()->willReturn([]);

    $stream_wrapper_manager = $this->prophesize(StreamWrapperManager::class);
    $stream_wrapper = $this->prophesize(LocalStream::class);
    $stream_wrapper->getDirectoryPath()->willReturn('/var/www/html');
    $stream_wrapper->reveal();
    $stream_wrapper_manager->getViaScheme('public')->willReturn($stream_wrapper);

    $container = new ContainerBuilder();
    $container->set('config.factory', $this->createMock(ConfigFactoryInterface::class));
    $container->set('current_user', $this->createMock(AccountProxyInterface::class));
    $container->set('entity_type.manager', $this->createMock(EntityTypeManager::class));
    $container->set('language_manager', $language_manager->reveal());
    $container->set('path_alias.manager', $this->createMock(AliasManagerInterface::class));
    $container->set('request_stack', $request_stack->reveal());
    $container->set('stream_wrapper_manager', $stream_wrapper_manager->reveal());
    \Drupal::setContainer($container);

    $this->widget = HelfiLinkitWidget::create(
      $container,
      [
        'field_definition' => $this->createMock(FieldDefinitionInterface::class),
        'settings' => [],
        'third_party_settings' => [],
      ],
      'helfi_linkit',
      [],
    );
  }

  /**
   * Tests internal absolute urls. These should be returned unchanged.
   *
   * @dataProvider internalAbsoluteUrlsData
   * @covers ::convertToUri
   * @covers ::create
   * @covers ::massageFieldValues
   */
  public function testInternalAbsoluteUrls(string $uri): void {
    $massagedValues = $this->widget->massageFormValues(
      [['uri' => $uri, 'attributes' => []]],
      [],
      $this->prophesize(FormStateInterface::class)->reveal(),
    );

    $this->assertEquals($uri, $massagedValues[0]['uri']);
  }

  /**
   * Data provider for ::testInternalAbsoluteUrls().
   *
   * @return array[]
   *   The data.
   */
  public function internalAbsoluteUrlsData(): array {
    return [
      ['https://helfi-etusivu.docker.so/fi/node/232'],
      ['https://helfi-etusivu.docker.so/sv/någon/sida/?fråga=parameter'],
      ['http://helfi-etusivu.docker.so/en/news/helsinki-city-council-jubilee-decision-free-admission-to-outdoor-swimming-facilities-select-cultural'],
    ];
  }

  /**
   * Test the massageFieldValues method.
   *
   * @dataProvider massageFieldValuesData
   * @covers ::convertToUri
   * @covers ::create
   * @covers ::massageFieldValues
   */
  public function testMassageFieldValues(string $uri, string $expected): void {
    $massagedValues = $this->widget->massageFormValues(
      [['uri' => $uri, 'attributes' => []]],
      [],
      $this->prophesize(FormStateInterface::class)->reveal(),
    );

    $this->assertEquals($expected, $massagedValues[0]['uri']);
  }

  /**
   * Data provider for ::massageFieldValues().
   *
   * @return array[]
   *   The data.
   */
  public function massageFieldValuesData(): array {
    return [
      ['https://google.com?query=string', 'https://google.com?query=string'],
      ['helfi-etusivu.docker.so?query=string', 'internal:/helfi-etusivu.docker.so?query=string'],
      ['/sv/någon/sida/', 'internal:/sv/någon/sida/'],
    ];
  }

}
