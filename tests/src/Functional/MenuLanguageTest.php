<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_platform_config\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\content_translation\Functional\ContentTranslationTestBase;
use Drupal\user\UserInterface;

/**
 * Tests menu link translations.
 *
 * @group helfi_platform_config
 */
class MenuLanguageTest extends ContentTranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $entityTypeId = 'menu_link_content';

  /**
   * {@inheritdoc}
   */
  protected $bundle = 'menu_link_content';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
    'content_translation',
    'block',
    'test_page_test',
    'menu_ui',
    'menu_link_content',
    'menu_block_current_language',
    'helfi_platform_config',
  ];

  /**
   * A user with permission to access admin pages and administer languages.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'administer menu',
    ]);
    $this->drupalLogin($this->adminUser);

    $edit = [
      'language_interface[enabled][language-session]' => TRUE,
      'language_interface[weight][language-session]' => -12,
    ];
    $this->drupalGet('/admin/config/regional/language/detection');
    $this->submitForm($edit, 'Save settings');
    // Make sure we are not logged in.
    $this->drupalLogout();
  }

  /**
   * Create new menu link.
   *
   * @param string $langcode
   *   The language code.
   * @param string $title
   *   The title.
   * @param array $overrides
   *   The overrides.
   *
   * @return \Drupal\menu_link_content\Entity\MenuLinkContent
   *   The menu link.
   */
  protected function createTestLink($langcode, $title, array $overrides = []) : MenuLinkContent {
    $defaults = [
      'menu_name' => 'main',
      'title' => $title,
      'langcode' => $langcode,
      'link' => [
        'uri' => 'internal:/admin/content',
      ],
    ];
    $link = MenuLinkContent::create($overrides + $defaults);
    $link->save();

    return $link;
  }

  /**
   * Tests that menu links are only visible for translated languages.
   */
  public function testMenuLanguage() {
    $this->drupalLogin($this->adminUser);
    $link = $this->createTestLink('en', 'First link', [
      'expanded' => 1,
    ]);

    $this->drupalGet('admin/structure/menu/manage/main', ['query' => ['language' => 'en']]);
    $this->assertSession()->linkExists($link->label());

    // Make sure link is not visible when translation doesnt exist.
    $this->drupalGet('admin/structure/menu/manage/main', ['query' => ['language' => 'fr']]);
    $this->assertSession()->linkNotExists($link->label());

    // Add translation and test that links gets visible.
    $link->addTranslation('fr', ['title' => 'First french title'])->save();
    $this->drupalGet('admin/structure/menu/manage/main', ['query' => ['language' => 'fr']]);
    $this->assertSession()->linkExists('First french title');

    // French link should not be visible to english.
    $this->drupalGet('admin/structure/menu/manage/main', ['query' => ['language' => 'en']]);
    $this->assertSession()->linkNotExists('First french title');

    // Test French only link.
    $link2 = $this->createTestLink('fr', 'French only title');
    $this->drupalGet('admin/structure/menu/manage/main', ['query' => ['language' => 'en']]);
    $this->assertSession()->linkNotExists($link2->label());

    // Test that untranslatable link is visible for both languages.
    foreach (
      [
        LanguageInterface::LANGCODE_NOT_APPLICABLE,
        LanguageInterface::LANGCODE_NOT_SPECIFIED,
      ] as $langcode
    ) {
      $link = $this->createTestLink($langcode, 'Untranslated ' . $langcode);

      foreach (['fr', 'en'] as $lang) {
        $this->drupalGet('admin/structure/menu/manage/main', ['query' => ['language' => $lang]]);
        $this->assertSession()->linkExists($link->label());
      }
    }
  }

}
