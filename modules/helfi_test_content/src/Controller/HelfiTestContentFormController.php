<?php

declare(strict_types=1);

namespace Drupal\helfi_test_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\helfi_test_content\Form\HelfiTestContentForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Helfi test content routes.
 */
final class HelfiTestContentFormController extends ControllerBase {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The config factory.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('form_builder'),
    );
  }

  /**
   * Builds the response.
   *
   * @return array
   *   Returns test form.
   */
  public function formPage(): array {
    return [
      '#type' => 'container',
      '#prefix' => '<article><div class="components components--test-content"><div class="component">',
      '#suffix' => '</div></div></article>',
      '#attached' => [
        'library' => [
          'helfi_test_content/test_focus',
        ],
      ],
      'form' => $this->formBuilder->getForm(HelfiTestContentForm::class),
    ];
  }

}
