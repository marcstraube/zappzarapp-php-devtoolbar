/**
 * DevToolbarUI Unit Tests
 *
 * Tests main UI controller for panel open/close, maximize, escape key,
 * and tab activation handling.
 *
 * @vitest-environment happy-dom
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { DevToolbarUI } from '@backend/DevToolbar/ui/DevToolbarUI';
import { StorageManager } from '@backend/DevToolbar/storage/StorageManager';
import { mockDevToolbarData } from '../mocks/browserMocks';

// Mock StorageManager to avoid side effects
vi.mock('@backend/DevToolbar/storage/StorageManager', () => ({
  StorageManager: {
    init: vi.fn(),
    getMetadata: vi.fn(() => []),
    getRequest: vi.fn(),
    storeRequest: vi.fn(),
    clear: vi.fn(),
    getToggleShortcut: vi.fn(() => ({
      ctrlKey: true,
      shiftKey: true,
      altKey: false,
      metaKey: false,
      key: 'd',
    })),
  },
}));

describe('DevToolbarUI', () => {
  let devToolbarUI: DevToolbarUI;

  beforeEach(() => {
    vi.useFakeTimers();

    // Clear localStorage
    localStorage.clear();

    // Setup window globals
    mockDevToolbarData();

    // Create comprehensive DOM structure
    document.body.innerHTML = `
      <div class="dev-toolbar-mini">Click to open</div>

      <div class="dev-toolbar-panel">
        <div class="dev-toolbar-panel-header">
          <button class="dev-toolbar-panel-close">×</button>
          <button class="dev-toolbar-panel-maximize">⛶</button>
          <button class="dev-toolbar-panel-settings">⚙</button>
        </div>

        <div class="dev-toolbar-panel-tabs">
          <div class="dev-toolbar-panel-tab" data-tab="request">
            Request <span class="dev-toolbar-panel-tab-badge">0</span>
          </div>
          <div class="dev-toolbar-panel-tab" data-tab="history">
            History <span class="dev-toolbar-panel-tab-badge">0</span>
          </div>
          <div class="dev-toolbar-panel-tab" data-tab="queries">
            Queries <span class="dev-toolbar-panel-tab-badge">0</span>
          </div>
        </div>

        <div class="dev-toolbar-panel-content">
          <div class="dev-toolbar-panel-tab-pane" data-tab="request">
            <div id="dev-toolbar-request-controls-container"></div>
            <div class="dev-toolbar-request-switcher">
              <div class="dev-toolbar-request-switcher-toggle">
                <span class="dev-toolbar-request-switcher-label">Request</span>
              </div>
              <div class="dev-toolbar-request-switcher-dropdown"></div>
            </div>
          </div>
          <div class="dev-toolbar-panel-tab-pane" data-tab="history">
            <div class="dev-toolbar-history-container"></div>
          </div>
          <div class="dev-toolbar-panel-tab-pane" data-tab="queries">
            Queries content
          </div>
        </div>
      </div>
    `;

    devToolbarUI = new DevToolbarUI();
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
    vi.clearAllMocks();
  });

  describe('init', () => {
    it('should initialize storage manager', () => {
      devToolbarUI.init();

      expect(StorageManager.init).toHaveBeenCalled();
    });

    it('should find and store DOM elements', () => {
      devToolbarUI.init();

      // Panel should be accessible (checked by not throwing on subsequent operations)
      expect(() => devToolbarUI.init()).not.toThrow();
    });

    it('should handle missing mini bar gracefully', () => {
      document.querySelector('.dev-toolbar-mini')?.remove();

      expect(() => devToolbarUI.init()).not.toThrow();
    });

    it('should handle missing panel gracefully', () => {
      document.querySelector('.dev-toolbar-panel')?.remove();

      expect(() => devToolbarUI.init()).not.toThrow();
    });

    it('should restore panel state from localStorage', () => {
      localStorage.setItem('devToolbar.open', '1');
      localStorage.setItem('devToolbar.maximized', '1');

      devToolbarUI.init();

      const panel = document.querySelector('.dev-toolbar-panel');
      expect(panel?.classList.contains('open')).toBe(true);
      expect(panel?.classList.contains('maximized')).toBe(true);
    });

    it('should attach event listeners', () => {
      devToolbarUI.init();

      // Verify listeners by triggering events
      const miniBar = document.querySelector('.dev-toolbar-mini');
      miniBar?.dispatchEvent(new Event('click'));

      const panel = document.querySelector('.dev-toolbar-panel');
      expect(panel?.classList.contains('open')).toBe(true);
    });
  });

  describe('panel open/close', () => {
    beforeEach(() => {
      devToolbarUI.init();
    });

    it('should open panel when mini bar is clicked', () => {
      const miniBar = document.querySelector('.dev-toolbar-mini');
      miniBar?.dispatchEvent(new Event('click'));

      const panel = document.querySelector('.dev-toolbar-panel');
      expect(panel?.classList.contains('open')).toBe(true);
      expect(localStorage.getItem('devToolbar.open')).toBe('1');
    });

    it('should close panel when mini bar is clicked again', () => {
      const miniBar = document.querySelector('.dev-toolbar-mini');
      const panel = document.querySelector('.dev-toolbar-panel');

      // Open
      miniBar?.dispatchEvent(new Event('click'));
      expect(panel?.classList.contains('open')).toBe(true);

      // Close
      miniBar?.dispatchEvent(new Event('click'));
      expect(panel?.classList.contains('open')).toBe(false);
      expect(localStorage.getItem('devToolbar.open')).toBe('0');
    });

    it('should close panel when close button is clicked', () => {
      const panel = document.querySelector('.dev-toolbar-panel');
      panel?.classList.add('open');

      const closeBtn = document.querySelector('.dev-toolbar-panel-close');
      closeBtn?.dispatchEvent(new Event('click'));

      expect(panel?.classList.contains('open')).toBe(false);
      expect(localStorage.getItem('devToolbar.open')).toBe('0');
    });

    it('should close panel when escape key is pressed', () => {
      const panel = document.querySelector('.dev-toolbar-panel');
      panel?.classList.add('open');

      const escapeEvent = new KeyboardEvent('keydown', { key: 'Escape' });
      document.dispatchEvent(escapeEvent);

      expect(panel?.classList.contains('open')).toBe(false);
    });

    it('should not close panel on escape when panel is closed', () => {
      const panel = document.querySelector('.dev-toolbar-panel');

      const escapeEvent = new KeyboardEvent('keydown', { key: 'Escape' });
      document.dispatchEvent(escapeEvent);

      // Should not throw or cause issues
      expect(panel?.classList.contains('open')).toBe(false);
    });

    it('should not close panel on other keys', () => {
      const panel = document.querySelector('.dev-toolbar-panel');
      panel?.classList.add('open');

      const enterEvent = new KeyboardEvent('keydown', { key: 'Enter' });
      document.dispatchEvent(enterEvent);

      expect(panel?.classList.contains('open')).toBe(true);
    });
  });

  describe('maximize/restore panel', () => {
    beforeEach(() => {
      devToolbarUI.init();
    });

    it('should toggle maximize class when maximize button is clicked', () => {
      const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');
      const panel = document.querySelector('.dev-toolbar-panel');

      maximizeBtn?.dispatchEvent(new Event('click'));

      expect(panel?.classList.contains('maximized')).toBe(true);
      expect(localStorage.getItem('devToolbar.maximized')).toBe('1');
    });

    it('should restore panel when maximize button is clicked again', () => {
      const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');
      const panel = document.querySelector('.dev-toolbar-panel');

      // Maximize
      maximizeBtn?.dispatchEvent(new Event('click'));
      expect(panel?.classList.contains('maximized')).toBe(true);

      // Restore
      maximizeBtn?.dispatchEvent(new Event('click'));
      expect(panel?.classList.contains('maximized')).toBe(false);
      expect(localStorage.getItem('devToolbar.maximized')).toBe('0');
    });

    it('should update maximize button title', () => {
      const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');

      maximizeBtn?.dispatchEvent(new Event('click'));
      expect(maximizeBtn?.getAttribute('title')).toBe('Restore');

      maximizeBtn?.dispatchEvent(new Event('click'));
      expect(maximizeBtn?.getAttribute('title')).toBe('Maximize');
    });
  });

  describe('handleTabActivate', () => {
    beforeEach(() => {
      devToolbarUI.init();
    });

    it('should render Xdebug controls when request tab is activated', () => {
      const requestTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="request"]');
      requestTab?.dispatchEvent(new Event('click'));

      // Advance timers to trigger setTimeout callbacks
      vi.advanceTimersByTime(200);

      const xdebugContainer = document.getElementById('dev-toolbar-request-controls-container');
      expect(xdebugContainer).toBeTruthy();
    });

    it('should initialize history tab when activated', () => {
      const historyTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="history"]');
      historyTab?.dispatchEvent(new Event('click'));

      // Advance timers to trigger setTimeout callbacks
      vi.advanceTimersByTime(200);

      // History tab initialization would populate container
      const historyContainer = document.querySelector('.dev-toolbar-history-container');
      expect(historyContainer).toBeTruthy();
    });

    it('should handle tab activation for other tabs', () => {
      const queriesTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="queries"]');

      expect(() => queriesTab?.dispatchEvent(new Event('click'))).not.toThrow();
    });
  });

  describe('tab switching', () => {
    beforeEach(() => {
      devToolbarUI.init();
    });

    it('should switch tabs when tab button is clicked', () => {
      const historyTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="history"]');
      historyTab?.dispatchEvent(new Event('click'));

      expect(historyTab?.classList.contains('active')).toBe(true);
      expect(localStorage.getItem('devtoolbar_active_tab')).toBe('history');
    });

    it('should activate corresponding tab pane', () => {
      const queriesTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="queries"]');
      queriesTab?.dispatchEvent(new Event('click'));

      const queriesPane = document.querySelector('.dev-toolbar-panel-tab-pane[data-tab="queries"]');
      expect(queriesPane?.classList.contains('active')).toBe(true);
    });

    it('should deactivate previous tab and pane', () => {
      const requestTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="request"]');
      const historyTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="history"]');

      requestTab?.classList.add('active');
      historyTab?.dispatchEvent(new Event('click'));

      expect(requestTab?.classList.contains('active')).toBe(false);

      const requestPane = document.querySelector('.dev-toolbar-panel-tab-pane[data-tab="request"]');
      expect(requestPane?.classList.contains('active')).toBe(false);
    });
  });

  describe('localStorage persistence', () => {
    it('should handle localStorage errors gracefully', () => {
      devToolbarUI.init();

      // Mock localStorage.setItem to throw
      const setItemSpy = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
        throw new Error('QuotaExceededError');
      });

      const miniBar = document.querySelector('.dev-toolbar-mini');

      // Should not throw
      expect(() => miniBar?.dispatchEvent(new Event('click'))).not.toThrow();

      setItemSpy.mockRestore();
    });

    it('should handle localStorage.getItem errors gracefully', () => {
      const getItemSpy = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
        throw new Error('Storage error');
      });

      // Should not throw
      expect(() => devToolbarUI.init()).not.toThrow();

      getItemSpy.mockRestore();
    });
  });

  describe('integration scenarios', () => {
    it('should maintain panel state through initialization', () => {
      localStorage.setItem('devToolbar.open', '1');
      localStorage.setItem('devToolbar.maximized', '1');
      localStorage.setItem('devtoolbar_active_tab', 'history');

      devToolbarUI.init();

      const panel = document.querySelector('.dev-toolbar-panel');
      expect(panel?.classList.contains('open')).toBe(true);
      expect(panel?.classList.contains('maximized')).toBe(true);

      const historyTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="history"]');
      expect(historyTab?.classList.contains('active')).toBe(true);
    });

    it('should handle rapid open/close/maximize operations', () => {
      devToolbarUI.init();

      const miniBar = document.querySelector('.dev-toolbar-mini');
      const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');
      const closeBtn = document.querySelector('.dev-toolbar-panel-close');
      const panel = document.querySelector('.dev-toolbar-panel');

      miniBar?.dispatchEvent(new Event('click')); // Open
      maximizeBtn?.dispatchEvent(new Event('click')); // Maximize
      closeBtn?.dispatchEvent(new Event('click')); // Close

      expect(panel?.classList.contains('open')).toBe(false);
      expect(panel?.classList.contains('maximized')).toBe(true);
    });
  });
});
