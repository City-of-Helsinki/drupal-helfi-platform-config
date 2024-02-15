<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Taxonomy related tests.
 *
 * @group helfi_platform_config
 */
class TaxonomyTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'helfi_platform_config',
  ];

  /**
   * Tests the access to taxonomy term pages.
   */
  public function testTermPageAccess() : void {
    $vocabulary = Vocabulary::create([
      'name' => 'Test vocabulary',
      'vid' => 'test',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $vocabulary->save();

    $term = Term::create([
      'vid' => $vocabulary->id(),
      'name' => 'Test term',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $term->save();

    $termUrl = '/taxonomy/term/' . $term->id();

    $this->drupalGet($termUrl);
    $this->assertSession()->statusCodeEquals(403);

    $account = $this->createUser();
    $this->drupalLogin($account);

    $this->drupalGet($termUrl);
    $this->assertSession()->statusCodeEquals(200);

  }

}
