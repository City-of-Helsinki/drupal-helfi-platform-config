<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;

/**
 * Search settings form.
 */
final class SearchSettingsForm extends ConfigFormBase {

  use AutowireTrait;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * Allows the route only when the active project is etusivu.
   */
  public function access(): AccessResultInterface {
    try {
      $project = $this->environmentResolver->getActiveProject()->getName();
    }
    catch (\InvalidArgumentException) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowedIf($project === Project::ETUSIVU);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'helfi_search_settings_form';
  }

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   Editable config names.
   */
  protected function getEditableConfigNames(): array {
    return ['helfi_search.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['ranking'] = [
      '#type' => 'details',
      '#title' => $this->t('Ranking'),
      '#open' => TRUE,
    ];

    $bundles = $this->config('helfi_search.settings')->get('deboost_bundles') ?? [];
    $form['ranking']['deboost_bundles'] = [
      '#type' => 'item',
      '#title' => $this->t('De-boosted bundles'),
      '#markup' => $bundles
        ? '<code>' . implode(', ', array_map('htmlspecialchars', $bundles)) . '</code>'
        : '<em>' . $this->t('(none)') . '</em>',
    ];

    $form['ranking']['deboost_factor'] = [
      '#type' => 'number',
      '#title' => $this->t('Deboost factor'),
      '#description' => $this->t('Score multiplier applied to de-boosted bundles. 1.0 disables the effect; 0.5 halves their KNN scores.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
      '#config_target' => 'helfi_search.settings:deboost_factor',
    ];

    $form['ranking']['min_score'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum similarity'),
      '#description' => $this->t('Raw cosine-similarity floor between query and document embeddings (0.0–1.0). Hits below this threshold are dropped. Higher values return fewer but more relevant results. Calculate similarity value from desired minimum score value with: similarity = desired_min_score * 2 - 1.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
      '#config_target' => 'helfi_search.settings:min_score',
    ];

    $form['external_links'] = [
      '#type' => 'details',
      '#title' => $this->t('External links'),
      '#open' => TRUE,
    ];

    $external_link_labels = [
      'jobs' => $this->t('Open jobs URL'),
      'events' => $this->t('Events URL'),
      'decisions' => $this->t('Decisions URL'),
      'contact' => $this->t('Contact URL'),
      'helsinki_near_you' => $this->t('Helsinki near you URL'),
    ];

    foreach ($external_link_labels as $key => $label) {
      $form['external_links'][$key] = [
        '#type' => 'url',
        '#title' => $label,
        '#config_target' => "helfi_search.settings:external_links.$key",
      ];
    }

    $form['ai_register_url'] = [
      '#type' => 'url',
      '#title' => $this->t('AI register URL'),
      '#config_target' => 'helfi_search.settings:ai_register_url',
    ];

    $form['pipeline'] = [
      '#type' => 'details',
      '#title' => $this->t('Indexing pipeline'),
      '#open' => FALSE,
    ];

    $form['pipeline']['ignored_classes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Ignored CSS classes'),
      '#description' => $this->t('Elements whose <code>class</code> attribute contains any of these classes are stripped from indexed HTML. One class per line. Exact match (no partials, no leading dot).<br><strong>Note:</strong> this value is not in <code>config_ignore</code>. To keep the shared Elasticsearch index consistent, the same list should be applied on every site.'),
      '#rows' => 12,
      '#config_target' => new ConfigTarget(
        'helfi_search.settings',
        'ignored_classes',
        static function (?array $value): string {
          return implode("\n", $value ?? []);
        },
        static function (string $value): array {
          $lines = preg_split('/\R/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
          return array_values(array_filter(
            array_map('trim', $lines),
            static fn (string $line): bool => $line !== '',
          ));
        },
      ),
    ];

    return $form;
  }

}
