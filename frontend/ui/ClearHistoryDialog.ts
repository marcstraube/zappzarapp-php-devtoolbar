/**
 * ClearHistoryDialog - Confirmation dialog for clearing request history
 *
 * Shows a styled modal with:
 * - Warning about data deletion
 * - List of what will be deleted
 * - List of what will be preserved
 * - Confirm/Cancel actions
 */

import { BaseDialog } from './BaseDialog.js';

/**
 * ClearHistoryDialog for showing clear confirmation
 */
export class ClearHistoryDialog extends BaseDialog {
  private onConfirm: (() => void) | null = null;

  /**
   * Open dialog with confirmation callback
   */
  open(onConfirm: () => void): void {
    if (this.isOpen) {
      return;
    }

    this.onConfirm = onConfirm;
    this.createModal();
    this.showModal();
    this.attachModalHandlers();
    this.isOpen = true;
  }

  protected override onClose(): void {
    this.onConfirm = null;
  }

  /**
   * Create modal HTML structure
   */
  private createModal(): void {
    const modalHTML = `
            <div class="dev-toolbar-modal-overlay" id="dev-toolbar-clear-history-overlay">
                <div class="dev-toolbar-modal dev-toolbar-clear-history-modal">
                    <div class="dev-toolbar-modal-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 1.5rem;">🗑️</span>
                            <h3>Clear Request History</h3>
                        </div>
                        <button class="dev-toolbar-modal-close" title="Close">×</button>
                    </div>

                    <div class="dev-toolbar-modal-content">
                        <!-- Warning -->
                        <div class="dev-toolbar-clear-warning">
                            <strong>⚠️ Warning:</strong> This action cannot be undone.
                        </div>

                        <!-- What will be deleted -->
                        <div class="dev-toolbar-clear-section">
                            <h4>Will be deleted:</h4>
                            <ul class="dev-toolbar-clear-list">
                                <li>All request metadata (timestamps, URIs, methods)</li>
                                <li>All request details (queries, performance data)</li>
                                <li>History statistics and trends</li>
                            </ul>
                        </div>

                        <!-- What will be preserved -->
                        <div class="dev-toolbar-clear-section">
                            <h4>Will be preserved:</h4>
                            <ul class="dev-toolbar-clear-list dev-toolbar-clear-list-preserved">
                                <li>DevToolbar settings (minibar labels, colors)</li>
                                <li>Active tab selection</li>
                                <li>Xdebug cookie settings</li>
                            </ul>
                        </div>
                    </div>

                    <div class="dev-toolbar-modal-footer">
                        <button class="dev-toolbar-btn dev-toolbar-btn-secondary" id="clear-history-cancel">
                            Cancel
                        </button>
                        <button class="dev-toolbar-btn dev-toolbar-btn-danger" id="clear-history-confirm">
                            🗑️ Clear History
                        </button>
                    </div>
                </div>
            </div>
        `;

    this.injectModal(modalHTML, 'dev-toolbar-clear-history-overlay');
  }

  /**
   * Attach event handlers to modal
   */
  private attachModalHandlers(): void {
    if (this.modal == null) {
      return;
    }

    // Confirm button
    const confirmBtn = this.modal.querySelector('#clear-history-confirm');
    confirmBtn?.addEventListener('click', () => {
      if (this.onConfirm != null) {
        this.onConfirm();
      }
      this.close();
    });

    // Cancel button
    const cancelBtn = this.modal.querySelector('#clear-history-cancel');
    cancelBtn?.addEventListener('click', () => this.close());

    // Standard close handlers (×, ESC, overlay)
    this.attachCloseHandlers();
  }
}
