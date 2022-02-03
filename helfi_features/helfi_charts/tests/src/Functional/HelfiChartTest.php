<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tpr\Functional;

use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Chart functionality.
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
   * Asserts chart URL formatter.
   *
   * @var int $media_id$
   *   The media id.
   */
  private function assertChartFormatter(int $media_id) : void {
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
      ->setComponent('field_helfi_chart_url', [
        'type' => 'helfi_charts',
      ])
      ->save();

    $this->drupalGet(Url::fromRoute('entity.media.add_form', ['media_type' => 'helfi_chart']));
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'name[0][value]' => 'Admin Test value',
      'field_helfi_chart_title[0][value]' => 'Test value',
      'field_helfi_chart_transcript[0][value]' => 'Transcription value',
      'field_helfi_chart_url[0][uri]' => 'https://google.com',
    ], 'Save');

    // Make sure we only allow valid domains.
    $this->assertSession()->pageTextContainsOnce('Given host (www.google.com) is not valid, must be one of: app.powerbi.com');

    // Make sure we can add valid maps.
    $urls = [
      'https://app.powerbi.com/view/9UC458',
    ];

    foreach ($urls as $delta => $url) {
      $this->drupalGet(Url::fromRoute('entity.media.add_form', ['media_type' => 'helfi_chart']));
      $this->assertSession()->statusCodeEquals(200);

      $this->submitForm([
        'name[0][value]' => 'Admin Test value ' . $delta,
        'field_helfi_chart_title[0][value]' => 'Test value'. $delta,
        'field_helfi_chart_transcript[0][value]' => 'Transcription value'. $delta,
        'field_helfi_chart_url[0][uri]' => $url,
      ], 'Save');
      $this->assertSession()->pageTextContainsOnce("Chart Admin Test value $delta has been created.");

      $medias = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties([
        'name' => 'Admin Test value ' . $delta,
      ]);
      /** @var \Drupal\media\MediaInterface */
      $media = reset($medias);
      $this->drupalGet(Url::fromRoute('entity.media.canonical', ['media' => $media->id()])->toString());
      $this->assertSession()->statusCodeEquals(200);
    }
  }

}
