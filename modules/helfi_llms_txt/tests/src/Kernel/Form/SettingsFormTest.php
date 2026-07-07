<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_llms_txt\Kernel\Form;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\helfi_llms_txt\Form\SettingsForm;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the llms.txt settings form.
 */
#[RunTestsInSeparateProcesses]
#[Group('helfi_llms_txt')]
class SettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'helfi_llms_txt',
  ];

  /**
   * Tests llm.txt config form.
   */
  public function testConfigForm(): void {
    $formBuilder = $this->container->get(FormBuilderInterface::class);

    $form_state = new FormState();
    $form_state->setValues(['content' => '# New content']);

    $formBuilder->submitForm(SettingsForm::class, $form_state);

    // Tests that submitting the form persists the content to config.
    $this->assertEmpty($form_state->getErrors());
    $this->assertSame(
      '# New content',
      $this->config('helfi_llms_txt.settings')->get('content'),
    );

    $form = $formBuilder->getForm(SettingsForm::class);

    // Tests that the content field reflects the stored config value.
    $this->assertArrayHasKey('content', $form);
    $this->assertSame('textarea', $form['content']['#type']);
    $this->assertSame('# New content', $form['content']['#default_value']);
  }

}
