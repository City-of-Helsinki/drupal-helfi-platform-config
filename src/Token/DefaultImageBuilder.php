<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Token;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Default og image for all entities.
 */
class DefaultImageBuilder implements OGImageBuilderInterface {

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LanguageManagerInterface $languageManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function applies(?EntityInterface $entity): bool {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function buildUri(?EntityInterface $entity): ?string {
    $module = $this->moduleHandler->getModule('helfi_platform_config');
    $current_language = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    $image_file_name = $current_language === 'sv' ? 'og-global-sv.png' : 'og-global.png';

    return $this->fileUrlGenerator
      ->generateAbsoluteString("{$module->getPath()}/fixtures/{$image_file_name}");
  }

}
