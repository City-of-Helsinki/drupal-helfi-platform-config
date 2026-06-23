<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Form;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_search\Form\SearchSettingsForm;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests the search settings form.
 */
#[RunTestsInSeparateProcesses]
#[Group('helfi_search')]
class SearchSettingsFormTest extends KernelTestBase {

  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'helfi_search',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['helfi_search']);
  }

  /**
   * Tests that submitting the form saves scalar and list values to config.
   */
  public function testSubmitSavesConfig(): void {
    $form_state = $this->submit([
      'deboost_factor' => 0.5,
      'min_score' => 0.4,
      'ai_register_url' => 'https://example.com/ai',
      'jobs' => 'https://example.com/jobs',
      'canonical_terms' => "OmaStadi\nMyHelsinki",
      'ignored_classes' => "is-hidden\nannouncement",
    ]);

    $this->assertEmpty($form_state->getErrors());

    $config = $this->config('helfi_search.settings');
    $this->assertEquals(0.5, $config->get('deboost_factor'));
    $this->assertEquals(0.4, $config->get('min_score'));
    $this->assertEquals('https://example.com/ai', $config->get('ai_register_url'));
    $this->assertEquals('https://example.com/jobs', $config->get('external_links.jobs'));
    // Textareas are stored as lists, one item per line.
    $this->assertSame(['OmaStadi', 'MyHelsinki'], $config->get('canonical_terms'));
    $this->assertSame(['is-hidden', 'announcement'], $config->get('ignored_classes'));
  }

  /**
   * Tests that textarea lines are trimmed and blank lines are dropped.
   */
  public function testTextareaLinesAreNormalized(): void {
    // Tests that an emptied textarea stores an empty list.
    $form_state = $this->submit([
      'canonical_terms' => '',
      'ignored_classes' => "   \n  ",
    ]);

    $this->assertEmpty($form_state->getErrors());

    $config = $this->config('helfi_search.settings');
    $this->assertSame([], $config->get('canonical_terms'));
    $this->assertSame([], $config->get('ignored_classes'));

    $form_state = $this->submit([
      // Leading/trailing whitespace, blank lines and a whitespace-only line.
      'canonical_terms' => "  OmaStadi  \n\n   \n\tMyHelsinki\n",
      'ignored_classes' => "\n is-hidden \n\n",
    ]);

    $this->assertEmpty($form_state->getErrors());

    $config = $this->config('helfi_search.settings');
    $this->assertSame(['OmaStadi', 'MyHelsinki'], $config->get('canonical_terms'));
    $this->assertSame(['is-hidden'], $config->get('ignored_classes'));

    $form_state = new FormState();
    $form = $this->container->get(FormBuilderInterface::class)
      ->getForm(SearchSettingsForm::class, $form_state);

    // Tests that stored lists are rendered back as newline-separated textareas.
    $this->assertSame(
      "OmaStadi\nMyHelsinki",
      $form['query_preprocessing']['canonical_terms']['#default_value'],
    );
  }

  /**
   * Tests that the route is only accessible on the etusivu project.
   */
  #[TestWith([Project::ETUSIVU, TRUE])]
  #[TestWith([Project::ASUMINEN, FALSE])]
  public function testAccess(string $project, bool $allowed): void {
    $this->setActiveProject($project, EnvironmentEnum::Local);
    $this->assertSame($allowed, $this->form()->access()->isAllowed());
  }

  /**
   * Returns a fresh form instance from the container.
   */
  private function form(): SearchSettingsForm {
    return $this->container->get(ClassResolverInterface::class)
      ->getInstanceFromDefinition(SearchSettingsForm::class);
  }

  /**
   * Submits the form with the given values.
   *
   * @phpstan-param array<string, mixed> $values
   */
  private function submit(array $values): FormState {
    $form_state = new FormState();
    $form_state->setValues($values);
    $this->container->get(FormBuilderInterface::class)
      ->submitForm(SearchSettingsForm::class, $form_state);

    return $form_state;
  }

}
