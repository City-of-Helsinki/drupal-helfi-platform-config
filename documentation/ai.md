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
| `AZURE_OPENAI_ENDPOINT` | Full Azure endpoint URL including deployment and API version |
| `AZURE_OPENAI_DEPLOYMENT_NAME` | Deployment name used as the model ID |

In production these are provisioned via Azure Keyvault through the CI pipeline. For local development add them to `.env.local` (gitignored) and run `make up`.

## API key management

The API key is stored in a `key.key` config entity (`helfi_azure_openai`) shipped by `helfi_platform_config`. It uses the Key module's `env` provider, which reads `AZURE_OPENAI_API_KEY` directly from the environment at runtime. The actual key value never enters Drupal configuration or the database.

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

Shared prompts are distributed as `ai.ai_prompt.*` config entities in `config/install/`. Use the `helfi_` prefix on all prompt IDs to avoid collisions with instance-specific prompts.

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
