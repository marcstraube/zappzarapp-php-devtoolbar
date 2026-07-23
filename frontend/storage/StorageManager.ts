/**
 * StorageManager - Handles localStorage persistence for DevToolbar
 *
 * Stores up to 50 lightweight metadata entries and 20 full request data entries.
 * Implements LRU eviction and automatic quota management.
 * Falls back to in-memory storage when localStorage is unavailable (private browsing).
 */

import type {
  RequestMetadata,
  RequestData,
  DevToolbarConfig,
  DevToolbarWindow,
  MinibarLabelType,
  BranchColors,
  KeyboardShortcut,
  PerformanceThresholds,
} from '../types/index.js';

import {
  MAX_METADATA,
  MAX_FULL_DATA,
  MIN_SAFE_ENTRIES,
  CONFIG_KEY,
  META_KEY,
  DATA_PREFIX,
  DEFAULT_MINIBAR_LABELS,
  DEFAULT_BRANCH_COLORS,
  DEFAULT_TOGGLE_SHORTCUT,
  DEFAULT_THRESHOLDS,
} from './StorageConfig.js';

import { debug, warn, error as logError } from '../utils/logger.js';

/**
 * In-memory storage fallback structure
 */
interface MemoryStore {
  meta: RequestMetadata[];
  requests: Record<string, RequestData>;
}

/**
 * StorageManager singleton class
 */
class StorageManagerClass {
  private useMemoryFallback = false;
  private memoryStore: MemoryStore = {
    meta: [],
    requests: {},
  };

  /**
   * Initialize storage and store current request
   */
  init(): void {
    // Test localStorage availability
    if (!this.isLocalStorageAvailable()) {
      warn('[DevToolbar] localStorage unavailable, using in-memory storage');
      this.useMemoryFallback = true;
    }

    // Store current request data
    const win = window as DevToolbarWindow;
    if (win.__DEV_TOOLBAR_DATA__ != null) {
      const { id, metadata, tabs, json_data } = win.__DEV_TOOLBAR_DATA__;
      this.storeRequest(id, metadata, tabs, json_data);
    }
  }

  /**
   * Check if localStorage is available
   * Returns false in private browsing mode or when disabled
   */
  isLocalStorageAvailable(): boolean {
    try {
      const testKey = '__devToolbarTest__';
      localStorage.setItem(testKey, '1');
      localStorage.removeItem(testKey);
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Store request with quota handling
   *
   * @param id Request ID
   * @param metadata Lightweight metadata
   * @param tabs Full tab HTML content
   * @param jsonData Optional JSON collector data for export
   */
  storeRequest(
    id: string,
    metadata: RequestMetadata,
    tabs: Record<string, string>,
    jsonData?: Record<string, unknown>
  ): void {
    debug('[StorageManager] Storing request:', id, 'useMemoryFallback:', this.useMemoryFallback);

    try {
      if (this.useMemoryFallback) {
        this.storeInMemory(id, metadata, tabs, jsonData);
        debug('[StorageManager] Stored in memory, total:', this.memoryStore.meta.length);
        return;
      }

      // Store metadata
      const metaArray = this.getMetadata();
      metaArray.unshift(metadata); // Add to beginning (newest first)

      // Enforce metadata limit
      if (metaArray.length > MAX_METADATA) {
        metaArray.length = MAX_METADATA;
      }

      localStorage.setItem(META_KEY, JSON.stringify(metaArray));

      // Store full data
      const fullData: RequestData = { id, metadata, tabs, json_data: jsonData };
      localStorage.setItem(DATA_PREFIX + id, JSON.stringify(fullData));

      // Enforce quota limits
      this.enforceQuotaLimits();
    } catch (e) {
      if ((e as Error).name === 'QuotaExceededError') {
        warn('[DevToolbar] Quota exceeded, evicting oldest entries');
        this.evictOldest();

        // Retry
        try {
          const metaArray = this.getMetadata();
          metaArray.unshift(metadata);
          if (metaArray.length > MAX_METADATA) {
            metaArray.length = MAX_METADATA;
          }
          localStorage.setItem(META_KEY, JSON.stringify(metaArray));
          localStorage.setItem(
            DATA_PREFIX + id,
            JSON.stringify({ id, metadata, tabs, json_data: jsonData })
          );
        } catch (retryError) {
          logError('[DevToolbar] Failed to store after eviction:', retryError);
        }
      } else {
        logError('[DevToolbar] Storage error:', e);
      }
    }
  }

  /**
   * Store in memory (private browsing fallback)
   */
  private storeInMemory(
    id: string,
    metadata: RequestMetadata,
    tabs: Record<string, string>,
    jsonData?: Record<string, unknown>
  ): void {
    this.memoryStore.meta.unshift(metadata);
    if (this.memoryStore.meta.length > MAX_METADATA) {
      this.memoryStore.meta.length = MAX_METADATA;
    }

    this.memoryStore.requests[id] = { id, metadata, tabs, json_data: jsonData };

    // Evict old full data
    const ids = this.memoryStore.meta.map((m) => m.id);
    const keysToKeep = ids.slice(0, MAX_FULL_DATA);

    for (const key in this.memoryStore.requests) {
      if (!keysToKeep.includes(key)) {
        delete this.memoryStore.requests[key];
      }
    }
  }

  /**
   * Retrieve full request data
   *
   * @param id Request ID
   * @return Request data or null
   */
  getRequest(id: string): RequestData | null {
    try {
      if (this.useMemoryFallback) {
        return this.memoryStore.requests[id] ?? null;
      }

      const data = localStorage.getItem(DATA_PREFIX + id);
      return data !== null ? (JSON.parse(data) as RequestData) : null;
    } catch (error) {
      logError('[DevToolbar] Failed to retrieve request:', error);
      return null;
    }
  }

  /**
   * Get all metadata entries
   *
   * @return Array of metadata objects
   */
  getMetadata(): RequestMetadata[] {
    try {
      if (this.useMemoryFallback) {
        return this.memoryStore.meta;
      }

      const data = localStorage.getItem(META_KEY);
      return data !== null ? (JSON.parse(data) as RequestMetadata[]) : [];
    } catch (error) {
      logError('[DevToolbar] Failed to retrieve metadata:', error);
      return [];
    }
  }

  /**
   * Enforce quota limits - keep only MAX_FULL_DATA entries
   */
  enforceQuotaLimits(): void {
    if (this.useMemoryFallback) {
      return;
    }

    const metaArray = this.getMetadata();
    const fullDataIds = metaArray.slice(0, MAX_FULL_DATA).map((m) => m.id);

    // Find all stored request keys
    const keysToDelete: string[] = [];
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key?.startsWith(DATA_PREFIX) === true) {
        const id = key.substring(DATA_PREFIX.length);
        if (!fullDataIds.includes(id)) {
          keysToDelete.push(key);
        }
      }
    }

    // Delete old entries
    keysToDelete.forEach((key) => {
      try {
        localStorage.removeItem(key);
      } catch (error) {
        logError('[DevToolbar] Failed to remove key:', key, error);
      }
    });

    if (keysToDelete.length > 0) {
      debug('[DevToolbar] Evicted', keysToDelete.length, 'old request entries');
    }
  }

  /**
   * Emergency eviction - delete oldest entries until under MIN_SAFE_ENTRIES
   */
  private evictOldest(): void {
    if (this.useMemoryFallback) {
      return;
    }

    const metaArray = this.getMetadata();

    // Keep only MIN_SAFE_ENTRIES newest metadata
    if (metaArray.length > MIN_SAFE_ENTRIES) {
      metaArray.length = MIN_SAFE_ENTRIES;
      localStorage.setItem(META_KEY, JSON.stringify(metaArray));
    }

    const idsToKeep = metaArray.slice(0, MIN_SAFE_ENTRIES).map((m) => m.id);

    // Delete all request data except the safe entries
    const keysToDelete: string[] = [];
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key?.startsWith(DATA_PREFIX) === true) {
        const id = key.substring(DATA_PREFIX.length);
        if (!idsToKeep.includes(id)) {
          keysToDelete.push(key);
        }
      }
    }

    keysToDelete.forEach((key) => {
      try {
        localStorage.removeItem(key);
      } catch (error) {
        logError('[DevToolbar] Failed to remove key during eviction:', key, error);
      }
    });

    debug('[DevToolbar] Emergency eviction: removed', keysToDelete.length, 'entries');
  }

  /**
   * Clear history data only (preserve settings and UI state)
   *
   * Removes:
   * - devToolbar.meta (metadata array)
   * - devToolbar.req_* (request data)
   *
   * Preserves:
   * - devToolbar.config (settings including minibarLabel, branchColors)
   * - devtoolbar_active_tab (current tab state)
   * - xdebug cookie (stored in document.cookie, not localStorage)
   */
  clear(): void {
    if (this.useMemoryFallback) {
      this.memoryStore = { meta: [], requests: {} };
      return;
    }

    try {
      // Remove metadata
      localStorage.removeItem(META_KEY);

      // Remove all request data (devToolbar.req_*)
      const keysToDelete: string[] = [];
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key?.startsWith(DATA_PREFIX) === true) {
          keysToDelete.push(key);
        }
      }

      keysToDelete.forEach((key) => localStorage.removeItem(key));
      debug('[DevToolbar] Cleared history data, preserved settings');
    } catch (error) {
      logError('[DevToolbar] Failed to clear data:', error);
    }
  }

  /**
   * Get config object
   */
  getConfig(): DevToolbarConfig {
    if (this.useMemoryFallback) {
      return {};
    }

    try {
      const config = localStorage.getItem(CONFIG_KEY);
      return config !== null ? (JSON.parse(config) as DevToolbarConfig) : {};
    } catch {
      return {};
    }
  }

  /**
   * Set config object
   */
  setConfig(config: DevToolbarConfig): void {
    if (this.useMemoryFallback) {
      return;
    }

    try {
      localStorage.setItem(CONFIG_KEY, JSON.stringify(config));
    } catch (error) {
      logError('[DevToolbar] Failed to save config:', error);
    }
  }

  /**
   * Get active minibar labels
   */
  getMinibarLabels(): MinibarLabelType[] {
    const config = this.getConfig();
    return config.minibarLabels ?? DEFAULT_MINIBAR_LABELS;
  }

  /**
   * Set active minibar labels
   */
  setMinibarLabels(labels: MinibarLabelType[]): void {
    const config = this.getConfig();
    this.setConfig({ ...config, minibarLabels: labels });
  }

  /**
   * Get branch color configuration
   */
  getBranchColors(): BranchColors {
    const config = this.getConfig();
    return config.branchColors ?? DEFAULT_BRANCH_COLORS;
  }

  /**
   * Set branch color configuration
   */
  setBranchColors(colors: BranchColors): void {
    const config = this.getConfig();
    this.setConfig({ ...config, branchColors: colors });
  }

  /**
   * Get keyboard shortcut for toggling toolbar
   */
  getToggleShortcut(): KeyboardShortcut {
    const config = this.getConfig();
    return config.toggleShortcut ?? DEFAULT_TOGGLE_SHORTCUT;
  }

  /**
   * Set keyboard shortcut for toggling toolbar
   */
  setToggleShortcut(shortcut: KeyboardShortcut): void {
    const config = this.getConfig();
    this.setConfig({ ...config, toggleShortcut: shortcut });
  }

  /**
   * Get performance alert thresholds (merged with defaults)
   */
  getThresholds(): Required<PerformanceThresholds> {
    const config = this.getConfig();
    return { ...DEFAULT_THRESHOLDS, ...config.thresholds };
  }

  /**
   * Set custom performance alert thresholds
   */
  setThresholds(thresholds: PerformanceThresholds): void {
    const config = this.getConfig();
    this.setConfig({ ...config, thresholds });
  }
}

// Export singleton instance
export const StorageManager = new StorageManagerClass();
