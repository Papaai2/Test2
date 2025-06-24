# Project Overview: HR Portal

## Project Goal
The primary goal of this project is to build a comprehensive, web-based HR Portal for managing employees and their attendance. Key functionalities include:
-   **User Management**: Administering user accounts with different roles (Admin, HR, Manager, User).
-   **Device Management**: Handling the CRUD (Create, Read, Update, Delete) operations for ZKTeco fingerprint attendance devices.
-   **Attendance Tracking**: Fetching punch logs from hardware devices, processing them to determine IN/OUT state, and storing them in the database.
-   **Violation & Policy Engine**: Automatically calculating attendance violations such as "Late In," "Early Out," and "Overtime" based on assigned shifts.
-   **Reporting**: Displaying attendance logs with robust filtering (by employee, date range) and pagination.
-   **Shift & Leave Management**: Creating and assigning work shifts and managing employee leave requests.
-   **System Stability**: Ensuring the application is robust, with clear error handling and a good user experience for administrators.

## Tech Stack
-   **Backend Language**: PHP
-   **Database**: MariaDB (connected via MySQL PDO driver)
-   **Frontend**: HTML, CSS, JavaScript
-   **Frameworks/Libraries**:
    -   **Styling**: Bootstrap 5
    -   **Icons**: Font Awesome
    -   **PHP Libraries (via Composer)**:
        -   `coding-libs/zkteco-php`: For all communication with ZKTeco fingerprint devices.
        -   `nesbot/carbon` & `illuminate/collections`: Used for advanced date/time handling and data manipulation.
-   **Development Environment**: XAMPP on Windows

## Key Files/Folders
-   **/app**: Contains all core application logic.
    -   `app/core`: Houses essential files like `bootstrap.php`, `database.php`, `auth.php`, and `helpers.php`.
    -   `app/core/services`: Contains key business logic classes, most importantly `AttendanceService.php`.
    -   `app/templates`: Reusable HTML parts like `header.php` and `footer.php`.
-   **/admin**: Admin-facing pages for managing users, devices, shifts, and viewing logs.
-   **/api**: PHP scripts that handle background AJAX requests (e.g., testing device connections, fetching logs).
-   **/vendor**: All third-party libraries managed by Composer.
-   `hr_portal.sql`: The complete database schema for the project.

## Coding Conventions
-   **Database**: Table and column names use `snake_case` (e.g., `attendance_logs`, `employee_code`).
-   **PHP**:
    -   A mix of Object-Oriented Programming (in services like `AttendanceService`) and procedural programming (in page scripts).
    -   Class names use `PascalCase`.
    -   Methods and variables use `camelCase`.
    -   Constants use `UPPER_SNAKE_CASE`.
-   **File Structure**: A custom structure that separates core logic, services, templates, and public-facing scripts.