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

    $this->ibmChatApp = new class (
      [],
      'ibm_chat_app',
      ['provider' => 'helfi_platform_config'],
      $this->routeMatch
    ) extends IbmChatApp {
    };

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
    $this->assertArrayHasKey('chat_title', $form);
    $this->assertArrayHasKey('chat_icon', $form);

    // Verify default values.
    $this->assertSame('', $form['hostname']['#default_value']);
    $this->assertSame('', $form['engagementId']['#default_value']);
    $this->assertSame('', $form['tenantId']['#default_value']);
    $this->assertSame('', $form['assistantId']['#default_value']);
    $this->assertSame('', $form['chat_title']['#default_value']);
    $this->assertSame('speechbubble-text', $form['chat_icon']['#default_value']);

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
        'chat_title' => 'Parking bot',
        'chat_icon' => 'customer-bot-positive',
        default => NULL,
      });

    // Submit the form.
    $this->ibmChatApp->blockSubmit([], $form_state);

    // Verify that the configuration is saved correctly.
    $this->assertSame('https://www.test.hel.ninja/chat', $this->ibmChatApp->getConfiguration()['hostname']);
    $this->assertSame('12345', $this->ibmChatApp->getConfiguration()['engagementId']);
    $this->assertSame('tenantID', $this->ibmChatApp->getConfiguration()['tenantId']);
    $this->assertSame('assistantID', $this->ibmChatApp->getConfiguration()['assistantId']);
    $this->assertSame('Parking bot', $this->ibmChatApp->getConfiguration()['chat_title']);
    $this->assertSame('customer-bot-positive', $this->ibmChatApp->getConfiguration()['chat_icon']);

    $expectedButtonSrc = 'https://www.test.hel.ninja/chat/get-widget-button?tenantId=tenantID&assistantId=assistantID&engagementId=12345';

    $expected = [
      'ibm_chat_app' => [
        '#attached' => [
          'library' => [
            'helfi_platform_config/chat_enhancer',
            'helfi_platform_config/chat_widget',
          ],
          'drupalSettings' => [
            'chatWidgetData' => [
              'scriptUrl' => $expectedButtonSrc,
              'chatId' => '12345',
              'provider' => 'ibm',
              'chatTitle' => 'Parking bot',
              'chatIcon' => 'customer-bot-positive',
            ],
          ],
        ],
      ],
    ];

    // Run the build method.
    $this->assertEquals($expected, $this->ibmChatApp->build());
  }

}
