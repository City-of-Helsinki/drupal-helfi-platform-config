# Shared Playwright E2E Testing Framework

This package provides a shared end-to-end (E2E) testing framework using Playwright for Helfi Drupal projects. It includes common configurations, utilities, and best practices for writing reliable browser tests.

## Table of Contents
- [Overview](#overview)
- [Project Structure](#project-structure)
- [Setup and Installation](#setup-and-installation)
- [Configuration](#configuration)
- [Key Components](#key-components)
- [Writing Tests](#writing-tests)
- [Updating the Shared Package](#updating-the-shared-package)
- [Best Practices](#best-practices)

## Overview

The E2E testing framework is designed to:
- Provide a consistent testing environment across all Helfi instances
- Handle common setup/teardown tasks automatically
- Include reusable test utilities and helpers
- Support parallel test execution
- Generate comprehensive test reports

## Project Structure

```
e2e/
├── .env                       # Environment variables
├── package.json               # NPM package configuration
├── playwright.config.ts       # Base Playwright configuration
├── tests/                     # Test files
└── utils/                     # Shared utilities
    ├── fetchJsonApiRequest.ts # API request helper
    ├── globalSetup.ts         # Global test setup
    ├── globalTeardown.ts      # Global test teardown
    ├── handlers.ts            # Common page handlers
    ├── logger.ts              # Logging utilities
    └── storagePath.ts         # Storage state management
```

## Setup and Installation

This package is intended to be used as a dependency in other projects. It is not intended to be used directly. 

When developing this package, the tests can be run in similar way as in the dependent projects. 

1. **Install Dependencies**:
   ```bash
   npm install
   ```

2. **Environment Variables**:
   Create a `.env` file with required variables:
   ```
   BASE_URL=https://your-site.docker.so
   ```

3. **Running Tests**:
   ```bash
   # Run all tests
   npm run test
   ```

## Configuration

The base configuration (in `playwright.config.ts`) includes:
- Default timeouts and retries
- Common reporters (list and HTML)
- Standard viewport settings
- Automatic screenshot and video capture on failure
- Storage state management

Projects can extend and override these settings as needed.

## Key Components

### Global Setup (`utils/globalSetup.ts`)
- Runs once before all tests
- Handles authentication and session management
- Saves browser state to a storage file
- Manages cookie consent and dialogs

### Global Teardown (`utils/globalTeardown.ts`)
- Cleans up after test execution
- Removes temporary files
- Handles any necessary cleanup tasks

### Storage Management (`utils/storagePath.ts`)
- Manages the storage state file location
- Handles cross-platform path resolution
- Ensures proper directory structure exists

### API Helpers (`utils/fetchJsonApiRequest.ts`)
- Provides typed HTTP request methods
- Handles JSON:API interactions
- Includes proper error handling and type safety

### Handlers (`utils/handlers.ts`)
- Common page interaction patterns
- Cookie consent handling
- Dialog and alert management

## Updating the Shared Package

To update the shared E2E package:
1. Make your changes to the shared files
2. Update the package version
   ```bash
   npm version patch --no-git-tag-version
   ```
3. Build the package:
   ```bash
   npm run build
   ```
4. Create a new tarball:
   ```bash
   npm pack
   ```
5. Update the version reference in *dependent projects*' `package.json` by running `npm i`.
