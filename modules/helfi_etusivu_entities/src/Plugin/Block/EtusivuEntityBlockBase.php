<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\helfi_api_base\Language\DefaultLanguageResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for etusivu remote entities blocks.
 */
abstract class EtusivuEntityBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new AnnouncementsBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\helfi_api_base\Language\DefaultLanguageResolver $defaultLanguageResolver
   *   Default language resolver.
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly LoggerInterface $logger,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly DefaultLanguageResolver $defaultLanguageResolver,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('logger.channel.helfi_etusivu_entities'),
      $container->get(RouteMatchInterface::class),
      $container->get(EntityTypeManagerInterface::class),
      $container->get(EntityFieldManagerInterface::class),
      $container->get(LanguageManagerInterface::class),
      $container->get(DefaultLanguageResolver::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'use_remote_entities' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['use_remote_entities'] = [
      '#type' => 'boolean',
      '#title' => $this->t('Fetch remote entities'),
      '#description' => $this->t('This options should be disabled for non-core sites that do not want to pull remote content.'),
      '#default_value' => $this->configuration['use_remote_entities'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['use_remote_entities'] = $form_state->getValues()['use_remote_entities'] ?: FALSE;
  }

  /**
   * {@inheritDoc}
   */
  abstract public function build(): array;

}
