<?php

declare(strict_types=1);

namespace Drupal\helfi_image_styles\Drush\Commands;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\FileInterface;
use Drupal\helfi_image_styles\ExifOrientation;
use Drupal\image\ImageDerivativeUtilities;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fixes the orientation of the existing images.
 *
 * @see \Drupal\helfi_image_styles\ExifOrientation
 */
#[AsCommand(
  name: 'helfi:image-styles:fix-exif-orientation',
  description: 'Rotates the existing images according to their EXIF orientation.',
  usages: [
    'helfi:image-styles:fix-exif-orientation --dry-run',
    'helfi:image-styles:fix-exif-orientation',
  ],
)]
final readonly class ExifOrientationCommand {

  use AutowireTrait;

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private EntityFieldManagerInterface $entityFieldManager,
    private ImageFactory $imageFactory,
    private ExifOrientation $exifOrientation,
    private ImageDerivativeUtilities $imageDerivativeUtilities,
  ) {
  }

  /**
   * Executes the command.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output interface.
   * @param bool $dryRun
   *   Whether to only list the images that would be rotated.
   *
   * @return int
   *   The exit code.
   */
  public function __invoke(
    SymfonyStyle $io,
    #[Option(description: 'Lists the images that would be rotated without changing them.')]
    bool $dryRun = FALSE,
  ): int {
    $storage = $this->entityTypeManager->getStorage('file');
    $count = 0;
    $lastId = 0;

    // Load the files in batches to keep the memory usage down.
    while ($ids = $this->getFileIds($lastId)) {
      $lastId = (int) end($ids);

      /** @var \Drupal\file\FileInterface $file */
      foreach ($storage->loadMultiple($ids) as $file) {
        // Environments using Stage File Proxy don't have all the files.
        if (!file_exists($file->getFileUri())) {
          continue;
        }
        $orientation = $dryRun
          ? $this->exifOrientation->getOrientation($file)
          : $this->fix($file);

        if ($orientation === NULL) {
          continue;
        }
        $count++;
        $io->writeln(sprintf('%s %d: %s (orientation %d)', $dryRun ? 'Would rotate' : 'Rotated', $file->id(), $file->getFileUri(), $orientation));
      }
      $storage->resetCache();
    }
    $io->success(sprintf('%s %d images.', $dryRun ? 'Would rotate' : 'Rotated', $count));

    return Command::SUCCESS;
  }

  /**
   * Gets the next batch of image file IDs.
   *
   * @param int $lastId
   *   The last ID of the previous batch.
   *
   * @return array<int|string, int|string>
   *   The file IDs.
   */
  private function getFileIds(int $lastId): array {
    return $this->entityTypeManager->getStorage('file')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('fid', $lastId, '>')
      ->condition('filemime', ExifOrientation::MIME_TYPES, 'IN')
      ->sort('fid')
      ->range(0, 50)
      ->execute();
  }

  /**
   * Rotates the image and updates the data depending on its dimensions.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   *
   * @return int|null
   *   The corrected EXIF orientation, or NULL if the image wasn't rotated.
   */
  private function fix(FileInterface $file): ?int {
    if (!$orientation = $this->exifOrientation->correct($file)) {
      return NULL;
    }
    $file->save();
    // The derivatives were generated from the image that wasn't rotated.
    $this->imageDerivativeUtilities->pathFlush($file->getFileUri());

    if ($this->exifOrientation->swapsDimensions($orientation)) {
      $image = $this->imageFactory->get($file->getFileUri());
      $width = (int) $image->getWidth();
      $height = (int) $image->getHeight();

      $this->updateImageFields($file, $width, $height);
      $this->updateCrops($file, $width, $height);
    }
    return $orientation;
  }

  /**
   * Updates the dimensions stored in the image fields.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   * @param int $width
   *   The new width.
   * @param int $height
   *   The new height.
   */
  private function updateImageFields(FileInterface $file, int $width, int $height): void {
    foreach ($this->entityFieldManager->getFieldMapByFieldType('image') as $entityTypeId => $fields) {
      $storage = $this->entityTypeManager->getStorage($entityTypeId);

      foreach (array_keys($fields) as $fieldName) {
        $ids = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition($fieldName . '.target_id', $file->id())
          ->execute();

        foreach ($storage->loadMultiple($ids) as $entity) {
          assert($entity instanceof ContentEntityInterface);
          $changed = FALSE;

          foreach ($entity->getTranslationLanguages() as $language) {
            /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
            foreach ($entity->getTranslation($language->getId())->get($fieldName) as $item) {
              if ((int) $item->target_id === (int) $file->id()) {
                $item->set('width', $width);
                $item->set('height', $height);
                $changed = TRUE;
              }
            }
          }
          if ($changed) {
            // Don't update the changed time and the other data, which is
            // only updated when the content is edited.
            $entity->setSyncing(TRUE);
            $entity->save();
          }
        }
      }
    }
  }

  /**
   * Scales the crops, like the focal points, to the new dimensions.
   *
   * The editors chose the focal point on a preview that was rotated by the
   * browser, but the position was calculated with the dimensions that weren't
   * rotated. So the position is only scaled to the new dimensions.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   * @param int $width
   *   The new width.
   * @param int $height
   *   The new height.
   */
  private function updateCrops(FileInterface $file, int $width, int $height): void {
    if (!$width || !$height) {
      return;
    }
    /** @var \Drupal\crop\CropInterface[] $crops */
    $crops = $this->entityTypeManager->getStorage('crop')
      ->loadByProperties(['uri' => $file->getFileUri()]);

    foreach ($crops as $crop) {
      // The old width was the new height and vice versa.
      $position = $crop->position();
      $crop->setPosition(
        (int) round($position['x'] * $width / $height),
        (int) round($position['y'] * $height / $width),
      );
      $crop->save();
    }
  }

}
