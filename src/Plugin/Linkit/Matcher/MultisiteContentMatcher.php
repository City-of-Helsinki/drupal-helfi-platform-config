<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Linkit\Matcher;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\Plugin\ExternalEntities\StorageClient\SharedIndex;
use Drupal\linkit\Attribute\Matcher;
use Drupal\linkit\Plugin\Linkit\Matcher\EntityMatcher;

/**
 * Matches multisite content and can hide the current instance.
 */
#[Matcher(
  id: 'helfi_multisite_content',
  label: new TranslatableMarkup('Multisite content'),
  target_entity: 'helfi_multisite_content',
)]
final class MultisiteContentMatcher extends EntityMatcher {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      SharedIndex::EXCLUDE_CURRENT_INSTANCE => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form[SharedIndex::EXCLUDE_CURRENT_INSTANCE] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude content from the current instance and language'),
      '#description' => $this->t('Content from this instance in the current language is already suggested by the regular content matcher.'),
      '#default_value' => $this->configuration[SharedIndex::EXCLUDE_CURRENT_INSTANCE],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration[SharedIndex::EXCLUDE_CURRENT_INSTANCE] = (bool) $form_state->getValue(SharedIndex::EXCLUDE_CURRENT_INSTANCE);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($search_string) {
    $query = parent::buildEntityQuery($search_string);
    if (!empty($this->configuration[SharedIndex::EXCLUDE_CURRENT_INSTANCE])) {
      $query->condition(SharedIndex::EXCLUDE_CURRENT_INSTANCE, 1);
    }
    return $query;
  }

}
