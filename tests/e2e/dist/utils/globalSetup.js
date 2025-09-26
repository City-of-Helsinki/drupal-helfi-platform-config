"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.default = globalSetup;
const test_1 = require("@playwright/test");
const handlers_1 = require("./handlers");
/**
 * Global setup function that runs once before all tests.
 * The function handles common setup tasks and saves the browser state to
 * in storageState file.
 */
async function globalSetup(config) {
    // Extract configuration values.
    const { baseURL, storageState } = config.projects[0].use;
    // Launch a new browser instance.
    const browser = await test_1.chromium.launch();
    const page = await browser.newPage({ baseURL });
    try {
        // Navigate to the base URL to initialize the session.
        await page.goto('/');
        // Handle cookie consent banner.
        await (0, handlers_1.cookieHandler)(page);
        // Handle any dialogs that appear on page load.
        await (0, handlers_1.dialogHandler)(page);
        // Save the browser's storage state (cookies, local storage) to a file.
        await page.context().storageState({ path: storageState });
    }
    finally {
        // Ensure the browser is always closed, even if an error occurs.
        await browser.close();
    }
}
