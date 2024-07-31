# Hel.fi: TFA

Provides default configuration for TFA module.

## Installation

1. Generate a random 256-bit key: `dd if=/dev/urandom bs=32 count=1 | base64 -i -`
2. Add a new secret called `TFA-ENCRYPTION-KEY` to Azure KeyVault and copy the key as value
3. Enable `helfi_tfa` module

## Testing on local

Modify `local.settings.php` file and add:

```php
$config['key.key.tfa']['key_provider_settings']['key_value'] = 'your-base64-encoded-random-256-bit-key';
$config['key.key.tfa']['key_provider_settings']['base64_encoded'] = TRUE;
```
