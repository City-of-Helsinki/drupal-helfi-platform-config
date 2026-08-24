<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Generates heading anchor slugs.
 *
 * @see \Drupal\helfi_platform_config\HeadingIdInjector
 */
final class HeadingSlugger {

  /**
   * Slugs already issued by this instance.
   *
   * @phpstan-var array<string, true>
   */
  private array $issued = [];

  /**
   * IDs already present on the page.
   *
   * @phpstan-var array<string, true>
   */
  private array $reserved = [];

  public function __construct(
    #[Autowire(service: 'transliteration')]
    private readonly TransliterationInterface $transliteration,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Sets the IDs the generated slugs must not collide with.
   *
   * @param string[] $reservedIds
   *   IDs already present on the page.
   */
  public function withReservedIds(array $reservedIds): self {
    $slugger = clone $this;
    $slugger->issued = [];
    $slugger->reserved = array_fill_keys($reservedIds, TRUE);
    return $slugger;
  }

  /**
   * Generate a unique fragment for the given heading text.
   *
   * @param string $headingText
   *   Raw heading text.
   *
   * @return string
   *   The slug, with collision suffixes applied if needed.
   */
  public function slug(string $headingText): string {
    $name = mb_trim(mb_strtolower($headingText));

    if ($name === '') {
      return '';
    }

    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_URL)
      ->getId();

    $name = $this->transliteration->transliterate($name, $langcode);

    // Replace anything that is not URL safe with '-'.
    $name = preg_replace('/[^A-Za-z0-9_]/', '-', $name) ?? $name;

    // Trailing -<digits> becomes _<digits> ('example-1' to 'example_1').
    $name = preg_replace('/-(\d+)$/', '_$1', $name) ?? $name;

    $available = $this->findAvailable($name);
    $this->issued[$available] = TRUE;
    return $available;
  }

  /**
   * Finds an available slug.
   */
  private function findAvailable(string $name, int $count = 0): string {
    $candidate = $count > 0 ? $name . '-' . $count : $name;

    if (isset($this->reserved[$candidate])) {
      return $this->findAvailable($name, $count + 1);
    }

    if (isset($this->issued[$candidate])) {
      if ($count === 0) {
        $count = 1;
      }
      return $this->findAvailable($name, $count + 1);
    }

    return $candidate;
  }

}
