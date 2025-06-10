<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
// Models
require_once '../../src/User.php';
require_once '../../src/Module.php';
require_once '../../src/Quiz.php';
require_once '../../src/Question.php';
require_once '../../src/Answer.php';
require_once '../../src/UserModuleProgress.php';
require_once '../../src/QuizResult.php';

start_session_if_not_started();
require_login('Employee');

$user_id = $_SESSION['user_id'];
$page_title = "Take Quiz";

// Instantiate handlers
$quizHandler = new Quiz($pdo);
$questionHandler = new Question($pdo);
$answerHandler = new Answer($pdo);
$userModuleProgressHandler = new UserModuleProgress($pdo);
$quizResultHandler = new QuizResult($pdo);
$moduleHandler = new Module($pdo); // For module context

// Validate quiz_id from GET parameter
if (!isset($_GET['quiz_id']) || !filter_var($_GET['quiz_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid quiz specified.";
    redirect('dashboard.php');
}
$quiz_id = (int)$_GET['quiz_id'];
$quiz = $quizHandler->getQuizById($quiz_id);

if (!$quiz) {
    $_SESSION['error_message'] = "Quiz not found.";
    redirect('dashboard.php');
}
$module_id = $quiz->module_id;
$module = $moduleHandler->getModuleById($module_id);
if (!$module) {
    $_SESSION['error_message'] = "Module for this quiz not found.";
    redirect('dashboard.php');
}

$page_title = "Quiz: " . escape_html($quiz->title);

// --- Eligibility Check ---
$module_progress = $userModuleProgressHandler->getModuleProgress($user_id, $module_id);
$can_take_quiz = false;

if ($module_progress) {
    if ($module_progress->status === 'quiz_available') {
        $can_take_quiz = true;
    } elseif ($module_progress->status === 'failed') {
        if ($quizResultHandler->canRetakeQuiz($user_id, $quiz_id, $quizHandler)) {
            $can_take_quiz = true;
        } else {
            $latest_attempt = $quizResultHandler->getLatestAttempt($user_id, $quiz_id);
            $completed_at_timestamp = strtotime($latest_attempt->completed_at);
            $cooldown_ends = date('Y-m-d H:i:s', $completed_at_timestamp + ($quiz->cooldown_period_hours * 3600));
            $_SESSION['error_message'] = "You are still in the cooldown period for this quiz. Please try again after " . $cooldown_ends . ".";
        }
    } elseif ($module_progress->status === 'passed') {
         $_SESSION['message'] = "You have already passed this quiz. Retaking is optional or may not be allowed by policy.";
         // Depending on policy, you might disable retakes or allow them. For now, allow access.
         $can_take_quiz = true; // Or set to false if passed quizzes cannot be retaken.
    } else {
        $_SESSION['error_message'] = "You are not yet eligible to take this quiz. Ensure all lessons are completed. Current status: " . $module_progress->status;
    }
} else {
    $_SESSION['error_message'] = "Module progress not found. Please complete the lessons first.";
}

if (!$can_take_quiz) {
    redirect("view_module.php?module_id=" . $module_id);
}

// --- Quiz Submission Handling (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['quiz_id']) || (int)$_POST['quiz_id'] !== $quiz_id) {
        $_SESSION['error_message'] = "Quiz ID mismatch during submission.";
        redirect("take_quiz.php?quiz_id=" . $quiz_id);
    }
    // CSRF token check would be good here

    $submitted_answers = $_POST['answers'] ?? []; // e.g., [question_id => answer_id]

    // 1. Create a new attempt record in quiz_results
    $attempt_number = $quizResultHandler->getNextAttemptNumber($user_id, $quiz_id);
    $quiz_result_id = $quizResultHandler->createAttempt($user_id, $quiz_id, $attempt_number);

    if (!$quiz_result_id) {
        $_SESSION['error_message'] = "Failed to start your quiz attempt. Please try again.";
        redirect("take_quiz.php?quiz_id=" . $quiz_id);
    }

    // 2. Update module progress to 'quiz_in_progress' (or similar)
    // Avoid changing status if it was 'failed' and now retaking, or 'passed' and retaking.
    if ($module_progress && !in_array($module_progress->status, ['failed', 'passed'])) {
         $userModuleProgressHandler->updateModuleStatus($user_id, $module_id, 'quiz_in_progress');
    }


    // 3. Record submitted answers
    $questions_in_quiz = $questionHandler->getQuestionsByQuizId($quiz_id);
    foreach ($questions_in_quiz as $question) {
        $question_id = $question->question_id;
        $selected_answer_id = $submitted_answers[$question_id] ?? null;

        if ($selected_answer_id) {
            $answer_obj = $answerHandler->getAnswerById($selected_answer_id);
            if ($answer_obj && $answer_obj->question_id == $question_id) { // Validate answer belongs to question
                $is_correct = (bool)$answer_obj->is_correct;
                $quizResultHandler->recordAnswer($quiz_result_id, $question_id, $selected_answer_id, $is_correct);
            } else {
                // Handle invalid answer ID or answer not belonging to question - log it, maybe record as incorrect
                $quizResultHandler->recordAnswer($quiz_result_id, $question_id, $selected_answer_id ?: 0, false); // Record with 0 if null or invalid
                 error_log("Invalid answer_id $selected_answer_id for question_id $question_id in quiz_result_id $quiz_result_id.");
            }
        } else {
            // No answer submitted for this question - record as incorrect (or handle as unanswered)
            // For now, we need an answer_id. This implies we might need a "null" answer or skip recording.
            // Let's assume for now that not answering means it will be marked wrong in scoring.
            // Or, if schema allows NULL answer_id in user_question_answers:
            // $quizResultHandler->recordAnswer($quiz_result_id, $question_id, null, false);
            // For now, we skip recording if no answer is selected. Scoring will count it as incorrect.
        }
    }

    // 4. Redirect to a results page (to be created in next step)
    // This page will handle scoring and display.
    $_SESSION['message'] = "Quiz submitted successfully! Your results will be calculated.";
    redirect("quiz_summary.php?attempt_id=" . $quiz_result_id); // Changed from quiz_result_page.php
    exit;
}


// --- Display Quiz Form (GET) ---
$questions = $questionHandler->getQuestionsByQuizId($quiz_id);
$question_answers_map = [];
foreach ($questions as $q) {
    $question_answers_map[$q->question_id] = $answerHandler->getAnswersByQuestionId($q->question_id);
}

include_once '../../src/includes/employee_header.php';
?>

<p><a href="view_module.php?module_id=<?php echo escape_html($module_id); ?>" class="e-button secondary">&laquo; Back to Module: <?php echo escape_html($module->title); ?></a></p>

<h1><?php echo escape_html($quiz->title); ?></h1>
<p>Module: <?php echo escape_html($module->title); ?></p>
<p>Please read each question carefully and select the best answer.</p>
<p>Passing Threshold: <?php echo escape_html($quiz->passing_threshold); ?>%</p>

<?php if (empty($questions)): ?>
    <div class="message error">This quiz currently has no questions. Please contact an administrator.</div>
<?php else: ?>
    <form action="take_quiz.php?quiz_id=<?php echo $quiz_id; ?>" method="POST">
        <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
        <!-- CSRF token -->

        <?php foreach ($questions as $index => $question): ?>
            <fieldset style="margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                <legend style="font-weight: bold; font-size: 1.1em;">Question <?php echo $index + 1; ?>:</legend>
                <p><?php echo nl2br(escape_html($question->question_text)); ?></p>

                <?php $answers_for_question = $question_answers_map[$question->question_id] ?? []; ?>
                <?php if (empty($answers_for_question)): ?>
                    <p class="message error">No answers available for this question.</p>
                <?php else: ?>
                    <?php foreach ($answers_for_question as $answer): ?>
                        <div style="margin-bottom: 10px;">
                            <input type="radio"
                                   name="answers[<?php echo escape_html($question->question_id); ?>]"
                                   id="answer_<?php echo escape_html($answer->answer_id); ?>"
                                   value="<?php echo escape_html($answer->answer_id); ?>"
                                   required> <!-- Make selection required per question -->
                            <label for="answer_<?php echo escape_html($answer->answer_id); ?>"><?php echo escape_html($answer->answer_text); ?></label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </fieldset>
        <?php endforeach; ?>

        <button type="submit" class="e-button success" style="font-size: 1.2em; padding: 12px 25px;">Submit Quiz</button>
    </form>
<?php endif; ?>

<?php
echo "</main>"; // Close main.e-container from header
ob_end_flush();
?>
</body>
</html>
