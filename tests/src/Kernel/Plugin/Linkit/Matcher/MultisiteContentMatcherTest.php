<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\Linkit\Matcher;

use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\helfi_api_base\Environment\ActiveProjectRoles;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ProjectMetadata;
use Drupal\helfi_api_base\Environment\ProjectRoleEnum;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\helfi_platform_config\Plugin\ExternalEntities\StorageClient\SharedIndex;
use Drupal\helfi_platform_config\Plugin\Linkit\Matcher\MultisiteContentMatcher;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Drupal\Tests\helfi_platform_config\Traits\InstallConfigEntityTrait;
use Elastic\Elasticsearch\ClientBuilder;
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

  use ApiTestTrait;
  use ElasticTrait;
  use EnvironmentResolverTrait;
  use InstallConfigEntityTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity',
    'external_entities',
    'field',
    'language',
    'linkit',
    'serialization',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->container
      ->get(RouteProviderInterface::class)
      ->getAllRoutes();

    $this->installConfig(['system', 'external_entities']);
    $this->installEntitySchema('user');
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->setEtusivuIndexRole(TRUE);

    $history = [];
    $this->container->set(
      'helfi_platform_config.etusivu_elastic_client',
      ClientBuilder::create()
        ->setHttpClient($this->createMockHistoryMiddlewareHttpClient($history))
        ->build(),
    );

    $this->installConfigEntity('helfi_platform_config', 'external_entities.external_entity_type.helfi_multisite_content');
    $this->installEntitySchema(MultisiteContentId::ENTITY_TYPE_ID);
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
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
    $this->container->set(
      'helfi_platform_config.etusivu_elastic_client',
      ClientBuilder::create()
        ->setHttpClient($this->createMockHistoryMiddlewareHttpClient($history, [
          $this->createElasticsearchResponse([]),
        ]))
        ->build(),
    );
    $this->container->get('entity_type.manager')->clearCachedDefinitions();

    $suggestions = $matcher->execute($string);
    $this->assertSame([], $suggestions->getSuggestions());
    $this->assertArrayHasKey(0, $history);

    $request = $history[0]['request'] ?? NULL;
    $this->assertInstanceOf(RequestInterface::class, $request);
    return $this->requestBody($request);
  }

  /**
   * Stubs whether the active project has the etusivu index role.
   */
  private function setEtusivuIndexRole(bool $enabled) : void {
    $project = new Project(
      'example',
      new ProjectMetadata('https://example.com/example'),
      roles: $enabled ? [ProjectRoleEnum::HasEtusivuIndex] : [],
    );
    $resolver = $this->createMock(EnvironmentResolverInterface::class);
    $resolver->method('getActiveProject')->willReturn($project);
    $this->container->set(ActiveProjectRoles::class, new ActiveProjectRoles($resolver));
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
