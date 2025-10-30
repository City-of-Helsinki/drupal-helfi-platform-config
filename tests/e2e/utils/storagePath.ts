import os from 'node:os';
import path from 'node:path';

/**
 * Gets the filesystem path for storing Playwright's authentication state.
 *
 * This function determines the appropriate directory for storing the browser's
 * storage state (cookies, local storage) between test runs.
 * It checks for custom directories in the following order of priority:
 * 1. STORAGE_STATE_DIR - Custom directory from environment variables
 * 2. XDG_RUNTIME_DIR - XDG Base Directory specification
 * 3. TMPDIR - System temporary directory
 * 4. Fallback to OS-specific temporary directory
 *
 * @returns {string} Absolute path to storageState.json file
 *
 * @example
 *   Basic usage: const statePath = getStorageStatePath();
 *   Typical output: '/tmp/storageState.json' or similar
 */
export const getStorageStatePath = (): string => {
  const dir = process.env.STORAGE_STATE_DIR || process.env.XDG_RUNTIME_DIR || process.env.TMPDIR || os.tmpdir();
  return path.resolve(dir, 'storageState.json');
};
