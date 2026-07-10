<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\helfi_ai\Service\AiGenerator;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns a tone-conforming rewrite of submitted editor content.
 */
final class ToneCheckController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly AiGenerator $generator,
  ) {}

  /**
   * Checks the tone of the posted content and returns a suggested rewrite.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request, with a JSON body of {content, langcode}.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   {suggestion: string} on success, or {error: string} with a 4xx/5xx code.
   */
  public function check(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);

    if (!isset($data['content'], $data['langcode'])) {
      throw new BadRequestException('Missing "content" or "langcode" parameter.');
    }
    ['content' => $content, 'langcode' => $langcode] = $data;

    if (trim($content) === '') {
      return new JsonResponse(['error' => 'No content to check.'], 400);
    }
    if (strlen($content) > AiGenerator::MAX_CONTENT_BYTES) {
      return new JsonResponse(['error' => 'Content is too large to check.'], 413);
    }

    $suggestion = $this->generator->checkTone($content, $langcode);

    if ($suggestion === NULL) {
      return new JsonResponse(['error' => 'Could not check the tone. Make sure the AI provider is configured.'], 502);
    }

    return new JsonResponse(['suggestion' => $suggestion]);
  }

}
