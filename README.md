# City-of-Helsinki/drupal-helfi-platform-config

![CI](https://github.com/City-of-Helsinki/drupal-helfi-platform-config/workflows/CI/badge.svg)

This repository primarily holds configuration for the Hel.fi platform.

## Documentation

- [Translations in Helfi Platform](documentation/translations.md)

## Upgrade instructions for 1.* to 2.*

1. Install the site with existing configuration by running either `make new` or `make fresh`.
2. When the site is up and running, run `composer require drupal/helfi_platform_config:^2.0` to retrieve the new version of HELfi Platform config.
3. Run updates and export the configurations by running `make drush-updb drush-cr drush-cex`.
4. Go through configuration changes from `/conf/cmi/` and revert/modify any changes what will override customised configurations.
5. Commit the changes to your repository.

## Contact

Slack: #helfi-drupal (http://helsinkicity.slack.com/)

Mail: drupal@hel.fi
