/**
 * DevToolbar Type Definitions
 *
 * TypeScript interfaces for DevToolbar data structures, window globals,
 * and storage models.
 */

/**
 * Request metadata stored for history navigation
 * Lightweight entries (up to 50 stored)
 *
 * Note: Field names match PHP's extractMetadata() output in DataInjectionRenderer
 */
export interface RequestMetadata {
  id: string;
  method: string;
  uri: string;
  status: number; // PHP: status_code
  timestamp: number; // Unix timestamp
  date: string; // Human-readable date (Y-m-d H:i:s)
  time: number; // PHP: execution_time (in ms)
  memory: number; // PHP: memory_peak (in bytes)
  query_count: number; // PHP: query count from queries collector
  badge_counts?: Record<string, number>; // PHP: badge counts per tab
}

/**
 * Full request data with tab content
 * Heavy entries (up to 20 stored)
 */
export interface RequestData {
  id: string;
  metadata: RequestMetadata;
  tabs: Record<string, string>; // Rendered HTML for UI display
  json_data?: Record<string, unknown>; // JSON collector data for export
}

/**
 * Minibar label display types
 */
export type MinibarLabelType = 'branding' | 'branch' | 'route' | 'request-id';

/**
 * Branch color configuration for git branches
 */
export interface BranchColors {
  feat: string; // Color for feature/* branches
  fix: string; // Color for fix/* branches
  hotfix: string; // Color for hotfix/* branches
  chore: string; // Color for chore/* branches
  default: string; // Color for other branches
}

/**
 * Keyboard shortcut configuration
 */
export interface KeyboardShortcut {
  key: string; // e.g., 'F12', 'D', 'Escape'
  ctrlKey?: boolean;
  shiftKey?: boolean;
  altKey?: boolean;
  metaKey?: boolean; // Cmd on Mac, Win on Windows
}

/**
 * Performance alert thresholds
 */
export interface PerformanceThresholds {
  time_ms?: number; // Request time (ms), default: 1000
  memory_mb?: number; // Memory peak (MB), default: 50
  query_count?: number; // Max queries, default: 50
  query_time_ms?: number; // Total query time (ms), default: 500
  http_count?: number; // Max HTTP requests, default: 10
  http_time_ms?: number; // Total HTTP time (ms), default: 1000
}

/**
 * DevToolbar configuration stored in localStorage
 */
export interface DevToolbarConfig {
  version?: string;
  minibarLabels?: MinibarLabelType[]; // Active minibar labels (can be multiple)
  branchColors?: BranchColors; // Git branch color scheme
  toggleShortcut?: KeyboardShortcut; // Keyboard shortcut to toggle toolbar
  thresholds?: PerformanceThresholds; // Performance alert thresholds
}

/**
 * Xdebug configuration injected by PHP
 */
export interface XdebugConfig {
  enabled: boolean;
  mode: string;
  idekey: string;
  client_host: string;
  client_port: number;
}

/**
 * Main DevToolbar data injected by PHP via window global
 */
export interface DevToolbarData {
  id: string;
  metadata: RequestMetadata;
  tabs: Record<string, string>;
  json_data?: Record<string, unknown>; // JSON collector data
}

/**
 * Extended Window interface with DevToolbar globals
 */
export interface DevToolbarWindow extends Window {
  __DEV_TOOLBAR_DATA__?: DevToolbarData;
  __XDEBUG_CONFIG__?: XdebugConfig;
}

/**
 * Storage entry format for in-memory fallback
 */
export interface StorageEntry {
  metadata: RequestMetadata;
  fullData?: RequestData;
}

/**
 * Type guard to check if window has DevToolbar data
 */
export function isDevToolbarWindow(win: Window): win is DevToolbarWindow {
  return '__DEV_TOOLBAR_DATA__' in win;
}

/**
 * Type guard to check if window has Xdebug config
 */
export function hasXdebugConfig(
  win: Window
): win is DevToolbarWindow & { __XDEBUG_CONFIG__: XdebugConfig } {
  return '__XDEBUG_CONFIG__' in win && win.__XDEBUG_CONFIG__ !== undefined;
}

/**
 * Tab names used in DevToolbar
 */
export type TabName = 'request' | 'database' | 'performance' | 'xdebug' | 'history';

/**
 * Event types for DevToolbar
 */
export type DevToolbarEventType = 'tab-change' | 'request-load' | 'storage-clear';
