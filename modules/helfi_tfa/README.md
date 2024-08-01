# Hel.fi: TFA

Provides default configuration for TFA module.

## Installation

1. Generate a random 256-bit key: `dd if=/dev/urandom bs=32 count=1 | base64 -i -`
2. Add a new secret called `TFA-ENCRYPTION-KEY` to Azure KeyVault and copy the key as value
3. Enable `helfi_tfa` module

## Exclude roles

Go to Configuration -> TFA Settings (`/admin/config/people/tfa`) and uncheck the role from `Roles required to set up TFA` and save the form.

Alternatively, you can override the roles setting in `all.settings.php`:
```php
$config['tfa.settings']['required_roles'] = [
  'content_producer' => 'content_producer',
  'admin' => 'admin',
  'read_only' => '0',
];
```
Setting the value to `0` means the role does not require TFA.

## Testing on local

Modify `local.settings.php` file and add:

```php
$config['key.key.tfa']['key_provider_settings']['key_value'] = 'your-base64-encoded-random-256-bit-key';
$config['key.key.tfa']['key_provider_settings']['base64_encoded'] = TRUE;
```
