<?php

declare(strict_types = 1);

namespace Drupal\hdbt_admin_editorial\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\linkit\Plugin\Field\FieldWidget\LinkitWidget;

/**
 * Plugin implementation of the 'link_target_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "link_target_field_widget",
 *   label = @Translation("Link with target"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class LinkTargetFieldWidget extends LinkitWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'link_target' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $item = $this->getLinkItem($items, $delta);
    $options = $item->get('options')->getValue();

    $element['options']['target_new'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open in new window/tab'),
      '#return_value' => TRUE,
      '#default_value' => $options['target_new'] ?? FALSE,
      '#weight' => 99,
    ];

    $element_path = $this->getElementStatePath($element, $delta);

    $element['options']['target_check'] = [
      '#title' => t('The link meets the accessibility requirements'),
      '#description' => t('I have made sure that the description of this link clearly states that it will open in a new tab. <a href="@wcag-techniques" target="_blank">See WCAG 3.2.5 accessibility requirement (the link opens in a new tab).</a>', [
        '@wcag-techniques' => 'https://www.w3.org/WAI/WCAG21/Techniques/general/G200.html',
      ]),
      '#type' => 'checkbox',
      '#default_value' => $options['target_new'] ?? FALSE,
      '#weight' => 99,
      '#states' => [
        'visible' => [
          ':input[name="' . $element_path . '[options][target_new]"]' => [
            'checked' => TRUE,
          ],
        ],
        'required' => [
          ':input[name="' . $element_path . '[options][target_new]"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    return $element;
  }

  /**
   * Get link items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   * @param string $delta
   *   Field delta with item.
   *
   * @return \Drupal\link\LinkItemInterface
   *   Returns an array of link items.
   */
  protected function getLinkItem(FieldItemListInterface $items, $delta) {
    return $items[$delta];
  }

  /**
   * Get element path as string for form element states.
   *
   * @param array $element
   *   Form element.
   * @param int $delta
   *   Form element delta.
   *
   * @return string
   *   Returns element path as a string.
   */
  protected function getElementStatePath(array $element, int $delta): string {
    $parents = $element['#field_parents'];
    $parents[] = $this->fieldDefinition->getName();
    $selector = $root = array_shift($parents);
    if ($parents) {
      $selector = $root . '[' . implode('][', $parents) . ']';
    }
    return $selector . '[' . $delta . ']';
  }

}
