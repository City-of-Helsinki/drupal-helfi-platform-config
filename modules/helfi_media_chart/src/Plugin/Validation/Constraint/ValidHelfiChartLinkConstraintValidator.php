<?php

declare(strict_types=1);

namespace Drupal\helfi_media_chart\Plugin\Validation\Constraint;

use Drupal\helfi_media_chart\Plugin\media\Source\Chart;
use Drupal\helfi_media_chart\UrlParserTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ValidHelfiChartLink constraint.
 */
final class ValidHelfiChartLinkConstraintValidator extends ConstraintValidator {

  use UrlParserTrait;

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    assert($constraint instanceof ValidHelfiChartLinkConstraint);
    foreach ($value->getValue() as $item) {
      ['uri' => $uri] = $item;

      try {
        $this->mediaUrlToUri($uri);
      }
      catch (\InvalidArgumentException) {
        $this->context->addViolation($constraint->errorMessage, [
          '%value' => $uri,
          '%domains' => implode(', ', Chart::CHART_POWERBI_URL),
        ]);
      }
    }
  }

}
