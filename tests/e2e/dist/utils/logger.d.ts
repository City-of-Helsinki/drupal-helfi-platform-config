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
declare const logger: (message: string, ...parameters: any[]) => void;
export { logger };
