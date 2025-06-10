# SEATS - System Overview

## 1. Project Purpose
SEATS (Skills Enhancement and Tracking System) is a web-based application designed to facilitate employee training and development. It allows administrators to create and manage training modules, lessons, and quizzes. Employees can access assigned training materials, complete lessons, take quizzes, and track their progress. The system aims to provide a structured learning environment and tools for monitoring training effectiveness.

## 2. Technologies Used
-   **Backend:** PHP (procedural and OOP)
-   **Database:** MySQL
-   **Web Server / Environment:** XAMPP (Apache, MySQL, PHP) - This is the assumed development environment. The application should be compatible with other similar environments (e.g., LAMP, MAMP, WAMP or dedicated hosting with Apache/PHP/MySQL).
-   **Frontend:**
    -   HTML5
    -   CSS3 (including custom styles in `public/css/style.css`)
    -   Bootstrap 5.3 (CDN linked, for UI components, grid system, and general styling)
    -   JavaScript (minimal, primarily for some Bootstrap components and minor dynamic interactions like content field toggling in admin forms).
-   **Database Management (assumed):** phpMyAdmin (for easy database import and inspection during development/setup).

## 3. Directory Structure
The project follows a structured directory layout:

-   **`config/`**: Contains configuration files, notably `database.php` for database connection parameters.
-   **`database/`**: Holds database-related files, primarily `schema.sql` which defines the database structure.
-   **`docs/`**: Contains documentation files, including this overview, employee manual, and admin manual.
-   **`public/`**: The web server's document root should point here (or a subdirectory within it if SEATS is part of a larger site). Contains all publicly accessible files.
    -   **`public/admin/`**: Admin-specific PHP pages for management tasks.
    -   **`public/employee/`**: Employee-specific PHP pages for accessing training.
    -   **`public/css/`**: Contains global CSS files (e.g., `style.css`).
    -   Root of `public/` contains common entry points like `login.php`, `register.php`, `index.php` (if any).
-   **`src/`**: Contains the core application logic and PHP classes (models) and shared includes.
    -   **`src/includes/`**: Common PHP files like `functions.php`, `admin_header.php`, `employee_header.php`.
    -   PHP class files for models (e.g., `User.php`, `Module.php`, `Lesson.php`, `Quiz.php`, `Question.php`, `Answer.php`, `UserModuleProgress.php`, `UserLessonProgress.php`, `QuizResult.php`) are directly in `src/`.

## 4. Database Setup
1.  **MySQL Server:** Ensure a MySQL server is running (e.g., via XAMPP Control Panel).
2.  **Create Database:**
    -   The `database/schema.sql` script includes `CREATE DATABASE IF NOT EXISTS seats_db;`.
    -   You can manually create the `seats_db` database using a tool like phpMyAdmin or run the `schema.sql` script which will create it.
3.  **Import Schema:**
    -   Using phpMyAdmin:
        1.  Select the (newly created or existing) `seats_db` database.
        2.  Go to the "Import" tab.
        3.  Choose the `database/schema.sql` file.
        4.  Click "Go" or "Import".
    -   Alternatively, use the MySQL command line: `mysql -u your_username -p seats_db < database/schema.sql`
4.  **User Privileges:** Ensure the MySQL user configured in `config/database.php` has the necessary permissions (SELECT, INSERT, UPDATE, DELETE, CREATE TABLE - though table creation is via schema) on the `seats_db` database. For XAMPP default `root` user, this is usually already configured.

## 5. Core Components (Models)

The application uses a set of PHP classes (models) in the `src/` directory to interact with the database and encapsulate business logic:

-   **`User.php`**: Manages user data, authentication, roles (Admin, Employee).
-   **`Module.php`**: Manages training modules (title, description, deadline, status).
-   **`Lesson.php`**: Manages lessons within modules (title, content type, content, order).
-   **`Quiz.php`**: Manages quizzes associated with modules (title, description, passing threshold, cooldown).
-   **`Question.php`**: Manages questions within quizzes (text, type, order).
-   **`Answer.php`**: Manages answer options for questions (text, correctness).
-   **`UserModuleProgress.php`**: Tracks employee progress at the module level (status, completion date).
-   **`UserLessonProgress.php`**: Tracks employee progress at the lesson level (status, completion date).
-   **`QuizResult.php`**: Stores results of quiz attempts by users, including scores and individual answers given.

## 6. Basic Setup and Configuration Steps

1.  **Download/Clone Files:** Place all project files (`config/`, `database/`, `docs/`, `public/`, `src/`) into your web server's document root (e.g., `htdocs/seats_app/` in XAMPP).
2.  **Start Web Server & MySQL:** Ensure Apache and MySQL services are running via XAMPP Control Panel or your specific environment's tools.
3.  **Database Setup:** Follow the steps outlined in "4. Database Setup" above to create and populate the `seats_db` database using `schema.sql`.
4.  **Configure Database Connection:**
    -   Open `config/database.php`.
    -   Verify the database connection constants:
        ```php
        define('DB_SERVER', 'localhost'); // Usually correct for local XAMPP
        define('DB_USERNAME', 'root');    // Default XAMPP username, change if needed
        define('DB_PASSWORD', '');        // Default XAMPP password (empty), change if you've set one
        define('DB_NAME', 'seats_db');    // Should match the database name you created/imported
        ```
    -   Adjust these values if your MySQL setup is different.
5.  **Access the Application:**
    -   Open your web browser and navigate to the `public` directory of the application.
    -   Example URL for local XAMPP: `http://localhost/seats_app/public/` or `http://localhost/seats_app/public/login.php`.
6.  **Admin User:**
    -   The `schema.sql` file may contain commented-out `INSERT` statements for creating an initial admin user. If not, you may need to use the `public/register.php` page (if accessible without login, or temporarily modify access) to create your first Admin user, or insert one directly into the `users` table via phpMyAdmin (ensuring the password is hashed correctly if doing so manually - recommended to use the registration form).
    -   Example for manual insertion using `SHA2('admin_password', 256)` (replace `'admin_password'` with a strong password):
        ```sql
        INSERT INTO users (email, password_hash, first_name, last_name, role)
        VALUES ('admin@example.com', SHA2('your_secure_password', 256), 'Admin', 'User', 'Admin');
        ```
        (Note: The application uses `password_hash()` which is more secure than direct SHA2. The `register.php` page uses `password_hash()`.)

This overview provides a basic understanding of the SEATS application structure and setup. Refer to the specific user manuals for employee and administrator functionalities.
