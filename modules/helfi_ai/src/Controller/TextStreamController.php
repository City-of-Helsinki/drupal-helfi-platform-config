<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\helfi_ai\Service\TextServicesGenerator;
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
  ) {}

  /**
   * Streams the feature's result as server-sent events.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request, with a JSON body of {text, langcode}.
   * @param 'tone'|'summary' $feature
   *   The feature, from the route defaults.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The event stream, or {error: string} with a 4xx code.
   */
  public function stream(Request $request, string $feature): Response {
    $generate = match ($feature) {
      'tone' => $this->generator->streamTone(...),
      'summary' => $this->generator->streamSummary(...),
    };

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

    return new EventStreamResponse(fn () => $generate($text, $langcode));
  }

}
