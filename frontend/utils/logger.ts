/**
 * Conditional Debug Logger for DevToolbar
 *
 * In development: All logs are shown
 * In production: Only warnings and errors are shown
 *
 * Uses @zappzarapp/browser-utils/logging under the hood.
 */

import { createLogger, LogLevel } from '@zappzarapp/browser-utils/logging';

export const { debug, info, warn, error } = createLogger({
  level: import.meta.env.DEV ? LogLevel.Debug : LogLevel.Warn,
});
