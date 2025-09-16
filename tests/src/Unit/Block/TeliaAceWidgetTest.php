<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_platform_config\Plugin\Block\TeliaAceWidget;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\TeliaAceWidget
 *
 * @group helfi_platform_config
 */
class TeliaAceWidgetTest extends BlockUnitTestBase {

  use StringTranslationTrait;

  /**
   * The block instance being tested.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\TeliaAceWidget
   */
  private TeliaAceWidget $teliaAceWidget;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->teliaAceWidget = new class (
      [],
      'telia_ace_widget',
      ['provider' => 'helfi_platform_config']
    ) extends TeliaAceWidget {
    };

    // Set the translation service for StringTranslationTrait.
    $this->teliaAceWidget->setStringTranslation($this->stringTranslation);
  }

  /**
   * Tests that the form returns the expected form structure.
   *
   * @covers ::blockForm
   */
  public function testBlockForm(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form = [];

    $form = $this->teliaAceWidget->blockForm($form, $form_state);

    // Verify the form contains the expected fields.
    $this->assertArrayHasKey('chat_id', $form);
    $this->assertArrayHasKey('chat_title', $form);

    // Verify field properties.
    $this->assertTrue($form['chat_id']['#required']);
    $this->assertFalse($form['chat_title']['#required']);

    // Verify default values.
    $this->assertEquals('', $form['chat_id']['#default_value']);
    $this->assertEquals('', $form['chat_title']['#default_value']);
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
        'chat_id' => 'ninja-chat',
        'chat_title' => 'Chat via Ninja chat',
        default => NULL,
      });

    // Submit the form.
    $this->teliaAceWidget->blockSubmit([], $form_state);

    // Verify that the configuration is saved correctly.
    $this->assertEquals('ninja-chat', $this->teliaAceWidget->getConfiguration()['chat_id']);
    $this->assertEquals('Chat via Ninja chat', $this->teliaAceWidget->getConfiguration()['chat_title']);
  }

  /**
   * Tests that render array has the correct variables for Telia ACE Widget.
   *
   * @covers ::build
   */
  public function testBuildReturnsCorrectRenderArray(): void {
    // Set configuration values.
    $this->teliaAceWidget->setConfiguration([
      'chat_id' => 'ninja-chat',
      'chat_title' => 'Chat via Ninja chat',
    ]);

    $expected = [
      'telia_chat_widget' => [
        'button' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'role' => 'region',
            'aria-label' => 'chat',
            'id' => 'humany_ninja-chat',
            'class' => ['hidden'],
          ],
        ],
        '#attached' => [
          'library' => ['helfi_platform_config/telia_ace_widget_loadjs'],
          'drupalSettings' => [
            'telia_ace_data' => [
              'script_url' => TeliaAceWidget::SDK_URL,
              'script_sri' => NULL,
              'chat_id' => Xss::filter('ninja-chat'),
              'chat_title' => Xss::filter('Chat via Ninja chat'),
            ],
          ],
        ],
      ],
    ];

    $this->assertEquals($expected, $this->teliaAceWidget->build());
  }

  /**
   * Test that the XSS filtering is applied on user input.
   *
   * @covers ::build
   */
  public function testBuildAppliesXssFiltering(): void {
    // Set configuration with potential XSS.
    $this->teliaAceWidget->setConfiguration([
      'chat_id' => '<script>alert("XSS")</script>',
      'chat_title' => '<img src="x" onerror="alert(\'XSS\')">',
    ]);

    $build = $this->teliaAceWidget->build();

    // Ensure XSS is filtered.
    $this->assertSame(
      Xss::filter('<script>alert("XSS")</script>'),
      $build['telia_chat_widget']['#attached']['drupalSettings']['telia_ace_data']['chat_id']
    );
    $this->assertSame(
      Xss::filter('<img src="x" onerror="alert(\'XSS\')">'),
      $build['telia_chat_widget']['#attached']['drupalSettings']['telia_ace_data']['chat_title']
    );
  }

}
