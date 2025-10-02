import { test } from '@playwright/test';
import { cookieHandler, dialogHandler } from '../utils/handlers';

test('Smoke test', async ({ page }) => {
  await page.goto('/fi/');
  await cookieHandler(page);
  await dialogHandler(page);
});
