<?php

class Quiz {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Creates a new quiz.
     *
     * @param int $module_id
     * @param string $title
     * @param string|null $description
     * @param float $passing_threshold
     * @param int $cooldown_period_hours
     * @return int|false The ID of the newly created quiz or false on failure.
     */
    public function createQuiz($module_id, $title, $description, $passing_threshold = 70.00, $cooldown_period_hours = 1) {
        if (empty($module_id) || empty($title) || !is_numeric($passing_threshold) || !is_numeric($cooldown_period_hours)) {
            return false; // Basic validation
        }

        $sql = "INSERT INTO quizzes (module_id, title, description, passing_threshold, cooldown_period_hours)
                VALUES (:module_id, :title, :description, :passing_threshold, :cooldown_period_hours)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':passing_threshold', $passing_threshold, PDO::PARAM_STR); // Decimal stored as string
            $stmt->bindParam(':cooldown_period_hours', $cooldown_period_hours, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating quiz: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches a single quiz by its ID.
     *
     * @param int $quiz_id
     * @return object|false Quiz object or false if not found.
     */
    public function getQuizById($quiz_id) {
        $sql = "SELECT q.*, m.title as module_title
                FROM quizzes q
                JOIN modules m ON q.module_id = m.module_id
                WHERE q.quiz_id = :quiz_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching quiz by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all quizzes for a given module.
     *
     * @param int $module_id
     * @return array Array of quiz objects.
     */
    public function getQuizzesByModuleId($module_id) {
        $sql = "SELECT * FROM quizzes WHERE module_id = :module_id ORDER BY created_at DESC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching quizzes by module ID: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetches all quizzes, optionally joining module information.
     *
     * @return array Array of quiz objects, potentially with module_title.
     */
    public function getAllQuizzesWithModuleInfo() {
        $sql = "SELECT q.*, m.title as module_title
                FROM quizzes q
                JOIN modules m ON q.module_id = m.module_id
                ORDER BY m.title ASC, q.created_at DESC";
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching all quizzes with module info: " . $e->getMessage());
            return [];
        }
    }


    /**
     * Updates an existing quiz.
     *
     * @param int $quiz_id
     * @param string $title
     * @param string|null $description
     * @param float $passing_threshold
     * @param int $cooldown_period_hours
     * @param int $module_id (optional, if you want to allow changing module)
     * @return bool True on success, false on failure.
     */
    public function updateQuiz($quiz_id, $title, $description, $passing_threshold, $cooldown_period_hours, $module_id = null) {
        if (empty($quiz_id) || empty($title) || !is_numeric($passing_threshold) || !is_numeric($cooldown_period_hours)) {
            return false;
        }

        $sql = "UPDATE quizzes SET title = :title, description = :description,
                passing_threshold = :passing_threshold, cooldown_period_hours = :cooldown_period_hours";
        if ($module_id !== null && is_numeric($module_id)) {
            $sql .= ", module_id = :module_id";
        }
        $sql .= ", updated_at = CURRENT_TIMESTAMP WHERE quiz_id = :quiz_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':passing_threshold', $passing_threshold, PDO::PARAM_STR);
            $stmt->bindParam(':cooldown_period_hours', $cooldown_period_hours, PDO::PARAM_INT);
            if ($module_id !== null && is_numeric($module_id)) {
                 $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating quiz: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a quiz by its ID.
     * (Assumes ON DELETE CASCADE is set in DB for related questions, answers, results)
     *
     * @param int $quiz_id
     * @return bool True on success, false on failure.
     */
    public function deleteQuiz($quiz_id) {
        // Before deleting a quiz, you might want to ensure related user results are handled if not cascaded.
        // For now, relying on DB cascade for questions and answers.
        $sql = "DELETE FROM quizzes WHERE quiz_id = :quiz_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting quiz: " . $e->getMessage());
            return false;
        }
    }
}
?>
