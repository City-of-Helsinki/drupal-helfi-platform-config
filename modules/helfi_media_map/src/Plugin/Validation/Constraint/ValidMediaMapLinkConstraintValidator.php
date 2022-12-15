<?php

declare(strict_types = 1);

namespace Drupal\helfi_media_map\Plugin\Validation\Constraint;

use Drupal\helfi_media_map\Plugin\media\Source\Map;
use League\Uri\Http;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ValidMapLink constraint.
 */
final class ValidMediaMapLinkConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($item, Constraint $constraint) {
    foreach ($item->getValue() as $value) {
      ['uri' => $uri] = $value;

      $uri = Http::createFromString($uri);

      if (!in_array($uri->getHost(), Map::VALID_URLS)) {
        $this->context->addViolation($constraint->errorMessage, [
          '%value' => $uri->getHost(),
          '%domains' => implode(', ', Map::VALID_URLS),
        ]);
      }
    }
  }

}
