/**
 * Browser Environment Mocks for DevToolbar Tests
 *
 * Provides localStorage, window globals, and DOM mocks for unit testing.
 */

import { vi } from 'vitest';
import type { DevToolbarData, XdebugConfig, RequestMetadata } from '@backend/DevToolbar/types';

/**
 * Mock localStorage implementation
 */
export function mockLocalStorage() {
  const storage: Record<string, string> = {};

  const localStorageMock = {
    getItem: vi.fn((key: string) => storage[key] ?? null),
    setItem: vi.fn((key: string, value: string) => {
      storage[key] = value;
    }),
    removeItem: vi.fn((key: string) => {
      delete storage[key];
    }),
    clear: vi.fn(() => {
      Object.keys(storage).forEach((key) => delete storage[key]);
    }),
    get length() {
      return Object.keys(storage).length;
    },
    key: vi.fn((index: number) => {
      const keys = Object.keys(storage);
      return keys[index] ?? null;
    }),
  };

  vi.stubGlobal('localStorage', localStorageMock);

  return {
    storage,
    mock: localStorageMock,
    reset: () => {
      Object.keys(storage).forEach((key) => delete storage[key]);
      vi.clearAllMocks();
    },
  };
}

/**
 * Mock window.__DEV_TOOLBAR_DATA__ global
 */
export function mockDevToolbarData(data?: Partial<DevToolbarData>): DevToolbarData {
  const mockData: DevToolbarData = {
    id: data?.id ?? 'test-request-123',
    metadata: data?.metadata ?? mockRequestMetadata(),
    tabs: data?.tabs ?? {
      request: '<div>Request info</div>',
      database: '<div>Database queries</div>',
      performance: '<div>Performance metrics</div>',
    },
  };

  vi.stubGlobal('__DEV_TOOLBAR_DATA__', mockData);

  return mockData;
}

/**
 * Mock window.__XDEBUG_CONFIG__ global
 */
export function mockXdebugConfig(config?: Partial<XdebugConfig>): XdebugConfig {
  const mockConfig: XdebugConfig = {
    enabled: config?.enabled ?? false,
    mode: config?.mode ?? 'off',
    idekey: config?.idekey ?? 'PHPSTORM',
    client_host: config?.client_host ?? '172.17.0.1',
    client_port: config?.client_port ?? 9003,
  };

  vi.stubGlobal('__XDEBUG_CONFIG__', mockConfig);

  return mockConfig;
}

/**
 * Create mock request metadata
 */
export function mockRequestMetadata(overrides?: Partial<RequestMetadata>): RequestMetadata {
  const timestamp = overrides?.timestamp ?? Math.floor(Date.now() / 1000);
  const date =
    overrides?.date ?? new Date(timestamp * 1000).toISOString().slice(0, 19).replace('T', ' ');

  return {
    id: overrides?.id ?? 'test-request-123',
    method: overrides?.method ?? 'GET',
    uri: overrides?.uri ?? '/test/endpoint',
    status: overrides?.status ?? 200,
    timestamp,
    date,
    time: overrides?.time ?? 123.45,
    memory: overrides?.memory ?? 1024000,
    query_count: overrides?.query_count ?? 5,
    badge_counts: overrides?.badge_counts ?? {},
  };
}

/**
 * Mock DOM element
 */
export function mockElement(tagName: string = 'div', attributes: Record<string, string> = {}) {
  const element = document.createElement(tagName);
  Object.entries(attributes).forEach(([key, value]) => {
    element.setAttribute(key, value);
  });
  return element;
}

/**
 * Reset all window globals
 */
export function resetWindowGlobals() {
  vi.unstubAllGlobals();
}

/**
 * Setup complete test environment
 */
export function setupTestEnvironment() {
  const storage = mockLocalStorage();
  const toolbarData = mockDevToolbarData();
  const xdebugConfig = mockXdebugConfig();

  return {
    storage,
    toolbarData,
    xdebugConfig,
    cleanup: () => {
      storage.reset();
      resetWindowGlobals();
    },
  };
}
