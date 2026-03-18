<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Sidebar Content constraint.
 *
 * @todo UHF-13030 Remove this when the field is removed.
 */
final class SidebarContentConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(MessengerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof SidebarContentConstraint);

    if ($value->count() > 0) {
      $this->context->addViolation($constraint->sidebarContentExists);
      $this->messenger->addError($this->t('The sidebar content area will be removed. Please move the content to the upper or lower content area or remove it.'));
    }
  }

}
