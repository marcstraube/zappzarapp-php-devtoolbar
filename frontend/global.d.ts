/**
 * Global type declarations for DevToolbar browser environment
 * Ensures TypeScript and ESLint recognize browser DOM APIs
 */

/// <reference lib="dom" />

// Re-export document and other DOM globals to ensure they're typed
declare const document: Document;
declare const window: Window & typeof globalThis;
declare const localStorage: Storage;

// Vite environment variables (injected at build time)
interface ImportMetaEnv {
  readonly DEV: boolean;
  readonly PROD: boolean;
  readonly MODE: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
