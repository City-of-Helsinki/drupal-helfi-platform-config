<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\Validation\Constraint;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\helfi_platform_config\Plugin\Validation\Constraint\MultisiteLinkAccessConstraint;
use Drupal\helfi_platform_config\Plugin\Validation\Constraint\MultisiteLinkAccessConstraintValidator;
use Drupal\link\LinkItemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Tests the multisite link access constraint.
 */
#[CoversClass(MultisiteLinkAccessConstraintValidator::class)]
#[Group('helfi_platform_config')]
final class MultisiteLinkAccessConstraintValidatorTest extends UnitTestCase {

  /**
   * Tests that the placeholder route skips the access check.
   */
  public function testMultisiteCanonicalRouteIsAllowed(): void {
    $url = $this->createMock(Url::class);
    $url->method('isRouted')->willReturn(TRUE);
    $url->method('getRouteName')->willReturn('entity.' . MultisiteContentId::ENTITY_TYPE_ID . '.canonical');
    $url->expects($this->never())->method('access');

    $link = $this->createMock(LinkItemInterface::class);
    $link->method('isEmpty')->willReturn(FALSE);
    $link->method('getUrl')->willReturn($url);

    $user = $this->createMock(AccountProxyInterface::class);
    $user->expects($this->never())->method('hasPermission');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('buildViolation');

    $validator = new MultisiteLinkAccessConstraintValidator($user);
    $validator->initialize($context);
    $validator->validate($link, new MultisiteLinkAccessConstraint());
  }

  /**
   * Tests that other inaccessible routes are still rejected.
   */
  public function testOtherInaccessibleRouteIsRejected(): void {
    $url = $this->createMock(Url::class);
    $url->method('isRouted')->willReturn(TRUE);
    $url->method('getRouteName')->willReturn('entity.node.canonical');
    $url->expects($this->once())->method('access')->willReturn(FALSE);

    $link = $this->createMock(LinkItemInterface::class);
    $link->method('isEmpty')->willReturn(FALSE);
    $link->method('getUrl')->willReturn($url);
    $link->method('__get')->willReturnMap([
      ['uri', 'entity:node/1'],
    ]);

    $user = $this->createMock(AccountProxyInterface::class);
    $user->expects($this->once())
      ->method('hasPermission')
      ->with('link to any page')
      ->willReturn(FALSE);

    $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $builder->expects($this->once())->method('atPath')->with('uri')->willReturnSelf();
    $builder->expects($this->once())->method('addViolation');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('buildViolation')
      ->willReturn($builder);

    $validator = new MultisiteLinkAccessConstraintValidator($user);
    $validator->initialize($context);
    $validator->validate($link, new MultisiteLinkAccessConstraint());
  }

}
