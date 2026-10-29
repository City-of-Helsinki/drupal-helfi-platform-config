<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\Linkit\Matcher;

use Drupal\Core\Form\FormState;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\Plugin\ExternalEntities\StorageClient\SharedIndex;
use Drupal\helfi_platform_config\Plugin\Linkit\Matcher\MultisiteContentMatcher;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticKernelTrait;
use Drupal\Tests\helfi_platform_config\Traits\InstallConfigEntityTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Http\Message\RequestInterface;

/**
 * Tests the multisite content Linkit matcher.
 */
#[CoversClass(MultisiteContentMatcher::class)]
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class MultisiteContentMatcherTest extends KernelTestBase {

  use ElasticKernelTrait;
  use EnvironmentResolverTrait;
  use InstallConfigEntityTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'linkit',
    'serialization',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->installConfigEntity('helfi_platform_config', 'external_entities.external_entity_type.helfi_multisite_content');
  }

  /**
   * A text search hides content from the current instance and language.
   */
  public function testDefaultSearchExcludesCurrentInstance() : void {
    $matcher = $this->matcher();
    $this->assertTrue($matcher->getConfiguration()['settings'][SharedIndex::EXCLUDE_CURRENT_INSTANCE]);

    $body = $this->search('library', $matcher);

    $this->assertSame('library', $body['query']['bool']['must'][0]['multi_match']['query']);
    $this->assertSame(100, $body['size']);
    $this->assertSame([
      'bool' => [
        'must' => [
          ['term' => ['instance' => 'etusivu']],
          ['term' => ['search_api_language' => 'en']],
        ],
      ],
    ], $body['query']['bool']['must_not'][0]);
  }

  /**
   * Turning the matcher setting off keeps current-instance suggestions.
   */
  public function testSearchKeepsCurrentInstanceWhenExclusionIsDisabled() : void {
    $matcher = $this->matcher();
    $form_state = new FormState();
    $form = $matcher->buildConfigurationForm([], $form_state);

    $this->assertSame('checkbox', $form[SharedIndex::EXCLUDE_CURRENT_INSTANCE]['#type']);
    $this->assertTrue($form[SharedIndex::EXCLUDE_CURRENT_INSTANCE]['#default_value']);

    $form_state->setValue(SharedIndex::EXCLUDE_CURRENT_INSTANCE, 0);
    $matcher->submitConfigurationForm($form, $form_state);
    $this->assertFalse($matcher->getConfiguration()['settings'][SharedIndex::EXCLUDE_CURRENT_INSTANCE]);

    $body = $this->search('library', $matcher);
    $this->assertArrayNotHasKey('must_not', $body['query']['bool']);
  }

  /**
   * Creates the matcher under test.
   */
  private function matcher() : MultisiteContentMatcher {
    $matcher = $this->container->get('plugin.manager.linkit.matcher')
      ->createInstance('helfi_multisite_content');
    $this->assertInstanceOf(MultisiteContentMatcher::class, $matcher);
    return $matcher;
  }

  /**
   * Runs one autocomplete search and returns the Elasticsearch body.
   *
   * @return array<string, mixed>
   *   Decoded request body.
   */
  private function search(string $string, MultisiteContentMatcher $matcher) : array {
    $history = [];
    $this->mockEtusivuElasticClient([
      $this->createElasticsearchResponse([]),
    ], $history);

    $suggestions = $matcher->execute($string);
    $this->assertSame([], $suggestions->getSuggestions());
    $this->assertArrayHasKey(0, $history);

    $request = $history[0]['request'] ?? NULL;
    $this->assertInstanceOf(RequestInterface::class, $request);
    return $this->requestBody($request);
  }

  /**
   * Decodes an Elasticsearch request body.
   *
   * @return array<string, mixed>
   *   Decoded JSON body.
   */
  private function requestBody(RequestInterface $request) : array {
    $body = json_decode((string) $request->getBody(), TRUE);
    $this->assertIsArray($body);
    return $body;
  }

}
