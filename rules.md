# General Guidelines & Preferences

This file documents the general rules and preferences for working on this project.

1.  **Provide Complete & Correct Code**: Always provide the full, complete code for any file that is being modified. The code must be a direct replacement for the entire file and should be free of fatal errors.

2.  **Eliminate IDE Warnings**: Code should be clean. If the IDE shows "yellow line" warnings for undefined functions (like `is_admin` or `redirect`), fix them by adding explicit `require_once` statements at the top of the file, even if it seems redundant. This improves code clarity and developer confidence.

3.  **Prioritize User Experience**:
    -   Background or long-running tasks (like fetching logs or testing a connection) should use AJAX to avoid page reloads and provide immediate feedback to the user (e.g., "Loading...", success/error messages).
    -   UI should be clean and uncluttered. The "old design" for action buttons (simple, side-by-side icons) is preferred.
    -   Data display should be user-friendly (e.g., show "Default Day Shift" instead of the ID "1").

4.  **Adhere to Existing Logic**:
    -   When adding new features, follow the existing patterns (e.g., using the `AttendanceService` for attendance logic, creating API endpoints in the `/api` directory for AJAX calls).
    -   Authentication checks (`is_admin()`, `is_authenticated()`, etc.) must be present on all restricted pages and API endpoints.