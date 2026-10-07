<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_image_styles\Kernel;

use Drupal\crop\Entity\Crop;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\helfi_image_styles\Drush\Commands\ExifOrientationCommand;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tests rotating the images according to their EXIF orientation.
 */
#[RunTestsInSeparateProcesses]
#[Group('helfi_image_styles')]
final class ExifOrientationTest extends KernelTestBase {

  /**
   * A 400x600 image stored sideways with the EXIF orientation 6 and quality 95.
   */
  private const string FIXTURE = __DIR__ . '/../../fixtures/exif-orientation-6.jpg';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'entity_test',
    'responsive_image',
    'breakpoint',
    'sophron',
    'file_mdm',
    'imagemagick',
    'webp',
    'focal_point',
    'crop',
    'image_style_quality',
    'helfi_image_styles',
  ];

  /**
   * The public files directory.
   */
  private string $directory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('crop');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'sophron', 'file_mdm', 'imagemagick', 'crop', 'focal_point']);
    $this->config('system.image')->set('toolkit', 'imagemagick')->save();

    // The ImageMagick binaries can't read the virtual file system.
    $this->directory = sys_get_temp_dir() . '/' . $this->databasePrefix;
    mkdir($this->directory);
    $this->setSetting('file_public_path', $this->directory);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::service('file_system')->deleteRecursive($this->directory);
    parent::tearDown();
  }

  /**
   * Creates a file entity from the fixture.
   *
   * @return \Drupal\file\Entity\File
   *   The saved file.
   */
  private function createFile(): File {
    $uri = 'public://image.jpg';
    copy(self::FIXTURE, $uri);

    $file = File::create(['uri' => $uri]);
    $file->save();

    return $file;
  }

  /**
   * Creates a file that existed before the images were rotated.
   *
   * @return \Drupal\file\Entity\File
   *   The saved file.
   */
  private function createExistingFile(): File {
    // The images aren't rotated with the other toolkits.
    $imageFactory = \Drupal::service('image.factory');
    $imageFactory->setToolkitId('gd');
    $file = $this->createFile();
    $imageFactory->setToolkitId('imagemagick');

    return $file;
  }

  /**
   * Gets the image information.
   *
   * @param string $uri
   *   The image URI or path.
   *
   * @return array{int, int, int|null}
   *   The width, height and EXIF orientation.
   */
  private function getImageInfo(string $uri): array {
    // Read the file again instead of using the cached metadata.
    \Drupal::service('file_metadata_manager')->release($uri);
    $image = \Drupal::service('image.factory')->get($uri, 'imagemagick');
    /** @var \Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit $toolkit */
    $toolkit = $image->getToolkit();

    return [$image->getWidth(), $image->getHeight(), $toolkit->getExifOrientation()];
  }

  /**
   * Runs the command.
   *
   * @param bool $dryRun
   *   Whether to only list the images.
   *
   * @return string
   *   The output.
   */
  private function runCommand(bool $dryRun): string {
    $output = new BufferedOutput();
    $command = ExifOrientationCommand::create($this->container);
    $this->assertSame(0, $command(new SymfonyStyle(new ArrayInput([]), $output), $dryRun));

    return $output->fetch();
  }

  /**
   * Tests that the image is rotated when it's added.
   */
  public function testRotate(): void {
    $this->assertSame([400, 600, 6], $this->getImageInfo(self::FIXTURE), 'The fixture is stored sideways.');

    $file = $this->createFile();
    $this->assertSame([600, 400, 1], $this->getImageInfo($file->getFileUri()));
    clearstatcache();
    $this->assertSame(filesize($file->getFileUri()), (int) $file->getSize());
    // The original quality is kept instead of the configured one.
    $this->assertSame(75, $this->config('imagemagick.settings')->get('quality'));
    $path = (string) \Drupal::service('file_system')->realpath($file->getFileUri());
    $this->assertSame('95', exec('magick identify -format %Q ' . escapeshellarg($path)));
  }

  /**
   * Tests that the existing files and the other toolkits are left alone.
   */
  public function testSkipped(): void {
    $file = $this->createExistingFile();
    $this->assertSame([400, 600, 6], $this->getImageInfo($file->getFileUri()));

    $file->save();
    $this->assertSame([400, 600, 6], $this->getImageInfo($file->getFileUri()));
  }

  /**
   * Tests fixing the existing images.
   */
  public function testCommand(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'entity_test',
      'type' => 'image',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    $file = $this->createExistingFile();
    $entity = EntityTest::create(['field_image' => ['target_id' => $file->id()]]);
    $entity->save();
    $this->assertSame([400, 600], [(int) $entity->get('field_image')->width, (int) $entity->get('field_image')->height]);
    // The editor chose the point at 25% from the left and 75% from the top on
    // the preview that the browser rotated to 600x400.
    $crop = Crop::findCrop($file->getFileUri(), 'focal_point');
    $this->assertInstanceOf(Crop::class, $crop);
    $crop->setPosition(100, 450)->save();

    $output = $this->runCommand(TRUE);
    $this->assertStringContainsString('Would rotate ' . $file->id() . ': public://image.jpg (orientation 6)', $output);
    $this->assertStringContainsString('Would rotate 1 images.', $output);
    $this->assertSame([400, 600, 6], $this->getImageInfo($file->getFileUri()));

    $output = $this->runCommand(FALSE);
    $this->assertStringContainsString('Rotated 1 images.', $output);
    $this->assertSame([600, 400, 1], $this->getImageInfo($file->getFileUri()));

    $entity = EntityTest::load($entity->id());
    $this->assertSame([600, 400], [(int) $entity->get('field_image')->width, (int) $entity->get('field_image')->height]);
    $this->assertSame(['x' => 150, 'y' => 300], Crop::load($crop->id())?->position());
    $file = File::load($file->id());
    clearstatcache();
    $this->assertSame(filesize($file->getFileUri()), (int) $file->getSize());

    // The fixed images are skipped.
    $this->assertStringContainsString('Rotated 0 images.', $this->runCommand(FALSE));
  }

}
