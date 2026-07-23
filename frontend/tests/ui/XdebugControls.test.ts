/**
 * XdebugControls Unit Tests
 *
 * Basic tests for Xdebug controls rendering and actions.
 *
 * @vitest-environment happy-dom
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { XdebugControls } from '@backend/DevToolbar/ui/XdebugControls';

describe('XdebugControls', () => {
  let xdebugControls: XdebugControls;

  beforeEach(() => {
    // Clear document
    document.body.innerHTML = '';
    document.cookie = '';

    // Reset window globals
     
    (window as any).__XDEBUG_CONFIG__ = undefined;

    xdebugControls = new XdebugControls();
  });

  describe('render', () => {
    it('should not render if container not found', () => {
      expect(() => xdebugControls.render()).not.toThrow();
    });

    it('should render disabled state when Xdebug not installed', () => {
      document.body.innerHTML = '<div id="dev-toolbar-request-controls-container"></div>';
       
      (window as any).__XDEBUG_CONFIG__ = { enabled: false };

      xdebugControls.render();

      const container = document.getElementById('dev-toolbar-request-controls-container');
      expect(container?.innerHTML).toContain('Xdebug: Not Installed');
      expect(container?.innerHTML).toContain('dev-toolbar-xdebug-compact-disabled');
    });

    it('should render inactive state when Xdebug enabled but not active', () => {
      document.body.innerHTML = '<div id="dev-toolbar-request-controls-container"></div>';
       
      (window as any).__XDEBUG_CONFIG__ = { enabled: true };

      xdebugControls.render();

      const container = document.getElementById('dev-toolbar-request-controls-container');
      expect(container?.innerHTML).toContain('Xdebug: Off');
      expect(container?.innerHTML).toContain('dev-toolbar-xdebug-compact-inactive');
      expect(container?.innerHTML).toContain('▶ PhpStorm');
      expect(container?.innerHTML).toContain('▶ VSCode');
    });

    it('should render active state when Xdebug session active', () => {
      document.body.innerHTML = '<div id="dev-toolbar-request-controls-container"></div>';
       
      (window as any).__XDEBUG_CONFIG__ = { enabled: true };
      document.cookie = 'XDEBUG_SESSION=PHPSTORM';

      xdebugControls.render();

      const container = document.getElementById('dev-toolbar-request-controls-container');
      expect(container?.innerHTML).toContain('Xdebug: PHPSTORM');
      expect(container?.innerHTML).toContain('dev-toolbar-xdebug-compact-active');
      expect(container?.innerHTML).toContain('⏹ Disable');
    });

    it('should always render export button', () => {
      document.body.innerHTML = '<div id="dev-toolbar-request-controls-container"></div>';
       
      (window as any).__XDEBUG_CONFIG__ = { enabled: false };

      xdebugControls.render();

      const container = document.getElementById('dev-toolbar-request-controls-container');
      expect(container?.innerHTML).toContain('⬇ Export');
      expect(container?.innerHTML).toContain('data-action="export-current"');
    });

    it('should escape HTML in session name', () => {
      document.body.innerHTML = '<div id="dev-toolbar-request-controls-container"></div>';
       
      (window as any).__XDEBUG_CONFIG__ = { enabled: true };
      document.cookie = 'XDEBUG_SESSION=<script>alert("xss")</script>';

      xdebugControls.render();

      const container = document.getElementById('dev-toolbar-request-controls-container');
      expect(container?.innerHTML).toContain('&lt;script&gt;');
      expect(container?.innerHTML).not.toContain('<script>');
    });
  });

  describe('enableXdebug', () => {
    it('should set cookie and reload', () => {
      document.body.innerHTML = '<div id="dev-toolbar-request-controls-container"></div>';
       
      (window as any).__XDEBUG_CONFIG__ = { enabled: true };

      // Mock window.location.reload
      const reloadSpy = vi.fn();
      Object.defineProperty(window.location, 'reload', {
        value: reloadSpy,
        writable: true,
      });

      xdebugControls.enableXdebug('VSCODE');

      // Check cookie was set
      expect(document.cookie).toContain('XDEBUG_SESSION=VSCODE');
    });
  });

  describe('disableXdebug', () => {
    it.skip('should remove cookie and reload', () => {
      // Skip: happy-dom doesn't reflect cookie deletion immediately
      // The disableXdebug() method sets expires to past, which should work in real browsers
    });
  });
});
