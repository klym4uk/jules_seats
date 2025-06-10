<?php

class Answer {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Creates a new answer for a question.
     *
     * @param int $question_id
     * @param string $answer_text
     * @param bool $is_correct Default false.
     * @return int|false The ID of the newly created answer or false on failure.
     */
    public function createAnswer($question_id, $answer_text, $is_correct = false) {
        if (empty($question_id) || !isset($answer_text)) { // answer_text can be "0"
            return false; // Basic validation
        }

        $sql = "INSERT INTO answers (question_id, answer_text, is_correct)
                VALUES (:question_id, :answer_text, :is_correct)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
            $stmt->bindParam(':answer_text', $answer_text, PDO::PARAM_STR);
            $stmt->bindParam(':is_correct', $is_correct, PDO::PARAM_BOOL);

            if ($stmt->execute()) {
                $answer_id = $this->pdo->lastInsertId();
                // If this answer is marked as correct for a single_choice question, ensure others are not.
                // This check should ideally be combined with fetching question_type.
                // For simplicity here, we might call setCorrectAnswerForQuestion separately if needed.
                return $answer_id;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating answer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches a single answer by its ID.
     *
     * @param int $answer_id
     * @return object|false Answer object or false if not found.
     */
    public function getAnswerById($answer_id) {
        $sql = "SELECT a.*, q.question_text, q.question_type
                FROM answers a
                JOIN questions q ON a.question_id = q.question_id
                WHERE a.answer_id = :answer_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':answer_id', $answer_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching answer by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all answers for a given question.
     *
     * @param int $question_id
     * @return array Array of answer objects.
     */
    public function getAnswersByQuestionId($question_id) {
        $sql = "SELECT * FROM answers WHERE question_id = :question_id ORDER BY created_at ASC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching answers by question ID: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Updates an existing answer.
     *
     * @param int $answer_id
     * @param string $answer_text
     * @param bool $is_correct
     * @return bool True on success, false on failure.
     */
    public function updateAnswer($answer_id, $answer_text, $is_correct) {
        if (empty($answer_id) || !isset($answer_text)) {
            return false;
        }

        $sql = "UPDATE answers SET answer_text = :answer_text, is_correct = :is_correct, updated_at = CURRENT_TIMESTAMP
                WHERE answer_id = :answer_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':answer_id', $answer_id, PDO::PARAM_INT);
            $stmt->bindParam(':answer_text', $answer_text, PDO::PARAM_STR);
            $stmt->bindParam(':is_correct', $is_correct, PDO::PARAM_BOOL);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating answer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes an answer by its ID.
     *
     * @param int $answer_id
     * @return bool True on success, false on failure.
     */
    public function deleteAnswer($answer_id) {
        $sql = "DELETE FROM answers WHERE answer_id = :answer_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':answer_id', $answer_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting answer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sets a specific answer as the correct one for a single-choice question,
     * and ensures all other answers for that question are marked as incorrect.
     *
     * @param int $question_id The ID of the question.
     * @param int $correct_answer_id The ID of the answer to be marked as correct.
     * @return bool True on success, false on failure.
     */
    public function setCorrectAnswerForQuestion($question_id, $correct_answer_id) {
        // First, set all answers for this question to is_correct = FALSE
        $sql_reset = "UPDATE answers SET is_correct = FALSE
                      WHERE question_id = :question_id";

        // Then, set the specified answer to is_correct = TRUE
        $sql_set_correct = "UPDATE answers SET is_correct = TRUE
                            WHERE answer_id = :answer_id AND question_id = :question_id_correct"; // Double check question_id for safety

        try {
            $this->pdo->beginTransaction();

            $stmt_reset = $this->pdo->prepare($sql_reset);
            $stmt_reset->bindParam(':question_id', $question_id, PDO::PARAM_INT);
            $stmt_reset->execute();

            $stmt_set_correct = $this->pdo->prepare($sql_set_correct);
            $stmt_set_correct->bindParam(':answer_id', $correct_answer_id, PDO::PARAM_INT);
            $stmt_set_correct->bindParam(':question_id_correct', $question_id, PDO::PARAM_INT);
            $stmt_set_correct->execute();

            return $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error setting correct answer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Removes the correct status from all answers for a given question.
     * Useful if a question type changes or a correct answer is deleted.
     *
     * @param int $question_id
     * @return bool
     */
    public function clearCorrectAnswersForQuestion($question_id) {
        $sql = "UPDATE answers SET is_correct = FALSE WHERE question_id = :question_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error clearing correct answers: " . $e->getMessage());
            return false;
        }
    }
}
?>
