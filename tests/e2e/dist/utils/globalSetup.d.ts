import { FullConfig } from '@playwright/test';
/**
 * Global setup function that runs once before all tests.
 * The function handles common setup tasks and saves the browser state to
 * in storageState file.
 */
export default function globalSetup(config: FullConfig): Promise<void>;
