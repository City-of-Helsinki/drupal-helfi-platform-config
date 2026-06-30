<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helfi_ai\PreviewEntityBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;

/**
 * Tests building the unsaved preview entity from form state.
 */
#[Group('helfi_ai')]
#[CoversClass(PreviewEntityBuilder::class)]
class PreviewEntityBuilderTest extends UnitTestCase {

  /**
   * A non-content-entity form yields NULL.
   */
  public function testReturnsNullForNonContentEntityForm(): void {
    $formState = $this->prophesize(FormStateInterface::class);
    $formState->getFormObject()->willReturn($this->prophesize(FormInterface::class)->reveal());

    $form = [];
    $this->assertNull(PreviewEntityBuilder::fromFormState($form, $formState->reveal()));
  }

  /**
   * A built entity that is not a content entity yields NULL.
   */
  public function testReturnsNullWhenBuiltEntityIsNotContentEntity(): void {
    $formObject = $this->prophesize(ContentEntityFormInterface::class);
    $formObject->buildEntity(Argument::cetera())
      ->willReturn($this->prophesize(EntityInterface::class)->reveal());
    $formState = $this->prophesize(FormStateInterface::class);
    $formState->getFormObject()->willReturn($formObject->reveal());

    $form = [];
    $this->assertNull(PreviewEntityBuilder::fromFormState($form, $formState->reveal()));
  }

  /**
   * A content entity form yields the built entity flagged for preview.
   */
  public function testReturnsEntityFlaggedForPreview(): void {
    $entity = $this->prophesize(ContentEntityInterface::class);
    $formObject = $this->prophesize(ContentEntityFormInterface::class);
    $formObject->buildEntity(Argument::cetera())->willReturn($entity->reveal());
    $formState = $this->prophesize(FormStateInterface::class);
    $formState->getFormObject()->willReturn($formObject->reveal());

    $form = [];
    $result = PreviewEntityBuilder::fromFormState($form, $formState->reveal());

    $this->assertInstanceOf(ContentEntityInterface::class, $result);
    // The builder flags the throwaway entity for unsaved-state rendering.
    // @phpstan-ignore-next-line
    $this->assertTrue($result->in_preview);
  }

}
