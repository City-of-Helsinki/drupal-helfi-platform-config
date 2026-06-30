<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\helfi_ai\Form\TitleSuggestionFormAlter;
use Drupal\helfi_ai\Hook\FormHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;

/**
 * Tests that the node form alter hook delegates to the suggester service.
 */
#[Group('helfi_ai')]
#[CoversClass(FormHooks::class)]
class FormHooksTest extends UnitTestCase {

  /**
   * The hook forwards the form to the title suggestion form alter service.
   */
  public function testNodeFormAlterDelegatesToService(): void {
    $formAlter = $this->prophesize(TitleSuggestionFormAlter::class);
    $formAlter->alter(Argument::type('array'), Argument::any())->shouldBeCalledOnce();

    $hooks = new FormHooks($formAlter->reveal());

    $form = [];
    $formState = $this->prophesize(FormStateInterface::class)->reveal();
    $hooks->nodeFormAlter($form, $formState, 'node_page_form');
  }

}
