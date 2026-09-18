<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_admin_tools\Unit\Hook;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\hdbt_admin_tools\Hook\FormHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the form hooks.
 *
 * @group hdbt_admin_tools
 */
final class FormHooksTest extends UnitTestCase {

  /**
   * Test that the hero states are applied only to the whitelisted forms.
   */
  #[DataProvider('providerFormNodeFormAlter')]
  public function testFormNodeFormAlter(string $form_id, bool $expect_hero): void {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $sut = new FormHooks($moduleHandler);

    $form = ['actions' => ['submit' => ['#submit' => []]]];
    $sut->formNodeFormAlter($form, $this->createMock(FormStateInterface::class), $form_id);

    if ($expect_hero) {
      $this->assertArrayHasKey('#states', $form['field_hero']);
    }
    else {
      $this->assertArrayNotHasKey('field_hero', $form);
    }

    // The submit callback is always added.
    $this->assertContains([FormHooks::class, 'nodeFormSubmit'], $form['actions']['submit']['#submit']);
  }

  /**
   * Data provider for testFormNodeFormAlter().
   *
   * @return array<string, array{string, bool}>
   *   The form id and whether hero states are expected.
   */
  public static function providerFormNodeFormAlter(): array {
    return [
      'landing page form' => ['node_landing_page_form', TRUE],
      'landing page edit form' => ['node_landing_page_edit_form', TRUE],
      'page form' => ['node_page_form', TRUE],
      'other node form' => ['node_article_form', FALSE],
    ];
  }

  /**
   * Test that a module can whitelist a form via the alter hook.
   */
  public function testFormNodeFormAlterHook(): void {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('alter')
      ->willReturnCallback(function (string $type, array &$data): void {
        $data[] = 'node_custom_form';
      });
    $sut = new FormHooks($moduleHandler);

    $form = ['actions' => ['submit' => ['#submit' => []]]];
    $sut->formNodeFormAlter($form, $this->createMock(FormStateInterface::class), 'node_custom_form');

    $this->assertArrayHasKey('#states', $form['field_hero']);
  }

  /**
   * Test that the submit callback redirects to the saved translation.
   */
  public function testNodeFormSubmitRedirects(): void {
    $language = $this->createMock(LanguageInterface::class);
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->method('getLanguage')->with('en')->willReturn($language);

    $container = new ContainerBuilder();
    $container->set('language_manager', $languageManager);
    \Drupal::setContainer($container);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->willReturnMap([
      ['langcode', 'en'],
      ['nid', 5],
    ]);
    $form_state->expects($this->once())
      ->method('setRedirect')
      ->with('entity.node.canonical', ['node' => 5], ['language' => $language]);

    FormHooks::nodeFormSubmit([], $form_state);
  }

  /**
   * Test that the submit callback does nothing without a langcode.
   */
  public function testNodeFormSubmitReturnsEarly(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->willReturn(NULL);
    $form_state->expects($this->never())->method('setRedirect');

    FormHooks::nodeFormSubmit([], $form_state);
  }

}
