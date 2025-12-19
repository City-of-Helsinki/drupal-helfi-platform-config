<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\helfi_react_search\Hook\ReactSearchHooks;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\UnitTestCase;

final class ReactSearchHooksTest extends UnitTestCase {

  public function testPreprocessParagraph(): void {

    // $configFactory = $this->createMock(ConfigFactoryInterface::class);

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

}
