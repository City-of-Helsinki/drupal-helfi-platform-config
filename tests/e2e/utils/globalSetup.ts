import { FullConfig, chromium } from '@playwright/test';
import { cookieHandler, dialogHandler } from "./handlers";

/**
 * Global setup function that runs once before all tests.
 * The function handles common setup tasks and saves the browser state to
 * in storageState file.
 */
export default async function globalSetup(config: FullConfig) {
  // Extract configuration values.
  const { baseURL, storageState } = config.projects[0].use;

  // Launch a new browser instance.
  const browser = await chromium.launch();
  const page = await browser.newPage({ baseURL });

  try {
    // Navigate to the base URL to initialize the session.
    await page.goto('/');

    // Handle cookie consent banner.
    await cookieHandler(page);

    // Handle any dialogs that appear on page load.
    await dialogHandler(page);

    // Save the browser's storage state (cookies, local storage) to a file.
    await page.context().storageState({ path: storageState as string });
  } finally {
    // Ensure the browser is always closed, even if an error occurs.
    await browser.close();
  }
}
