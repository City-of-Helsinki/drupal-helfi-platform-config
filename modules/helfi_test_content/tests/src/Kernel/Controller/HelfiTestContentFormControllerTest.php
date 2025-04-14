<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_test_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\helfi_test_content\Controller\HelfiTestContentFormController;

/**
 * Kernel test for HelfiTestContentFormController.
 *
 * @group helfi_test_content
 */
class HelfiTestContentFormControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'helfi_test_content',
  ];

  /**
   * The controller under test.
   *
   * @var \Drupal\helfi_test_content\Controller\HelfiTestContentFormController
   */
  protected HelfiTestContentFormController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->controller = new HelfiTestContentFormController(
      $this->container->get('form_builder')
    );
  }

  /**
   * Tests the formPage() method.
   */
  public function testFormPage(): void {
    $build = $this->controller->formPage();

    $this->assertIsArray($build);
    $this->assertArrayHasKey('#type', $build);
    $this->assertEquals('container', $build['#type']);

    $this->assertArrayHasKey('#prefix', $build);
    $this->assertStringContainsString('<article>', $build['#prefix']);
    $this->assertStringContainsString('components--test-content', $build['#prefix']);

    $this->assertArrayHasKey('#suffix', $build);
    $this->assertStringContainsString('</article>', $build['#suffix']);

    $this->assertArrayHasKey('form', $build);
    $this->assertIsArray($build['form']);
    $this->assertArrayHasKey('#form_id', $build['form']);
    $this->assertEquals('helfi_test_content_form', $build['form']['#form_id']);

    $this->assertArrayHasKey('#attached', $build);
    $this->assertArrayHasKey('library', $build['#attached']);
    $this->assertContains('helfi_test_content/test_focus', $build['#attached']['library']);
  }

}
