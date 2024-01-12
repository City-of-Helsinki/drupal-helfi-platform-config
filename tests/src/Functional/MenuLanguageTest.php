<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_platform_config\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\content_translation\Functional\ContentTranslationTestBase;
use Drupal\Tests\helfi_api_base\Traits\DefaultConfigurationTrait;
use Drupal\user\UserInterface;

/**
 * Tests menu link translations.
 *
 * @group helfi_platform_config
 */
class MenuLanguageTest extends ContentTranslationTestBase {

  use DefaultConfigurationTrait;

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
    'system',
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
    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => 'en', 'fr' => 'fr', 'it' => 'it'])
      ->save();
  }

  /**
   * Create a new menu link.
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
  protected function createTestLink(string $langcode, $title, array $overrides = []) : MenuLinkContent {
    $defaults = [
      'menu_name' => 'main',
      'title' => $title,
      'langcode' => $langcode,
      'link' => [
        'uri' => 'internal:/admin',
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

    $this->drupalGetWithLanguage('admin/structure/menu/manage/main', 'en');
    $this->assertSession()->linkExists('First link');

    // Make sure a link is not visible when translation doesn't exist.
    $this->drupalGetWithLanguage('admin/structure/menu/manage/main', 'fr');
    $this->assertSession()->linkNotExists($link->label());

    // Add translation and test that links gets visible.
    $link->addTranslation('fr', ['title' => 'First French title'])->save();
    $this->drupalGetWithLanguage('admin/structure/menu/manage/main', 'fr');
    $this->assertSession()->linkExists('First French title');

    // French link should not be visible to english.
    $this->drupalGetWithLanguage('admin/structure/menu/manage/main', 'en');
    $this->assertSession()->linkNotExists('First french title');

    // Test French only link.
    $link2 = $this->createTestLink('fr', 'French only title');
    $this->drupalGetWithLanguage('admin/structure/menu/manage/main', 'en');
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
        $this->drupalGetWithLanguage('admin/structure/menu/manage/main', $lang);
        $this->assertSession()->linkExists($link->label());
      }
    }
  }

  /**
   * Run same tests with account's 'preferred_admin_langcode' set to French.
   */
  public function testMenuLanguageWithAdminUiLanguage() : void {
    $this->adminUser->set('preferred_admin_langcode', 'fr');
    $this->adminUser->save();
    $this->testMenuLanguage();
  }

}
