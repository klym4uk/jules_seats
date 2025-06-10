<?php

class UserLessonProgress {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Marks a lesson as 'viewed' when a user starts it.
     * If no record exists, creates one with 'viewed'.
     * If 'not_viewed', updates to 'viewed'.
     *
     * @param int $user_id
     * @param int $lesson_id
     * @return bool True on success, false on failure.
     */
    public function startLesson($user_id, $lesson_id) {
        $existing_progress = $this->getLessonProgress($user_id, $lesson_id);

        if ($existing_progress) {
            if ($existing_progress->status === 'not_viewed') {
                return $this->updateLessonStatus($user_id, $lesson_id, 'viewed');
            }
            return true; // Already viewed or completed
        } else {
            // Create new progress record with 'viewed' status
            $sql = "INSERT INTO user_lesson_progress (user_id, lesson_id, status)
                    VALUES (:user_id, :lesson_id, 'viewed')
                    ON DUPLICATE KEY UPDATE status = VALUES(status)"; // Handle race conditions
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':lesson_id', $lesson_id, PDO::PARAM_INT);
                return $stmt->execute();
            } catch (PDOException $e) {
                error_log("Error starting lesson progress: " . $e->getMessage());
                return false;
            }
        }
    }

    /**
     * Marks a lesson as 'completed' for a user.
     *
     * @param int $user_id
     * @param int $lesson_id
     * @return bool True on success, false on failure.
     */
    public function markLessonComplete($user_id, $lesson_id) {
        // Ensure a record exists before marking as complete, or create it.
        $this->ensureProgressRecordExists($user_id, $lesson_id, 'viewed'); // Ensure it's at least 'viewed'

        $sql = "UPDATE user_lesson_progress
                SET status = 'completed', completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE user_id = :user_id AND lesson_id = :lesson_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':lesson_id', $lesson_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error marking lesson complete: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates the status of a lesson for a user. (Generic updater if needed)
     *
     * @param int $user_id
     * @param int $lesson_id
     * @param string $status Enum('not_viewed', 'viewed', 'completed')
     * @return bool True on success, false on failure.
     */
    public function updateLessonStatus($user_id, $lesson_id, $status) {
        $this->ensureProgressRecordExists($user_id, $lesson_id);

        $sql = "UPDATE user_lesson_progress SET status = :status, updated_at = CURRENT_TIMESTAMP";
        if ($status === 'completed') {
            $sql .= ", completed_at = CURRENT_TIMESTAMP";
        } else {
            // If marking as 'viewed' or 'not_viewed', clear completed_at
            $sql .= ", completed_at = NULL";
        }
        $sql .= " WHERE user_id = :user_id AND lesson_id = :lesson_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':lesson_id', $lesson_id, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating lesson status: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Fetches progress for a specific lesson for a user.
     *
     * @param int $user_id
     * @param int $lesson_id
     * @return object|false Progress object or false if not found.
     */
    public function getLessonProgress($user_id, $lesson_id) {
        $sql = "SELECT * FROM user_lesson_progress
                WHERE user_id = :user_id AND lesson_id = :lesson_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':lesson_id', $lesson_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching lesson progress: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all completed lessons for a user within a specific module.
     *
     * @param int $user_id
     * @param int $module_id
     * @return array Array of completed lesson progress objects (or just their IDs).
     */
    public function getCompletedLessonsForModule($user_id, $module_id) {
        $sql = "SELECT ulp.*
                FROM user_lesson_progress ulp
                JOIN lessons l ON ulp.lesson_id = l.lesson_id
                WHERE ulp.user_id = :user_id
                AND l.module_id = :module_id
                AND ulp.status = 'completed'";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching completed lessons for module: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ensures a user_lesson_progress record exists for the user and lesson.
     * If not, creates one with a specified default status.
     *
     * @param int $user_id
     * @param int $lesson_id
     * @param string $default_status Default 'not_viewed'
     * @return bool True if record exists or was created, false on error.
     */
    private function ensureProgressRecordExists($user_id, $lesson_id, $default_status = 'not_viewed') {
        $progress = $this->getLessonProgress($user_id, $lesson_id);
        if (!$progress) {
            $sql = "INSERT INTO user_lesson_progress (user_id, lesson_id, status)
                    VALUES (:user_id, :lesson_id, :status)
                    ON DUPLICATE KEY UPDATE user_id = user_id"; // Benign update for race condition
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':lesson_id', $lesson_id, PDO::PARAM_INT);
                $stmt->bindParam(':status', $default_status, PDO::PARAM_STR);
                return $stmt->execute();
            } catch (PDOException $e) {
                error_log("Error ensuring lesson progress record: " . $e->getMessage());
                return false;
            }
        }
        return true;
    }
}
?>
