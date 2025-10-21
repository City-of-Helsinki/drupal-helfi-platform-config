# Hel.fi Calculator

Helfi Calculator provides configurable calculator functionality for various financial calculations that can be added to the site via Calculator paragraphs.

## Features

### Core Functionality

- Supports multiple calculator types via drupal libraries
- Built-in translation system for UI elements, which does not use Drupal translations

### Included Calculators

1. **Home Care Service Voucher**
2. **Home Care Client Fee**
3. **Families Home Services Client Fee**
4. **Continuous Housing Service Voucher**
5. **Early Childhood Education Fee**
6. **Helsinki Benefit Amount Estimate**

## Installation

1. Enable the module and clear the caches:
   ```bash
   drush en helfi_calculator -y && drush cr
   ```

## Development Setup

1. Set up Node.js environment:
   ```bash
   nvm use
   npm install
   ```

2. Available development commands:
  - `npm run dev`: Build non-minified assets and watch file changes
  - `npm run build`: Build production assets
  - `npm run lint`: Run code linting

## Architecture

The Helfi Calculator module adds calculators to the site using a custom Calculator paragraph. Calculator settings are defined as JSON and managed through Drupal’s configuration API, but they are not stored in the repository. Each calculator is placed in its own folder with separate files for logic, forms, and translations. All calculators share a common JavaScript base class (HelfiCalculatorBase). The correct libraries are loaded automatically based on the selected calculator type. Translations are loaded directly from the JSON files in the calculator’s folder instead of Drupal’s translation system.

## Creating a New Calculator

1. Create a new JavaScript file in `assets/js/yourCalculator/yourCalculator.js` and add the minified path to `helfi_calculator.libraries.yml`
2. Extend the `HelfiCalculator` class and add the _form and _translations files to `assets/js/yourCalculator/`. See examples from other calculators
3. Add the calculator to the `calculators` array in `helfi_calculator.settings.yml`
4. Register the calculator in Drupal configuration by adding calculator settings to to the configuration via `/admin/tools/calculator-settings` configuration form. An example of the JSON can be found in example-calc.html or helsinki-benefit-test.html.

## Known Issues

### Icons in example-calc.html
Icons may not display correctly in the example calculator due to incorrect paths. Update the icon paths in the HTML file to match your environment.
