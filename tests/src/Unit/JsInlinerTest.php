<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Core\Asset\JsOptimizer;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\helfi_platform_config\JsInliner;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Argument;

/**
 * Tests the JsInliner service.
 */
class JsInlinerTest extends UnitTestCase {

  use ProphecyTrait;

  const TEST_LIBRARY = [
    'version' => '1.0.0',
    'js' => [
      [
        'type' => 'file',
        'data' => 'test-library.js',
      ],
    ],
  ];

  /**
   * The mocked JsOptimizer service.
   */
  protected ObjectProphecy $jsOptimizer;

  /**
   * The mocked library discovery service.
   */
  protected ObjectProphecy $libraryDiscovery;

  /**
   * The mocked cache backend.
   */
  protected ObjectProphecy $cache;

  /**
   * The JsInliner service.
   */
  protected JsInliner $inliner;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->jsOptimizer = $this->prophesize(JsOptimizer::class);

    $this->libraryDiscovery = $this->prophesize(LibraryDiscoveryInterface::class);
    $this->libraryDiscovery->getLibraryByName('test', 'test-library')->willReturn(self::TEST_LIBRARY);

    $this->cache = $this->prophesize(CacheBackendInterface::class);
    // First call to cache get returns FALSE, second call returns cached data.
    $this->cache->get('helfi_platform_config:inline_js:test.test-library:1.0.0')->willReturn(FALSE, (object) ['data' => 'test-data-from-cache']);

    $this->inliner = new JsInliner(
      $this->jsOptimizer->reveal(),
      $this->libraryDiscovery->reveal(),
      $this->cache->reveal()
    );
  }

  /**
   * Tests the getInline method.
   */
  public function testGetInline(): void {
    // Should call js optimizer only once with preprocess TRUE.
    $this->jsOptimizer->optimize(Argument::that(fn (array $args) => $args['preprocess'] === TRUE))->shouldBeCalledTimes(1)->willReturn('test-data');
    // Should not call js optimizer with preprocess FALSE or not set.
    $this->jsOptimizer->optimize(Argument::that(fn (array $args) => $args['preprocess'] === FALSE || !isset($args['preprocess'])))->shouldNotBeCalled();
    // Should call cache get twice as we reset the storage variable once.
    $this->cache->get('helfi_platform_config:inline_js:test.test-library:1.0.0')->shouldBeCalledTimes(2);
    // Should call cache set only once.
    $this->cache->set('helfi_platform_config:inline_js:test.test-library:1.0.0', 'test-data')->shouldBeCalledTimes(1);

    // First call:
    // - Internal storage is empty; should call cache get.
    // - Cache returns FALSE; should call js optimizer and cache set.
    $data1 = $this->inliner->getInline('test', 'test-library');

    // Second call:
    // - We manually clear the internal storage; should call cache get.
    // - Cache returns cached data; should return cached data and not call js optimizer or cache set.
    $this->inliner->reset();
    $data2 = $this->inliner->getInline('test', 'test-library');

    // Third call:
    // - Data returned from internal storage; should not call cache get, js optimizer or cache set.
    $data3 = $this->inliner->getInline('test', 'test-library');

    $this->assertEquals('test-data', $data1);
    $this->assertEquals('test-data-from-cache', $data2);
    $this->assertEquals('test-data-from-cache', $data3);
  }

  /**
   * Tests the getInline method with a non-existent library.
   */
  public function testGetInlineWithNonExistentLibrary(): void {
    $this->libraryDiscovery->getLibraryByName('test', 'non-existent-library')->willReturn(FALSE);

    $this->assertNull($this->inliner->getInline('test', 'non-existent-library'));
  }

  /**
   * Tests the getInline method with an exception thrown by the js optimizer.
   */
  public function testGetInlineWithExceptionThrownByJsOptimizer(): void {
    $this->jsOptimizer->optimize(Argument::any())->willThrow(new \Exception('Test exception'));
    $this->cache->get(Argument::any())->willReturn(FALSE);
    $this->cache->set(Argument::any(), Argument::is(''))->shouldBeCalled();

    $this->assertNull($this->inliner->getInline('test', 'test-library'));
  }

}
