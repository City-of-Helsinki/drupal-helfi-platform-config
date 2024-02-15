<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Functional;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\helfi_api_base\Traits\DefaultConfigurationTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;

/**
 * Tests the language switcher alter changes affecting anonymous user.
 *
 * @group helfi_platform_config
 */
class LanguageSwitcherAlterTest extends BrowserTestBase {

  use DefaultConfigurationTrait;
  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
    'content_translation',
    'node',
    'block',
    'helfi_platform_config',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface|null
   */
  protected ?NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();
    foreach (['fi', 'sv'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => 'en', 'fi' => 'fi', 'sv' => 'sv'])
      ->save();

    $this->drupalPlaceBlock('language_switcher_admin', [
      'region' => 'header_branding',
      'theme' => $this->defaultTheme,
    ]);

    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);

    NodeType::create([
      'type' => 'page',
    ])->save();
  }

  /**
   * Tests that languages are visible in language switcher.
   */
  public function testLanguageSwitcher() : void {
    $node = Node::create(['type' => 'page', 'title' => 'Title en']);
    $node->save();

    foreach (['fi', 'sv'] as $language) {
      $node->addTranslation($language, [
        'title' => 'Title ' . $language,
      ]);
    }
    $node->save();

    $node->getTranslation('sv')
      ->set('status', 0)
      ->save();

    foreach (['en', 'fi', 'sv'] as $langcode) {
      $this->drupalGetWithLanguage("node/{$node->id()}", $langcode);
      $elements = $this->xpath('//span|a[@class="language-link"]');
      $this->assertCount(3, $elements);
    }
  }

}
