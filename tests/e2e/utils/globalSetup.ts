import fs from 'node:fs';
import path from 'node:path';
import { chromium, type FullConfig } from '@playwright/test';
import { cookieHandler, dialogHandler } from './handlers';
import { getStorageStatePath } from './storagePath';

/**
 * Global setup function that runs once before all tests.
 * The function handles common setup tasks and saves the browser state to
 * a storageState file.
 */
export default async function globalSetup(config: FullConfig) {
  const storageStatePath = getStorageStatePath();

  // Early return if storage state already exists.
  if (fs.existsSync(storageStatePath)) return;

  // Ensure the parent directory exists.
  fs.mkdirSync(path.dirname(storageStatePath), { recursive: true });

  // Launch a new browser instance.
  const browser = await chromium.launch();
  const page = await browser.newPage({ baseURL: config.projects[0].use.baseURL });

  try {
    // Navigate to the base URL to initialize the session.
    await page.goto('/');

    // Handle cookie consent banner.
    await cookieHandler(page);

    // Handle any dialogs that appear on page load.
    await dialogHandler(page);

    // Save the browser's storage state (cookies, local storage) to a file.
    await page.context().storageState({ path: storageStatePath });
  } finally {
    // Ensure the browser is always closed, even if an error occurs.
    await browser.close();
  }
}
