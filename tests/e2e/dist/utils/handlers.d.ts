import { Page } from "@playwright/test";
/**
 * Handles cookie consent banner acceptance.
 *
 * This function waits for and clicks the 'Accept all cookies' button
 * in the HDS (Helsinki Design System) cookie consent banner.
 *
 * @param page - Playwright Page object representing the browser page
 */
declare const cookieHandler: (page: Page) => Promise<void>;
/**
 * Disables the survey dialog by setting a cookie.
 *
 * This prevents the survey dialog from appearing during tests,
 * ensuring consistent test execution.
 */
declare const dialogHandler: (page: Page) => Promise<void>;
export { cookieHandler, dialogHandler, };
