<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_chart\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests media chart functionality.
 *
 * @group helfi_platform_config
 */
class MediaChartTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'media',
    'menu_ui',
    'media',
    'helfi_media_chart',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();
    // Setup standalone media urls from the settings.
    $this->config('media.settings')->set('standalone_url', TRUE)
      ->save();
    // Switch text field formatter for helfi_chart.
    $this->switchTextFieldFormatter('media', 'helfi_chart', 'field_helfi_chart_transcript');
    $this->refreshVariables();
    // Rebuild routes.
    \Drupal::service('router.builder')->rebuild();

    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests 'hel_map' media type.
   */
  public function testMediaType() : void {
    $addRoute = Url::fromRoute('entity.media.add_form', ['media_type' => 'helfi_chart']);
    $this->drupalGet($addRoute);
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'name[0][value]' => 'Test value',
      'field_helfi_chart_title[0][value]' => 'Test value',
      'field_helfi_chart_url[0][uri]' => 'https://google.com',
      'field_helfi_chart_transcript[0][value]' => '123',
    ], 'Save');

    // Make sure we only allow valid domains.
    $this->assertSession()->pageTextContainsOnce('Given host (https://google.com) is not valid, must be one of: app.powerbi.com');

    $this->drupalGet($addRoute);
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'name[0][value]' => 'Chart',
      'field_helfi_chart_url[0][uri]' => 'https://app.powerbi.com/view?r=123',
      'field_helfi_chart_title[0][value]' => 'Test value',
      'field_helfi_chart_transcript[0][value]' => '123',
    ], 'Save');
    $this->assertSession()->pageTextContainsOnce('Chart embed Chart has been created.');

    $medias = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties([
      'name' => 'Chart',
    ]);
    /** @var \Drupal\media\MediaInterface $media */
    $media = reset($medias);
    $this->drupalGet(Url::fromRoute('entity.media.canonical', ['media' => $media->id()]));
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Switch text field formatter.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $entity_bundle
   *   Entity bundle.
   * @param string $field_name
   *   Field name.
   */
  private function switchTextFieldFormatter(string $entity_type, string $entity_bundle, string $field_name) : void {
    // Get the FieldConfig for the specified field.
    $field_storage = FieldStorageConfig::loadByName($entity_type, $field_name);
    if ($field_storage) {
      $field_config = FieldConfig::loadByName($entity_type, $entity_bundle, $field_name);

      // Set the default text format for the field to 'plain_text'.
      $field_config->set('third_party_settings', [
        'allowed_formats' => [
          'allowed_formats' => [
            'plain_text',
          ],
        ],
      ]);
      $field_config->save();
    }
  }

}
