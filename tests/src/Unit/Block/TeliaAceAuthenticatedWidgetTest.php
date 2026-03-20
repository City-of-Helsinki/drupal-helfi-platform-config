<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_platform_config\Plugin\Block\TeliaAceAuthenticatedWidget;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\TeliaAceAuthenticatedWidget
 *
 * @group helfi_platform_config
 */
class TeliaAceAuthenticatedWidgetTest extends BlockUnitTestBase {

  use StringTranslationTrait;

  /**
   * The block instance being tested.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\TeliaAceAuthenticatedWidget
   */
  private TeliaAceAuthenticatedWidget $teliaAceAuthenticatedWidget;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->teliaAceAuthenticatedWidget = new class (
      [],
      'telia_ace_authenticated_widget',
      ['provider' => 'helfi_platform_config']
    ) extends TeliaAceAuthenticatedWidget {
    };

    // Set the translation service for StringTranslationTrait.
    $this->teliaAceAuthenticatedWidget->setStringTranslation($this->stringTranslation);
  }

  /**
   * Tests that the form returns the expected form structure.
   *
   * @covers ::blockForm
   */
  public function testBlockForm(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form = [];

    $form = $this->teliaAceAuthenticatedWidget->blockForm($form, $form_state);

    // Verify the form contains the expected fields.
    $this->assertArrayHasKey('chat_script_tag', $form);

    // Verify field properties.
    $this->assertTrue($form['chat_script_tag']['#required']);

    // Verify default values.
    $this->assertEquals('', $form['chat_script_tag']['#default_value']);
  }

  /**
   * Tests that submit saves the form values and updates configuration.
   *
   * @covers ::blockSubmit
   */
  public function testBlockSubmit(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')
      ->willReturnCallback(fn($key) => match ($key) {
        'chat_script_tag' => '<script></script>',
        default => NULL,
      });

    // Submit the form.
    $this->teliaAceAuthenticatedWidget->blockSubmit([], $form_state);

    // Verify that the configuration is saved correctly.
    $this->assertEquals('<script></script>', $this->teliaAceAuthenticatedWidget->getConfiguration()['chat_script_tag']);
  }

  /**
   * Tests that render array has the correct variables for Telia ACE Widget.
   *
   * @covers ::build
   */
  public function testBuildReturnsCorrectRenderArray(): void {
    // Set configuration values.
    $this->teliaAceAuthenticatedWidget->setConfiguration([
      'chat_script_tag' => '<script></script>',
    ]);

    $expected = [
      '#markup' => '<script></script>',
      '#allowed_tags' => ['script'],
    ];

    $this->assertEquals($expected, $this->teliaAceAuthenticatedWidget->build());
  }

}
