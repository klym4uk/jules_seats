<?php

class Question {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Creates a new question.
     *
     * @param int $quiz_id
     * @param string $question_text
     * @param string $question_type Enum('single_choice', 'multiple_choice')
     * @param int $order_in_quiz
     * @return int|false The ID of the newly created question or false on failure.
     */
    public function createQuestion($quiz_id, $question_text, $question_type = 'single_choice', $order_in_quiz = 0) {
        if (empty($quiz_id) || empty($question_text) || !isset($order_in_quiz)) {
            return false; // Basic validation
        }

        $sql = "INSERT INTO questions (quiz_id, question_text, question_type, order_in_quiz)
                VALUES (:quiz_id, :question_text, :question_type, :order_in_quiz)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            $stmt->bindParam(':question_text', $question_text, PDO::PARAM_STR);
            $stmt->bindParam(':question_type', $question_type, PDO::PARAM_STR);
            $stmt->bindParam(':order_in_quiz', $order_in_quiz, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating question: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches a single question by its ID.
     *
     * @param int $question_id
     * @return object|false Question object or false if not found.
     */
    public function getQuestionById($question_id) {
        $sql = "SELECT q.*, qz.title as quiz_title
                FROM questions q
                JOIN quizzes qz ON q.quiz_id = qz.quiz_id
                WHERE q.question_id = :question_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching question by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all questions for a given quiz, ordered by order_in_quiz.
     *
     * @param int $quiz_id
     * @return array Array of question objects.
     */
    public function getQuestionsByQuizId($quiz_id) {
        $sql = "SELECT * FROM questions WHERE quiz_id = :quiz_id ORDER BY order_in_quiz ASC, created_at ASC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching questions by quiz ID: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Updates an existing question.
     *
     * @param int $question_id
     * @param string $question_text
     * @param string $question_type
     * @param int $order_in_quiz
     * @return bool True on success, false on failure.
     */
    public function updateQuestion($question_id, $question_text, $question_type, $order_in_quiz) {
        if (empty($question_id) || empty($question_text) || !isset($order_in_quiz)) {
            return false;
        }

        $sql = "UPDATE questions SET question_text = :question_text, question_type = :question_type,
                order_in_quiz = :order_in_quiz, updated_at = CURRENT_TIMESTAMP
                WHERE question_id = :question_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
            $stmt->bindParam(':question_text', $question_text, PDO::PARAM_STR);
            $stmt->bindParam(':question_type', $question_type, PDO::PARAM_STR);
            $stmt->bindParam(':order_in_quiz', $order_in_quiz, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating question: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a question by its ID.
     * (Assumes ON DELETE CASCADE is set in DB for related answers)
     *
     * @param int $question_id
     * @return bool True on success, false on failure.
     */
    public function deleteQuestion($question_id) {
        // Before deleting a question, ensure related answers are handled (DB cascade is preferred).
        // Also, consider implications for user_question_answers if not handled by cascade or soft delete.
        $sql = "DELETE FROM questions WHERE question_id = :question_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting question: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Counts the number of questions in a specific quiz.
     * @param int $quiz_id
     * @return int Number of questions.
     */
    public function countQuestionsInQuiz($quiz_id) {
        $sql = "SELECT COUNT(*) FROM questions WHERE quiz_id = :quiz_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting questions in quiz: " . $e->getMessage());
            return 0;
        }
    }
}
?>
