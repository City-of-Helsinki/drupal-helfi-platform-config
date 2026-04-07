<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\Plugin\Validation\Constraint;

use Drupal\helfi_react_search\Plugin\Validation\Constraint\LinkedEventsQueryStringConstraint;
use Drupal\helfi_react_search\Plugin\Validation\Constraint\LinkedEventsQueryStringConstraintValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Tests the LinkedEventsQueryStringConstraintValidator.
 */
#[Group('helfi_react_search')]
final class LinkedEventsQueryStringConstraintValidatorTest extends UnitTestCase {

  /**
   * Tests validate() for query strings with and without disallowed parameters.
   */
  #[DataProvider('providerValidate')]
  public function testValidate(array $itemValues, int $expectedViolations): void {
    $constraint = new LinkedEventsQueryStringConstraint();

    $builders = [];
    for ($i = 0; $i < $expectedViolations; $i++) {
      $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
      $builder->method('setParameter')->willReturnSelf();
      $builder->expects($this->once())->method('addViolation');
      $builders[] = $builder;
    }

    $context = $this->createMock(ExecutionContextInterface::class);
    if ($expectedViolations === 0) {
      $context->expects($this->never())->method('buildViolation');
    }
    else {
      $context->expects($this->exactly($expectedViolations))
        ->method('buildViolation')
        ->with($constraint->notValid)
        ->willReturnOnConsecutiveCalls(...$builders);
    }

    $value = [];
    foreach ($itemValues as $itemValue) {
      $item = new \stdClass();
      $item->value = $itemValue;
      $value[] = $item;
    }

    $sut = new LinkedEventsQueryStringConstraintValidator();
    $sut->initialize($context);
    $sut->validate($value, $constraint);
  }

  /**
   * Data provider for testValidate().
   *
   * @return array<string, array{0: list<string>, 1: int}>
   *   The test cases.
   */
  public static function providerValidate(): array {
    return [
      'no items' => [
        [],
        0,
      ],
      'non-query string is skipped' => [
        ['https://api.example.com/v1/event/'],
        0,
      ],
      'query string without disallowed parameters' => [
        ['?full_text=music&page=1'],
        0,
      ],
      'single disallowed parameter' => [
        ['?all_ongoing=1'],
        1,
      ],
      'multiple disallowed parameters on one item' => [
        ['?all_ongoing_AND=1&all_ongoing_OR=1'],
        1,
      ],
      'only second item violates' => [
        [
          '?full_text=test',
          '?all_ongoing_OR=yes',
        ],
        1,
      ],
      'multiple items each violate' => [
        [
          '?all_ongoing=1',
          '?all_ongoing_AND=1',
        ],
        2,
      ],
    ];
  }

  /**
   * Tests that violation builder receives expected parameters.
   */
  public function testValidateSetsViolationParameters(): void {
    $constraint = new LinkedEventsQueryStringConstraint();

    $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $builder->expects($this->exactly(2))
      ->method('setParameter')
      ->willReturnCallback(function (string $name, string $value) use ($builder): ConstraintViolationBuilderInterface {
        static $invocation = 0;
        $invocation++;
        if ($invocation === 1) {
          self::assertSame('%value', $name);
          self::assertSame('?all_ongoing=1', $value);
        }
        elseif ($invocation === 2) {
          self::assertSame('%disallowedQueryParameters', $name);
          self::assertSame('all_ongoing', $value);
        }
        return $builder;
      });
    $builder->expects($this->once())->method('addViolation');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('buildViolation')
      ->with($constraint->notValid)
      ->willReturn($builder);

    $item = new \stdClass();
    $item->value = '?all_ongoing=1';

    $sut = new LinkedEventsQueryStringConstraintValidator();
    $sut->initialize($context);
    $sut->validate([$item], $constraint);
  }

}
