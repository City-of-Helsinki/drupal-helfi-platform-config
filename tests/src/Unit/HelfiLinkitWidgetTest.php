<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Core\DependencyInjection\ContainerNotInitializedException;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\helfi_platform_config\Plugin\Field\FieldWidget\HelfiLinkitWidget;
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

    $this->widget = new HelfiLinkitWidget(
      [],
      [],
      $this->prophesize(FieldDefinitionInterface::class)->reveal(),
      [],
      [],
      $this->prophesize(AccountProxyInterface::class)->reveal(),
      $this->prophesize(EntityTypeManager::class)->reveal(),
      $request_stack->reveal(),
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

    // Urls that should be passed forward to \Drupal\linkit\Utility\LinkitHelper
    // throwing error within test context.
    $should_throw_error = [
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

    foreach ($should_throw_error as $value) {
      try {
        $this->widget->massageFormValues(
          [$value],
          [],
          $this->prophesize(FormStateInterface::class)->reveal(),
        );
      }
      catch (\Throwable $t) {
        $this->assertInstanceOf(ContainerNotInitializedException::class, $t);
      }
    }
  }

}
