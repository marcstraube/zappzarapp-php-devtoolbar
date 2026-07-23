# DevToolbar - Manual Testing Guide

## Testing Modal Dialogs

The DevToolbar uses styled modal dialogs instead of browser `alert()` calls.
This guide explains how to test them manually.

### Prerequisites

1. Open your application in the browser
2. Open DevToolbar (click mini-bar)
3. Open Browser DevTools Console (F12 → Console tab)

---

## Test Scenarios

### 1. Test Error Modal - "No Request Data"

**What it tests:** Error dialog when toolbar data is missing

**Steps:**

1. Open Browser DevTools Console
2. Run: `delete window.__DEV_TOOLBAR_DATA__`
3. Click the "Export" button in the DevToolbar
4. **Expected:** Red error modal with title "Export Failed" and message "No
   request data available for export."

---

### 2. Test Error Modal - "Request Not Found"

**What it tests:** Error dialog when a historical request was deleted from
storage

**Steps:**

1. Open DevToolbar
2. Navigate to the "HISTORY" tab
3. Ensure there are some historical requests listed
4. Open Browser DevTools Console
5. Find a request ID and delete only that request:

   ```javascript
   // Get first request ID from metadata
   const meta = JSON.parse(localStorage.getItem('devToolbar.meta') || '[]');
   const requestId = meta[0]?.id;
   console.log('Deleting request:', requestId);
   // Delete the request data but keep metadata
   localStorage.removeItem('devToolbar.req_' + requestId);
   ```

6. Click the "Export" icon (📋) next to the first request in the history table
7. **Expected:** Red error modal with title "Export Failed" and message "Request
   not found in history."

---

### 3. Test Success - Normal Export

**What it tests:** Normal export flow without errors

**Steps:**

1. Open DevToolbar (fresh page load, don't delete anything)
2. Click the "Export" button in the main toolbar
3. **Expected:** Browser downloads a JSON file named
   `devtoolbar-request-{id}-{timestamp}.json`
4. No modal should appear (success case)

---

### 4. Test Modal Features

**What it tests:** Modal interaction features (ESC key, overlay click, close
button)

**Steps:**

1. Trigger any error modal (use Test 1 or Test 2)
2. **Test ESC key:** Press `Esc` key → Modal should close
3. Trigger the modal again
4. **Test overlay click:** Click outside the modal (on the dark overlay) → Modal
   should close
5. Trigger the modal again
6. **Test close button:** Click the `×` button in the top-right → Modal should
   close
7. Trigger the modal again
8. **Test OK button:** Click the "OK" button → Modal should close

---

## Modal Types

The `MessageDialog` component supports 4 types:

| Type      | Icon | Color | Usage                                    |
| --------- | ---- | ----- | ---------------------------------------- |
| `error`   | ❌   | Red   | Critical errors, failed operations       |
| `warning` | ⚠️   | Amber | Non-critical issues, deprecation notices |
| `info`    | ℹ️   | Blue  | Informational messages                   |
| `success` | ✅   | Green | Successful operations                    |

Currently used modals:

- **Error**: "No request data available" (DevToolbarUI)
- **Error**: "Request not found" (HistoryTabManager)

---

## Cleanup After Testing

After running tests that modify storage or window objects:

```javascript
// Reload the page to restore normal state
location.reload();
```

Or:

```javascript
// Clear all DevToolbar storage
localStorage.clear();
// Then reload
location.reload();
```

---

---

## Testing Keyboard Shortcuts

### Toggle DevToolbar with Keyboard Shortcut

**What it tests:** Configurable keyboard shortcut to open/close the DevToolbar

**Default Shortcut:** `Ctrl+Shift+D`

**Steps to test default:**

1. Ensure DevToolbar is closed
2. Press `Ctrl+Shift+D`
3. **Expected:** DevToolbar opens
4. Press `Ctrl+Shift+D` again
5. **Expected:** DevToolbar closes

**Steps to configure custom shortcut:**

1. Open DevToolbar
2. Click the ⚙️ Settings button (top-right)
3. Scroll to "Toggle Keyboard Shortcut" section
4. Click in the input field
5. Press your desired key combination (e.g., `Ctrl+Shift+D`)
6. **Expected:** Input shows your combination (e.g., "Ctrl+Shift+D")
7. Click "Save & Reload"
8. **Expected:** Page reloads
9. Press your new shortcut
10. **Expected:** DevToolbar toggles

**Reset to default:**

1. Open Settings
2. Click "Reset to Ctrl+Shift+D" link in the shortcut section
3. Save & Reload

**Notes:**

- Shortcuts work with any combination of modifiers (Ctrl, Shift, Alt, Cmd/Win) +
  a key
- The shortcut is saved to localStorage and persists across page loads
- ESC key still closes the toolbar (cannot be changed)

---

## Adding New Test Scenarios

When adding new modal dialogs:

1. Document the test scenario in this file
2. Include:
   - What it tests
   - Steps to trigger
   - Expected modal type and message
3. Update the "Modal Types" table if using a new type
