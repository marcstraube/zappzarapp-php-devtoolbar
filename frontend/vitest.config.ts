import { defineConfig } from 'vitest/config';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  resolve: {
    alias: {
      // Tests inherited from the main repo import via @backend/DevToolbar/...
      // In this package the modules live one level up from tests/.
      '@backend/DevToolbar': resolve(here, '.'),
    },
  },
  test: {
    environment: 'happy-dom',
    globals: true,
    include: ['tests/**/*.{test,spec}.ts'],
    exclude: ['node_modules/**', '../node_modules/**'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      reportsDirectory: './build/coverage',
      include: ['storage/**', 'ui/**', 'utils/**', 'types/**', 'index.ts'],
      exclude: [
        '**/*.test.ts',
        '**/*.spec.ts',
        'devtoolbar.build.ts',
        'global.d.ts',
        'tests/**',
      ],
    },
  },
});
