# AI

The `helfi_ai` module's tone check, AI summary and SEO title suggestions use the [Helfi Text Services](#helfi-text-services) API at `https://helfi-text-services.azurewebsites.net/api` (`TextServicesClient::BASE_URL`).

## Helfi Text Services

Helfi Text Services is the city's own text processing API. Its prompts, models and model settings are managed in the API's admin UI, not in Drupal.

| Service | Path | Used by |
|---|---|---|
| Tone of voice | `v1/tone-of-voice` | Tone check |
| Summarization | `v1/summarization` | AI summary |
| SEO title suggestions | `v1/seo-title-suggestions` | SEO title suggestions |

Every request sends the content's language as `content_language`, so the response is in the same language as the content.

Structured results are requested with `response_format: json_structure` and a JSON schema; the API returns the validated object in `data` (`TextServicesResponse::$data`), and plain text in `text`.

The AI summary and the tone check are streamed. For the summary, the widget's AJAX callback returns the page text, and the browser streams the summary from `/helfi-ai/summary` into the summary editor, showing each item as soon as it is complete. For the tone check, the CKEditor plugin streams the rewrite from `/helfi-ai/tone-check` into the dialog's Suggested tab, and shows the comparison and Replace once it is complete.

The API base URL and the service paths are defined in code (`\Drupal\helfi_ai\TextServices\TextServicesClient` and `\Drupal\helfi_ai\TextServices\Service`). The only configuration is the API key, `helfi_ai.settings:text_services.api_key`. The platform `settings.php` reads it from the `HELFI_TEXT_API_KEY` environment variable:

```php
if ($helfi_text_api_key = getenv('HELFI_TEXT_API_KEY')) {
  $config['helfi_ai.settings']['text_services']['api_key'] = $helfi_text_api_key;
}
```

For local development, set it in `public/sites/default/local.settings.php` (gitignored) and run `drush cr`:

```php
$config['helfi_ai.settings']['text_services']['api_key'] = 'YOUR_API_KEY';
```

The key is never exported with configuration. Note that `drush config:get helfi_ai.settings --include-overridden` prints it.

### Using the API in code

```php
use Drupal\helfi_ai\TextServices\Service;
use Drupal\helfi_ai\TextServices\TextServicesClientInterface;
use Drupal\helfi_ai\TextServices\TextServicesRequest;

$client = \Drupal::service(TextServicesClientInterface::class);
$response = $client->send(new TextServicesRequest(Service::Tone, '<p>Text to rewrite</p>', 'fi'));
$text = $response->text;

// Streamed: 'start', 'delta' (the text in parts, for showing progress) and
// 'done' (the final response) events.
foreach ($client->stream(new TextServicesRequest(Service::Tone, '<p>Text to rewrite</p>', 'fi')) as $event) {
  if ($event['event'] === 'done') {
    $text = $event['data']['text'];
  }
}
```

Failed requests throw `\Drupal\helfi_ai\TextServices\TextServicesException`, with the HTTP status as the exception code. The API accepts at most 30 000 characters of text.

## SEO title suggestions

The `helfi_ai` module adds an AI **Generate SEO title with AI** button next to the node title field. It builds the unsaved node from the current form values, strips it to plain text, and asks [Helfi Text Services](#helfi-text-services) for up to three title candidates, shown in a modal for the editor to pick from. The chosen title fills the title field and can still be edited before saving.

| Aspect | Value |
|---|---|
| Service | `v1/seo-title-suggestions` |
| Permission | `use helfi ai title suggestion` (granted to `admin`, `editor`, `content_producer`) |
| Content types | `helfi_ai.settings:seo_title_bundles` (defaults to `page`) |

The content types offering the button are read from configuration, so an instance can adjust them by overriding `seo_title_bundles` — no code change required.
