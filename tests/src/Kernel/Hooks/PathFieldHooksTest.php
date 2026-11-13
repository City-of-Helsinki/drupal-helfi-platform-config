<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Hooks;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the path field alterations.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class PathFieldHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'language',
    'path',
    'path_alias',
    'link',
    'views',
    'redirect',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('redirect');
    $this->installConfig(['language', 'redirect']);

    foreach (['fi', 'sv'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
    // Language negotiation services are only registered for multilingual
    // sites, so rebuild the container after adding the languages.
    $this->container->get('kernel')->rebuildContainer();
  }

  /**
   * Tests the path alias and redirect source field description and prefix.
   *
   * @param array<string, string> $sitePrefixes
   *   The helfi_proxy site prefixes.
   * @param string $langcode
   *   The language of the current request.
   * @param string $expectedPrefix
   *   The expected instance path after the base URL.
   */
  #[DataProvider('pathFieldData')]
  public function testPathField(array $sitePrefixes, string $langcode, string $expectedPrefix): void {
    $this->container->get('config.storage')->write('helfi_proxy.settings', [
      'prefixes' => $sitePrefixes,
    ]);
    $this->container->get('config.factory')->reset('helfi_proxy.settings');

    $description = 'Add the final part of the URL, meaning everything that comes after the instance.';

    $form = $this->buildForm('path_alias', $langcode);
    $baseUrl = $this->container->get('router.request_context')->getCompleteBaseUrl();

    $path = $form['path']['widget'][0]['value'];
    $this->assertEquals($description, (string) $path['#description']);
    $this->assertEquals(rtrim($baseUrl, '/') . $expectedPrefix, $path['#field_prefix']);

    // The alias field is left as the path module sets it.
    $alias = $form['alias']['widget'][0]['value'];
    $this->assertEquals($baseUrl, $alias['#field_prefix']);
    $this->assertStringStartsWith('Specify an alternative path', (string) $alias['#description']);

    // The redirect source keeps the trailing slash set by the redirect module.
    $form = $this->buildForm('redirect', $langcode);
    $source = $form['redirect_source']['widget'][0]['path'];
    $this->assertEquals($description, (string) $source['#description']);
    $this->assertEquals('http://localhost' . $expectedPrefix . '/', $source['#field_prefix']);
  }

  /**
   * Data provider for testPathField().
   *
   * @return array<string, array{array<string, string>, string, string}>
   *   The test data.
   */
  public static function pathFieldData(): array {
    $sote = [
      'fi' => 'sosiaali-ja-terveyspalvelut',
      'sv' => 'social-och-halsovardstjanster',
      'en' => 'health-and-social-services',
    ];

    return [
      'no site prefix' => [['zxx' => 'etusivu'], 'fi', '/fi'],
      'site prefix, finnish' => [$sote, 'fi', '/fi/sosiaali-ja-terveyspalvelut'],
      'site prefix, swedish' => [$sote, 'sv', '/sv/social-och-halsovardstjanster'],
    ];
  }

  /**
   * Builds an entity add form in the given language.
   *
   * @param string $entityTypeId
   *   The entity type.
   * @param string $langcode
   *   The language prefix of the request.
   *
   * @return array<string, mixed>
   *   The form.
   */
  private function buildForm(string $entityTypeId, string $langcode): array {
    $request = Request::create("/$langcode/admin");
    $request->setSession($this->container->get('session'));
    $this->container->get('request_stack')->push($request);
    $this->container->get('language_manager')->reset();

    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entityTypeId)
      ->create();

    return $this->container->get('entity.form_builder')->getForm($entity);
  }

}
