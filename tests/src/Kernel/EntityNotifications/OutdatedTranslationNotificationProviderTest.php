<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\EntityNotifications;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_platform_config\EntityNotifications\OutdatedTranslationNotificationProvider;
use Drupal\Tests\helfi_platform_config\Kernel\Translation\OutdatedTranslationTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the outdated translation notification provider.
 */
#[CoversClass(OutdatedTranslationNotificationProvider::class)]
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class OutdatedTranslationNotificationProviderTest extends OutdatedTranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('router.builder')->rebuild();
    $this->setUpCurrentUser(permissions: ['access content', 'translate any entity']);
  }

  /**
   * Gets the notifications for the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheability
   *   The cacheability.
   *
   * @return list<\Drupal\helfi_platform_config\EntityNotifications\EntityNotification>
   *   The notifications.
   */
  private function getNotifications(ContentEntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
    return $this->container->get(OutdatedTranslationNotificationProvider::class)
      ->getNotifications($entity, $cacheability ?? new CacheableMetadata());
  }

  /**
   * Tests the notification on the original and the translations.
   */
  public function testNotification(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, [
      'sv' => self::ORIGINAL - 21 * self::DAY,
      'en' => self::ORIGINAL,
    ]);

    // The original and the outdated translation get the notification.
    foreach (['fi', 'sv'] as $langcode) {
      $cacheability = new CacheableMetadata();
      $notifications = $this->getNotifications($node->getTranslation($langcode), $cacheability);

      $this->assertCount(1, $notifications);
      $notification = $notifications[0];
      $this->assertSame('Check the language versions', (string) $notification->label);
      $this->assertSame('alert-circle-fill', $notification->icon);
      $this->assertSame('entity.node.content_translation_overview', $notification->url->getRouteName());
      $this->assertEquals(['node' => $node->id()], $notification->url->getRouteParameters());
      $this->assertSame(['translation-state--outdated'], $notification->url->getOption('attributes')['class']);
      $this->assertContains('node:' . $node->id(), $cacheability->getCacheTags());
      $this->assertContains('user.permissions', $cacheability->getCacheContexts());
    }

    // The up-to-date translation does not.
    $this->assertSame([], $this->getNotifications($node->getTranslation('en')));
  }

  /**
   * Tests a node without outdated translations.
   */
  public function testUpToDate(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, ['sv' => self::ORIGINAL - self::DAY]);

    $cacheability = new CacheableMetadata();
    $this->assertSame([], $this->getNotifications($node, $cacheability));
    $this->assertContains('node:' . $node->id(), $cacheability->getCacheTags());
  }

  /**
   * Tests that users without access to the overview get no notification.
   */
  public function testNoAccess(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, ['sv' => self::ORIGINAL - 21 * self::DAY]);
    $this->setUpCurrentUser(permissions: ['access content']);

    $this->assertSame([], $this->getNotifications($node));
  }

  /**
   * Tests a bundle that is not translatable.
   */
  public function testTranslationDisabled(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, ['sv' => self::ORIGINAL - 21 * self::DAY]);
    $this->container->get('content_translation.manager')->setEnabled('node', 'page', FALSE);

    $this->assertSame([], $this->getNotifications($node));
  }

  /**
   * Tests the rendered notification.
   */
  public function testRender(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, ['sv' => self::ORIGINAL - 21 * self::DAY]);

    $build = [
      '#theme' => 'helfi_entity_notifications',
      '#notifications' => $this->getNotifications($node),
    ];
    $output = (string) $this->container->get('renderer')->renderInIsolation($build);

    $this->assertMatchesRegularExpression('/<a [^>]*class="translation-state--outdated entity-notifications__link"[^>]*><span class="hel-icon hel-icon--alert-circle-fill" aria-hidden="true"><\/span>Check the language versions\s*<\/a>/', $output);
  }

}
