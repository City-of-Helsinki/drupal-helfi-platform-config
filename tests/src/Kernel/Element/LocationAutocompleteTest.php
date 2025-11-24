<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Element;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests location autocomplete element.
 *
 * @group helfi_platform_config
 */
class LocationAutocompleteTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'helfi_platform_config',
    'config_rewrite',
    'system',
  ];

  /**
   * Tests location autocomplete element rendering.
   */
  public function testLocationAutocomplete(): void {
    $form = $this->buildForm([
      'field' => [
        '#type' => 'helfi_location_autocomplete',
        '#autocomplete_route_name' => 'helfi_api_base.location_autocomplete',
      ],
    ]);

    $markup = $this->render($form);

    // Tests that the element renders correctly.
    $this->assertStringContainsString('data-helfi-location-autocomplete', $markup);
  }

  /**
   * Builds form.
   *
   * @param array $form
   *   Form render array.
   */
  private function buildForm(array $form): mixed {
    $formState = new FormState();

    $formBuilder = $this->container
      ->get(FormBuilderInterface::class);

    return $formBuilder->buildForm(new class($form) extends FormBase {

      public function __construct(private readonly array $renderArray) {
      }

      /**
       * {@inheritdoc}
       */
      public function getFormId(): string {
        return 'test_form';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form += $this->renderArray;
        return $form;
      }

      /**
       * {@inheritdoc}
       */
      public function submitForm(array &$form, FormStateInterface $form_state) {
      }

    }, $formState);
  }

}
