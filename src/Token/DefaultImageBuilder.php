<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Token;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_platform_config\Image\ImageBuilderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Default og image for all entities.
 */
class DefaultImageBuilder implements ImageBuilderInterface {

  /**
   * Constructs a new instance.
   *
   * @param array<string, string> $defaultImages
   *   Default image paths keyed by langcode.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file url generator.
   */
  public function __construct(
    #[Autowire(param: 'helfi_platform_config.default_image')]
    private readonly array $defaultImages,
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
    $current_language = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    $image_path = $this->defaultImages[$current_language] ?? $this->defaultImages['default'];

    return $this->fileUrlGenerator->generateAbsoluteString($image_path);
  }

}
