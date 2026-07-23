/**
 * TabManager Unit Tests
 *
 * Basic tests for tab switching and state management.
 * DOM-heavy tests are minimal - focus on business logic.
 *
 * @vitest-environment happy-dom
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { TabManager } from '@backend/DevToolbar/ui/TabManager';

describe('TabManager', () => {
  let tabManager: TabManager;

  beforeEach(() => {
    // Clear localStorage
    localStorage.clear();

    // Create basic DOM structure
    document.body.innerHTML = `
            <div class="dev-toolbar-panel">
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
                    <div class="dev-toolbar-panel-tab-pane" data-tab="request">Request content</div>
                    <div class="dev-toolbar-panel-tab-pane" data-tab="history">History content</div>
                    <div class="dev-toolbar-panel-tab-pane" data-tab="queries">Queries content</div>
                </div>
            </div>
        `;

    tabManager = new TabManager();
  });

  describe('init', () => {
    it('should initialize with default tab', () => {
      tabManager.init();

      expect(tabManager.getCurrentTab()).toBe('request');
    });

    it('should restore saved tab from localStorage', () => {
      localStorage.setItem('devtoolbar_active_tab', 'history');

      tabManager.init();

      expect(tabManager.getCurrentTab()).toBe('history');
    });
  });

  describe('getCurrentTab', () => {
    it('should return current active tab', () => {
      tabManager.init();

      expect(tabManager.getCurrentTab()).toBe('request');
    });
  });

  describe('setActiveTab', () => {
    it('should change active tab', () => {
      tabManager.init();
      tabManager.setActiveTab('history');

      expect(tabManager.getCurrentTab()).toBe('history');
    });

    it('should persist tab to localStorage', () => {
      tabManager.init();
      tabManager.setActiveTab('queries');

      expect(localStorage.getItem('devtoolbar_active_tab')).toBe('queries');
    });

    it('should activate tab button in DOM', () => {
      tabManager.init();
      tabManager.setActiveTab('history');

      const historyTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="history"]');
      const requestTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="request"]');

      expect(historyTab?.classList.contains('active')).toBe(true);
      expect(requestTab?.classList.contains('active')).toBe(false);
    });

    it('should activate tab pane in DOM', () => {
      tabManager.init();
      tabManager.setActiveTab('queries');

      const queriesPane = document.querySelector('.dev-toolbar-panel-tab-pane[data-tab="queries"]');
      const requestPane = document.querySelector('.dev-toolbar-panel-tab-pane[data-tab="request"]');

      expect(queriesPane?.classList.contains('active')).toBe(true);
      expect(requestPane?.classList.contains('active')).toBe(false);
    });

    it('should call onActivate callback', () => {
      const callback = vi.fn();

      tabManager.init();
      tabManager.setActiveTab('history', callback);

      expect(callback).toHaveBeenCalledWith('history');
    });
  });

  describe('updateBadgeCounts', () => {
    it('should update badge counts', () => {
      tabManager.init();
      tabManager.updateBadgeCounts({ queries: 42, history: 10 });

      const queriesBadge = document.querySelector(
        '.dev-toolbar-panel-tab[data-tab="queries"] .dev-toolbar-panel-tab-badge'
      );
      const historyBadge = document.querySelector(
        '.dev-toolbar-panel-tab[data-tab="history"] .dev-toolbar-panel-tab-badge'
      );

      expect(queriesBadge?.textContent).toBe('42');
      expect(historyBadge?.textContent).toBe('10');
    });

    it('should handle empty badge counts', () => {
      tabManager.init();

      expect(() => tabManager.updateBadgeCounts({})).not.toThrow();
    });
  });

  describe('updateHistoryBadge', () => {
    it('should update history badge count', () => {
      tabManager.init();
      tabManager.updateHistoryBadge(25);

      const historyBadge = document.querySelector(
        '.dev-toolbar-panel-tab[data-tab="history"] .dev-toolbar-panel-tab-badge'
      );

      expect(historyBadge?.textContent).toBe('25');
    });

    it('should handle zero count', () => {
      tabManager.init();
      tabManager.updateHistoryBadge(0);

      const historyBadge = document.querySelector(
        '.dev-toolbar-panel-tab[data-tab="history"] .dev-toolbar-panel-tab-badge'
      );

      expect(historyBadge?.textContent).toBe('0');
    });

    it('should handle missing badge element gracefully', () => {
      tabManager.init();

      const badge = document.querySelector(
        '.dev-toolbar-panel-tab[data-tab="history"] .dev-toolbar-panel-tab-badge'
      );
      badge?.remove();

      expect(() => tabManager.updateHistoryBadge(10)).not.toThrow();
    });
  });

  describe('edge cases', () => {
    it('should handle missing tab elements gracefully', () => {
      document.body.innerHTML = '<div></div>';
      tabManager.init();

      expect(() => tabManager.setActiveTab('nonexistent')).not.toThrow();
    });

    it('should handle null badge counts', () => {
      tabManager.init();

       
      expect(() => tabManager.updateBadgeCounts(null as any)).not.toThrow();
    });

    it('should handle undefined badge counts', () => {
      tabManager.init();

       
      expect(() => tabManager.updateBadgeCounts(undefined as any)).not.toThrow();
    });
  });

  describe('integration', () => {
    it('should persist and restore tab across reloads', () => {
      tabManager.init();
      tabManager.setActiveTab('queries');

      // Simulate page reload
      const newTabManager = new TabManager();
      newTabManager.init();

      expect(newTabManager.getCurrentTab()).toBe('queries');
    });

    it('should update UI and storage atomically', () => {
      tabManager.init();
      tabManager.setActiveTab('history');

      // Check localStorage
      expect(localStorage.getItem('devtoolbar_active_tab')).toBe('history');

      // Check UI
      const historyTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="history"]');
      expect(historyTab?.classList.contains('active')).toBe(true);

      // Check internal state
      expect(tabManager.getCurrentTab()).toBe('history');
    });

    it('should handle rapid tab switches correctly', () => {
      tabManager.init();

      tabManager.setActiveTab('history');
      tabManager.setActiveTab('queries');
      tabManager.setActiveTab('request');

      expect(tabManager.getCurrentTab()).toBe('request');
      expect(localStorage.getItem('devtoolbar_active_tab')).toBe('request');

      const requestTab = document.querySelector('.dev-toolbar-panel-tab[data-tab="request"]');
      expect(requestTab?.classList.contains('active')).toBe(true);
    });
  });
});
