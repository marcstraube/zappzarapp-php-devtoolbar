/**
 * DevToolbar Browser Bundle Build Script
 *
 * Builds TypeScript modules into a single IIFE bundle for browser injection.
 * Output: ../assets/devtoolbar.js (committed alongside the PHP package so
 * consumers don't need a Node toolchain).
 *
 * Usage (from package root):
 *   make frontend-build
 *
 * Or directly from the frontend/ directory:
 *   pnpm run build      # production (minified, no sourcemap)
 *   NODE_ENV=development pnpm run build   # dev (unminified + sourcemap)
 */

/// <reference types="node" />

import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Paths relative to this file's location
const entryPoint = join(__dirname, 'index.ts');
const outputFile = join(__dirname, '../assets/devtoolbar.js');

const isDev = process.env.NODE_ENV === 'development';

async function build(): Promise<void> {
   
  console.log('[DevToolbar Build] Starting browser bundle build...');
   
  console.log('[DevToolbar Build] Entry:', entryPoint);
   
  console.log('[DevToolbar Build] Output:', outputFile);
   
  console.log('[DevToolbar Build] Mode:', isDev ? 'development' : 'production');

  try {
    await esbuild.build({
      entryPoints: [entryPoint],
      bundle: true,
      format: 'iife',
      target: 'es2020',
      platform: 'browser',
      outfile: outputFile,
      minify: !isDev,
      sourcemap: isDev,
      logLevel: 'info',
      treeShaking: true,
      legalComments: 'none',
      define: {
        'import.meta.env.DEV': JSON.stringify(isDev),
      },
      banner: {
        js: '/* DevToolbar - Generated browser bundle - DO NOT EDIT MANUALLY */',
      },
    });

     
    console.log('[DevToolbar Build] ✓ Bundle created successfully');
  } catch (error) {
    console.error('[DevToolbar Build] ✗ Build failed:', error);
    process.exit(1);
  }
}

void build();
