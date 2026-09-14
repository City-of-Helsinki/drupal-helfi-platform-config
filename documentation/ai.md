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
//
// Each tier is a self-contained endpoint + deployment pair, so tiers may live
// on different Azure resources and carry their own api-version. A tier is
// registered only when both halves resolve; unconfigured tiers fall back to
// 'default' in \Drupal\helfi_ai\Service\AiGenerator::resolveModel().
$azure_openai_tiers = [
  'default' => ['AZURE_OPENAI_ENDPOINT', 'AZURE_OPENAI_DEPLOYMENT_NAME'],
  'low' => ['AZURE_OPENAI_ENDPOINT_LOW', 'AZURE_OPENAI_DEPLOYMENT_LOW'],
  'high' => ['AZURE_OPENAI_ENDPOINT_HIGH', 'AZURE_OPENAI_DEPLOYMENT_HIGH'],
];

foreach ($azure_openai_tiers as $azure_tier => [$azure_endpoint_var, $azure_deployment_var]) {
  if (!$azure_endpoint = getenv($azure_endpoint_var)) {
    continue;
  }
  // Classic Azure endpoints carry the deployment name in the URL path, the
  // newer ones do not, so an explicit variable always wins.
  $azure_deployment = getenv($azure_deployment_var) ?:
    (preg_match('#/deployments/([^/?]+)#', $azure_endpoint, $azure_matches) ? $azure_matches[1] : NULL);

  if (!$azure_deployment) {
    continue;
  }

  $config['ai.settings']['models']['azure']['chat'][$azure_deployment] = [
    'endpoint' => $azure_endpoint,
    'api_key' => 'helfi_azure_openai',
    'connect_header' => 'api-key',
  ];
  $config['helfi_ai.settings']['model_tiers'][$azure_tier] = 'azure__' . $azure_deployment;

  if ($azure_tier === 'default') {
    $config['ai.settings']['default_providers']['chat']['model_id'] = $azure_deployment;
    $config['ai.settings']['default_providers']['embeddings']['model_id'] = $azure_deployment;
  }
}
```

Only the first two variables are required. The rest are opt-in, and an instance that sets none of them behaves exactly as it did before model tiers existed.

| Variable | Required | Description |
|---|---|---|
| `AZURE_OPENAI_API_KEY` | yes | Azure OpenAI API key. Shared by every tier |
| `AZURE_OPENAI_ENDPOINT` | yes | Full Azure Chat Completions URL for the default tier, including deployment name and `api-version` query parameter |
| `AZURE_OPENAI_DEPLOYMENT_NAME` | no | Deployment name for the default tier. Parsed from the endpoint URL when omitted |
| `AZURE_OPENAI_ENDPOINT_LOW` | no | Chat Completions URL for the low tier |
| `AZURE_OPENAI_DEPLOYMENT_LOW` | no | Deployment name for the low tier |
| `AZURE_OPENAI_ENDPOINT_HIGH` | no | Chat Completions URL for the high tier |
| `AZURE_OPENAI_DEPLOYMENT_HIGH` | no | Deployment name for the high tier |

In production these are provisioned via Azure Keyvault through the CI pipeline.

## Model tiers

Features differ in what they need from a model: rewriting text in the city's tone of voice benefits from a capable model, while producing a summary or a few title candidates does not. Rather than naming models in code, each feature asks for a **tier** and the instance decides which deployment backs it.

| Tier | Used by |
|---|---|
| `high` | Tone check |
| `low` | AI summary, SEO title suggestions |
| `default` | Fallback for any tier the instance has not configured |

Tiers are declared in code via `\Drupal\helfi_ai\ModelTier` and mapped to providers in `helfi_ai.settings:model_tiers`, which `settings.php` populates from the environment. The stored value is the AI module's provider and model string, for example `azure__gpt-5-nano`.

Resolution falls back in three steps, so nothing hard-fails when a tier is missing:

```
requested tier → default tier → site-wide provider in 'ai.settings'
```

An instance therefore only needs to provision the tiers it actually wants to differentiate. Adding one is a pipeline change, not a code change: set the endpoint and deployment variables for that tier and the mapping appears on the next deploy.

Note that `default` is also the slot a third tier would occupy if `medium` is ever needed, so it is deliberately not named `medium` today.

## Local development

Container env vars are not consistently available across instances, so override the relevant config in `public/sites/default/local.settings.php` (gitignored):

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

// Point every tier at the same deployment. Add 'low' and 'high' entries only
// if you have more than one deployment to test tier routing against.
$config['helfi_ai.settings']['model_tiers']['default'] = 'azure__' . $azure_deployment;
```

Set `model_tiers` directly rather than via `putenv()`. `local.settings.php` is included at the end of `settings.php`, long after the tier loop above has already read the environment, so environment variables set there arrive too late to register a tier.

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

That uses the site-wide default model. To pick a model by capability instead, resolve a [tier](#model-tiers) and pass it as the second argument:

```php
use Drupal\helfi_ai\ModelTier;

$tiers = \Drupal::config('helfi_ai.settings')->get('model_tiers') ?? [];
$preferred = $tiers[ModelTier::High->value] ?? $tiers[ModelTier::Default->value] ?? NULL;

['provider_id' => $provider, 'model_id' => $model] = $ai->getSetProvider('chat', $preferred);
```

## Prompt Library

Shared prompts are distributed as `ai.ai_prompt_type.*` and `ai.ai_prompt.*` config entities in a module's `config/install/` directory. Use the `helfi_` prefix on prompt type IDs to avoid collisions with instance-specific prompts.

The Drupal AI module stores each prompt under the ID `{type}__{prompt_id}` and substitutes variables with single-brace placeholders, e.g. `{content}`.

### Adding a prompt

```yaml
# config/install/ai.ai_prompt_type.helfi_content_summary.yml
id: helfi_content_summary
label: 'HELfi content summary'
variables:
  -
    name: content
    help_text: 'The page content as plain text'
    required: true
tokens: {  }
```

```yaml
# config/install/ai.ai_prompt.helfi_content_summary__helfi_content_summary_default.yml
id: helfi_content_summary__helfi_content_summary_default
label: 'HELfi content summary (default)'
type: helfi_content_summary
prompt: |
  Summarize the following content in 2-3 sentences in a neutral, informative tone:
  {content}
```

### Calling a prompt from code

```php
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

$prompt = \Drupal::entityTypeManager()
  ->getStorage('ai_prompt')
  ->load('helfi_content_summary__helfi_content_summary_default');

$text = str_replace('{content}', $my_content, $prompt->getPrompt());

$ai = \Drupal::service('ai.provider');
['provider_id' => $provider, 'model_id' => $model] = $ai->getSetProvider('chat');

$input = new ChatInput([new ChatMessage('user', $text)]);
$answer = $provider->chat($input, $model)->getNormalized()->getText();
```

## SEO title suggestions

The `helfi_ai` module adds an AI **Generate SEO title with AI** button next to the node title field. It builds the unsaved node from the current form values, strips it to plain text, and asks the chat provider for a few GEO/SEO-optimized title candidates, shown in a modal for the editor to pick from. The chosen title fills the title field and can still be edited before saving.

| Aspect | Value |
|---|---|
| Prompt | `ai.ai_prompt.helfi_seo_title__helfi_seo_title_default` (type `helfi_seo_title`) |
| Permission | `use helfi ai title suggestion` (granted to `admin`, `editor`, `content_producer`) |
| Content types | `helfi_ai.settings:seo_title_bundles` (defaults to `page`) |

The content types offering the button are read from configuration, so an instance can adjust them by overriding `seo_title_bundles` — no code change required. The prompt is shipped as config and tuned there; it instructs the model to respond in the page's language, so no per-language prompt is needed.
