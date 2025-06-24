# Project Tasks and Roadmap

This file tracks the project's completed tasks and outlines future development plans.

## ✅ Completed Project Milestones

### **I. Core System & Setup**

-   **Application Bootstrap**: A central `bootstrap.php` is in place to handle configuration, database connections, and autoloading.
-   **Database Schema**: A complete database schema (`hr_portal.sql`) has been created, defining all core tables like `users`, `departments`, `shifts`, `devices`, `attendance_logs`, and tables for a full leave management system.
-   **Dependency Management**: `composer.json` is set up to manage PHP dependencies, including key libraries like `nesbot/carbon` for dates and `coding-libs/zkteco-php` for device communication.
-   **Error Handling**: A custom error handler is configured in `app/core/error_handler.php` to manage and log PHP errors.
-   **Admin User Creation**: A script (`create_admin.php`) exists to securely create the initial administrator account.

### **II. User and Access Control Management**

-   **Authentication**: A complete user login (`login.php`) and logout (`logout.php`) system is implemented with session management and password verification.
-   **Role-Based Access Control (RBAC)**: The system defines multiple user roles (`user`, `manager`, `hr`, `hr_manager`, `admin`) with specific permissions enforced by helper functions like `require_role()`.
-   **User Management UI (`admin/users.php`)**: A full CRUD interface allows admins to add, edit, and view users. This includes assigning roles, departments, shifts, and managers.
-   **Forced Password Change**: Functionality exists in `change_password.php` to require users to change their password upon first login or when flagged by an admin.
-   **Bulk User Import**: Admins can import users in bulk via a CSV file upload (`admin/import_users_csv.php`).

### **III. Device & Attendance Log Integration**

-   **Device Management (`admin/devices.php`)**: Admins have a full CRUD interface to manage ZKTeco attendance devices, including fields for IP address, port, and serial number.
-   **Live Device Status**: A "Test Connection" feature provides real-time online/offline status for devices using an AJAX call.
-   **Manual Log Fetching**: A "Fetch Logs" button allows admins to manually sync all attendance records from a device, which are then processed and stored in the database.
-   **Attendance Service (`app/core/services/AttendanceService.php`)**:
    -   Handles all business logic for processing raw punches from devices.
    -   Intelligently determines the punch state (In or Out) based on the user's previous punch.
    -   Includes validation to prevent duplicate punches and filter out rapid successive punches.
-   **Violation Engine (`app/core/services/AttendanceService.php`)**:
    -   Automatically calculates violations for "Late In", "Early Out", and "Overtime" based on the user's assigned shift rules.
    -   Correctly assigns a `'valid'` status to "Overtime" punches while flagging other violations as `'invalid'`.

### **IV. HR Administration**

-   **Department Management (`admin/departments.php`)**: Full CRUD functionality for creating, editing, and deleting company departments.
-   **Shift Management (`admin/shifts.php`)**: Full CRUD functionality for defining work shifts, including start/end times, grace periods, and night shift settings.
-   **Attendance Log Viewer (`admin/attendance_logs.php`)**:
    -   A comprehensive view of all attendance records.
    -   Server-side filtering by employee, date range, and status.
    -   Efficient pagination to handle a large number of log entries.
-   **Audit Trail (`admin/audit_logs.php`)**: The system logs key administrator actions, such as device updates and log fetching, for accountability.
-   **Leave Management (Foundation)**:
    -   CRUD interface for `Leave Types` is implemented in `admin/leave_management.php`.
    -   A page for viewing team vacation requests (`reports/manager_history.php`) is available for managers.
    -   Bulk operations API (`api/bulk_operations.php`) exists for resetting leave balances and performing annual accruals.

### **V. User Interface & Experience**

-   **Unified Look and Feel**: A consistent dark-themed UI is implemented across the portal using a custom stylesheet (`css/style.css`) and Bootstrap 5.
-   **AJAX-powered Features**: Key actions (testing devices, fetching logs, notifications) use JavaScript and API endpoints to provide a responsive user experience.
-   **Notifications**: A real-time notification system is in place (`js/main.js`, `api/get_notifications.php`) to alert users to events like leave request status changes.

## 🚀 Future Plans (Proposed Roadmap)

### **High Priority**
1.  **Dashboard Implementation**:
    -   Flesh out the main admin dashboard (`admin/index.php`) to be a true summary panel.
    -   Create and display widgets for key metrics: "Employees Present Today," "Employees on Leave," "Devices Online," and a feed of recent violations.
    -   Fully implement the `api/get_dashboard_stats.php` to provide live data for these widgets.

2.  **Reporting Module**:
    -   Create a dedicated "Reports" section in the navigation.
    -   Build out the **Timesheet Report** (`reports/timesheet.php`) to calculate and display total work hours, overtime, and a summary of violations for selected employees and date ranges.
    -   Implement the **Export to CSV** functionality for all reports, making `api/export_reports.php` fully operational.

3.  **Complete the Leave Management Workflow**:
    -   Build out the UI for employees to submit leave requests (`requests/create.php`) and view their request history (`requests/index.php`).
    -   Create a dedicated interface for managers and HR to review and approve/reject requests (`requests/view.php`), including adding comments.
    -   Implement the logic for automatically calculating and updating leave balances when requests are approved.
    -   Develop the Leave Calendar (`api/get_leave_calendar.php`) into a visual tool on the dashboard or a dedicated page.

### **Medium Priority**
4.  **Manual Attendance Correction**:
    -   Create a feature for Admins/HR to manually add a missing punch or edit an incorrect one directly from the `admin/attendance_logs.php` page.
    -   This would involve re-introducing a "Notes" column to the database and UI to require a reason for any manual change, improving the audit trail.

5.  **User Self-Service Portal**:
    -   Create a profile page where users can view their own attendance data and detailed leave balances without needing to contact HR.
    -   Ensure the `change_password.php` functionality is integrated into this profile page.

### **Low Priority**
6.  **Automated Log Fetching (Cron Job)**:
    -   Create a script that can be run on a schedule (e.g., every hour) to automatically fetch logs from all online devices. This would remove the need for manual fetching and ensure data is always up-to-date.