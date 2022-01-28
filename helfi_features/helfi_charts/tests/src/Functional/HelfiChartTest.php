<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tpr\Functional;

use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests media map functionality.
 *
 * @group helfi_charts
 */
class HelfiChartTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
    'link',
    'helfi_charts',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();
    // Setup standalone media urls from the settings.
    $this->config('media.settings')->set('standalone_url', TRUE)
      ->save();
    $this->refreshVariables();
    // Rebuild routes.
    \Drupal::service('router.builder')->rebuild();

    $account = $this->createUser([
      'view media',
      'create media',
      'update media',
      'update any media',
      'delete media',
      'delete any media',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Asserts media map formatter.
   *
   * @var int $media_id$
   *   The media id.
   */
  private function assertMapFormatter(int $media_id) : void {
    $media = Media::load($media_id);

    $this->drupalGet(Url::fromRoute('entity.media.revision', [
      'media' => $media->id(),
      'media_revision' => $media->getRevisionId(),
    ]));
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests 'helfi_chart' media type.
   */
  public function testMediaType() : void {
    \Drupal::service('entity_display.repository')->getViewDisplay('media', MediaType::load('helfi_chart')->id(), 'full')
      ->setComponent('field_media_helfi_chart', [
        'type' => 'helfi_charts',
      ])
      ->save();

    $this->drupalGet(Url::fromRoute('entity.media.add_form', ['media_type' => 'helfi_chart']));
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'name[0][value]' => 'Test value',
      'field_media_helfi_chart[0][uri]' => 'https://google.com',
    ], 'Save');

    // Make sure we only allow valid domains.
    $this->assertSession()->pageTextContainsOnce('Given host (google.com) is not valid, must be one of: palvelukartta.hel.fi, kartta.hel.fi');

    // Make sure we can add valid maps.
    $urls = [
      'https://kartta.hel.fi/link/9UC458',
      'https://palvelukartta.hel.fi/fi/embed/address/helsinki/Keskuskatu/8?city=helsinki,espoo,vantaa,kauniainen',
    ];

    foreach ($urls as $delta => $url) {
      $this->drupalGet(Url::fromRoute('entity.media.add_form', ['media_type' => 'helfi_chart']));
      $this->assertSession()->statusCodeEquals(200);

      $this->submitForm([
        'name[0][value]' => 'Chart value ' . $delta,
        'field_media_helfi_chart[0][uri]' => $url,
      ], 'Save');
      $this->assertSession()->pageTextContainsOnce("Chart (kartta.hel.fi, palvelukartta.hel.fi) Chart value $delta has been created.");

      $medias = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties([
        'name' => 'Chart value ' . $delta,
      ]);
      /** @var \Drupal\media\MediaInterface */
      $media = reset($medias);
      $this->drupalGet(Url::fromRoute('entity.media.canonical', ['media' => $media->id()])->toString());
      $this->assertSession()->statusCodeEquals(200);
    }
  }

}
