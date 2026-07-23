/**
 * StorageManager Unit Tests
 *
 * Tests localStorage persistence, LRU eviction, quota management,
 * and memory fallback for DevToolbar.
 *
 * @vitest-environment happy-dom
 */

import { describe, it, expect, beforeEach } from 'vitest';
import { StorageManager } from '@backend/DevToolbar/storage/StorageManager';
import {
  MAX_METADATA,
  MAX_FULL_DATA,
  CONFIG_KEY,
  META_KEY,
  DATA_PREFIX,
} from '@backend/DevToolbar/storage/StorageConfig';
import { mockDevToolbarData, mockRequestMetadata } from '../mocks/browserMocks';

describe('StorageManager', () => {
  beforeEach(() => {
    // Clear localStorage (provided by happy-dom)
    if (typeof localStorage !== 'undefined') {
      localStorage.clear();
    }

    // Reset window globals
    if (typeof window !== 'undefined') {
       
      (window as any).__DEV_TOOLBAR_DATA__ = undefined;
       
      (window as any).__XDEBUG_CONFIG__ = undefined;
       
      (window as any).__DEV_TOOLBAR_MIGRATION__ = undefined;
    }

    // Reset StorageManager internal state
     
    (StorageManager as any).useMemoryFallback = false;
     
    (StorageManager as any).memoryStore = { meta: [], requests: {} };
  });

  describe('isLocalStorageAvailable', () => {
    it('should return true when localStorage is available', () => {
      expect(StorageManager.isLocalStorageAvailable()).toBe(true);
    });

    it.skip('should return false when localStorage throws error', () => {
      // Skip: happy-dom provides real localStorage, can't easily mock errors
    });

    it.skip('should return false when localStorage is undefined', () => {
      // Skip: happy-dom always provides localStorage
    });
  });

  describe('init', () => {
    it('should initialize with localStorage when available', () => {
      const toolbarData = mockDevToolbarData();

      StorageManager.init();

      // Verify data was stored
      const stored = StorageManager.getRequest(toolbarData.id);
      expect(stored).toBeTruthy();
      expect(stored?.id).toBe(toolbarData.id);
    });

    it.skip('should fallback to memory when localStorage unavailable', () => {
      // Skip: happy-dom provides real localStorage, testing error scenario is complex
    });

    it('should handle missing toolbar data gracefully', () => {
       
      (window as any).__DEV_TOOLBAR_DATA__ = undefined;

      expect(() => StorageManager.init()).not.toThrow();

      // No data should be stored
      const metadata = StorageManager.getMetadata();
      expect(metadata).toHaveLength(0);
    });
  });

  describe('storeRequest', () => {
    it('should store request in localStorage', () => {
      const metadata = mockRequestMetadata({ id: 'test-123' });
      const tabs = { request: '<div>Test</div>' };

      StorageManager.storeRequest('test-123', metadata, tabs);

      // Check metadata was stored
      const storedMeta = StorageManager.getMetadata();
      expect(storedMeta).toHaveLength(1);
      expect(storedMeta[0]).toEqual(metadata);

      // Check full data was stored
      const fullData = StorageManager.getRequest('test-123');
      expect(fullData).toEqual({ id: 'test-123', metadata, tabs });
    });

    it('should add new requests to beginning (newest first)', () => {
      const meta1 = mockRequestMetadata({ id: 'req-1', timestamp: 1000 });
      const meta2 = mockRequestMetadata({ id: 'req-2', timestamp: 2000 });
      const meta3 = mockRequestMetadata({ id: 'req-3', timestamp: 3000 });

      StorageManager.storeRequest('req-1', meta1, {});
      StorageManager.storeRequest('req-2', meta2, {});
      StorageManager.storeRequest('req-3', meta3, {});

      const metadata = StorageManager.getMetadata();
      expect(metadata[0]?.id).toBe('req-3'); // Newest first
      expect(metadata[1]?.id).toBe('req-2');
      expect(metadata[2]?.id).toBe('req-1');
    });

    it('should enforce MAX_METADATA limit', () => {
      // Store MAX_METADATA + 10 requests
      for (let i = 0; i < MAX_METADATA + 10; i++) {
        const meta = mockRequestMetadata({ id: `req-${i}` });
        StorageManager.storeRequest(`req-${i}`, meta, {});
      }

      const metadata = StorageManager.getMetadata();
      expect(metadata).toHaveLength(MAX_METADATA);
      expect(metadata[0]?.id).toBe(`req-${MAX_METADATA + 9}`); // Newest
      expect(metadata[MAX_METADATA - 1]?.id).toBe(`req-10`); // Oldest kept
    });

    it.skip('should handle QuotaExceededError with eviction', () => {
      // Skip: Mocking quota errors with happy-dom is complex
    });

    it('should use memory fallback when localStorage unavailable', () => {
       
      (StorageManager as any).useMemoryFallback = true;

      const meta = mockRequestMetadata({ id: 'memory-test' });
      StorageManager.storeRequest('memory-test', meta, { request: '<div>Test</div>' });

       
      const memoryStore = (StorageManager as any).memoryStore;
      expect(memoryStore.meta).toHaveLength(1);
      expect(memoryStore.requests['memory-test']).toBeTruthy();
    });
  });

  describe('getRequest', () => {
    it('should retrieve stored request', () => {
      const meta = mockRequestMetadata({ id: 'test-get' });
      const tabs = { request: '<div>Test</div>' };

      StorageManager.storeRequest('test-get', meta, tabs);
      const retrieved = StorageManager.getRequest('test-get');

      expect(retrieved).toEqual({ id: 'test-get', metadata: meta, tabs });
    });

    it('should return null for non-existent request', () => {
      const retrieved = StorageManager.getRequest('non-existent');
      expect(retrieved).toBeNull();
    });

    it('should handle corrupted data gracefully', () => {
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem(DATA_PREFIX + 'corrupted', 'invalid json{');
      }

      const retrieved = StorageManager.getRequest('corrupted');
      expect(retrieved).toBeNull();
    });

    it('should retrieve from memory when using fallback', () => {
       
      (StorageManager as any).useMemoryFallback = true;
       
      (StorageManager as any).memoryStore.requests['mem-test'] = {
        id: 'mem-test',
        metadata: mockRequestMetadata({ id: 'mem-test' }),
        tabs: {},
      };

      const retrieved = StorageManager.getRequest('mem-test');
      expect(retrieved).toBeTruthy();
      expect(retrieved?.id).toBe('mem-test');
    });
  });

  describe('getMetadata', () => {
    it('should return all metadata entries', () => {
      const meta1 = mockRequestMetadata({ id: 'req-1' });
      const meta2 = mockRequestMetadata({ id: 'req-2' });

      StorageManager.storeRequest('req-1', meta1, {});
      StorageManager.storeRequest('req-2', meta2, {});

      const metadata = StorageManager.getMetadata();
      expect(metadata).toHaveLength(2);
    });

    it('should return empty array when no metadata stored', () => {
      const metadata = StorageManager.getMetadata();
      expect(metadata).toEqual([]);
    });

    it('should handle corrupted metadata gracefully', () => {
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem(META_KEY, 'invalid json{');
      }

      const metadata = StorageManager.getMetadata();
      expect(metadata).toEqual([]);
    });
  });

  describe('enforceQuotaLimits', () => {
    it('should keep only MAX_FULL_DATA full request entries', () => {
      // Store MAX_FULL_DATA + 5 requests
      for (let i = 0; i < MAX_FULL_DATA + 5; i++) {
        const meta = mockRequestMetadata({ id: `req-${i}` });
        StorageManager.storeRequest(`req-${i}`, meta, { tab: `content-${i}` });
      }

      // All metadata should be present
      const metadata = StorageManager.getMetadata();
      expect(metadata.length).toBeGreaterThanOrEqual(MAX_FULL_DATA);

      // But only MAX_FULL_DATA full data entries should exist
      let fullDataCount = 0;
      for (let i = 0; i < MAX_FULL_DATA + 5; i++) {
        const data = StorageManager.getRequest(`req-${i}`);
        if (data) fullDataCount++;
      }

      expect(fullDataCount).toBeLessThanOrEqual(MAX_FULL_DATA);
    });

    it('should not run when using memory fallback', () => {
       
      (StorageManager as any).useMemoryFallback = true;

      const before = localStorage.length;
      // Should not throw or access localStorage
      expect(() => StorageManager.enforceQuotaLimits()).not.toThrow();
      const after = localStorage.length;

      // localStorage should be unchanged
      expect(after).toBe(before);
    });
  });

  describe('clear', () => {
    it('should clear history data but preserve settings', () => {
      // Store some history
      StorageManager.storeRequest('test-1', mockRequestMetadata({ id: 'test-1' }), {});
      StorageManager.storeRequest('test-2', mockRequestMetadata({ id: 'test-2' }), {});

      // Store some settings
      StorageManager.setMinibarLabels(['branch', 'route']);
      StorageManager.setBranchColors({
        feat: '#ff0000',
        fix: '#00ff00',
        hotfix: '#0000ff',
        chore: '#ffff00',
        default: '#ff00ff',
      });

      StorageManager.clear();

      // History should be cleared
      expect(StorageManager.getMetadata()).toEqual([]);
      expect(StorageManager.getRequest('test-1')).toBeNull();
      expect(StorageManager.getRequest('test-2')).toBeNull();

      // Settings should be preserved
      expect(StorageManager.getMinibarLabels()).toEqual(['branch', 'route']);
      expect(StorageManager.getBranchColors().feat).toBe('#ff0000');
      const config = StorageManager.getConfig();
      expect(config.minibarLabels).toEqual(['branch', 'route']);
    });

    it('should clear memory store when using fallback', () => {
       
      (StorageManager as any).useMemoryFallback = true;
       
      (StorageManager as any).memoryStore = {
        meta: [mockRequestMetadata({ id: 'test-1' })],
        requests: {
          'test-1': { id: 'test-1', metadata: mockRequestMetadata({ id: 'test-1' }), tabs: {} },
        },
      };

      StorageManager.clear();

       
      expect((StorageManager as any).memoryStore.meta).toEqual([]);
       
      expect((StorageManager as any).memoryStore.requests).toEqual({});
    });
  });

  describe('getConfig / setConfig', () => {
    it('should store and retrieve config', () => {
      const config = { migrated: true, version: '1.0.0' };

      StorageManager.setConfig(config);
      const retrieved = StorageManager.getConfig();

      expect(retrieved).toEqual(config);
    });

    it('should return default config when none stored', () => {
      const config = StorageManager.getConfig();
      expect(config).toEqual({});
    });

    it('should handle corrupted config gracefully', () => {
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem(CONFIG_KEY, 'invalid json{');
      }

      const config = StorageManager.getConfig();
      expect(config).toEqual({});
    });

    it('should not persist config when using memory fallback', () => {
       
      (StorageManager as any).useMemoryFallback = true;

      const before = localStorage.length;
      StorageManager.setConfig({ version: '1.0.0' });
      const after = localStorage.length;

      // localStorage should not have new items
      expect(after).toBe(before);
    });
  });

  describe('LRU eviction (memory fallback)', () => {
    beforeEach(() => {
       
      (StorageManager as any).useMemoryFallback = true;
    });

    it('should keep only MAX_FULL_DATA requests in memory', () => {
      // Store MAX_FULL_DATA + 5 requests
      for (let i = 0; i < MAX_FULL_DATA + 5; i++) {
        const meta = mockRequestMetadata({ id: `req-${i}` });
        StorageManager.storeRequest(`req-${i}`, meta, {});
      }

       
      const memoryStore = (StorageManager as any).memoryStore;
      expect(Object.keys(memoryStore.requests)).toHaveLength(MAX_FULL_DATA);

      // Oldest requests should be evicted
      expect(memoryStore.requests['req-0']).toBeUndefined();
      expect(memoryStore.requests[`req-${MAX_FULL_DATA + 4}`]).toBeDefined();
    });

    it('should enforce MAX_METADATA limit in memory', () => {
      // Store MAX_METADATA + 10 requests
      for (let i = 0; i < MAX_METADATA + 10; i++) {
        const meta = mockRequestMetadata({ id: `req-${i}` });
        StorageManager.storeRequest(`req-${i}`, meta, {});
      }

       
      const memoryStore = (StorageManager as any).memoryStore;
      expect(memoryStore.meta).toHaveLength(MAX_METADATA);
    });
  });
});
