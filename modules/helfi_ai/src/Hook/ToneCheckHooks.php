<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Utility\Error;
use Drupal\helfi_ai\ToneCheck\ToneCheckPrecheck;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for the tone check pre-check.
 */
class ToneCheckHooks {

  use AutowireTrait;

  public function __construct(
    private readonly ToneCheckPrecheck $precheck,
    #[Autowire(service: 'logger.channel.helfi_ai')] private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node forms.
   *
   * @param array<string, mixed> $form
   *   The node form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    $submit = $form['actions']['submit']['#submit'] ?? NULL;

    if (!$formObject instanceof EntityFormInterface || !is_array($submit)) {
      return;
    }
    $node = $formObject->getEntity();
    $position = array_search('::save', $submit, TRUE);

    if ($node instanceof NodeInterface && $position !== FALSE && $this->precheck->applies($node)) {
      array_splice($form['actions']['submit']['#submit'], (int) $position, 0, [[self::class, 'registerEditorSave']]);
    }
  }

  /**
   * Submit handler that registers the save as an editor save.
   *
   * @param array<string, mixed> $form
   *   The node form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public static function registerEditorSave(array $form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    $node = $formObject instanceof EntityFormInterface ? $formObject->getEntity() : NULL;

    if ($node instanceof NodeInterface) {
      \Drupal::service(ToneCheckPrecheck::class)->registerEditorSave($node);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for nodes.
   */
  #[Hook('node_insert')]
  public function nodeInsert(NodeInterface $node): void {
    $this->evaluate($node);
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for nodes.
   */
  #[Hook('node_update')]
  public function nodeUpdate(NodeInterface $node): void {
    $this->evaluate($node);
  }

  /**
   * Evaluates the pre-check without ever breaking the save.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The saved node.
   */
  private function evaluate(NodeInterface $node): void {
    try {
      $this->precheck->evaluate($node);
    }
    catch (\Throwable $e) {
      Error::logException($this->logger, $e);
    }
  }

}
