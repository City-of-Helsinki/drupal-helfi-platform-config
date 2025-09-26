import fs from 'node:fs/promises';
import { getStorageStatePath } from './storagePath';

/**
 * Global teardown function that runs once after all tests complete.
 * Cleans up test artifacts like browser storage state files.
 */
export default async function globalTeardown() {
  const storageStatePath = getStorageStatePath();

  try {
    // Attempt to remove the storage state file.
    await fs.unlink(storageStatePath);
  } catch (error: any) {
    // Ignore 'file not found' errors and log a warning for other errors.
    if (error?.code !== 'ENOENT') {
      console.warn(
        `[playwright] storageState cleanup failed, delete the storageState file manually. ` +
        `Error: ${error.message || error}`
      );
    }
  }
}
