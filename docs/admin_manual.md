# SEATS Administrator Manual

Welcome to the SEATS (Skills Enhancement and Tracking System) Administrator Panel! This manual provides guidance on managing users, training content, and monitoring progress.

## 1. Logging In and Out

### Logging In
1.  Navigate to the SEATS portal login page (e.g., `http://localhost/seats_app/public/login.php`).
2.  Enter your **Admin Email** and **Password**.
3.  Click the "Login" button.
4.  Upon successful login, you will be redirected to the Admin Dashboard.

### Logging Out
1.  To log out, locate your name in the top navigation bar (usually dark-themed for admins).
2.  Click the "Logout" button.
3.  You will be redirected to the login page.

## 2. Admin Dashboard Overview
The Admin Dashboard is your central hub for system management. It displays:
-   **Summary Statistics:** Cards showing key metrics like:
    -   Total Employees
    -   Total Admins
    -   Active Modules
    -   Percentage of Users who have passed at least one module.
-   **Quick Links:** Easy access to main administrative sections.

## 3. User Management

### Adding New Users
1.  From the Admin Dashboard or the "Users" navigation link, you can access user management.
2.  Click the "Add New User" button (this may link to `public/register.php` or a dedicated admin user creation page like `public/admin/add_user.php`).
3.  Fill in the user's details: First Name, Last Name, Email, Password.
4.  Select the user's **Role**: 'Employee' or 'Admin'.
5.  Submit the form to create the user. The user will be added to the database, and their password will be securely hashed.

### Viewing Users
1.  Navigate to the "Users" section from the top navigation bar.
2.  A table lists all registered users with their ID, Name, Email, Role, Registration Date, and Last Login.

### Editing and Deleting Users (Current Implementation Note)
-   The current UI primarily supports adding users via the `register.php` script (which can be used by admins for initial setup) and listing users in `public/admin/users.php`.
-   **Placeholder Functionality:** "Edit" and "Delete" buttons are present in the user list but are marked as placeholders ("Edit (PH)", "Delete (PH)").
-   **Full CRUD:** For full user editing (e.g., changing names, email, roles, or resetting passwords) and deletion, direct database management via a tool like phpMyAdmin might be required with the current UI limitations. Future enhancements would involve building dedicated admin interfaces for these actions.

## 4. Training Content Management

Access "Modules & Training" from the top navigation bar to manage modules, lessons, and quizzes.

### 4.1. Modules (`manage_modules.php`)

-   **Viewing Modules:** All existing modules are listed with their ID, Title, a snippet of the Description, Deadline, Status (Active, Inactive, Archived), creation/update timestamps, and action buttons.
-   **Creating a New Module:**
    1.  Use the "Create New Module" form on the `manage_modules.php` page.
    2.  Enter Title, Description, an optional Deadline (date), and set the initial Status.
    3.  Click "Create Module".
-   **Editing an Existing Module:**
    1.  Find the module in the list and click its "Edit" button.
    2.  The form will pre-fill with the module's current details.
    3.  Modify as needed and click "Update Module".
-   **Deleting a Module:**
    1.  Click the "Delete" button for the desired module.
    2.  A JavaScript confirmation prompt will appear. Confirm to delete.
    3.  **Note:** Deleting a module will also delete all its associated lessons, quizzes, questions, answers, and user progress records due to database cascade rules (`ON DELETE CASCADE`). This action is irreversible.
-   **Managing Content:**
    -   "Lessons": Click to go to the lesson management page for that module.
    -   "Quizzes": Click to go to the quiz management page for that module.

### 4.2. Lessons (`manage_lessons.php`)

This page is accessed by clicking "Lessons" for a specific module on the `manage_modules.php` page. The current module's title is displayed.

-   **Viewing Lessons:** Lessons for the selected module are listed with Order, Title, Type, a content preview, and action buttons.
-   **Creating a New Lesson:**
    1.  Use the "Create New Lesson" form.
    2.  Enter Title, Order in Module (e.g., 1, 2, 3 for sequence).
    3.  Select **Content Type**:
        -   **Text:** A textarea will be shown for `Content (Text)`.
        -   **Video URL:** An input field for `Content URL` (e.g., YouTube link) will be shown.
        -   **Image URL:** An input field for `Content URL` will be shown.
    4.  Fill in the relevant content field based on the type.
    5.  Click "Create Lesson".
-   **Editing an Existing Lesson:**
    1.  Click "Edit" for the lesson. The form pre-fills.
    2.  Modify details and click "Update Lesson". The appropriate content field (text or URL) will be shown based on the saved content type.
-   **Deleting a Lesson:**
    1.  Click "Delete". Confirm the action.
    2.  **Note:** This will also delete user progress related to this specific lesson due to database cascade rules.

## 5. Quiz Management

### 5.1. Quizzes (`manage_quizzes.php`)

This page can be accessed from a module's "Quizzes" link on `manage_modules.php` (showing quizzes for that module) or potentially from a general quiz management area (if developed, showing all quizzes).

-   **Viewing Quizzes:** Lists quizzes with ID, Title, associated Module, Passing Threshold, Cooldown period, number of questions, and action buttons.
-   **Creating a New Quiz:**
    1.  Use the "Create New Quiz" form.
    2.  Select the **Module** this quiz belongs to from the dropdown.
    3.  Enter Title, Description (optional), Passing Threshold (e.g., 70.00 for 70%), and Cooldown Period (in hours, e.g., 1 for one hour before a user can retake a failed quiz).
    4.  Click "Create Quiz".
-   **Editing an Existing Quiz:**
    1.  Click "Edit" for the quiz. The form pre-fills.
    2.  Modify details and click "Update Quiz".
-   **Deleting a Quiz:**
    1.  Click "Delete". Confirm the action.
    2.  **Note:** Deleting a quiz will also delete its associated questions, answers, and all user quiz results due to database cascade rules. This is irreversible.
-   **Managing Questions:** Click "Questions" to go to the question and answer management page for that quiz.

### 5.2. Questions & Answers (`manage_questions.php`)

This page is accessed by clicking "Questions" for a specific quiz on the `manage_quizzes.php` page. The current quiz's title is displayed.

-   **Viewing Questions:** Questions for the selected quiz are listed with Order, Text, Type, number of answers, and action buttons.
-   **Creating a New Question:**
    1.  Use the "Add New Question" form.
    2.  Enter Question Text, Order in Quiz, and select Question Type (currently "Single Choice" is the primary supported type for answer logic).
    3.  Click "Create Question".
-   **Editing a Question:**
    1.  Click "Edit Q" for the question. The form pre-fills.
    2.  Modify and click "Update Question".
-   **Deleting a Question:**
    1.  Click "Del Q". Confirm action. Deletes the question and its answers (due to cascade).

-   **Managing Answers for a Question:**
    1.  Click "Manage Answers" for a specific question in the list.
    2.  The "Manage Answers for Question..." section will appear/highlight.
    3.  **Existing Answers List:** Shows current answers. For "Single Choice" questions:
        -   Use the **radio button** next to an answer to mark it as the correct one. The form submits automatically when a radio button is clicked, updating the correct answer.
        -   "Edit A": Click to load the answer text into the "Edit Answer Option" form below.
        -   "Del A": Click to delete an answer option.
    4.  **Add/Edit Answer Option Form:**
        -   To add a new answer: Fill in "Answer Text" and click "Add Answer". If it's a single-choice question, you can optionally check "Set this new answer as the correct one?"
        -   To edit an existing answer's text: Click "Edit A" for an answer. Its text loads into this form. Modify and click "Update Answer Text". (Correctness for single-choice is handled by the radio buttons above).

## 6. Progress Monitoring

### User Progress Overview (`user_progress_overview.php`)
-   Accessible from "User Progress" in the top navigation.
-   Lists all employees.
-   For each employee: Name, Email, and their Overall Progress % (calculated as passed active modules / total active modules).
-   Click "View Details" to see a specific employee's detailed progress.

### Detailed Employee Progress (`view_employee_progress.php`)
-   Shows the selected employee's name and email.
-   Lists all modules in the system.
-   For each module: the employee's Status, Completion Date (if applicable), Latest Quiz Score, and total Quiz Attempts for that module's quiz.

### Module Progress Overview (`module_progress_overview.php`)
-   Accessible from "Modules & Training" > "Module Progress" (or similar link).
-   Lists all modules.
-   For each module: Title, Status (active/inactive), Associated Quiz Title, Average Quiz Score for that quiz, and Quiz Pass Rate.
-   Click "View Details" (if a quiz is associated) to see user-specific progress for that module.

### Detailed Module Progress (`view_module_progress_detail.php`)
-   Shows the selected module's title, status, and associated quiz.
-   Lists all users who have interacted with this module.
-   For each user: Name, Email, their status for this module, Latest Quiz Score for this module's quiz, number of attempts, and last attempt date.

## 7. Reporting (`reports.php`)

Accessible from "Reports" in the top navigation.

### User Progress Report
1.  Click "View User Progress Report" on the main reports page.
2.  Displays a table of all employees: User ID, Name, Email, Overall Progress %, Modules Passed (active ones), and Total Active Modules in the system.
3.  Click **"Export User Progress to CSV"** to download the report.

### Quiz Results Report
1.  Click "View Quiz Results Report" on the main reports page.
2.  Select a **Quiz** from the dropdown menu.
3.  Click "View Report" (or it may auto-submit on selection).
4.  Displays for the selected quiz:
    -   Quiz Title, Module Title.
    -   Summary: Average Score and Pass Rate.
    -   A table of all attempts: User Name, Email, Attempt #, Score, Pass/Fail Status, and Attempt Date.
5.  Click **"Export These Results to CSV"** to download the report for the currently selected quiz.

For any issues not covered, direct database inspection might be necessary for advanced troubleshooting.
