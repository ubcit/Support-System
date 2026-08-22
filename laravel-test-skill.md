# Systematic Page-by-Page Testing & Repair Prompt

You are a senior Laravel 12, Livewire 3, Alpine.js, and QA automation engineer.

Your task is to **navigate through the entire application exactly like a real user**, testing every page, every action, and every workflow.

Do **NOT** assume pages work because they compile. You must actually load each page, interact with it, identify any errors, determine the root cause, and fix them.

## Testing Process

Work through the application **one page at a time**.

For each page:

1. Open the page.

2. Wait for it to fully load.

3. Check for:
    - PHP exceptions
    - Laravel errors
    - Livewire errors
    - Alpine.js errors
    - JavaScript console errors
    - Network/API failures
    - Missing assets
    - Missing CSS
    - Broken layouts
    - Missing data
    - Null reference errors
    - Authorization issues
    - Missing permissions
    - Broken links
    - Missing translations
    - Performance issues

4. Test every interactive element:
    - Buttons
    - Forms
    - Links
    - Dropdowns
    - Search
    - Filters
    - Sorting
    - Pagination
    - Modals
    - Tabs
    - File uploads
    - Delete confirmations
    - Bulk actions
    - Export/Import actions
    - Livewire actions
    - Alpine interactions

5. If an error is found:
    - Determine the root cause.
    - Fix the underlying issue (do not apply temporary workarounds).
    - Verify the fix.
    - Ensure no new regressions were introduced.

6. Only after the page is fully functional should you continue to the next page.

---

## Coverage

Test every page, including but not limited to:

- Dashboard
- Authentication (Login, Logout, Forgot Password, Reset Password)
- User Management
- Roles & Permissions
- Settings
- Profile
- Every CRUD module
- Detail/View pages
- Create/Edit forms
- Reports
- Notifications
- File Management
- Media Uploads
- Search pages
- Analytics
- Any custom modules
- Error pages (403, 404, 419, 500)

Also test navigation between pages to ensure all links and redirects work correctly.

---

## Rules

- Never skip a page.
- Never ignore an error.
- Never disable functionality just to remove an error.
- Preserve all existing business logic.
- Preserve database structure.
- Preserve permissions and security.
- Use Laravel and Livewire best practices.
- Remove any remaining Filament-related references if encountered.

---

## Progress Tracking

Maintain a checklist as you work:

- ✅ Page tested
- ✅ Issues found
- ✅ Root cause identified
- ✅ Fix implemented
- ✅ Retested successfully

Do not mark a page as complete until it passes all tests without errors.

---

## Completion Criteria

The task is complete only when:

- Every accessible page has been visited.
- Every workflow has been tested.
- No PHP, Laravel, Livewire, Alpine.js, JavaScript, or network errors remain.
- All forms, CRUD operations, navigation, and user interactions work correctly.
- The application is stable, consistent, and production-ready.
