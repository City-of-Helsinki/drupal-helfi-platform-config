<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Kernel;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\helfi_etusivu_entities\AnnouncementsLazyBuilder;
use Drupal\helfi_node_announcement\Entity\Announcement;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests announcement visibility.
 */
#[Group('helfi_platform_config')]
final class AnnouncementsLazyBuilderTest extends LazyBuilderTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('system.site')->set('page.front', '/frontpage')->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function builderClass(): string {
    return AnnouncementsLazyBuilder::class;
  }

  /**
   * Tests which announcements are visible for a path or content page.
   */
  public function testVisibility(): void {
    $pageA = $this->page('Page A');
    $pageB = $this->page('Page B');

    $this->announcement('Global');
    $this->announcement('On feedback', [
      'field_announcement_all_pages' => 0,
      'field_announcement_page_paths' => '/feedback',
    ]);
    $this->announcement('On section', [
      'field_announcement_all_pages' => 0,
      'field_announcement_page_paths' => '/section/*',
    ]);
    $this->announcement('On front', [
      'field_announcement_all_pages' => 0,
      'field_announcement_page_paths' => '<front>',
    ]);
    $this->announcement('On page', [
      'field_announcement_all_pages' => 0,
      'field_announcement_content_pages' => ['target_id' => $pageA->id()],
    ]);
    $this->announcement('On other page', [
      'field_announcement_all_pages' => 0,
      'field_announcement_content_pages' => ['target_id' => $pageB->id()],
    ]);
    $this->announcement('Hidden', ['status' => NodeInterface::NOT_PUBLISHED]);
    $this->announcement('Finnish', ['langcode' => 'fi']);

    $this->setCurrentPage('/feedback');
    $this->assertSame(['On feedback', 'Global'], $this->announcementTitles());

    $this->setCurrentPage('/section/child');
    $this->assertSame(['On section', 'Global'], $this->announcementTitles());

    $this->setCurrentPage('/section');
    $this->assertSame(['Global'], $this->announcementTitles());

    $this->setCurrentPage('/frontpage');
    $this->assertSame(['On front', 'Global'], $this->announcementTitles());

    $this->setCurrentPage('/page-a', $pageA);
    $this->assertSame(['On page', 'Global'], $this->announcementTitles());

    $this->setCurrentPage('/page-b', $pageB);
    $this->assertSame(['On other page', 'Global'], $this->announcementTitles());

    $this->setCurrentPage('/feedback', NULL, 'system.404');
    $this->assertSame(['Global'], $this->announcementTitles());
  }

  /**
   * Tests that urgent and page-specific announcements render first.
   */
  public function testOrder(): void {
    $this->announcement('General');
    $this->announcement('Specific', [
      'field_announcement_all_pages' => 0,
      'field_announcement_page_paths' => '/feedback',
    ]);
    $this->announcement('Alert', [
      'field_announcement_type' => 'alert',
    ]);

    $this->setCurrentPage('/feedback');
    $this->assertSame(
      ['Alert', 'Specific', 'General'],
      $this->announcementTitles(),
    );
  }

  /**
   * Tests that severity orders first and specificity within the same type.
   */
  public function testOrderWithinType(): void {
    $page = $this->page('Page');
    $specific = [
      'field_announcement_all_pages' => 0,
      'field_announcement_page_paths' => '/page',
    ];

    $this->announcement('Notification general');
    $this->announcement('Notification specific', $specific);
    $this->announcement('Alert general', ['field_announcement_type' => 'alert']);
    $this->announcement('Attention on page', [
      'field_announcement_type' => 'attention',
      'field_announcement_all_pages' => 0,
      'field_announcement_content_pages' => ['target_id' => $page->id()],
    ]);
    $this->announcement('Alert specific', ['field_announcement_type' => 'alert'] + $specific);
    $this->announcement('Attention general', ['field_announcement_type' => 'attention']);
    $this->announcement('Unrestricted but not on all pages', [
      'field_announcement_all_pages' => 0,
    ]);

    $this->setCurrentPage('/page', $page);
    $this->assertSame([
      'Alert specific',
      'Alert general',
      'Attention on page',
      'Attention general',
      'Notification specific',
      'Notification general',
    ], $this->announcementTitles());
  }

  /**
   * Tests that announcement nodes use the bundle class.
   */
  public function testBundleClass(): void {
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create(['type' => 'announcement']);
    $this->assertInstanceOf(Announcement::class, $node);

    // Only Etusivu has the field.
    $this->assertFalse($node->publishExternally());
    $this->assertSame(['helfi_external_entity_announcement'], $node->getExternalCacheTags());
  }

  /**
   * Tests populating an announcement from a remote announcement.
   */
  public function testPopulateFromRemoteEntity(): void {
    $entityTypeManager = $this->container->get('entity_type.manager');
    $remote = $entityTypeManager->getStorage('helfi_announcements')->create([
      'uuid' => 'c9ee55c3-9ca5-4c53-900e-82b6d6928a99',
      'langcode' => 'fi',
      'title' => 'Remote',
      'body' => '<p>Body</p>',
      'status' => NodeInterface::PUBLISHED,
      'announcement_type' => 'alert',
      'announcement_assistive_technology_close_button_title' => 'Close remote',
      'announcement_link_text' => 'Read more',
      'announcement_link_url' => 'https://example.com',
    ]);
    $this->assertInstanceOf(FieldableEntityInterface::class, $remote);

    $node = $entityTypeManager->getStorage('node')->create(['type' => 'announcement']);
    $this->assertInstanceOf(Announcement::class, $node);
    $this->assertSame($node, $node->populateFromRemoteEntity($remote));

    $this->assertTrue($node->isNew());
    $this->assertSame('c9ee55c3-9ca5-4c53-900e-82b6d6928a99', $node->uuid());
    $this->assertSame('fi', $node->get('langcode')->value);
    $this->assertSame('Remote', $node->label());
    $this->assertSame('<p>Body</p>', $node->get('body')->value);
    $this->assertSame('minimal', $node->get('body')->format);
    $this->assertTrue($node->isPublished());
    $this->assertSame('alert', $node->getAnnouncementType());
    $this->assertSame('Close remote', $node->get('field_announcement_title')->value);
    $this->assertSame('https://example.com', $node->get('field_announcement_link')->uri);
    $this->assertSame('Read more', $node->get('field_announcement_link')->title);
  }

  /**
   * Creates an announcement.
   *
   * @param string $title
   *   The announcement title.
   * @param array<string, mixed> $values
   *   Field value overrides.
   */
  private function announcement(string $title, array $values = []): void {
    $node = Node::create($values + [
      'type' => 'announcement',
      'title' => $title,
      'status' => NodeInterface::PUBLISHED,
      'langcode' => 'en',
      'field_announcement_title' => $title,
      'field_announcement_type' => 'notification',
      'field_announcement_all_pages' => 1,
    ]);
    $node->save();
  }

  /**
   * Builds the local announcements and returns their titles.
   *
   * @return string[]
   *   Visible announcement titles, in render order.
   */
  private function announcementTitles(): array {
    $builder = $this->container->get(AnnouncementsLazyBuilder::class);
    if (!$builder instanceof AnnouncementsLazyBuilder) {
      throw new \RuntimeException('The announcement builder is missing.');
    }
    return $this->titles($builder->lazyBuild(FALSE));
  }

}
