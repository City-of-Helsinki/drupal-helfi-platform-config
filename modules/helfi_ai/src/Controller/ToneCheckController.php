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
 * Streams a tone-conforming rewrite of submitted editor content.
 */
final class ToneCheckController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly TextServicesGenerator $generator,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Streams a suggested rewrite of the posted content as server-sent events.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request, with a JSON body of {content, langcode}.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The event stream, or {error: string} with a 4xx code.
   */
  public function check(Request $request): Response {
    if (!$this->configFactory->get('helfi_ai.settings')->get('enable_tone_check')) {
      return new JsonResponse(['error' => 'Tone check is disabled.'], 403);
    }
    $data = json_decode($request->getContent(), TRUE);

    if (!isset($data['content'], $data['langcode'])) {
      throw new BadRequestException('Missing "content" or "langcode" parameter.');
    }
    ['content' => $content, 'langcode' => $langcode] = $data;

    if (trim($content) === '') {
      return new JsonResponse(['error' => 'No content to check.'], 400);
    }
    if (mb_strlen($content) > TextServicesGenerator::MAX_CONTENT_LENGTH) {
      return new JsonResponse(['error' => 'Content is too large to check.'], 413);
    }

    return new EventStreamResponse(fn () => $this->generator->streamTone($content, $langcode));
  }

}
