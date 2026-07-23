/**
 * RequestSwitcher - Manages request history navigation
 *
 * Handles the request switcher dropdown:
 * - Populating dropdown from localStorage metadata
 * - Loading historical requests
 * - Restoring current request
 * - Updating switcher label and highlighting
 */

import { StorageManager } from '../storage/StorageManager';
import { timeAgo } from '../utils/timeUtils';
import type { RequestData, DevToolbarWindow } from '../types';
import { debug, error as logError } from '../utils/logger.js';
import { HtmlEscaper } from '@zappzarapp/browser-utils/html';

/**
 * RequestSwitcher for navigating between current and historical requests
 */
export class RequestSwitcher {
  private isLoadingRequest: boolean = false;
  private isViewingHistoricalRequest: boolean = false;
  private currentHistoricalRequestId: string | null = null;
  private currentRequestData: string | null = null;
  private originalBadgeCounts: Record<string, number> | null = null;

  /**
   * Initialize request switcher
   *
   * @param onRequestLoad - Callback when request is loaded (for tab re-initialization)
   */
  init(onRequestLoad?: (requestId: string) => void): void {
    const switcher = document.querySelector('.dev-toolbar-request-switcher');
    if (!switcher) return;

    const toggle = switcher.querySelector('.dev-toolbar-request-switcher-toggle');
    const dropdown = switcher.querySelector('.dev-toolbar-request-switcher-dropdown');

    // Store current request data for restoration
    this.storeCurrentRequestData();

    // Store original badge counts
    if (typeof window !== 'undefined' && 'window' in globalThis) {
      const win = window as DevToolbarWindow;
      if (win.__DEV_TOOLBAR_DATA__?.metadata != null) {
        this.originalBadgeCounts = win.__DEV_TOOLBAR_DATA__.metadata.badge_counts ?? null;
        debug('[RequestSwitcher] Stored original badge counts:', this.originalBadgeCounts);
      }
    }

    // Populate dropdown from localStorage
    this.populateSwitcher();

    // Toggle dropdown
    toggle?.addEventListener('click', (e) => {
      e.stopPropagation();
      switcher.classList.toggle('open');
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!switcher.contains(e.target as Node)) {
        switcher.classList.remove('open');
      }
    });

    // Handle item clicks
    dropdown?.addEventListener('click', (e) => {
      const target = e.target as HTMLElement;
      const element = target.closest('.dev-toolbar-request-switcher-item');
      if (!(element instanceof HTMLElement)) return;

      debug('[RequestSwitcher] Item clicked:', element.dataset);

      // Handle special actions
      if (element.dataset.action === 'view-history') {
        debug('[RequestSwitcher] Opening HISTORY tab');
        if (onRequestLoad != null) {
          onRequestLoad('history');
        }
        switcher.classList.remove('open');
        return;
      }

      if (element.dataset.action === 'current') {
        switcher.classList.remove('open');
        if (this.isViewingHistoricalRequest) {
          this.restoreCurrentRequest(onRequestLoad);
        } else {
          debug('[RequestSwitcher] Already viewing current request');
        }
        return;
      }

      const requestId = element.dataset.requestId;
      if (requestId != null) {
        void this.loadHistoricalRequest(requestId, onRequestLoad);
      }
    });

    debug('[RequestSwitcher] Initialized');
  }

  /**
   * Check if currently viewing a historical request
   */
  isViewingHistory(): boolean {
    return this.isViewingHistoricalRequest;
  }

  /**
   * Get currently viewed historical request ID
   */
  getCurrentHistoricalRequestId(): string | null {
    return this.currentHistoricalRequestId;
  }

  /**
   * Populate switcher dropdown from localStorage
   */
  populateSwitcher(): void {
    const dropdown = document.querySelector('.dev-toolbar-request-switcher-dropdown');
    if (!dropdown) return;

    const metaArray = StorageManager.getMetadata();
    debug('[RequestSwitcher] Populating with', metaArray.length, 'requests');

    let html = '';

    // Current request (always first)
    const isCurrentActive = !this.isViewingHistoricalRequest;
    html += `<div class="dev-toolbar-request-switcher-item ${isCurrentActive ? 'active' : ''}" data-action="current">
            <span class="dev-toolbar-request-switcher-item-indicator">●</span>
            <span class="dev-toolbar-request-switcher-item-label">Current Request</span>
        </div>`;

    // Recent requests (latest 5)
    const recentRequests = metaArray.slice(0, 5);
    if (recentRequests.length > 0) {
      html += '<div class="dev-toolbar-request-switcher-separator">Recent</div>';
      recentRequests.forEach((meta) => {
        const isActive =
          this.isViewingHistoricalRequest && this.currentHistoricalRequestId === meta.id;
        const statusClass = this.getStatusClass(meta.status);
        const time = timeAgo(meta.timestamp);

        html += `<div class="dev-toolbar-request-switcher-item ${isActive ? 'active' : ''}" data-request-id="${HtmlEscaper.escape(meta.id)}">
                    <span class="dev-toolbar-request-switcher-item-method">${HtmlEscaper.escape(meta.method)}</span>
                    <span class="dev-toolbar-request-switcher-item-status status-${statusClass}">${HtmlEscaper.escape(String(meta.status))}</span>
                    <span class="dev-toolbar-request-switcher-item-uri" title="${HtmlEscaper.escape(meta.uri)}">${HtmlEscaper.escape(meta.uri)}</span>
                    <span class="dev-toolbar-request-switcher-item-time">${time}</span>
                </div>`;
      });
    }

    // View all history link
    if (metaArray.length > 5) {
      html += `<div class="dev-toolbar-request-switcher-item dev-toolbar-request-switcher-item-action" data-action="view-history">
                <span class="dev-toolbar-request-switcher-item-label">View all ${metaArray.length} requests →</span>
            </div>`;
    } else if (metaArray.length > 0) {
      html += `<div class="dev-toolbar-request-switcher-item dev-toolbar-request-switcher-item-action" data-action="view-history">
                <span class="dev-toolbar-request-switcher-item-label">View history →</span>
            </div>`;
    }

    dropdown.innerHTML = html;
  }

  /**
   * Load historical request from localStorage
   */
  loadHistoricalRequest(requestId: string, onRequestLoad?: (requestId: string) => void): void {
    if (this.isLoadingRequest) {
      debug('[RequestSwitcher] Already loading a request, ignoring');
      return;
    }

    this.isLoadingRequest = true;
    const switcher = document.querySelector('.dev-toolbar-request-switcher');
    switcher?.classList.add('loading');

    debug('[RequestSwitcher] Loading historical request:', requestId);

    try {
      const requestData = StorageManager.getRequest(requestId);

      if (requestData == null) {
        // Intentional throw for centralized error handling in catch block
        // noinspection ExceptionCaughtLocallyJS
        throw new Error('Request not found in localStorage');
      }

      // Reconstruct tabs HTML
      const tabsHtml = this.buildTabsHTML(requestData);

      // Replace tab content
      const contentContainer = document.querySelector('.dev-toolbar-panel-content');
      if (contentContainer == null) {
        // Intentional throw for centralized error handling in catch block
        // noinspection ExceptionCaughtLocallyJS
        throw new Error('Content container not found');
      }

      contentContainer.innerHTML = tabsHtml;

      // Mark as viewing historical request
      this.isViewingHistoricalRequest = true;
      this.currentHistoricalRequestId = requestId;

      // Re-populate dropdown to update highlighting
      this.populateSwitcher();

      // Update switcher label
      this.updateSwitcherLabel(requestId, true);

      // Notify callback
      if (onRequestLoad != null) {
        onRequestLoad(requestId);
      }

      debug('[RequestSwitcher] Successfully loaded historical request');
    } catch (error) {
      logError('[RequestSwitcher] Failed to load request:', error);
    } finally {
      this.isLoadingRequest = false;
      switcher?.classList.remove('loading');
      switcher?.classList.remove('open');
    }
  }

  /**
   * Restore current request
   */
  restoreCurrentRequest(onRequestLoad?: (requestId: string) => void): void {
    debug('[RequestSwitcher] Restoring current request');

    const contentContainer = document.querySelector('.dev-toolbar-panel-content');
    if (contentContainer == null || this.currentRequestData == null) {
      return;
    }

    // Restore original content
    contentContainer.innerHTML = this.currentRequestData;

    // Reset flags
    this.isViewingHistoricalRequest = false;
    this.currentHistoricalRequestId = null;

    // Re-populate dropdown
    this.populateSwitcher();

    // Update switcher label
    this.updateSwitcherLabel('current', false);

    // Notify callback
    if (onRequestLoad != null) {
      onRequestLoad('current');
    }

    debug('[RequestSwitcher] Restored current request');
  }

  /**
   * Store current request data for restoration
   */
  private storeCurrentRequestData(): void {
    const contentContainer = document.querySelector('.dev-toolbar-panel-content');
    if (contentContainer != null) {
      this.currentRequestData = contentContainer.innerHTML;
    }
  }

  /**
   * Build tabs HTML from request data
   */
  private buildTabsHTML(requestData: RequestData): string {
    let html = '';
    for (const [tabName, content] of Object.entries(requestData.tabs)) {
      html += `<div class="dev-toolbar-panel-tab-pane" data-tab="${tabName}">${content}</div>`;
    }
    return html;
  }

  /**
   * Update switcher label
   */
  private updateSwitcherLabel(requestId: string, isHistorical: boolean): void {
    const switcher = document.querySelector('.dev-toolbar-request-switcher');
    const toggle = switcher?.querySelector(
      '.dev-toolbar-request-switcher-toggle'
    ) as HTMLElement | null;
    if (toggle == null) return;

    toggle.dataset.current = requestId;

    const label = toggle.querySelector('.dev-toolbar-request-switcher-label');
    if (label != null) {
      label.textContent = isHistorical ? 'Request (Historical)' : 'Request';
      (label as HTMLElement).style.color = isHistorical ? '#f59e0b' : '';
    }
  }

  /**
   * Get status class for status code
   */
  private getStatusClass(status: number): string {
    if (status >= 200 && status < 300) return 'success';
    if (status >= 300 && status < 400) return 'redirect';
    if (status >= 400 && status < 500) return 'client-error';
    if (status >= 500) return 'server-error';
    return 'unknown';
  }

  /**
   * Get original badge counts
   */
  getOriginalBadgeCounts(): Record<string, number> | null {
    return this.originalBadgeCounts;
  }
}
