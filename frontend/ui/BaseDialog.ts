/**
 * BaseDialog - Abstract base class for DevToolbar modal dialogs
 *
 * Provides shared modal lifecycle: show, hide, remove, close handlers (×, ESC, overlay click).
 * Subclasses implement createModal() and optionally override onClose() for cleanup.
 */

import { ShortcutManager } from '@zappzarapp/browser-utils/keyboard';

export abstract class BaseDialog {
  protected modal: HTMLElement | null = null;
  protected isOpen = false;
  private escKeyCleanup: (() => void) | null = null;

  /**
   * Close the dialog
   */
  close(): void {
    if (!this.isOpen) {
      return;
    }

    this.hideModal();
    this.removeModal();
    this.isOpen = false;
    this.onClose();
  }

  /**
   * Hook for subclasses to clean up on close
   */
  protected onClose(): void {}

  /**
   * Inject modal HTML into DOM and store reference
   */
  protected injectModal(html: string, overlayId: string): void {
    const container = document.createElement('div');
    container.innerHTML = html;
    const modalElement = container.firstElementChild;
    if (modalElement != null) {
      document.body.appendChild(modalElement);
    }
    this.modal = document.getElementById(overlayId);
  }

  /**
   * Show modal with fade-in animation
   */
  protected showModal(): void {
    if (this.modal != null) {
      this.modal.style.display = 'flex';
      // Trigger reflow for animation
      void this.modal.offsetHeight;
      this.modal.style.opacity = '1';
    }
  }

  /**
   * Attach standard close handlers: × button, ESC key, overlay click
   *
   * Call this from subclass attachModalHandlers() after adding dialog-specific handlers.
   */
  protected attachCloseHandlers(): void {
    if (this.modal == null) {
      return;
    }

    // Close button (×)
    const closeBtn = this.modal.querySelector('.dev-toolbar-modal-close');
    closeBtn?.addEventListener('click', () => this.close());

    // ESC key
    this.escKeyCleanup = ShortcutManager.onEscape(() => this.close());

    // Overlay click (close if clicked outside modal)
    this.modal.addEventListener('click', (e) => {
      if (e.target === this.modal) {
        this.close();
      }
    });
  }

  /**
   * Hide modal (fade out)
   */
  private hideModal(): void {
    if (this.modal != null) {
      this.modal.style.opacity = '0';
    }
  }

  /**
   * Remove modal from DOM with fade-out delay
   */
  private removeModal(): void {
    if (this.modal != null) {
      if (this.escKeyCleanup != null) {
        this.escKeyCleanup();
        this.escKeyCleanup = null;
      }
      setTimeout(() => {
        this.modal?.remove();
        this.modal = null;
      }, 200);
    }
  }
}
