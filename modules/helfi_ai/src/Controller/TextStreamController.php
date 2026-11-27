<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_ai\Service\TextServicesGenerator;
use Drupal\helfi_ai\ToneCheck\ToneCheckPrecheck;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams the result of an AI feature for the posted text.
 */
final class TextStreamController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly TextServicesGenerator $generator,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ToneCheckPrecheck $precheck,
  ) {}

  /**
   * Streams the feature's result as server-sent events.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request, with a JSON body of {text, langcode} and, for the tone
   *   check, optionally {entityType, entityId}.
   * @param 'tone'|'summary' $feature
   *   The feature, from the route defaults.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The event stream, or {error: string} with a 4xx code.
   */
  public function stream(Request $request, string $feature): Response {
    if ($feature === 'tone' && !$this->configFactory->get('helfi_ai.settings')->get('enable_tone_check')) {
      return new JsonResponse(['error' => 'The feature is disabled.'], 403);
    }
    $data = json_decode($request->getContent(), TRUE);
    $text = $data['text'] ?? NULL;
    $langcode = $data['langcode'] ?? NULL;

    if (!is_string($text) || !is_string($langcode)) {
      throw new BadRequestException('Missing or invalid "text" or "langcode" parameter.');
    }

    if (trim($text) === '') {
      return new JsonResponse(['error' => 'No text to process.'], 400);
    }
    if (mb_strlen($text) > TextServicesGenerator::MAX_CONTENT_LENGTH) {
      return new JsonResponse(['error' => 'The text is too long.'], 413);
    }

    if ($feature === 'tone') {
      $onComplete = $this->getToneCheckCompletion($data, $langcode);
      return new EventStreamResponse(fn () => $this->generator->streamTone($text, $langcode, $onComplete));
    }
    return new EventStreamResponse(fn () => $this->generator->streamSummary($text, $langcode));
  }

  /**
   * Gets the callback that marks the tone check of the posted page done.
   *
   * @param array<string, mixed> $data
   *   The request data.
   * @param string $langcode
   *   The language of the checked text.
   *
   * @return \Closure|null
   *   The callback, or NULL if the request has no valid page the user can edit.
   */
  private function getToneCheckCompletion(array $data, string $langcode): ?\Closure {
    $entityId = $data['entityId'] ?? NULL;

    if (($data['entityType'] ?? NULL) !== 'node' || !is_string($entityId)) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($entityId);

    if (!$node instanceof NodeInterface || !$node->hasTranslation($langcode) || !$this->precheck->applies($node)) {
      return NULL;
    }
    $translation = $node->getTranslation($langcode);

    if (!$translation->access('update')) {
      return NULL;
    }
    return fn () => $this->precheck->complete($translation);
  }

}
