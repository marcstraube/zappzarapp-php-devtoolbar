/**
 * TabManager - Handles tab switching and active state
 *
 * Manages the DevToolbar tab navigation:
 * - Switching between tabs (request, history, queries, etc.)
 * - Persisting active tab to localStorage
 * - Updating tab button and pane active states
 */

import { debug, warn, error as logError } from '../utils/logger.js';

/**
 * TabManager singleton for managing DevToolbar tabs
 */
export class TabManager {
  private currentTab: string = 'request';
  private readonly STORAGE_KEY = 'devtoolbar_active_tab';

  /**
   * Initialize tab manager with optional saved tab
   */
  init(): void {
    // Restore last active tab from localStorage
    const savedTab = localStorage.getItem(this.STORAGE_KEY);
    if (savedTab != null) {
      this.currentTab = savedTab;
      debug('[DevToolbar] Restored active tab:', savedTab);
    }
  }

  /**
   * Get current active tab
   */
  getCurrentTab(): string {
    return this.currentTab;
  }

  /**
   * Set active tab and update UI
   *
   * @param tabName - Tab identifier (e.g., 'request', 'history', 'queries')
   * @param onActivate - Optional callback after tab activation
   */
  setActiveTab(tabName: string, onActivate?: (tabName: string) => void): void {
    this.currentTab = tabName;
    debug('[TabManager] Setting active tab:', tabName);

    // Save to localStorage for persistence across reloads
    localStorage.setItem(this.STORAGE_KEY, tabName);

    // Update tab buttons
    this.updateTabButtons(tabName);

    // Update tab panes
    const activated = this.updateTabPanes(tabName);

    if (!activated) {
      logError('[TabManager] Warning: No pane activated for tab:', tabName);
    }

    // Execute callback if provided
    if (onActivate != null) {
      onActivate(tabName);
    }
  }

  /**
   * Update tab button active states
   */
  private updateTabButtons(activeTabName: string): void {
    const tabButtons = document.querySelectorAll('.dev-toolbar-panel-tab');
    debug('[TabManager] Found tab buttons:', tabButtons.length);

    tabButtons.forEach((tab) => {
      const tabElement = tab as HTMLElement;
      if (tabElement.dataset.tab === activeTabName) {
        tabElement.classList.add('active');
        debug('[TabManager] Activated tab button:', activeTabName);
      } else {
        tabElement.classList.remove('active');
      }
    });
  }

  /**
   * Update tab pane active states
   *
   * @returns True if a pane was activated
   */
  private updateTabPanes(activeTabName: string): boolean {
    const panes = document.querySelectorAll('.dev-toolbar-panel-tab-pane');
    debug('[TabManager] Found panes:', panes.length);

    let activatedPane = false;
    panes.forEach((pane) => {
      const paneElement = pane as HTMLElement;
      const paneTab = paneElement.dataset.tab;

      if (paneTab === activeTabName) {
        paneElement.classList.add('active');
        activatedPane = true;
        debug('[TabManager] ✓ Activated pane for', activeTabName);
      } else {
        paneElement.classList.remove('active');
      }
    });

    return activatedPane;
  }

  /**
   * Update badge counts for tabs
   *
   * @param badgeCounts - Badge counts for each tab (e.g., {queries: 5, exceptions: 2})
   */
  updateBadgeCounts(badgeCounts: Record<string, number> | undefined): void {
    if (badgeCounts == null) {
      return;
    }

    debug('[TabManager] Updating badge counts:', badgeCounts);

    Object.entries(badgeCounts).forEach(([tabName, count]) => {
      const badge = document.querySelector(
        `.dev-toolbar-panel-tab[data-tab="${tabName}"] .dev-toolbar-panel-tab-badge`
      );

      if (badge != null) {
        badge.textContent = String(count);
        debug(`[TabManager] Updated ${tabName} badge to:`, count);
      }
    });
  }

  /**
   * Update history tab badge with count from metadata
   *
   * @param count - Number of history entries
   */
  updateHistoryBadge(count: number): void {
    const historyBadge = document.querySelector(
      '.dev-toolbar-panel-tab[data-tab="history"] .dev-toolbar-panel-tab-badge'
    );

    if (historyBadge != null) {
      historyBadge.textContent = String(count);
      debug('[TabManager] HISTORY badge updated to:', count);
    } else {
      warn('[TabManager] HISTORY badge element not found');
    }
  }
}
