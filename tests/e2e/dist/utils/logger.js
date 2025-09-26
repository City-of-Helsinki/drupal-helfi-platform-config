"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.logger = void 0;
/**
 * A conditional logger that only outputs logs when APP_DEBUG is set to 'TRUE'.
 *
 * @param message - The primary message to log
 * @param parameters - Optional additional parameters to log (supports any type)
 * @example
 * // Basic usage
 * logger('Test started');
 *
 * // With additional parameters
 * logger('User logged in:', { id: 123, name: 'Test User' });
 */
const logger = (message, ...parameters) => {
    // Only log messages if debug mode is explicitly enabled.
    if (process.env.APP_DEBUG === 'TRUE') {
        // Handle both simple messages and messages with additional parameters.
        if (parameters.length > 0) {
            console.log(message, ...parameters);
        }
        else {
            console.log(message);
        }
    }
};
exports.logger = logger;
