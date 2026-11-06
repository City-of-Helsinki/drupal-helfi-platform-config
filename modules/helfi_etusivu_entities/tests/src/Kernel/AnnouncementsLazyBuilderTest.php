<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Kernel;

use Drupal\helfi_etusivu_entities\AnnouncementsLazyBuilder;
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
