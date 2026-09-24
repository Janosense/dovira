// Unit tests of the theme features' pure JS modules (docs/TESTING.md).
// Vitest reads this file instead of vite.config.js, so the build's plugins
// and .env loading never run in tests.
import { defineConfig } from 'vitest/config'
import { normalizePath } from 'vite'
import { join } from 'path'

export default defineConfig({
  test: {
    // Only pure modules are tested: no DOM library (docs/TECH-STACK.md → CONVENTIONS).
    environment: 'node',
    include: ['tests/js/**/*.test.js'],
    // A test that asserts nothing fails the run, as in the PHPUnit suites.
    expect: {
      requireAssertions: true,
    },
  },

  resolve: {
    alias: {
      '@scripts': normalizePath(join(__dirname, './source/scripts')),
    }
  }
})
