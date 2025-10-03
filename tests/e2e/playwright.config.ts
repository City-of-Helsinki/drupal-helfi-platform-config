import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import fs from 'node:fs';
import { getStorageStatePath } from './utils/storagePath';

type Config = Parameters<typeof defineConfig>[0];

const baseSetupPath = require.resolve('./utils/globalSetup');
const baseTeardownPath = require.resolve('./utils/globalTeardown');

const base: Config = {
  globalSetup: [baseSetupPath],
  globalTeardown: [baseTeardownPath],
  testDir: './tests',
  testMatch: '**/*.ts',
  timeout: 300_000,
  expect: { timeout: 5_000 },
  fullyParallel: true,
  retries: process.env.CI ? 2 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.BASE_URL ?? 'https://www.test.hel.ninja/',
    storageState: getStorageStatePath(),
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    viewport: { width: 1280, height: 800 },
    launchOptions: { slowMo: process.env.SLOWMO ? 1_000 : 0 },
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
};

export const baseConfig = defineConfig(base);

const toArray = (v: Config['globalSetup']) =>
  v ? (Array.isArray(v) ? v : [v]) : [];

const mergeAppend = (
  baseVal: Config['globalSetup'],
  overrideVal: Config['globalSetup']
) => Array.from(new Set([...toArray(baseVal), ...toArray(overrideVal)]));

export function makeConfig(overrides: Partial<Config> = {}) {
  const merged: Config = {
    ...base,
    ...overrides,
    use: { ...base.use, ...(overrides.use ?? {}) },
    globalSetup: mergeAppend(base.globalSetup, overrides.globalSetup),
    globalTeardown: mergeAppend(base.globalTeardown, overrides.globalTeardown),
  };
  return defineConfig(merged);
}

export default baseConfig;
