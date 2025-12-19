<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\Hook;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\helfi_react_search\Hook\ReactSearchHooks;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\UnitTestCase;

final class ReactSearchHooksTest extends UnitTestCase {

  /**
   * Test hook_preprocess_paragraph().
   */
  public function testPreprocessParagraph(): void {
    $elasticProxyConfig = $this->prophesize(ImmutableConfig::class);
    $elasticProxyConfig->get('elastic_proxy_url')->willReturn('anything');

    $reactSearchConfig = $this->prophesize(ImmutableConfig::class);
    $reactSearchConfig->get('sentry_dsn_react')->willReturn('anything');

    $reactHooksClass = new ReactSearchHooks(
      $this->getConfigFactoryStub([
        'elastic_proxy.settings' => ['elastic_proxy_url' => 'anything1'],
        'react_search.settings' => ['sentry_dsn_react' => 'anything2'],
      ])
    );

    $badParagraph = $this->prophesize(Paragraph::class);
    $badParagraph->getType()->willReturn('accordion');

    $variables['paragraph'] = $badParagraph->reveal();
    $reactHooksClass->preprocessParagraph($variables);

    $this->assertFalse(
      isset($variables['#attached']['drupalSettings']['helfi_react_search']['elastic_proxy_url']),
      'Elastic proxy url should not be set.'
    );
    $this->assertFalse(
      isset($variables['#attached']['drupalSettings']['helfi_react_search']['sentry_dsn_react']),
      'Sentry dsn react should not be set.'
    );

    $goodParagraph = $this->prophesize(Paragraph::class);
    $goodParagraph->getType()->willReturn('event_list');

    $variables['paragraph'] = $goodParagraph->reveal();
    $reactHooksClass->preprocessParagraph($variables);

    $this->assertEquals(
      'anything1',
      $variables['#attached']['drupalSettings']['helfi_react_search']['elastic_proxy_url'],
      'Elastic proxy url should be set.'
    );
    $this->assertEquals(
      'anything2',
      $variables['#attached']['drupalSettings']['helfi_react_search']['sentry_dsn_react'],
      'Sentry dsn react should be set.'
    );
  }

  /**
   * Test hook_theme().
   */
  public function testTheme(): void {
    $reactHooksClass = new ReactSearchHooks(
      $this->getConfigFactoryStub([])
    );

    $this->assertIsArray($reactHooksClass->theme());
  }

  /**
   * Test hook_preprocess_form_element().
   */
  public function testPreprocessFormElement(): void {
    $variables = [];

    $reactHooksClass = new ReactSearchHooks(
      $this->getConfigFactoryStub([])
    );

    $reactHooksClass->preprocessFormElement($variables);
    $this->assertFalse(isset($variables['description']['content']['#items']));

    $variables = [
      'name' => 'something_field_api_url',
      'description' => ['content' => ['#items' => [1,2,3]]],
    ];
    $reactHooksClass->preprocessFormElement($variables);
    $this->assertNotEmpty($variables['description']['content']);
  }



}
