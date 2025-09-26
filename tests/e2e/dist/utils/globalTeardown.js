"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.default = globalTeardown;
const promises_1 = __importDefault(require("node:fs/promises"));
const storagePath_1 = require("./storagePath");
/**
 * Global teardown function that runs once after all tests complete.
 * Cleans up test artifacts like browser storage state files.
 */
async function globalTeardown() {
    const storageStatePath = (0, storagePath_1.getStorageStatePath)();
    try {
        // Attempt to remove the storage state file.
        await promises_1.default.unlink(storageStatePath);
    }
    catch (error) {
        // Ignore 'file not found' errors and log a warning for other errors.
        if (error?.code !== 'ENOENT') {
            console.warn(`[playwright] storageState cleanup failed, delete the storageState file manually. ` +
                `Error: ${error.message || error}`);
        }
    }
}
