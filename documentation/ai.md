# AI

This module integrates the [Drupal AI module](https://www.drupal.org/project/ai) with Azure OpenAI across all hel.fi instances via `helfi_platform_config`.

## Installed modules

- [`drupal/ai`](https://www.drupal.org/project/ai) — core AI abstraction layer, provider plugin system, and Prompt Library
- [`drupal/ai_provider_azure`](https://www.drupal.org/project/ai_provider_azure) — Azure AI Studio provider (plugin ID: `azure`)
- [`drupal/key`](https://www.drupal.org/project/key) — secure API key management

## Enabling on an instance

Add the following block to the instance `settings.php`. The API key is managed separately via the Key module (see below) and does not go here.

```php
// Azure OpenAI for Drupal AI module (ai_provider_azure).
// See: https://helsinkisolutionoffice.atlassian.net/browse/UHF-13110.
if (getenv('AZURE_OPENAI_ENDPOINT') && getenv('AZURE_OPENAI_DEPLOYMENT_NAME')) {
  $deployment = getenv('AZURE_OPENAI_DEPLOYMENT_NAME');
  $config['ai.settings']['default_providers']['chat']['model_id'] = $deployment;
  $config['ai.settings']['default_providers']['embeddings']['model_id'] = $deployment;
  $config['ai.settings']['models']['azure']['chat'][$deployment] = [
    'endpoint' => getenv('AZURE_OPENAI_ENDPOINT'),
    'api_key' => 'helfi_azure_openai',
    'connect_header' => 'api-key',
  ];
}
```

Three environment variables are required:

| Variable | Description |
|---|---|
| `AZURE_OPENAI_API_KEY` | Azure OpenAI API key |
| `AZURE_OPENAI_ENDPOINT` | Full Azure Chat Completions URL including deployment name and `api-version` query parameter |
| `AZURE_OPENAI_DEPLOYMENT_NAME` | Deployment name used as the model ID |

In production these are provisioned via Azure Keyvault through the CI pipeline.

## Local development

Use `local.settings.php`: Override `ai.settings` and the Key entity directly:

```php
$azure_api_key    = 'YOUR_API_KEY';
$azure_endpoint   = 'YOUR_AZURE_ENDPOINT';
$azure_deployment = 'YOUR_DEPLOYMENT_NAME';

$config['ai.settings']['default_providers']['chat']['provider_id'] = 'azure';
$config['ai.settings']['default_providers']['chat']['model_id'] = $azure_deployment;
$config['ai.settings']['default_providers']['embeddings']['provider_id'] = 'azure';
$config['ai.settings']['default_providers']['embeddings']['model_id'] = $azure_deployment;
$config['ai.settings']['models']['azure']['chat'][$azure_deployment] = [
  'endpoint' => $azure_endpoint,
  'api_key' => 'helfi_azure_openai',
  'connect_header' => 'api-key',
];
$config['key.key.helfi_azure_openai']['key_provider'] = 'config';
$config['key.key.helfi_azure_openai']['key_provider_settings']['key_value'] = $azure_api_key;
```

Run `drush cr` after editing `local.settings.php`. The endpoint must be the full Chat Completions URL from Azure AI Studio (the one ending in `/chat/completions?api-version=...`), not the Responses API URL.

## API key management

In production the API key is stored in a `key.key` config entity (`helfi_azure_openai`) shipped by `helfi_platform_config`. It uses the Key module's `env` provider, which reads `AZURE_OPENAI_API_KEY` directly from the environment at runtime. The actual key value never enters Drupal configuration or the database.

For local dev the override above swaps the provider to `config` so the value comes from `local.settings.php` instead.

## Using the AI API in custom modules

Use the `ai.provider` service to get the configured default provider and make requests.

```php
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

$ai = \Drupal::service('ai.provider');
['provider_id' => $provider, 'model_id' => $model] = $ai->getSetProvider('chat');

$input = new ChatInput([
  new ChatMessage('user', 'Summarize the following text: ' . $text),
]);

$response = $provider->chat($input, $model)->getNormalized();
$answer = $response->getText();
```

## Prompt Library

Shared prompts are distributed as `ai.ai_prompt.*` config entities in `config/install/`.

### Adding a prompt

```yaml
# config/install/ai.ai_prompt.helfi_content_summary.yml
id: helfi_content_summary
label: 'Content summary'
type: helfi_generic
prompt: 'Summarize the following content in 2-3 sentences in a neutral, informative tone: {{ content }}'
```

### Calling a prompt from code

Load the prompt entity, substitute any variables, then pass it to the provider:

```php
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

$prompt = \Drupal::entityTypeManager()
  ->getStorage('ai_prompt')
  ->load('helfi_content_summary');

$text = str_replace('{{ content }}', $my_content, $prompt->getPrompt());

$ai = \Drupal::service('ai.provider');
['provider_id' => $provider, 'model_id' => $model] = $ai->getSetProvider('chat');

$input = new ChatInput([new ChatMessage('user', $text)]);
$answer = $provider->chat($input, $model)->getNormalized()->getText();
```
