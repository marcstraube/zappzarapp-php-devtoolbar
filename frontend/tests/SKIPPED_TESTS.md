# Skipped Tests - DevToolbar Unit Tests

## Overview

This document explains why certain tests are skipped in the DevToolbar unit test
suite and outlines the strategy for testing these scenarios.

## Test Environment: happy-dom

The DevToolbar unit tests use
[happy-dom](https://github.com/capricorn86/happy-dom) as the DOM implementation
for fast, Node.js-based testing. While happy-dom provides excellent performance
and broad DOM API coverage, it has some limitations that make certain edge cases
difficult to test in a pure unit test environment.

## Skipped Test Categories

### 1. Cookie Deletion Edge Cases

**File:** `ui/XdebugControls.test.ts:119`

**Test:** `should remove cookie and reload`

**Reason:** happy-dom doesn't reflect cookie deletion immediately when setting
`expires` to a past date. While this works correctly in real browsers, the test
environment doesn't accurately simulate this behavior.

**Coverage Strategy:**

- The production code is correct and follows browser standards
- Manual testing in real browsers confirms the functionality works
- Consider adding a browser-based integration test (Playwright/Puppeteer) for
  this scenario

---

### 2. localStorage Error Handling

**File:** `storage/StorageManager.test.ts`

These tests are skipped because happy-dom provides a fully functional
localStorage implementation, making it difficult to simulate error conditions
without complex mocking.

#### Test: `should return false when localStorage throws error` (Line 50)

**Scenario:** Testing behavior when localStorage operations throw exceptions

**Reason:** happy-dom's localStorage doesn't throw errors in normal operation.
Mocking would require overriding global objects in a way that could affect other
tests.

**Coverage Strategy:**

- Edge case rarely occurs in modern browsers
- Defensive code handles gracefully with try-catch blocks
- Manual testing can verify error handling with browser DevTools

#### Test: `should return false when localStorage is undefined` (Line 54)

**Scenario:** Testing behavior when localStorage API is not available

**Reason:** happy-dom always provides localStorage. Simulating absence would
require deleting global objects.

**Coverage Strategy:**

- Modern browsers consistently provide localStorage
- Code includes existence checks (`if (typeof localStorage !== 'undefined')`)
- Fallback to memory storage is tested separately

#### Test: `should fallback to memory when localStorage unavailable` (Line 71)

**Scenario:** Testing automatic fallback to in-memory storage

**Reason:** Cannot easily simulate localStorage unavailability without complex
mocking

**Coverage Strategy:**

- Fallback logic is simple and defensive
- Memory storage itself is fully tested
- Production monitoring can detect fallback usage

#### Test: `should handle QuotaExceededError with eviction` (Line 132)

**Scenario:** Testing behavior when localStorage quota is exceeded

**Reason:** happy-dom's localStorage doesn't enforce quota limits. Triggering
`QuotaExceededError` would require filling memory with massive data.

**Coverage Strategy:**

- Eviction algorithm is deterministic and can be tested in isolation
- Quota limits vary by browser (5-10MB typically)
- Production logging tracks eviction events

---

## Alternative Testing Strategies

### Manual Testing

All skipped scenarios should be manually tested during:

- Pre-release QA
- Browser compatibility testing
- DevToolbar feature demonstrations

### Browser-Based Integration Tests

Consider adding integration tests using:

- **Playwright** - Cross-browser testing with real browser engines
- **Puppeteer** - Chromium-based testing with full browser context

Example test locations for integration tests:

```text
tests/integration/DevToolbar/
├── cookies.spec.ts         # Cookie behavior including deletion
├── storage-limits.spec.ts  # localStorage quota handling
└── error-scenarios.spec.ts # Fallback mechanisms
```

### Production Monitoring

The DevToolbar includes logging for edge cases:

- Storage fallback activation
- Eviction events
- Cookie operation failures

Monitor production logs to detect if these scenarios occur in real usage.

---

## Test Philosophy

**Unit Tests:** Fast, focused tests that verify core logic with mocked
dependencies

**Integration Tests:** Browser-based tests that verify real-world behavior with
actual browser APIs

**Skipped Tests:** Document edge cases that are better suited for integration
testing or manual verification

---

## Maintaining This Document

When adding new skipped tests:

1. Add an entry to this document explaining why
2. Include the file path and line number
3. Describe the coverage strategy
4. Link to related integration tests if available

When fixing a skipped test:

1. Remove the `.skip` modifier
2. Update or remove the corresponding entry in this document
3. Document what changed that made the test possible
