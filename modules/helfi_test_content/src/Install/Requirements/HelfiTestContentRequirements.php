<?php

declare(strict_types=1);

namespace Drupal\helfi_test_content\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;

/**
 * Prevents installing test content in production.
 */
final class HelfiTestContentRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   *
   * @return array<string, array<string, mixed>>
   *   The requirements.
   */
  public static function getRequirements(): array {
    // Not available during site install.
    if (!\Drupal::hasService(EnvironmentResolverInterface::class)) {
      return [];
    }

    $environment = \Drupal::service(EnvironmentResolverInterface::class)
      ->getActiveEnvironmentName();

    if ($environment !== EnvironmentEnum::Prod->value) {
      return [];
    }

    return [
      'helfi_test_content' => [
        'title' => new TranslatableMarkup('Hel.fi: Test content'),
        'severity' => RequirementSeverity::Error,
        'description' => new TranslatableMarkup('The helfi_test_content module must never be installed in production.'),
      ],
    ];
  }

}
