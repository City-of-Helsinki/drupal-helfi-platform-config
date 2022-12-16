<?php

declare(strict_types = 1);

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
  public function validate($item, Constraint $constraint) {
    foreach ($item->getValue() as $value) {
      ['uri' => $uri] = $value;

      try {
        $this->mediaUrlToUri($uri);
      }
      catch (\InvalidArgumentException) {
        $this->context->addViolation($constraint->errorMessage, [
          '%value' => $uri,
          '%domains' => Chart::CHART_POWERBI_URL,
        ]);
      }
    }
  }

}
