/**
 * XdebugControls - Manages Xdebug debugging controls
 *
 * Renders and handles Xdebug controls in the Request tab:
 * - Display Xdebug status (enabled/disabled, active session)
 * - Enable/disable Xdebug debugging
 * - IDE-specific session activation (PhpStorm, VSCode)
 */

import type { XdebugConfig, DevToolbarWindow } from '../types';
import { debug } from '../utils/logger.js';
import { HtmlEscaper } from '@zappzarapp/browser-utils/html';

/**
 * XdebugControls singleton for managing Xdebug UI
 */
export class XdebugControls {
  private readonly CONTAINER_ID = 'dev-toolbar-request-controls-container';

  /**
   * Render Xdebug controls based on current state
   *
   * Reads configuration from window.__XDEBUG_CONFIG__ and cookies
   */
  render(): void {
    const container = document.getElementById(this.CONTAINER_ID);
    if (!container) {
      return; // Container not found (not on REQUEST tab)
    }

    // Get Xdebug configuration from server-injected data
    const xdebugConfig = this.getXdebugConfig();
    const xdebugEnabled = xdebugConfig.enabled;

    // Check current cookie status
    const { xdebugActive, xdebugSessionName } = this.getXdebugStatus();

    // Build and inject HTML
    container.innerHTML = this.buildControlsHTML(xdebugEnabled, xdebugActive, xdebugSessionName);

    debug('[Xdebug] Controls rendered, status:', xdebugActive ? 'active' : 'inactive');
  }

  /**
   * Get Xdebug configuration from window global
   */
  private getXdebugConfig(): XdebugConfig {
    if (typeof window !== 'undefined' && 'window' in globalThis) {
      const win = window as DevToolbarWindow;
      return (
        win.__XDEBUG_CONFIG__ ?? {
          enabled: false,
          mode: 'off',
          idekey: '',
          client_host: '',
          client_port: 0,
        }
      );
    }
    return {
      enabled: false,
      mode: 'off',
      idekey: '',
      client_host: '',
      client_port: 0,
    };
  }

  /**
   * Get Xdebug session status from cookies
   */
  private getXdebugStatus(): { xdebugActive: boolean; xdebugSessionName: string } {
    const cookies = this.parseCookies();
    const xdebugActive = 'XDEBUG_SESSION' in cookies;
    const xdebugSessionName = cookies['XDEBUG_SESSION'] ?? '';

    return { xdebugActive, xdebugSessionName };
  }

  /**
   * Parse document.cookie into key-value pairs
   */
  private parseCookies(): Record<string, string> {
    if (typeof document === 'undefined') {
      return {};
    }

    return document.cookie.split(';').reduce((acc: Record<string, string>, cookie) => {
      const [key, value] = cookie.trim().split('=');
      if (key != null && key !== '') {
        acc[key] = value ?? '';
      }
      return acc;
    }, {});
  }

  /**
   * Build controls HTML
   */
  private buildControlsHTML(
    xdebugEnabled: boolean,
    xdebugActive: boolean,
    xdebugSessionName: string
  ): string {
    const statusSection = this.buildStatusSection(xdebugEnabled, xdebugActive, xdebugSessionName);
    const actionsSection = this.buildActionsSection(xdebugEnabled, xdebugActive);

    return `${statusSection}${actionsSection}`;
  }

  /**
   * Build status section HTML
   */
  private buildStatusSection(
    xdebugEnabled: boolean,
    xdebugActive: boolean,
    xdebugSessionName: string
  ): string {
    if (!xdebugEnabled) {
      return `<div class="dev-toolbar-request-status">
                <div class="dev-toolbar-xdebug-compact dev-toolbar-xdebug-compact-disabled">
                  <span class="dev-toolbar-xdebug-label">Xdebug: Not Installed</span>
                </div>
              </div>`;
    }

    const statusClass = xdebugActive ? 'active' : 'inactive';
    const statusText = xdebugActive ? `Xdebug: ${xdebugSessionName}` : 'Xdebug: Off';
    const statusIcon = xdebugActive ? '●' : '○';

    return `<div class="dev-toolbar-request-status">
              <div class="dev-toolbar-xdebug-compact dev-toolbar-xdebug-compact-${statusClass}">
                <span class="dev-toolbar-xdebug-indicator">${statusIcon}</span>
                <span class="dev-toolbar-xdebug-label">${HtmlEscaper.escape(statusText)}</span>
              </div>
            </div>`;
  }

  /**
   * Build actions section HTML
   */
  private buildActionsSection(xdebugEnabled: boolean, xdebugActive: boolean): string {
    const xdebugButtons = this.buildXdebugButtons(xdebugEnabled, xdebugActive);

    return `<div class="dev-toolbar-request-actions">
              ${xdebugButtons}
              <button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="export-current" title="Export current request as JSON">
                ⬇ Export
              </button>
            </div>`;
  }

  /**
   * Build Xdebug control buttons
   */
  private buildXdebugButtons(xdebugEnabled: boolean, xdebugActive: boolean): string {
    if (!xdebugEnabled) {
      return '';
    }

    if (xdebugActive) {
      return `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-disable" title="Disable Xdebug step debugging">
                ⏹ Disable
              </button>`;
    }

    return `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="PHPSTORM" title="Enable Xdebug for PhpStorm">
              ▶ PhpStorm
            </button>
            <button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="VSCODE" title="Enable Xdebug for VSCode">
              ▶ VSCode
            </button>`;
  }

  /**
   * Enable Xdebug debugging for specific IDE
   *
   * @param ide - IDE identifier (PHPSTORM, VSCODE)
   */
  enableXdebug(ide: string): void {
    debug(`[Xdebug] Enabling Xdebug for ${ide}`);

    // Set cookie
    document.cookie = `XDEBUG_SESSION=${ide}; path=/; max-age=3600`;

    // Re-render controls to show updated state
    this.render();

    // Reload page to activate Xdebug
    window.location.reload();
  }

  /**
   * Disable Xdebug debugging
   */
  disableXdebug(): void {
    debug('[Xdebug] Disabling Xdebug');

    // Remove cookie
    document.cookie = 'XDEBUG_SESSION=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';

    // Re-render controls to show updated state
    this.render();

    // Reload page to deactivate Xdebug
    window.location.reload();
  }
}
