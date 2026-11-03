<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests TPR favorites in the user dashboard.
 */
#[Group('helfi_tpr_config')]
#[RunTestsInSeparateProcesses]
class FavoriteUserContentTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_users',
    'helfi_platform_config_base',
    'helfi_tpr_config',
  ];

  /**
   * Gets the listing row of the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The table row.
   */
  private function getRow(ContentEntityInterface $entity): NodeElement {
    return $this->assertSession()->elementExists('xpath', '//tr[contains(., "' . $entity->label() . '")]');
  }

  /**
   * Asserts that an entity favorited in its listing is listed in the dashboard.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity last edited by another user.
   * @param string $listingPath
   *   The entity listing path.
   */
  private function assertListingFavoriteIsListed(ContentEntityInterface $entity, string $listingPath): void {
    $entityTypeId = $entity->getEntityTypeId();
    $user = $this->drupalCreateUser([
      'access remote entities overview',
      "update any $entityTypeId",
      "view $entityTypeId",
      'use favorites',
    ]);
    $this->drupalLogin($user);
    $dashboardPath = 'user/' . $user->id();

    $this->drupalGet($dashboardPath);
    $this->assertSession()->pageTextNotContains($entity->label());

    $this->drupalGet($listingPath);
    $path = $this->assertSession()->elementExists('css', '.favorite-toggle', $this->getRow($entity))->getAttribute('data-hx-post');
    $this->assertNotNull($path, 'The favorite toggle has a post URL.');

    $response = $this->getHttpClient()->request('POST', $this->getAbsoluteUrl($path), [
      'cookies' => $this->getSessionCookies(),
      'http_errors' => FALSE,
    ]);
    $this->assertEquals(200, $response->getStatusCode(), 'The toggle request succeeds.');
    $this->assertStringContainsString('favorite-toggle--active', (string) $response->getBody(), 'The listing click adds the favorite.');

    $this->drupalGet($dashboardPath);
    $this->assertSession()->elementExists('css', '.favorite-toggle--active', $this->getRow($entity));
  }

  /**
   * Tests that a unit favorited in the unit listing is listed.
   */
  public function testUnitListingFavoriteIsListed(): void {
    $unit = Unit::create([
      'id' => 'favorite-unit',
      'name' => $this->randomMachineName(),
      'revision_user' => $this->drupalCreateUser()->id(),
    ]);
    $unit->save();

    $this->assertListingFavoriteIsListed($unit, 'admin/content/integrations/tpr-unit');
  }

  /**
   * Tests that a service favorited in the service listing is listed.
   */
  public function testServiceListingFavoriteIsListed(): void {
    $service = Service::create([
      'id' => 'favorite-service',
      'name' => $this->randomMachineName(),
      'revision_user' => $this->drupalCreateUser()->id(),
    ]);
    $service->save();

    $this->assertListingFavoriteIsListed($service, 'admin/content/integrations/tpr-service');
  }

}
