<?php

declare(strict_types = 1);

namespace Drupal\helfi_paragraphs_hearings\Plugin\migrate\process;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Handle media entity.
 *
 * @MigrateProcessPlugin(
 *   id = "media_handler",
 * )
 */
class MediaHandler extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $destination = $row->getDestination();
    return $this->handleMedia($destination);
  }

  /**
   * Handle media.
   *
   * @param array $destination
   *   Destination array.
   *
   * @return string
   *   Media id.
   */
  private function handleMedia(array $destination): string {
    $filename = $destination['_filename'];
    $file_path = $destination['_file_copy'];

    $result = \Drupal::database()
      ->select('file_managed', 'f')
      ->condition('f.filename', $filename)
      ->fields('f', ['fid'])
      ->range(0, 1)
      ->execute()
      ->fetchAll();

    if (!$result) {
      $file_image = File::create(['uri' => $file_path]);
      $file_image->save();
      $file_id = $file_image->id();
    }
    else {
      $result = reset($result);
      $file_id = $result->fid;
    }

    $ids = \Drupal::entityQuery('media')
      ->condition('bundle', 'image')
      ->condition('field_media_image.target_id', $file_id)
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($ids)) {
      return reset($ids);
    }
    else {
      $media_image = Media::create([
        'bundle' => 'image',
        'uid' => 0,
        'name' => pathinfo($filename, PATHINFO_FILENAME),
        'field_media_image' => [
          'target_id' => $file_id,
        ],
      ]);

      $media_image->save();
      $new_media_id = $media_image->id();

      return $new_media_id;
    }
  }

}
