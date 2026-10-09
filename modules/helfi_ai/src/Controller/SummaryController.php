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
 * Streams an AI summary of the posted text.
 */
final class SummaryController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly TextServicesGenerator $generator,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Streams a summary of the posted text as server-sent events.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request, with a JSON body of {text, langcode}.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The event stream, or {error: string} with a 4xx code.
   */
  public function summary(Request $request): Response {
    if (!$this->configFactory->get('helfi_ai.settings')->get('enable_ai_summary')) {
      return new JsonResponse(['error' => 'AI summary is disabled.'], 403);
    }
    $data = json_decode($request->getContent(), TRUE);

    if (!isset($data['text'], $data['langcode'])) {
      throw new BadRequestException('Missing "text" or "langcode" parameter.');
    }
    ['text' => $text, 'langcode' => $langcode] = $data;

    if (trim($text) === '') {
      return new JsonResponse(['error' => 'No text to summarize.'], 400);
    }
    if (mb_strlen($text) > TextServicesGenerator::MAX_CONTENT_LENGTH) {
      return new JsonResponse(['error' => 'Text is too long to summarize.'], 413);
    }

    return new EventStreamResponse(fn () => $this->generator->streamSummary($text, $langcode));
  }

}
