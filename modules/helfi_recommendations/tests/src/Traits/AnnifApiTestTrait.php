<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Traits;

use Drupal\helfi_platform_config\TextConverter\TextConverterInterface;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides shared functionality for tests.
 */
trait AnnifApiTestTrait {

  use ProphecyTrait;
  use ApiTestTrait {
    getFixture as private apiGetFixture;
  }

  /**
   * Gets the fixture data.
   *
   * @param string $name
   *   The fixture name.
   *
   * @return string
   *   The fixture.
   */
  protected function getFixture(string $name): string {
    $file = sprintf("%s/../../fixtures/%s", __DIR__, $name);

    if (!file_exists($file)) {
      throw new \InvalidArgumentException(sprintf('Fixture %s not found', $name));
    }

    return file_get_contents($file);
  }

  /**
   * Gets mock response for batch api.
   *
   * @param string $fixture
   *   The fixture name.
   * @param array $entities
   *   Entities that will be passed to batch callback.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Mocked response.
   */
  protected function getMockResponse(string $fixture, array $entities): ResponseInterface {
    $fixture = json_decode($this->getFixture($fixture), TRUE, flags: JSON_THROW_ON_ERROR);

    $body = array_map(fn ($key) => array_merge([
      'document_id' => $key,
    ], $fixture), array_keys($entities));

    return new Response(body: json_encode($body));
  }

  /**
   * Gets text converter manager.
   */
  private function getTextConverterManager(?TextConverterInterface $textConverter = NULL): TextConverterManager {
    if (!$textConverter) {
      $textConverter = $this->prophesize(TextConverterInterface::class);
      $textConverter
        ->applies(Argument::any())
        ->willReturn(TRUE);

      $textConverter
        ->convert(Argument::any())
        ->willReturn('Test content');

      $textConverter = $textConverter->reveal();
    }

    $textConverterManager = new TextConverterManager();
    $textConverterManager->add($textConverter);

    return $textConverterManager;
  }

}
