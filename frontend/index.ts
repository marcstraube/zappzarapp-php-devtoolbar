/**
 * DevToolbar Browser Entry Point
 *
 * Main entry point for the browser bundle.
 * Initializes DevToolbar when DOM is ready.
 */

import { DevToolbarUI } from './ui/DevToolbarUI';

/**
 * Initialize DevToolbar when DOM is ready
 */
function initDevToolbar(): void {
  const toolbar = new DevToolbarUI();
  toolbar.init();
}

// Auto-initialize on DOMContentLoaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDevToolbar);
} else {
  // DOM already loaded
  initDevToolbar();
}
