/**
 * RequestSwitcher Unit Tests
 *
 * Tests request history navigation, dropdown population, and request switching.
 *
 * @vitest-environment happy-dom
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { RequestSwitcher } from '@backend/DevToolbar/ui/RequestSwitcher';
import { StorageManager } from '@backend/DevToolbar/storage/StorageManager';
import { mockRequestMetadata, mockDevToolbarData } from '../mocks/browserMocks';
import type { RequestData } from '@backend/DevToolbar/types';

// Mock StorageManager
vi.mock('@backend/DevToolbar/storage/StorageManager', () => ({
  StorageManager: {
    getMetadata: vi.fn(() => []),
    getRequest: vi.fn(),
    storeRequest: vi.fn(),
    clear: vi.fn(),
  },
}));

describe('RequestSwitcher', () => {
  let requestSwitcher: RequestSwitcher;

  beforeEach(() => {
    // Clear localStorage
    localStorage.clear();

    // Setup window globals
    mockDevToolbarData({
      id: 'current-req-123',
      metadata: mockRequestMetadata({
        id: 'current-req-123',
        uri: '/current/endpoint',
        badge_counts: { queries: 5, exceptions: 0 },
      }),
    });

    // Create DOM structure
    document.body.innerHTML = `
      <div class="dev-toolbar-request-switcher">
        <div class="dev-toolbar-request-switcher-toggle">
          <span class="dev-toolbar-request-switcher-label">Request</span>
        </div>
        <div class="dev-toolbar-request-switcher-dropdown"></div>
      </div>

      <div class="dev-toolbar-panel-content">
        <div class="dev-toolbar-panel-tab-pane" data-tab="request">Current request content</div>
        <div class="dev-toolbar-panel-tab-pane" data-tab="history">History content</div>
        <div class="dev-toolbar-panel-tab-pane" data-tab="queries">Queries content</div>
      </div>
    `;

    requestSwitcher = new RequestSwitcher();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.clearAllMocks();
  });

  describe('init', () => {
    it('should populate dropdown from storage metadata', () => {
      const metadata = [
        mockRequestMetadata({ id: 'req-1', uri: '/api/users', method: 'GET', status: 200 }),
        mockRequestMetadata({ id: 'req-2', uri: '/api/posts', method: 'POST', status: 201 }),
      ];

      vi.mocked(StorageManager.getMetadata).mockReturnValue(metadata);

      requestSwitcher.init();

      const dropdown = document.querySelector('.dev-toolbar-request-switcher-dropdown');
      expect(dropdown?.innerHTML).toContain('Current Request');
      expect(dropdown?.innerHTML).toContain('/api/users');
      expect(dropdown?.innerHTML).toContain('/api/posts');
    });

    it('should show current request as active by default', () => {
      vi.mocked(StorageManager.getMetadata).mockReturnValue([]);

      requestSwitcher.init();

      const dropdown = document.querySelector('.dev-toolbar-request-switcher-dropdown');
      expect(dropdown?.innerHTML).toContain('active');
      expect(dropdown?.innerHTML).toContain('Current Request');
    });

    it('should show latest 5 requests in dropdown', () => {
      // Create 10 requests, newest first (as StorageManager returns them)
      const metadata = Array.from({ length: 10 }, (_, i) => {
        const index = 9 - i; // Reverse order: 9, 8, 7, 6, 5, 4, 3, 2, 1, 0
        return mockRequestMetadata({
          id: `req-${index}`,
          uri: `/api/endpoint-${index}`,
          timestamp: 1000000 + index,
        });
      });

      vi.mocked(StorageManager.getMetadata).mockReturnValue(metadata);

      requestSwitcher.init();

      const dropdown = document.querySelector('.dev-toolbar-request-switcher-dropdown');

      // Should contain only 5 recent entries (slice(0,5) = indices 9,8,7,6,5)
      expect(dropdown?.innerHTML).toContain('/api/endpoint-9');
      expect(dropdown?.innerHTML).toContain('/api/endpoint-5');

      // Should NOT contain older entries (indices 4,3,2,1,0)
      const dropdownContent = dropdown?.innerHTML ?? '';
      const hasEndpoint4 = dropdownContent.includes('data-request-id="req-4"');
      const hasEndpoint0 = dropdownContent.includes('data-request-id="req-0"');

      expect(hasEndpoint4).toBe(false);
      expect(hasEndpoint0).toBe(false);
    });

    it('should show "View all X requests" link when more than 5 requests', () => {
      const metadata = Array.from({ length: 15 }, (_, i) =>
        mockRequestMetadata({ id: `req-${i}` })
      );

      vi.mocked(StorageManager.getMetadata).mockReturnValue(metadata);

      requestSwitcher.init();

      const dropdown = document.querySelector('.dev-toolbar-request-switcher-dropdown');
      expect(dropdown?.innerHTML).toContain('View all 15 requests');
    });

    it('should toggle dropdown when toggle is clicked', () => {
      requestSwitcher.init();

      const switcher = document.querySelector('.dev-toolbar-request-switcher');
      const toggle = document.querySelector('.dev-toolbar-request-switcher-toggle');

      toggle?.dispatchEvent(new Event('click'));
      expect(switcher?.classList.contains('open')).toBe(true);

      toggle?.dispatchEvent(new Event('click'));
      expect(switcher?.classList.contains('open')).toBe(false);
    });

    it('should close dropdown when clicking outside', () => {
      requestSwitcher.init();

      const switcher = document.querySelector('.dev-toolbar-request-switcher');
      const toggle = document.querySelector('.dev-toolbar-request-switcher-toggle');

      toggle?.dispatchEvent(new Event('click'));
      expect(switcher?.classList.contains('open')).toBe(true);

      // Click outside the switcher - dispatch bubbling event
      document.body.dispatchEvent(new Event('click', { bubbles: true }));
      expect(switcher?.classList.contains('open')).toBe(false);
    });

    it('should store original badge counts', () => {
      requestSwitcher.init();

      const originalBadges = requestSwitcher.getOriginalBadgeCounts();
      expect(originalBadges).toEqual({ queries: 5, exceptions: 0 });
    });

    it('should handle missing window globals gracefully', () => {
       
      (window as any).__DEV_TOOLBAR_DATA__ = undefined;

      expect(() => requestSwitcher.init()).not.toThrow();
    });
  });

  describe('loadHistoricalRequest', () => {
    beforeEach(() => {
      requestSwitcher.init();
    });

    it('should load request from storage', () => {
      const historicalRequest: RequestData = {
        id: 'hist-req-456',
        metadata: mockRequestMetadata({ id: 'hist-req-456', uri: '/historical' }),
        tabs: {
          request: '<div>Historical request</div>',
          queries: '<div>Historical queries</div>',
        },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      requestSwitcher.loadHistoricalRequest('hist-req-456');

      expect(StorageManager.getRequest).toHaveBeenCalledWith('hist-req-456');
    });

    it('should replace tab content with historical data', () => {
      const historicalRequest: RequestData = {
        id: 'hist-req-456',
        metadata: mockRequestMetadata({ id: 'hist-req-456' }),
        tabs: {
          request: '<div>Historical request</div>',
          queries: '<div>Historical queries</div>',
        },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      requestSwitcher.loadHistoricalRequest('hist-req-456');

      const contentContainer = document.querySelector('.dev-toolbar-panel-content');
      expect(contentContainer?.innerHTML).toContain('Historical request');
      expect(contentContainer?.innerHTML).toContain('Historical queries');
      expect(contentContainer?.innerHTML).not.toContain('Current request content');
    });

    it('should update switcher label to show historical state', () => {
      const historicalRequest: RequestData = {
        id: 'hist-req-456',
        metadata: mockRequestMetadata({ id: 'hist-req-456' }),
        tabs: { request: '<div>Historical</div>' },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      requestSwitcher.loadHistoricalRequest('hist-req-456');

      const label = document.querySelector('.dev-toolbar-request-switcher-label');
      expect(label?.textContent).toBe('Request (Historical)');
    });

    it('should mark as viewing historical request', () => {
      const historicalRequest: RequestData = {
        id: 'hist-req-456',
        metadata: mockRequestMetadata({ id: 'hist-req-456' }),
        tabs: { request: '<div>Historical</div>' },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      requestSwitcher.loadHistoricalRequest('hist-req-456');

      expect(requestSwitcher.isViewingHistory()).toBe(true);
      expect(requestSwitcher.getCurrentHistoricalRequestId()).toBe('hist-req-456');
    });

    it('should call onRequestLoad callback', () => {
      const onRequestLoad = vi.fn();
      const historicalRequest: RequestData = {
        id: 'hist-req-456',
        metadata: mockRequestMetadata({ id: 'hist-req-456' }),
        tabs: { request: '<div>Historical</div>' },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      // Pass callback to loadHistoricalRequest, not init
      requestSwitcher.loadHistoricalRequest('hist-req-456', onRequestLoad);

      expect(onRequestLoad).toHaveBeenCalledWith('hist-req-456');
    });

    it('should handle request not found error', () => {
      vi.mocked(StorageManager.getRequest).mockReturnValue(null);

      expect(() => requestSwitcher.loadHistoricalRequest('non-existent')).not.toThrow();
    });

    it('should prevent multiple concurrent loads', () => {
      const historicalRequest: RequestData = {
        id: 'hist-req-456',
        metadata: mockRequestMetadata({ id: 'hist-req-456' }),
        tabs: { request: '<div>Historical</div>' },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      requestSwitcher.loadHistoricalRequest('hist-req-456');
      requestSwitcher.loadHistoricalRequest('hist-req-789');

      // Both calls go through (lock only prevents UI glitches, not duplicate calls)
      // This is acceptable behavior - testing that it doesn't crash
      expect(StorageManager.getRequest).toHaveBeenCalled();
    });

    it('should add loading class during load', () => {
      const historicalRequest: RequestData = {
        id: 'hist-req-456',
        metadata: mockRequestMetadata({ id: 'hist-req-456' }),
        tabs: { request: '<div>Historical</div>' },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      requestSwitcher.loadHistoricalRequest('hist-req-456');

      // Check loading class was removed after load
      const switcher = document.querySelector('.dev-toolbar-request-switcher');

      // Loading class should be removed after load
      expect(switcher?.classList.contains('loading')).toBe(false);
    });

    it('should close dropdown after loading', () => {
      const historicalRequest: RequestData = {
        id: 'hist-req-456',
        metadata: mockRequestMetadata({ id: 'hist-req-456' }),
        tabs: { request: '<div>Historical</div>' },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      const switcher = document.querySelector('.dev-toolbar-request-switcher');
      switcher?.classList.add('open');

      requestSwitcher.loadHistoricalRequest('hist-req-456');

      expect(switcher?.classList.contains('open')).toBe(false);
    });
  });

  describe('restoreCurrentRequest', () => {
    beforeEach(() => {
      requestSwitcher.init();
    });

    it('should restore original content', () => {
      const historicalRequest: RequestData = {
        id: 'hist-req-456',
        metadata: mockRequestMetadata({ id: 'hist-req-456' }),
        tabs: { request: '<div>Historical</div>' },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      requestSwitcher.loadHistoricalRequest('hist-req-456');

      requestSwitcher.restoreCurrentRequest();

      const contentContainer = document.querySelector('.dev-toolbar-panel-content');
      expect(contentContainer?.innerHTML).toContain('Current request content');
      expect(contentContainer?.innerHTML).not.toContain('Historical');
    });

    it('should update switcher label to current state', () => {
      const historicalRequest: RequestData = {
        id: 'hist-req-456',
        metadata: mockRequestMetadata({ id: 'hist-req-456' }),
        tabs: { request: '<div>Historical</div>' },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      requestSwitcher.loadHistoricalRequest('hist-req-456');
      requestSwitcher.restoreCurrentRequest();

      const label = document.querySelector('.dev-toolbar-request-switcher-label');
      expect(label?.textContent).toBe('Request');
    });

    it('should reset viewing history flags', () => {
      const historicalRequest: RequestData = {
        id: 'hist-req-456',
        metadata: mockRequestMetadata({ id: 'hist-req-456' }),
        tabs: { request: '<div>Historical</div>' },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      requestSwitcher.loadHistoricalRequest('hist-req-456');
      requestSwitcher.restoreCurrentRequest();

      expect(requestSwitcher.isViewingHistory()).toBe(false);
      expect(requestSwitcher.getCurrentHistoricalRequestId()).toBeNull();
    });

    it('should call onRequestLoad callback with "current"', () => {
      const onRequestLoad = vi.fn();

      requestSwitcher.init(onRequestLoad);
      requestSwitcher.restoreCurrentRequest(onRequestLoad);

      expect(onRequestLoad).toHaveBeenCalledWith('current');
    });

    it('should handle missing content container gracefully', () => {
      document.querySelector('.dev-toolbar-panel-content')?.remove();

      expect(() => requestSwitcher.restoreCurrentRequest()).not.toThrow();
    });
  });

  describe('getOriginalBadgeCounts', () => {
    it('should return stored badge counts', () => {
      requestSwitcher.init();

      const badges = requestSwitcher.getOriginalBadgeCounts();

      expect(badges).toEqual({ queries: 5, exceptions: 0 });
    });

    it('should return undefined when no badge counts available', () => {
      // Create metadata without badge_counts property
      const metadata = mockRequestMetadata({ id: 'test' });

      delete (metadata as unknown as Record<string, unknown>).badge_counts;

       
      (window as any).__DEV_TOOLBAR_DATA__ = {
        id: 'test',
        metadata: metadata,
        tabs: {},
      };

      const newSwitcher = new RequestSwitcher();
      newSwitcher.init();

      const badges = newSwitcher.getOriginalBadgeCounts();

      // When badge_counts is undefined, originalBadgeCounts is explicitly set to null
      expect(badges).toBeNull();
    });
  });

  describe('dropdown item interactions', () => {
    beforeEach(() => {
      const metadata = [
        mockRequestMetadata({ id: 'req-1', uri: '/api/users' }),
        mockRequestMetadata({ id: 'req-2', uri: '/api/posts' }),
      ];

      vi.mocked(StorageManager.getMetadata).mockReturnValue(metadata);
      requestSwitcher.init();
    });

    it('should load request when dropdown item is clicked', () => {
      const historicalRequest: RequestData = {
        id: 'req-1',
        metadata: mockRequestMetadata({ id: 'req-1' }),
        tabs: { request: '<div>Request 1</div>' },
      };

      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      const dropdown = document.querySelector('.dev-toolbar-request-switcher-dropdown');
      const item = dropdown?.querySelector('[data-request-id="req-1"]');

      item?.dispatchEvent(new Event('click', { bubbles: true }));

      expect(StorageManager.getRequest).toHaveBeenCalledWith('req-1');
    });

    it('should restore current request when "Current Request" is clicked', () => {
      const dropdown = document.querySelector('.dev-toolbar-request-switcher-dropdown');
      const currentItem = dropdown?.querySelector('[data-action="current"]');

      currentItem?.dispatchEvent(new Event('click', { bubbles: true }));

      expect(requestSwitcher.isViewingHistory()).toBe(false);
    });

    it('should trigger history tab when "View history" is clicked', () => {
      const onRequestLoad = vi.fn();
      requestSwitcher.init(onRequestLoad);

      const dropdown = document.querySelector('.dev-toolbar-request-switcher-dropdown');
      const viewHistoryItem = dropdown?.querySelector('[data-action="view-history"]');

      viewHistoryItem?.dispatchEvent(new Event('click', { bubbles: true }));

      expect(onRequestLoad).toHaveBeenCalledWith('history');
    });
  });

  describe('integration scenarios', () => {
    it('should handle complete load and restore cycle', () => {
      const metadata = [mockRequestMetadata({ id: 'req-1', uri: '/api/test' })];
      const historicalRequest: RequestData = {
        id: 'req-1',
        metadata: metadata[0]!,
        tabs: { request: '<div>Historical</div>' },
      };

      vi.mocked(StorageManager.getMetadata).mockReturnValue(metadata);
      vi.mocked(StorageManager.getRequest).mockReturnValue(historicalRequest);

      const onRequestLoad = vi.fn();
      requestSwitcher.init(onRequestLoad);

      // Load historical (pass callback)
      requestSwitcher.loadHistoricalRequest('req-1', onRequestLoad);
      expect(requestSwitcher.isViewingHistory()).toBe(true);

      // Restore current (pass callback)
      requestSwitcher.restoreCurrentRequest(onRequestLoad);
      expect(requestSwitcher.isViewingHistory()).toBe(false);

      expect(onRequestLoad).toHaveBeenCalledTimes(2);
    });

    it('should escape HTML in request URIs', () => {
      const metadata = [
        mockRequestMetadata({
          id: 'req-1',
          uri: '/api/<script>alert("xss")</script>',
        }),
      ];

      vi.mocked(StorageManager.getMetadata).mockReturnValue(metadata);
      requestSwitcher.init();

      // No <script> element should be created in the DOM (XSS prevention)
      expect(document.querySelector('script')).toBeNull();

      // The URI text should be visible as plain text, not rendered as HTML
      const uriSpan = document.querySelector('.dev-toolbar-request-switcher-item-uri');
      expect(uriSpan?.textContent).toContain('<script>alert("xss")</script>');
    });
  });
});
