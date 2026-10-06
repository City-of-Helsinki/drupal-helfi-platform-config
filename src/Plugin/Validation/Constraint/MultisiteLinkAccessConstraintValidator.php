<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Validation\Constraint;

use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Validation\Constraint\LinkAccessConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Allows links to the multisite content placeholder route.
 *
 * That route is not publicly viewable. The stored URI is replaced with the
 * external URL when the link is rendered.
 */
final class MultisiteLinkAccessConstraintValidator extends LinkAccessConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if ($value instanceof LinkItemInterface && !$value->isEmpty()) {
      try {
        $url = $value->getUrl();
        if ($url->isRouted() && $url->getRouteName() === 'entity.' . MultisiteContentId::ENTITY_TYPE_ID . '.canonical') {
          return;
        }
      }
      catch (\InvalidArgumentException) {
      }
    }

    parent::validate($value, $constraint);
  }

}
