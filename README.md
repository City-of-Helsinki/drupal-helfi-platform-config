# City-of-Helsinki/drupal-helfi-platform-config

![CI](https://github.com/City-of-Helsinki/drupal-helfi-platform-config/workflows/CI/badge.svg)

This repository primarily holds configuration for the Hel.fi platform.

## Documentation

- [Translations in Helfi Platform](documentation/translations.md)

## Upgrade instructions for 2.x to 3.x

1. Update the module to latest 2.x version:
   1. `composer update drupal/helfi_platform_config`
   2. Run database updates (`drush updb`)
   3. Export your configuration (`drush config:export`)
2. Remove `helfi_media_map` module: `composer remove drupal/helfi_media_map` (this will be included in 3.x platform config module)
3. Update the module to 3.x version: `composer require drupal/helfi_platform_config:^3.0 drush/drush:^11 -W `
4. @todo fill this.

## Contact

Slack: #helfi-drupal (http://helsinkicity.slack.com/)

Mail: helfi-drupal-aaaactuootjhcono73gc34rj2u@druid.slack.com
