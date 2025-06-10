-- Drop the database if it exists to ensure a clean setup
DROP DATABASE IF EXISTS seats_db;

-- Create the database
CREATE DATABASE seats_db;

-- Use the database
USE seats_db;

-- Table: users
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('Employee', 'Admin') DEFAULT 'Employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: modules
CREATE TABLE modules (
    module_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    deadline DATE, -- Changed from TIMESTAMP to DATE as deadline usually doesn't need time
    status ENUM('active', 'inactive', 'archived') DEFAULT 'inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: lessons
CREATE TABLE lessons (
    lesson_id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content_type ENUM('text', 'video', 'image') DEFAULT 'text',
    content_text TEXT,
    content_url VARCHAR(255),
    order_in_module INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE
);

-- Table: quizzes
CREATE TABLE quizzes (
    quiz_id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    passing_threshold DECIMAL(5,2) DEFAULT 70.00,
    cooldown_period_hours INT DEFAULT 1, -- Cooldown period in hours
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE
);

-- Table: questions
CREATE TABLE questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('single_choice', 'multiple_choice') DEFAULT 'single_choice',
    order_in_quiz INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE
);

-- Table: answers
CREATE TABLE answers (
    answer_id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    answer_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE
);

-- Table: user_module_progress
CREATE TABLE user_module_progress (
    user_module_progress_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    module_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'training_completed', 'quiz_available', 'passed', 'failed') DEFAULT 'not_started',
    completion_date TIMESTAMP NULL, -- Date when the module was completed (passed)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(module_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_module (user_id, module_id) -- Ensures a user has only one progress entry per module
);

-- Table: user_lesson_progress
CREATE TABLE user_lesson_progress (
    user_lesson_progress_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    status ENUM('not_viewed', 'viewed', 'completed') DEFAULT 'not_viewed',
    completed_at TIMESTAMP NULL, -- Date when the lesson was completed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(lesson_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_lesson (user_id, lesson_id) -- Ensures a user has only one progress entry per lesson
);

-- Table: quiz_results
CREATE TABLE quiz_results (
    quiz_result_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,
    attempt_number INT DEFAULT 1,
    score DECIMAL(5,2) NOT NULL,
    passed BOOLEAN NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Timestamp of when the quiz attempt was completed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE
);

-- Table: user_question_answers
-- Stores the specific answer chosen by a user for a question in a quiz attempt
CREATE TABLE user_question_answers (
    user_question_answer_id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_result_id INT NOT NULL, -- References a specific attempt
    question_id INT NOT NULL,
    answer_id INT NOT NULL, -- The answer chosen by the user
    is_correct BOOLEAN, -- Denormalized for easier reporting: was the chosen answer correct?
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_result_id) REFERENCES quiz_results(quiz_result_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
    FOREIGN KEY (answer_id) REFERENCES answers(answer_id) ON DELETE CASCADE
);

-- Example of how to add an Admin user (password is 'admin_password' hashed, use a strong hash in practice)
-- INSERT INTO users (email, password_hash, first_name, last_name, role)
-- VALUES ('admin@example.com', SHA2('admin_password', 256), 'Admin', 'User', 'Admin');

-- Example of how to add an Employee user (password is 'employee_password' hashed)
-- INSERT INTO users (email, password_hash, first_name, last_name, role)
-- VALUES ('employee@example.com', SHA2('employee_password', 256), 'Employee', 'User', 'Employee');

-- Note: ON DELETE CASCADE is used for related data that should be removed if the parent entity is deleted.
-- For example, if a module is deleted, its lessons, quizzes, and progress records should also be deleted.
-- Consider if this is the desired behavior for all foreign keys.
-- `last_login_at` in `users` and `completion_date` in `user_module_progress` are nullable and updated by application logic.
-- `passing_threshold` in `quizzes` has a default, can be overridden per quiz.
-- `cooldown_period_hours` in `quizzes` has a default, can be overridden.
-- `order_in_module` and `order_in_quiz` should be managed by application logic to maintain sequence.
-- `content_text` and `content_url` in `lessons` are nullable based on `content_type`.
-- `is_correct` in `user_question_answers` is set based on the correctness of the chosen `answer_id`.
-- Added `UNIQUE KEY` constraints to `user_module_progress` and `user_lesson_progress` to prevent duplicate entries.
-- Changed `modules.deadline` from TIMESTAMP to DATE.
-- Added `DROP DATABASE IF EXISTS seats_db;` for easier re-running of the script during development.
-- `quiz_results.completed_at` defaults to `CURRENT_TIMESTAMP` as it signifies when the attempt was finished.
-- `updated_at` columns now use `ON UPDATE CURRENT_TIMESTAMP`.
