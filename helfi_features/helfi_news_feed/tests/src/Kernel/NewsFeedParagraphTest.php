<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_news_feed\Kernel;

use Drupal\helfi_news_feed\Entity\NewsFeedParagraph;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests NewsFeedParagraph installation.
 *
 * @group helfi_news_feed
 */
class NewsFeedParagraphTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'user',
    'paragraphs',
    'external_entities',
    'helfi_news_feed',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('paragraphs_type');
    $this->installConfig('paragraphs');
    $this->installConfig('helfi_news_feed');
    $this->installEntitySchema('helfi_news');
  }

  /**
   * Tests that paragraph uses proper bundle class.
   */
  public function testBundleClass() : void {
    $paragraph = Paragraph::create([
      'type' => 'news_list',
    ]);
    $paragraph->save();

    $this->assertInstanceOf(NewsFeedParagraph::class, $paragraph);
  }

}
