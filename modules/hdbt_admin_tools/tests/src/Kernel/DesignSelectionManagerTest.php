<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_admin_tools\Kernel\Controller;

use Drupal\hdbt_admin_tools\DesignSelectionManager;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests Design selection manager.
 *
 * @group hdbt_admin_tools
 */
class DesignSelectionManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hdbt_admin_tools',
  ];

  /**
   * Tests getImages().
   */
  #[DataProvider('getImagesData')]
  public function testGetImages(string $fieldName, array $selections, array $expected): void {
    /** @var \Drupal\hdbt_admin_tools\DesignSelectionManager $service */
    $service = $this->container->get(DesignSelectionManager::class);

    $this->assertEquals($expected, $service->getImages($fieldName, $selections));
  }

  /**
   * Data provider for testGetImages().
   *
   * @return array[]
   *   The data.
   */
  public static function getImagesData() : array {
    $basePath = '/modules/contrib/helfi_platform_config/modules/hdbt_admin_tools/assets/images';
    return [
      [
        'accordion-design',
        ['grey', 'white'],
        [
          'grey' => "$basePath/accordion-design--grey.svg",
          'white' => "$basePath/accordion-design--white.svg",
        ],
      ],
      [
        'nonexistent-field',
        ['grey', 'white'],
        [
          'grey' => "$basePath/custom-style.svg",
          'white' => "$basePath/custom-style.svg",
        ],
      ],
    ];
  }

}
