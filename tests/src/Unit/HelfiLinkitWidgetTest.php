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
   * Test the massageFieldValues method.
   */
  public function testMassageFieldValues(): void {
    // Urls that should be returned unchanged.
    $internal_absolute_urls = [
      [
        'uri' => 'https://helfi-etusivu.docker.so/fi/node/232',
        'attributes' => [],
      ],
      [
        'uri' => 'https://helfi-etusivu.docker.so/sv/någon/sida/?fråga=parameter',
        'attributes' => [],
      ],
      [
        'uri' => 'http://helfi-etusivu.docker.so/en/news/helsinki-city-council-jubilee-decision-free-admission-to-outdoor-swimming-facilities-select-cultural',
        'attributes' => [],
      ],
    ];

    $massagedValues = $this->widget->massageFormValues(
      $internal_absolute_urls,
      [],
      $this->prophesize(FormStateInterface::class)->reveal(),
    );

    foreach ($massagedValues as $key => $value) {
      $this->assertEquals(
        $value['uri'],
        $internal_absolute_urls[$key]['uri']
      );
    }

    // Should be passed forward to \Drupal\linkit\Utility\LinkitHelper.
    $external_or_internal_urls = [
      [
        'uri' => 'https://google.com?query=string',
        'attributes' => [],
      ],
      [
        'uri' => 'helfi-etusivu.docker.so?query=string',
        'attributes' => [],
      ],
      [
        'uri' => '/sv/någon/sida/',
        'attributes' => [],
      ],
    ];

    $expected_results = [
      'https://google.com?query=string',
      'internal:/helfi-etusivu.docker.so?query=string',
      'internal:/sv/någon/sida/',
    ];

    $linkit_massaged_values = $this->widget->massageFormValues(
      $external_or_internal_urls,
      [],
      $this->prophesize(FormStateInterface::class)->reveal(),
    );

    foreach ($linkit_massaged_values as $key => $value) {
      $this->assertEquals(
        $value['uri'],
        $expected_results[$key]
      );
    }
  }

}
