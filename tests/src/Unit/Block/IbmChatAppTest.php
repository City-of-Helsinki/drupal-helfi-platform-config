<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\helfi_platform_config\Plugin\Block\IbmChatApp;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\IbmChatApp
 *
 * @group helfi_platform_config
 */
class IbmChatAppTest extends BlockUnitTestBase {

  /**
   * The block instance being tested.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\IbmChatApp
   */
  private IbmChatApp $ibmChatApp;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->ibmChatApp = new IbmChatApp(
      [],
      'ibm_chat_app',
      ['provider' => 'helfi_platform_config'],
    );

    // Set the translation service for StringTranslationTrait.
    $this->ibmChatApp->setStringTranslation($this->stringTranslation);
  }

  /**
   * Tests that blockForm() returns the expected form structure.
   *
   * @covers ::blockForm
   */
  public function testBlockForm(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form = [];

    $form = $this->ibmChatApp->blockForm($form, $form_state);

    // Verify that the form contains the expected fields.
    $this->assertArrayHasKey('hostname', $form);
    $this->assertArrayHasKey('engagementId', $form);
    $this->assertArrayHasKey('tenantId', $form);
    $this->assertArrayHasKey('assistantId', $form);

    // Verify default values.
    $this->assertSame('', $form['hostname']['#default_value']);
    $this->assertSame('', $form['engagementId']['#default_value']);
    $this->assertSame('', $form['tenantId']['#default_value']);
    $this->assertSame('', $form['assistantId']['#default_value']);

    // Verify titles are translated.
    $this->assertEquals($this->translate('Chat Hostname'), $form['hostname']['#title']);
    $this->assertEquals($this->translate('Chat Engagement Id'), $form['engagementId']['#title']);
    $this->assertEquals($this->translate('Chat Tenant Id'), $form['tenantId']['#title']);
    $this->assertEquals($this->translate('Chat Assistant Id'), $form['assistantId']['#title']);
  }

  /**
   * Tests that submit saves the form values and updates configuration.
   *
   * @covers ::blockSubmit
   * @covers ::build
   */
  public function testBlockSubmit(): void {
    // Mock the form state and getValue method.
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')
      ->willReturnCallback(fn($key) => match ($key) {
        'hostname' => 'https://www.test.hel.ninja/chat',
        'engagementId' => '12345',
        'tenantId' => 'tenantID',
        'assistantId' => 'assistantID',
        default => NULL,
      });

    // Submit the form.
    $this->ibmChatApp->blockSubmit([], $form_state);

    // Verify that the configuration is saved correctly.
    $this->assertSame('https://www.test.hel.ninja/chat', $this->ibmChatApp->getConfiguration()['hostname']);
    $this->assertSame('12345', $this->ibmChatApp->getConfiguration()['engagementId']);
    $this->assertSame('tenantID', $this->ibmChatApp->getConfiguration()['tenantId']);
    $this->assertSame('assistantID', $this->ibmChatApp->getConfiguration()['assistantId']);

    $expectedButtonSrc = 'https://www.test.hel.ninja/chat/get-widget-button?tenantId=tenantID&assistantId=assistantID&engagementId=12345';

    $expected = [
      'ibm_chat_app' => [
        '#title' => $this->translate('IBM Chat App'),
        '#attached' => [
          'library' => ['helfi_platform_config/chat_enhancer'],
          'html_head' => [
            [
              [
                '#tag' => 'script',
                '#attributes' => [
                  'async' => TRUE,
                  'type' => 'text/javascript',
                  'src' => $expectedButtonSrc,
                ],
              ],
              'chat_app_button',
            ],
          ],
        ],
      ],
    ];

    // Run the build method.
    $this->assertEquals($expected, $this->ibmChatApp->build());
  }

}
