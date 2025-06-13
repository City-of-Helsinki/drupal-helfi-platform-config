<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Site\Settings;
use Drupal\helfi_react_search\DTO\LinkedEventsItem;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Handle autocomplete for linked events data.
 */
final class LinkedEventsAutocompleteController extends ControllerBase {

  use AutowireTrait;

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly ClientInterface $client,
  ) {
  }

  /**
   * Autocomplete the label from linked events.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that contains the typed tags.
   * @param string $target_type
   *   The ID of the target type.
   * @param string $selection_handler
   *   Parameter passed by Select2.
   * @param string $selection_settings_key
   *   The hashed key of the key/value entry that holds the selection handler
   *   settings.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown if the selection settings key is not found in the key/value store
   *   or if it does not match the stored data.
   * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
   * @throws \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException
   */
  public function handleAutocomplete(Request $request, string $target_type, string $selection_handler, string $selection_settings_key): Response {
    // Get the typed string from the URL, if it exists.
    $input = $request->query->get('q');
    if (!$input) {
      throw new BadRequestException();
    }

    // This validates the request hash.
    $settings = $this->getSelectionSettings($target_type, $selection_handler, $selection_settings_key);

    $langcode = $this->languageManager()->getCurrentLanguage()->getId();
    $results = [];

    try {
      $response = $this->client->request('GET', "https://api.hel.fi/linkedevents/v1/$target_type/", [
        'query' => $this->buildSearchQuery($input, $settings),
      ]);

      $response = Utils::jsonDecode($response->getBody()->getContents());

      $results = array_map(fn (object $item) => [
        // Ids are JSON serialized LinkedEventsItems.
        'id' => json_encode(new LinkedEventsItem($item->id, (array) $item->name)),
        'text' => $item->name?->{$langcode} ?: $item->name?->en ?: 'Unknown',
      ], $response->data ?? []);
    }
    catch (GuzzleException) {
      return new Response(status: 503);
    }

    return new JsonResponse([
      'results' => $results,
    ]);
  }

  /**
   * Build linked events search query.
   *
   * @param string $input
   *   Input string.
   * @param array $settings
   *   Autocomplete settings.
   *
   * @return array
   *   Search query.
   */
  private function buildSearchQuery(string $input, array $settings): array {
    parse_str($settings['query'] ?? '', $query);

    // Find keywords that contain a specific string.
    $query['text'] = $input;

    return $query;
  }

  /**
   * Gets and validates selection settings from key value.
   *
   * This mimics select2 autocomplete functionality.
   * The hash set by select2 widget is validated here.
   *
   * @return array
   *   Selection settings set by the widget.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown if the selection settings key is not found in the key/value store
   *   or if it does not match the stored data.
   */
  private function getSelectionSettings(string $target_type, string $selection_handler, string $selection_settings_key): array {
    // Selection settings are passed in as a hashed key of a serialized array
    // stored in the key/value store.
    $selection_settings = $this->keyValue('entity_autocomplete')->get($selection_settings_key, FALSE);
    if ($selection_settings !== FALSE) {
      $selection_settings_hash = Crypt::hmacBase64(serialize($selection_settings) . $target_type . $selection_handler, Settings::getHashSalt());

      if (hash_equals($selection_settings_hash, $selection_settings_key)) {
        return $selection_settings;
      }
    }

    // Disallow access when the selection settings hash does not match the
    // passed-in key.
    throw new AccessDeniedHttpException('Invalid selection settings key.');
  }

}
