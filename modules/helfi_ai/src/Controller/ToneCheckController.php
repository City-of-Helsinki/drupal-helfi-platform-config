<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_ai\Service\AiToneChecker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns a tone-conforming rewrite of submitted editor content.
 *
 * Called by the CKEditor tone-check plugin. Access is gated by permission and a
 * CSRF request-header token (see helfi_ai.routing.yml).
 */
final class ToneCheckController implements ContainerInjectionInterface {

  /**
   * Maximum accepted content size in bytes (256 KB).
   *
   * Bounds the payload sent to the AI provider to avoid runaway cost/latency.
   */
  private const MAX_CONTENT_BYTES = 256 * 1024;

  public function __construct(
    private readonly AiToneChecker $toneChecker,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(AiToneChecker::class),
      $container->get('language_manager'),
    );
  }

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
    $content = is_array($data) && isset($data['content']) ? (string) $data['content'] : '';
    $langcode = is_array($data) && isset($data['langcode']) && $data['langcode'] !== ''
      ? (string) $data['langcode']
      : $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    if (trim($content) === '') {
      return new JsonResponse(['error' => 'No content to check.'], 400);
    }
    if (strlen($content) > self::MAX_CONTENT_BYTES) {
      return new JsonResponse(['error' => 'Content is too large to check.'], 413);
    }

    $suggestion = $this->toneChecker->check($content, $langcode);
    if ($suggestion === NULL) {
      return new JsonResponse(['error' => 'Could not check the tone. Make sure the AI provider is configured.'], 502);
    }

    return new JsonResponse(['suggestion' => $suggestion]);
  }

}
