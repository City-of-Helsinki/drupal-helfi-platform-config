<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_admin_tools\Unit\Plugin\Validation\Constraint;

use Drupal\hdbt_admin_tools\Plugin\Validation\Constraint\SidebarContentConstraint;
use Drupal\hdbt_admin_tools\Plugin\Validation\Constraint\SidebarContentConstraintValidator;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests the SidebarContentConstraintValidator.
 *
 * @group hdbt_admin_tools
 */
final class SidebarContentConstraintValidatorTest extends UnitTestCase {

  /**
   * Tests that create() returns a properly constructed instance.
   */
  public function testCreate(): void {
    $messenger = $this->createMock(MessengerInterface::class);

    $container = $this->createMock(ContainerInterface::class);
    $container
      ->method('get')
      ->with(MessengerInterface::class)
      ->willReturn($messenger);

    $sut = SidebarContentConstraintValidator::create($container);
    $this->assertInstanceOf(SidebarContentConstraintValidator::class, $sut);
  }

  /**
   * Tests validate() behavior with different sidebar item counts.
   */
  #[DataProvider('providerValidate')]
  public function testValidate(
    int $itemCount,
    int $expectedViolations,
    int $expectedErrors,
  ): void {
    $constraint = new SidebarContentConstraint();

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger
      ->expects($this->exactly($expectedErrors))
      ->method('addError');

    $translation = $this->createMock(TranslationInterface::class);
    $translation
      ->method('translateString')
      ->willReturnCallback(fn($str) => $str->getUntranslatedString());

    $context = $this->createMock(ExecutionContextInterface::class);
    $context
      ->expects($this->exactly($expectedViolations))
      ->method('addViolation')
      ->with($constraint->sidebarContentExists);

    $value = $this->createMock(\Countable::class);
    $value->method('count')->willReturn($itemCount);

    $sut = new SidebarContentConstraintValidator($messenger);
    $sut->setStringTranslation($translation);
    $sut->initialize($context);
    $sut->validate($value, $constraint);
  }

  /**
   * Data provider for testValidate().
   *
   * @return array[]
   *   The test cases.
   */
  public static function providerValidate(): array {
    return [
      'no violation when sidebar is empty' => [
        'itemCount' => 0,
        'expectedViolations' => 0,
        'expectedErrors' => 0,
      ],
      'adds violation when sidebar has one item' => [
        'itemCount' => 1,
        'expectedViolations' => 1,
        'expectedErrors' => 1,
      ],
      'adds violation when sidebar has many items' => [
        'itemCount' => 5,
        'expectedViolations' => 1,
        'expectedErrors' => 1,
      ],
    ];
  }

}
