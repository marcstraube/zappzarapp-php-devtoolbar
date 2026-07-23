/**
 * MessageDialog - Generic message dialog for errors, warnings, and info
 *
 * Shows a styled modal with:
 * - Icon based on message type
 * - Title and message
 * - OK button to dismiss
 */

import { HtmlEscaper } from '@zappzarapp/browser-utils/html';
import { BaseDialog } from './BaseDialog.js';

type MessageType = 'error' | 'warning' | 'info' | 'success';

interface MessageDialogOptions {
  type?: MessageType;
  title?: string;
  message: string;
  okButtonText?: string;
}

/**
 * MessageDialog for showing messages to the user
 */
export class MessageDialog extends BaseDialog {
  /**
   * Open dialog with message
   */
  open(options: MessageDialogOptions): void {
    if (this.isOpen) {
      return;
    }

    this.createModal(options);
    this.showModal();
    this.attachModalHandlers();
    this.isOpen = true;
  }

  /**
   * Get icon and color for message type
   */
  private getTypeConfig(type: MessageType): { icon: string; color: string } {
    const configs = {
      error: { icon: '❌', color: '#ef4444' },
      warning: { icon: '⚠️', color: '#f59e0b' },
      info: { icon: 'ℹ️', color: '#3b82f6' },
      success: { icon: '✅', color: '#10b981' },
    };
    return configs[type];
  }

  /**
   * Create modal HTML structure
   */
  private createModal(options: MessageDialogOptions): void {
    const type = options.type ?? 'info';
    const title = options.title ?? this.getDefaultTitle(type);
    const okButtonText = options.okButtonText ?? 'OK';
    const { icon, color } = this.getTypeConfig(type);

    const modalHTML = `
            <div class="dev-toolbar-modal-overlay" id="dev-toolbar-message-overlay">
                <div class="dev-toolbar-modal dev-toolbar-message-modal">
                    <div class="dev-toolbar-modal-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 1.5rem;">${icon}</span>
                            <h3 style="color: ${color};">${HtmlEscaper.escape(title)}</h3>
                        </div>
                        <button class="dev-toolbar-modal-close" title="Close">×</button>
                    </div>

                    <div class="dev-toolbar-modal-content">
                        <p style="white-space: pre-wrap; margin: 0;">${HtmlEscaper.escape(options.message)}</p>
                    </div>

                    <div class="dev-toolbar-modal-footer">
                        <button class="dev-toolbar-btn dev-toolbar-btn-primary" id="message-dialog-ok">
                            ${HtmlEscaper.escape(okButtonText)}
                        </button>
                    </div>
                </div>
            </div>
        `;

    this.injectModal(modalHTML, 'dev-toolbar-message-overlay');
  }

  /**
   * Get default title for message type
   */
  private getDefaultTitle(type: MessageType): string {
    const titles = {
      error: 'Error',
      warning: 'Warning',
      info: 'Information',
      success: 'Success',
    };
    return titles[type];
  }

  /**
   * Attach event handlers to modal
   */
  private attachModalHandlers(): void {
    if (this.modal == null) {
      return;
    }

    // OK button
    const okBtn = this.modal.querySelector('#message-dialog-ok');
    okBtn?.addEventListener('click', () => this.close());

    // Standard close handlers (×, ESC, overlay)
    this.attachCloseHandlers();
  }
}

/**
 * Helper function to show a message dialog
 */
export function showMessage(options: MessageDialogOptions): void {
  const dialog = new MessageDialog();
  dialog.open(options);
}

/**
 * Helper function to show an error dialog
 */
export function showError(message: string, title?: string): void {
  showMessage({ type: 'error', title, message });
}
