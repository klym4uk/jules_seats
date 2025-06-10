<?php

class QuizResult {
    private $pdo;
    private $quizHandler; // To get quiz details like cooldown

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        // $this->quizHandler = new Quiz($pdo); // Avoid creating new handlers if not strictly necessary here, pass Quiz object if needed
    }

    /**
     * Creates a new quiz attempt record.
     * Score and passed status are initially default/null until quiz is submitted and graded.
     *
     * @param int $user_id
     * @param int $quiz_id
     * @param int $attempt_number
     * @return int|false The ID of the newly created quiz_results record or false on failure.
     */
    public function createAttempt($user_id, $quiz_id, $attempt_number) {
        // Initial score is 0, passed is false. These will be updated upon submission.
        $initial_score = 0.00;
        $initial_passed_status = false;
        // completed_at will be set when the quiz is submitted and graded.

        $sql = "INSERT INTO quiz_results (user_id, quiz_id, attempt_number, score, passed)
                VALUES (:user_id, :quiz_id, :attempt_number, :score, :passed)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            $stmt->bindParam(':attempt_number', $attempt_number, PDO::PARAM_INT);
            $stmt->bindParam(':score', $initial_score, PDO::PARAM_STR);
            $stmt->bindParam(':passed', $initial_passed_status, PDO::PARAM_BOOL);

            if ($stmt->execute()) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating quiz attempt: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Records a user's answer for a specific question in a quiz attempt.
     *
     * @param int $quiz_result_id ID of the quiz attempt (from quiz_results table)
     * @param int $question_id
     * @param int $answer_id The answer chosen by the user
     * @param bool $is_correct Whether the chosen answer was correct
     * @return bool True on success, false on failure.
     */
    public function recordAnswer($quiz_result_id, $question_id, $answer_id, $is_correct) {
        $sql = "INSERT INTO user_question_answers (quiz_result_id, question_id, answer_id, is_correct)
                VALUES (:quiz_result_id, :question_id, :answer_id, :is_correct)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':quiz_result_id', $quiz_result_id, PDO::PARAM_INT);
            $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
            $stmt->bindParam(':answer_id', $answer_id, PDO::PARAM_INT);
            $stmt->bindParam(':is_correct', $is_correct, PDO::PARAM_BOOL);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error recording answer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates the score, passed status, and completed_at timestamp of a quiz attempt.
     * This is called after all answers are processed and the quiz is graded.
     *
     * @param int $quiz_result_id
     * @param float $score
     * @param bool $passed_status
     * @return bool True on success, false on failure.
     */
    public function updateAttemptScoreAndStatus($quiz_result_id, $score, $passed_status) {
        $sql = "UPDATE quiz_results
                SET score = :score, passed = :passed, completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE quiz_result_id = :quiz_result_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':quiz_result_id', $quiz_result_id, PDO::PARAM_INT);
            $stmt->bindParam(':score', $score, PDO::PARAM_STR); // Decimal stored as string
            $stmt->bindParam(':passed', $passed_status, PDO::PARAM_BOOL);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating attempt score and status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches the most recent quiz attempt for a user and a specific quiz.
     *
     * @param int $user_id
     * @param int $quiz_id
     * @return object|false Latest quiz_results object or false if no attempts.
     */
    public function getLatestAttempt($user_id, $quiz_id) {
        $sql = "SELECT * FROM quiz_results
                WHERE user_id = :user_id AND quiz_id = :quiz_id
                ORDER BY attempt_number DESC
                LIMIT 1";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching latest attempt: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks if a user can retake a quiz based on cooldown period.
     *
     * @param int $user_id
     * @param int $quiz_id
     * @param Quiz $quizHandler An instance of the Quiz model to fetch quiz details.
     * @return bool True if user can retake, false otherwise.
     */
    public function canRetakeQuiz($user_id, $quiz_id, Quiz $quizHandler) {
        $latest_attempt = $this->getLatestAttempt($user_id, $quiz_id);

        if (!$latest_attempt) {
            return true; // No previous attempts, can take.
        }

        if ($latest_attempt->passed) {
            // Depending on policy, users might not be allowed to retake passed quizzes.
            // For now, let's assume they can, or this logic can be adjusted.
            // return false; // Example: Cannot retake if passed
            return true;
        }

        // If failed, check cooldown
        $quiz = $quizHandler->getQuizById($quiz_id);
        if (!$quiz) {
            error_log("canRetakeQuiz: Quiz not found with ID $quiz_id");
            return false; // Cannot determine cooldown if quiz details are missing
        }

        $cooldown_hours = (int)$quiz->cooldown_period_hours;
        if ($cooldown_hours <= 0) {
            return true; // No cooldown period.
        }

        $completed_at_timestamp = strtotime($latest_attempt->completed_at);
        $current_timestamp = time();
        $hours_since_completion = ($current_timestamp - $completed_at_timestamp) / 3600;

        if ($hours_since_completion >= $cooldown_hours) {
            return true; // Cooldown has passed.
        }

        error_log("User $user_id cannot retake quiz $quiz_id. Cooldown: $cooldown_hours hrs, Time since last attempt: $hours_since_completion hrs.");
        return false; // Still in cooldown.
    }

    /**
     * Calculates the next attempt number for a user and a specific quiz.
     *
     * @param int $user_id
     * @param int $quiz_id
     * @return int The next attempt number.
     */
    public function getNextAttemptNumber($user_id, $quiz_id) {
        $latest_attempt = $this->getLatestAttempt($user_id, $quiz_id);
        if ($latest_attempt) {
            return (int)$latest_attempt->attempt_number + 1;
        }
        return 1; // First attempt
    }

    /**
     * Fetches a specific quiz attempt by its result ID.
     *
     * @param int $quiz_result_id
     * @return object|false Quiz_results object or false if not found.
     */
    public function getAttemptById($quiz_result_id) {
        $sql = "SELECT qr.*, q.title as quiz_title, q.passing_threshold, m.title as module_title, m.module_id
                FROM quiz_results qr
                JOIN quizzes q ON qr.quiz_id = q.quiz_id
                JOIN modules m ON q.module_id = m.module_id
                WHERE qr.quiz_result_id = :quiz_result_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':quiz_result_id', $quiz_result_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching attempt by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all answers submitted by a user for a specific quiz attempt.
     *
     * @param int $quiz_result_id
     * @return array Array of user_question_answers objects, potentially with question_text and answer_text.
     */
    public function getSubmittedAnswersForAttempt($quiz_result_id) {
        $sql = "SELECT uqa.*, q.question_text, a.answer_text as chosen_answer_text, a_correct.answer_text as correct_answer_text
                FROM user_question_answers uqa
                JOIN questions q ON uqa.question_id = q.question_id
                JOIN answers a ON uqa.answer_id = a.answer_id
                LEFT JOIN answers a_correct ON q.question_id = a_correct.question_id AND a_correct.is_correct = TRUE
                WHERE uqa.quiz_result_id = :quiz_result_id
                ORDER BY q.order_in_quiz ASC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':quiz_result_id', $quiz_result_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching submitted answers for attempt: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculates the average score for a specific quiz across all completed attempts.
     *
     * @param int $quiz_id
     * @return float|false Average score or false on error/no completed attempts.
     */
    public function getAverageScoreForQuiz($quiz_id) {
        // Considers only attempts that have a non-null 'completed_at'
        $sql = "SELECT AVG(score) FROM quiz_results
                WHERE quiz_id = :quiz_id AND completed_at IS NOT NULL";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            $stmt->execute();
            $avg_score = $stmt->fetchColumn();
            return ($avg_score === null) ? 0.00 : (float)$avg_score; // Return 0.00 if no attempts
        } catch (PDOException $e) {
            error_log("Error calculating average score for quiz: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculates the pass rate for a specific quiz.
     *
     * @param int $quiz_id
     * @param float $passing_threshold (Optional, if not fetched from quiz table directly)
     * @return float|false Pass rate (0.0 to 100.0) or false on error.
     */
    public function getPassRateForQuiz($quiz_id) {
        // This method assumes you'll fetch $passing_threshold from the quizzes table separately
        // or pass it in if you already have it.
        // For simplicity, we'll count passed attempts and total completed attempts.
        $sql_total_completed = "SELECT COUNT(*) FROM quiz_results
                                WHERE quiz_id = :quiz_id AND completed_at IS NOT NULL";
        $sql_passed_completed = "SELECT COUNT(*) FROM quiz_results
                                 WHERE quiz_id = :quiz_id AND completed_at IS NOT NULL AND passed = TRUE";
        try {
            $stmt_total = $this->pdo->prepare($sql_total_completed);
            $stmt_total->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            $stmt_total->execute();
            $total_completed_attempts = (int)$stmt_total->fetchColumn();

            if ($total_completed_attempts == 0) {
                return 0.00; // No completed attempts, so pass rate is 0
            }

            $stmt_passed = $this->pdo->prepare($sql_passed_completed);
            $stmt_passed->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            $stmt_passed->execute();
            $passed_attempts = (int)$stmt_passed->fetchColumn();

            return round(($passed_attempts / $total_completed_attempts) * 100, 2);

        } catch (PDOException $e) {
            error_log("Error calculating pass rate for quiz: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all attempts for a specific user on a specific quiz.
     * Ordered by attempt number.
     *
     * @param int $user_id
     * @param int $quiz_id
     * @return array Array of quiz_results objects.
     */
    public function getAllAttemptsForUserQuiz($user_id, $quiz_id) {
        $sql = "SELECT * FROM quiz_results
                WHERE user_id = :user_id AND quiz_id = :quiz_id
                ORDER BY attempt_number ASC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error fetching all attempts for user quiz: " . $e->getMessage());
            return [];
        }
    }
}
?>
