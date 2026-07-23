/**
 * SettingsManager - Manages DevToolbar Settings Modal
 *
 * Provides UI for configuring:
 * - Minibar label display mode (branding, branch, route, request-id)
 * - Git branch color scheme for different branch types
 * - Performance alert thresholds
 */

import type {
  MinibarLabelType,
  BranchColors,
  KeyboardShortcut,
  PerformanceThresholds,
} from '../types/index.js';
import { StorageManager } from '../storage/StorageManager.js';
import {
  DEFAULT_BRANCH_COLORS,
  DEFAULT_TOGGLE_SHORTCUT,
  DEFAULT_THRESHOLDS,
} from '../storage/StorageConfig.js';
import { debug, error as logError } from '../utils/logger.js';
import { HtmlEscaper } from '@zappzarapp/browser-utils/html';
import { BaseDialog } from './BaseDialog.js';

/**
 * SettingsManager singleton for managing settings UI
 */
export class SettingsManager extends BaseDialog {
  private currentShortcut: KeyboardShortcut | null = null;

  /**
   * Open settings modal
   */
  open(): void {
    if (this.isOpen) {
      return;
    }

    this.createModal();
    this.showModal();
    this.attachModalHandlers();
    this.isOpen = true;
  }

  /**
   * Create modal HTML structure
   */
  private createModal(): void {
    const currentLabels = StorageManager.getMinibarLabels();
    const currentColors = StorageManager.getBranchColors();
    const currentShortcut = StorageManager.getToggleShortcut();
    const currentThresholds = StorageManager.getThresholds();

    const modalHTML = `
            <div class="dev-toolbar-modal-overlay" id="dev-toolbar-settings-overlay">
                <div class="dev-toolbar-modal">
                    <div class="dev-toolbar-modal-header">
                        <h3>DevToolbar Settings</h3>
                        <button class="dev-toolbar-modal-close" title="Close">×</button>
                    </div>

                    <div class="dev-toolbar-modal-content">
                        <!-- Minibar Label Selection -->
                        <div class="dev-toolbar-settings-group">
                            <label>Minibar Labels (multiple selection)</label>
                            <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 0.875rem;">
                                Select which labels to display in the minibar (left to right order)
                            </p>
                            <div class="dev-toolbar-settings-checkboxes">
                                ${this.buildCheckboxOption('branding', '⚡ Branding', 'Show lightning bolt icon', currentLabels)}
                                ${this.buildCheckboxOption('branch', 'Git Branch', 'Show current git branch name', currentLabels)}
                                ${this.buildCheckboxOption('route', 'Current Route', 'Show HTTP method and URI', currentLabels)}
                                ${this.buildCheckboxOption('request-id', 'Request ID', 'Show unique request identifier', currentLabels)}
                            </div>
                        </div>

                        <!-- Branch Color Configuration -->
                        <div class="dev-toolbar-settings-group">
                            <label>Git Branch Colors</label>
                            <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 0.875rem;">
                                Customize colors for different branch types when using branch display mode
                            </p>
                            <div class="dev-toolbar-settings-colors">
                                ${this.buildColorInput('feat', 'feat/* branches', currentColors.feat)}
                                ${this.buildColorInput('fix', 'fix/* branches', currentColors.fix)}
                                ${this.buildColorInput('hotfix', 'hotfix/* branches', currentColors.hotfix)}
                                ${this.buildColorInput('chore', 'chore/* branches', currentColors.chore)}
                                ${this.buildColorInput('default', 'Other branches', currentColors.default)}
                            </div>
                        </div>

                        <!-- Keyboard Shortcut Configuration -->
                        <div class="dev-toolbar-settings-group">
                            <label>Toggle Keyboard Shortcut</label>
                            <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 0.875rem;">
                                Keyboard shortcut to open/close the DevToolbar. Click in the box and press your desired key combination.
                            </p>
                            <div class="dev-toolbar-settings-shortcut">
                                <input
                                    type="text"
                                    id="shortcut-input"
                                    readonly
                                    placeholder="Press keys..."
                                    value="${this.formatShortcut(currentShortcut)}"
                                    style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-family: monospace; background: #f9fafb; cursor: pointer;"
                                >
                                <p style="margin: 8px 0 0 0; color: #6b7280; font-size: 0.75rem;">
                                    Current: <strong>${this.formatShortcut(currentShortcut)}</strong> |
                                    <a href="#" id="reset-shortcut" style="color: #3b82f6; text-decoration: none;">Reset to Ctrl+Shift+D</a>
                                </p>
                            </div>
                        </div>

                        <!-- Performance Thresholds -->
                        <div class="dev-toolbar-settings-group">
                            <label>Performance Alert Thresholds</label>
                            <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 0.875rem;">
                                Alerts trigger when values exceed these thresholds
                            </p>
                            <div class="dev-toolbar-settings-thresholds">
                                ${this.buildThresholdInput('time_ms', 'Request Time', 'ms', currentThresholds.time_ms)}
                                ${this.buildThresholdInput('memory_mb', 'Memory Peak', 'MB', currentThresholds.memory_mb)}
                                ${this.buildThresholdInput('query_count', 'Query Count', '', currentThresholds.query_count)}
                                ${this.buildThresholdInput('query_time_ms', 'Query Time (total)', 'ms', currentThresholds.query_time_ms)}
                                ${this.buildThresholdInput('http_count', 'HTTP Requests', '', currentThresholds.http_count)}
                                ${this.buildThresholdInput('http_time_ms', 'HTTP Time (total)', 'ms', currentThresholds.http_time_ms)}
                            </div>
                            <p style="margin: 8px 0 0 0; color: #6b7280; font-size: 0.75rem;">
                                <a href="#" id="reset-thresholds" style="color: #3b82f6; text-decoration: none;">Reset to Defaults</a>
                            </p>
                        </div>
                    </div>

                    <div class="dev-toolbar-modal-footer">
                        <button class="dev-toolbar-btn dev-toolbar-btn-secondary" id="settings-cancel">
                            Cancel
                        </button>
                        <button class="dev-toolbar-btn dev-toolbar-btn-primary" id="settings-save">
                            Save & Reload
                        </button>
                    </div>
                </div>
            </div>
        `;

    this.injectModal(modalHTML, 'dev-toolbar-settings-overlay');
  }

  /**
   * Build checkbox option HTML
   */
  private buildCheckboxOption(
    value: string,
    title: string,
    description: string,
    currentValues: MinibarLabelType[]
  ): string {
    const checked = currentValues.includes(value as MinibarLabelType) ? 'checked' : '';

    return `
            <label class="dev-toolbar-settings-checkbox-item">
                <input type="checkbox" name="minibar-label" value="${HtmlEscaper.escape(value)}" ${checked}>
                <div>
                    <strong>${HtmlEscaper.escape(title)}</strong>
                    <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 0.875rem;">
                        ${HtmlEscaper.escape(description)}
                    </p>
                </div>
            </label>
        `;
  }

  /**
   * Build color input HTML
   */
  private buildColorInput(type: string, label: string, value: string): string {
    return `
            <div class="dev-toolbar-settings-color-item">
                <label for="color-${HtmlEscaper.escape(type)}">${HtmlEscaper.escape(label)}</label>
                <input type="color" id="color-${HtmlEscaper.escape(type)}" name="color-${HtmlEscaper.escape(type)}" value="${HtmlEscaper.escape(value)}">
            </div>
        `;
  }

  /**
   * Build threshold number input HTML
   */
  private buildThresholdInput(key: string, label: string, unit: string, value: number): string {
    const suffix =
      unit !== ''
        ? ` <span style="color: #6b7280; font-size: 0.75rem;">${HtmlEscaper.escape(unit)}</span>`
        : '';
    return `
            <div class="dev-toolbar-settings-threshold-item">
                <label for="threshold-${HtmlEscaper.escape(key)}">${HtmlEscaper.escape(label)}${suffix}</label>
                <input type="number" id="threshold-${HtmlEscaper.escape(key)}" name="threshold-${HtmlEscaper.escape(key)}" value="${value}" min="0" step="1"
                    class="dev-toolbar-settings-threshold-input">
            </div>
        `;
  }

  /**
   * Format shortcut for display
   */
  private formatShortcut(shortcut: KeyboardShortcut): string {
    const parts: string[] = [];
    if (shortcut.ctrlKey === true) parts.push('Ctrl');
    if (shortcut.shiftKey === true) parts.push('Shift');
    if (shortcut.altKey === true) parts.push('Alt');
    if (shortcut.metaKey === true) {
      parts.push(/Mac|iPhone|iPad/.test(navigator.userAgent) ? 'Cmd' : 'Win');
    }
    parts.push(shortcut.key);
    return parts.join('+');
  }

  /**
   * Attach event handlers to modal
   */
  private attachModalHandlers(): void {
    if (this.modal == null) {
      return;
    }

    // Initialize current shortcut from storage
    this.currentShortcut = StorageManager.getToggleShortcut();

    // Keyboard shortcut input
    const shortcutInput = this.modal.querySelector<HTMLInputElement>('#shortcut-input');
    shortcutInput?.addEventListener('keydown', (e) => {
      e.preventDefault();
      e.stopPropagation();

      // Ignore modifier-only keys
      if (['Control', 'Shift', 'Alt', 'Meta'].includes(e.key)) {
        return;
      }

      // Capture the shortcut
      this.currentShortcut = {
        key: e.key,
        ctrlKey: e.ctrlKey,
        shiftKey: e.shiftKey,
        altKey: e.altKey,
        metaKey: e.metaKey,
      };

      // Update input display
      shortcutInput.value = this.formatShortcut(this.currentShortcut);
      debug('[Settings] Captured shortcut:', this.currentShortcut);
    });

    // Reset shortcut link
    const resetLink = this.modal.querySelector('#reset-shortcut');
    resetLink?.addEventListener('click', (e) => {
      e.preventDefault();
      this.currentShortcut = { ...DEFAULT_TOGGLE_SHORTCUT };
      if (shortcutInput != null) {
        shortcutInput.value = this.formatShortcut(this.currentShortcut);
      }
    });

    // Reset thresholds link
    const resetThresholds = this.modal.querySelector('#reset-thresholds');
    resetThresholds?.addEventListener('click', (e) => {
      e.preventDefault();
      const keys = Object.keys(DEFAULT_THRESHOLDS) as (keyof Required<PerformanceThresholds>)[];
      for (const key of keys) {
        const input = this.modal?.querySelector<HTMLInputElement>(`#threshold-${key}`);
        if (input != null) {
          input.value = String(DEFAULT_THRESHOLDS[key]);
        }
      }
    });

    // Save button
    const saveBtn = this.modal.querySelector('#settings-save');
    saveBtn?.addEventListener('click', () => this.saveSettings());

    // Cancel button
    const cancelBtn = this.modal.querySelector('#settings-cancel');
    cancelBtn?.addEventListener('click', () => this.close());

    // Standard close handlers (×, ESC, overlay)
    this.attachCloseHandlers();
  }

  /**
   * Save settings and reload page
   */
  private saveSettings(): void {
    const selectedLabels = this.getSelectedLabels();
    const branchColors = this.getBranchColors();

    if (selectedLabels.length === 0) {
      logError('[Settings] No labels selected');
      return;
    }

    // Save to localStorage
    StorageManager.setMinibarLabels(selectedLabels);
    StorageManager.setBranchColors(branchColors);

    // Save keyboard shortcut
    if (this.currentShortcut != null) {
      StorageManager.setToggleShortcut(this.currentShortcut);
    }

    // Save thresholds
    const thresholds = this.getThresholdValues();
    StorageManager.setThresholds(thresholds);

    // Save to cookies so PHP can read the settings
    this.saveSettingsToCookies(selectedLabels, branchColors, thresholds);

    debug('[Settings] Saved:', { labels: selectedLabels, colors: branchColors, thresholds });

    // Reload page to apply changes (server-side rendering)
    window.location.reload();
  }

  /**
   * Save settings to cookies for PHP access
   */
  private saveSettingsToCookies(
    labels: MinibarLabelType[],
    colors: BranchColors,
    thresholds: PerformanceThresholds
  ): void {
    const labelsJson = JSON.stringify(labels);
    document.cookie = `devbar_labels=${encodeURIComponent(labelsJson)}; path=/; max-age=31536000`;

    const colorsJson = JSON.stringify(colors);
    document.cookie = `devbar_colors=${encodeURIComponent(colorsJson)}; path=/; max-age=31536000`;

    const thresholdsJson = JSON.stringify(thresholds);
    document.cookie = `devbar_thresholds=${encodeURIComponent(thresholdsJson)}; path=/; max-age=31536000`;

    debug('[Settings] Cookies set:', {
      labels: `devbar_labels=${encodeURIComponent(labelsJson)}`,
      colors: `devbar_colors=${encodeURIComponent(colorsJson)}`,
      thresholds: `devbar_thresholds=${encodeURIComponent(thresholdsJson)}`,
    });
  }

  /**
   * Get selected minibar labels from form
   */
  private getSelectedLabels(): MinibarLabelType[] {
    if (this.modal == null) {
      return [];
    }

    const selectedCheckboxes = this.modal.querySelectorAll<HTMLInputElement>(
      'input[name="minibar-label"]:checked'
    );

    const labels: MinibarLabelType[] = [];
    selectedCheckboxes.forEach((checkbox) => {
      labels.push(checkbox.value as MinibarLabelType);
    });

    return labels;
  }

  /**
   * Get branch colors from form
   */
  private getBranchColors(): BranchColors {
    return {
      feat: this.getColorValue('feat') ?? DEFAULT_BRANCH_COLORS.feat,
      fix: this.getColorValue('fix') ?? DEFAULT_BRANCH_COLORS.fix,
      hotfix: this.getColorValue('hotfix') ?? DEFAULT_BRANCH_COLORS.hotfix,
      chore: this.getColorValue('chore') ?? DEFAULT_BRANCH_COLORS.chore,
      default: this.getColorValue('default') ?? DEFAULT_BRANCH_COLORS.default,
    };
  }

  /**
   * Get color value from color input
   */
  private getColorValue(type: string): string | null {
    if (this.modal == null) {
      return null;
    }

    const input = this.modal.querySelector<HTMLInputElement>(`input[name="color-${type}"]`);
    return input?.value ?? null;
  }

  /**
   * Get threshold values from form
   */
  private getThresholdValues(): PerformanceThresholds {
    const keys = Object.keys(DEFAULT_THRESHOLDS) as (keyof Required<PerformanceThresholds>)[];
    const thresholds: PerformanceThresholds = {};

    for (const key of keys) {
      const input = this.modal?.querySelector<HTMLInputElement>(`#threshold-${key}`);
      if (input != null) {
        const value = parseInt(input.value, 10);
        if (!isNaN(value) && value >= 0) {
          thresholds[key] = value;
        }
      }
    }

    return thresholds;
  }
}
