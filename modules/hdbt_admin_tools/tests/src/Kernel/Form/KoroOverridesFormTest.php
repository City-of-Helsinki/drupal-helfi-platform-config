<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_admin_tools\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\hdbt_admin_tools\Config\KoroPathOverride;
use Drupal\hdbt_admin_tools\Form\KoroOverrides;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the KoroOverrides form.
 *
 * @group hdbt_admin_tools
 */
class KoroOverridesFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'language',
    'hdbt_admin_tools',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'language', 'hdbt_admin_tools']);
  }

  /**
   * Submit the form with the given rules.
   *
   * @param string $rules
   *   The rules as entered in the textarea.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The submitted form state.
   */
  private function submitRules(string $rules): FormStateInterface {
    $form_state = (new FormState())->setValues(['koro_overrides' => $rules]);
    $this->container->get('form_builder')->submitForm(KoroOverrides::class, $form_state);
    return $form_state;
  }

  /**
   * Get the stored rules.
   *
   * @return array<int, array{path: string, koro: string}>|null
   *   The stored rules.
   */
  private function getStoredRules(): ?array {
    return $this->config(KoroPathOverride::CONFIG_NAME)->get('koro_overrides');
  }

  /**
   * Tests that valid rules are saved.
   */
  public function testValidRulesAreSaved(): void {
    $form_state = $this->submitRules("/a/|wave\r\n\n  /a/b | calm  ");

    $this->assertSame([], $form_state->getErrors());
    $this->assertSame([
      ['path' => '/a', 'koro' => 'wave'],
      ['path' => '/a/b', 'koro' => 'calm'],
    ], $this->getStoredRules());
  }

  /**
   * Tests that stored rules are shown one per line.
   */
  public function testStoredRulesAreShown(): void {
    $this->submitRules("/a|wave\n/b|calm");

    $form = $this->container->get('form_builder')->getForm(KoroOverrides::class);
    $this->assertSame("/a|wave\n/b|calm", $form['koro_overrides']['#default_value']);
  }

  /**
   * Tests that an empty submission removes the rules.
   */
  public function testEmptySubmissionRemovesRules(): void {
    $this->submitRules('/a|wave');
    $this->submitRules('/');
    $this->submitRules('');

    $this->assertSame([], $this->getStoredRules());
  }

  /**
   * Tests that invalid rules are rejected.
   */
  #[DataProvider('providerInvalidRules')]
  public function testInvalidRulesAreRejected(string $rules, string $expected): void {
    $form_state = $this->submitRules($rules);

    $this->assertSame($expected, (string) $form_state->getErrors()['koro_overrides']);
    $this->assertNull($this->getStoredRules(), 'Invalid rules are not saved.');
  }

  /**
   * Data provider for testInvalidRulesAreRejected().
   *
   * @return array<string, array{string, string}>
   *   The rules and the expected error.
   */
  public static function providerInvalidRules(): array {
    return [
      'Path without a leading slash' => ['a|wave', 'Path a must start with a slash.'],
      'Root path' => ['/|wave', 'Use the appearance settings to set the koro for the whole site.'],
      'Path of only slashes' => ['///|wave', 'Use the appearance settings to set the koro for the whole site.'],
      'Unknown wave motif' => ['/a|unknown', 'Wave motif unknown is not available.'],
      'Rule without a wave motif' => ['/a', 'Wave motif  is not available.'],
    ];
  }

}
