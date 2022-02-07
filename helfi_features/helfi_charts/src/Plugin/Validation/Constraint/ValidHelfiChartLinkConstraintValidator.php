<?php

declare(strict_types = 1);

namespace Drupal\helfi_charts\Plugin\Validation\Constraint;

use Drupal\helfi_charts\Plugin\media\Source\Chart;
use League\Uri\Http;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ValidHelfiChartLink constraint.
 */
final class ValidHelfiChartLinkConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($item, Constraint $constraint) {
    foreach ($item->getValue() as $value) {
      ['uri' => $uri] = $value;

      $uri = Http::createFromString($uri);

      if (!in_array($uri->getHost(), Chart::VALID_URLS)) {
        $this->context->addViolation($constraint->errorMessage, [
          '%value' => $uri->getHost(),
          '%domains' => implode(', ', Chart::VALID_URLS),
        ]);
      }
    }
  }

}
