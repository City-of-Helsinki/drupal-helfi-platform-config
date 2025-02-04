<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Form\FormStateInterface;
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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->requestStack = $container->get('request_stack');
    return $instance;
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
