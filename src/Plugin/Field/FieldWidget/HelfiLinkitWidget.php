<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\linkit\Plugin\Field\FieldWidget\LinkitWidget;
use Drupal\linkit\Utility\LinkitHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Overrides the linkit widget.
 */
#[FieldWidget(
  id: "helfi_linkit",
  label: new TranslatableMarkup('Helfi: Linkit'),
  field_types: ['link']
)]

final class HelfiLinkitWidget extends LinkitWidget {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * Constructs a new instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   The widget third party settings.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The current request stack.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    AccountProxyInterface $currentUser,
    EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $currentUser, $entityTypeManager);
  }

  /**
   * Circumvent Linkit to allow linking to internal pages using absolute URLs.
   *
   * \Drupal\Linkit\Plugin\Field\FieldWidget\LinkitWidget always runs links
   * through uriFromUserInput, which coerces absolute URLs to node links if
   * they belong to the current site.
   *
   * @param string $input
   *   The user input.
   *
   * @return string
   *   The URI.
   */
  protected function convertToUri(string $input) {
    if (
      UrlHelper::isExternal($input) &&
      UrlHelper::externalIsLocal($input, $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost())
    ) {
      return $input;
    }

    return LinkitHelper::uriFromUserInput($input);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      $value['uri'] = $this->convertToUri($value['uri']);
      $value += ['options' => $value['attributes']];
    }
    return $values;
  }

}
