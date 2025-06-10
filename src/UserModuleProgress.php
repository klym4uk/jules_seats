<?php

class UserModuleProgress {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Starts a module for a user or updates its status if already started.
     * If no record exists, creates one with 'in_progress'.
     * If 'not_started', updates to 'in_progress'.
     *
     * @param int $user_id
     * @param int $module_id
     * @return bool True on success, false on failure.
     */
    public function startModule($user_id, $module_id) {
        $existing_progress = $this->getModuleProgress($user_id, $module_id);

        if ($existing_progress) {
            // If module is 'not_started' or another initial state, update to 'in_progress'
            // Avoid reverting 'completed', 'passed', 'failed', 'quiz_available' states unless explicitly intended
            if (in_array($existing_progress->status, ['not_started'])) {
                return $this->updateModuleStatus($user_id, $module_id, 'in_progress');
            }
            return true; // Already in a more advanced state or 'in_progress'
        } else {
            // Create new progress record
            $sql = "INSERT INTO user_module_progress (user_id, module_id, status)
                    VALUES (:user_id, :module_id, 'in_progress')
                    ON DUPLICATE KEY UPDATE status = VALUES(status)"; // Handles race condition if record created between get and insert
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
                return $stmt->execute();
            } catch (PDOException $e) {
                error_log("Error starting module progress: " . $e->getMessage());
                return false;
            }
        }
    }

    /**
     * Updates the status of a module for a user.
     *
     * @param int $user_id
     * @param int $module_id
     * @param string $status Enum('not_started', 'in_progress', 'training_completed', 'quiz_available', 'passed', 'failed')
     * @return bool True on success, false on failure.
     */
    public function updateModuleStatus($user_id, $module_id, $status) {
        $sql = "UPDATE user_module_progress
                SET status = :status, updated_at = CURRENT_TIMESTAMP";

        if ($status === 'passed' || $status === 'failed' || $status === 'training_completed') {
            // These statuses imply completion of an attempt or training phase
            $sql .= ", completion_date = CURRENT_TIMESTAMP";
        } else {
            // For other statuses like 'in_progress', 'not_started', 'quiz_available', clear completion_date
            $sql .= ", completion_date = NULL";
        }

        $sql .= " WHERE user_id = :user_id AND module_id = :module_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating module status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches progress for a specific module for a user.
     *
     * @param int $user_id
     * @param int $module_id
     * @return object|false Progress object or false if not found.
     */
    public function getModuleProgress($user_id, $module_id) {
        $sql = "SELECT * FROM user_module_progress
                WHERE user_id = :user_id AND module_id = :module_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching module progress: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches progress for all modules for a user.
     * Includes module title for display.
     *
     * @param int $user_id
     * @return array Array of progress objects with module details.
     */
    public function getAllModulesProgressForUser($user_id) {
        $sql = "SELECT ump.*, m.title as module_title, m.description as module_description, m.status as module_status
                FROM user_module_progress ump
                JOIN modules m ON ump.module_id = m.module_id
                WHERE ump.user_id = :user_id
                ORDER BY m.title ASC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching all modules progress for user: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Checks if all lessons in a module are completed by a user.
     * If so, updates the module progress status to 'training_completed' or 'quiz_available'
     * depending on whether a quiz exists for the module.
     *
     * @param int $user_id
     * @param int $module_id
     * @return bool True if all lessons completed and status updated, false otherwise.
     */
    public function areAllLessonsCompleted($user_id, $module_id) {
        // Count total active lessons in the module
        $sql_total_lessons = "SELECT COUNT(*) FROM lessons WHERE module_id = :module_id"; // Add more filters like lesson status if needed
        $stmt_total = $this->pdo->prepare($sql_total_lessons);
        $stmt_total->bindParam(':module_id', $module_id, PDO::PARAM_INT);
        $stmt_total->execute();
        $total_lessons = (int)$stmt_total->fetchColumn();

        if ($total_lessons == 0) { // No lessons in the module
            // If no lessons, training is technically complete. Check for quizzes.
            $sql_quiz_exists = "SELECT COUNT(*) FROM quizzes WHERE module_id = :module_id";
            $stmt_quiz = $this->pdo->prepare($sql_quiz_exists);
            $stmt_quiz->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt_quiz->execute();
            $quiz_exists = (int)$stmt_quiz->fetchColumn() > 0;

            $new_status = $quiz_exists ? 'quiz_available' : 'training_completed'; // Or 'passed' if no quiz means auto-pass
            $this->ensureProgressRecordExists($user_id, $module_id); // Make sure a progress record exists
            return $this->updateModuleStatus($user_id, $module_id, $new_status);
        }

        // Count completed lessons by the user in that module
        $sql_completed_lessons = "SELECT COUNT(*) FROM user_lesson_progress ulp
                                  JOIN lessons l ON ulp.lesson_id = l.lesson_id
                                  WHERE ulp.user_id = :user_id AND l.module_id = :module_id
                                  AND ulp.status = 'completed'";
        $stmt_completed = $this->pdo->prepare($sql_completed_lessons);
        $stmt_completed->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_completed->bindParam(':module_id', $module_id, PDO::PARAM_INT);
        $stmt_completed->execute();
        $completed_lessons = (int)$stmt_completed->fetchColumn();

        if ($total_lessons > 0 && $completed_lessons >= $total_lessons) {
            // All lessons completed. Now check if a quiz exists for this module.
            $sql_quiz_exists = "SELECT COUNT(*) FROM quizzes WHERE module_id = :module_id";
            $stmt_quiz = $this->pdo->prepare($sql_quiz_exists);
            $stmt_quiz->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt_quiz->execute();
            $quiz_exists = (int)$stmt_quiz->fetchColumn() > 0;

            $new_status = $quiz_exists ? 'quiz_available' : 'training_completed'; // Or 'passed' if no quiz and training complete = module passed

            $this->ensureProgressRecordExists($user_id, $module_id); // Make sure a progress record exists
            return $this->updateModuleStatus($user_id, $module_id, $new_status);
        }

        return false; // Not all lessons completed
    }

    /**
     * Ensures a user_module_progress record exists for the user and module.
     * If not, creates one with 'not_started' status.
     * This is a helper to prevent errors when trying to update a non-existent record.
     *
     * @param int $user_id
     * @param int $module_id
     * @return bool True if record exists or was created, false on error.
     */
    private function ensureProgressRecordExists($user_id, $module_id) {
        $progress = $this->getModuleProgress($user_id, $module_id);
        if (!$progress) {
            $sql = "INSERT INTO user_module_progress (user_id, module_id, status)
                    VALUES (:user_id, :module_id, 'not_started')
                    ON DUPLICATE KEY UPDATE user_id = user_id"; // Benign update if race condition
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
                return $stmt->execute();
            } catch (PDOException $e) {
                error_log("Error ensuring module progress record: " . $e->getMessage());
                return false;
            }
        }
        return true;
    }

    /**
     * Fetches progress for all users on a specific module.
     * Includes user's first name and last name.
     *
     * @param int $module_id
     * @return array Array of progress objects with user details.
     */
    public function getUsersProgressForModule($module_id) {
        $sql = "SELECT ump.*, u.first_name, u.last_name, u.email
                FROM user_module_progress ump
                JOIN users u ON ump.user_id = u.user_id
                WHERE ump.module_id = :module_id
                ORDER BY u.last_name ASC, u.first_name ASC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching users' progress for module: " . $e->getMessage());
            return [];
        }
    }
}
?>
