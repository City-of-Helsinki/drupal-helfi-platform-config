"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.getStorageStatePath = void 0;
const node_path_1 = __importDefault(require("node:path"));
const node_os_1 = __importDefault(require("node:os"));
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
 * // Basic usage: const statePath = getStorageStatePath();
 * // Typical output: '/tmp/storageState.json' or similar
 */
const getStorageStatePath = () => {
    const dir = process.env.STORAGE_STATE_DIR ||
        process.env.XDG_RUNTIME_DIR ||
        process.env.TMPDIR ||
        node_os_1.default.tmpdir();
    return node_path_1.default.resolve(dir, 'storageState.json');
};
exports.getStorageStatePath = getStorageStatePath;
