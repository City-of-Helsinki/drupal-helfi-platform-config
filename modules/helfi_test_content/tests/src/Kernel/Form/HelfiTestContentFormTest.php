<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_test_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\helfi_test_content\Form\HelfiTestContentForm;

/**
 * Tests the HelfiTestContentForm.
 *
 * @group helfi_test_content
 */
class HelfiTestContentFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'helfi_test_content',
  ];

  /**
   * Tests that the form builds correctly.
   */
  public function testFormBuild(): void {
    /** @var \Drupal\Core\Form\FormBuilderInterface $formBuilder */
    $formBuilder = $this->container->get('form_builder');

    /** @var \Drupal\Core\Form\FormInterface $form */
    $form = $formBuilder->getForm(HelfiTestContentForm::class);
    $this->assertIsArray($form);

    // Basic field presence tests.
    $this->assertArrayHasKey('textfield', $form);
    $this->assertEquals('textfield', $form['textfield']['#type']);

    $this->assertArrayHasKey('textarea', $form);
    $this->assertEquals('textarea', $form['textarea']['#type']);

    $this->assertArrayHasKey('select', $form);
    $this->assertEquals('select', $form['select']['#type']);

    $this->assertArrayHasKey('fieldset_radio', $form);
    $this->assertArrayHasKey('fieldset_radios', $form['fieldset_radio']);
    $this->assertEquals('radios', $form['fieldset_radio']['fieldset_radios']['#type']);
    $this->assertEquals('one', $form['fieldset_radio']['fieldset_radios']['#default_value']);

    $this->assertArrayHasKey('checkbox', $form);
    $this->assertEquals(FALSE, $form['checkbox']['#default_value']);

    $this->assertArrayHasKey('checkbox_selected', $form);
    $this->assertEquals(TRUE, $form['checkbox_selected']['#default_value']);

    $this->assertArrayHasKey('submit', $form);
    $this->assertEquals('submit', $form['submit']['#type']);
    $this->assertEquals('Submit', $form['submit']['#value']->__toString());

    $this->assertArrayHasKey('cancel', $form);
    $this->assertEquals('button', $form['cancel']['#type']);
    $this->assertEquals('Cancel', $form['cancel']['#value']->__toString());
  }

}
