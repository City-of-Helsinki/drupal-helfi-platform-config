"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.dialogHandler = exports.cookieHandler = void 0;
const logger_1 = require("./logger");
/**
 * Handles cookie consent banner acceptance.
 *
 * This function waits for and clicks the 'Accept all cookies' button
 * in the HDS (Helsinki Design System) cookie consent banner.
 *
 * @param page - Playwright Page object representing the browser page
 */
const cookieHandler = async (page) => {
    try {
        // Wait for the cookie banner to appear in the DOM.
        await page.waitForSelector('.hds-cc--banner', {
            state: 'attached',
            timeout: 5000
        });
        // Locate and wait for the accept all cookies button.
        const agreeButton = page.locator('.hds-cc__all-cookies-button');
        await agreeButton.waitFor({ state: 'attached' });
        // Click the button to accept all cookies.
        await agreeButton.click();
    }
    catch (error) {
        // Log if no cookie banner is found.
        (0, logger_1.logger)('No cookie banner found.');
    }
};
exports.cookieHandler = cookieHandler;
/**
 * Disables the survey dialog by setting a cookie.
 *
 * This prevents the survey dialog from appearing during tests,
 * ensuring consistent test execution.
 */
const dialogHandler = async (page) => {
    try {
        // Set 'helfi_no_survey' cookie to disable survey dialog
        await page.context().addCookies([{
                name: 'helfi_no_survey',
                value: '1',
                domain: (new URL(page.url())).hostname,
                path: '/',
                httpOnly: false
            }]);
    }
    catch (error) {
        const errorMessage = error instanceof Error ? error.message : 'Unknown error';
        (0, logger_1.logger)(`Failed to set survey cookie: ${errorMessage}`);
    }
};
exports.dialogHandler = dialogHandler;
