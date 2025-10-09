import fs from 'node:fs/promises';
import { getStorageStatePath } from './storagePath';

/**
 * Global teardown function that runs once after all tests complete.
 * Cleans up test artifacts like browser storage state files.
 */
export default async function globalTeardown() {
  const storageStatePath = getStorageStatePath();
  await fs.rm(storageStatePath, { force: true });
}
