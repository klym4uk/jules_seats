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
$page_title = "Quiz Summary";

// Instantiate handlers
$quizHandler = new Quiz($pdo);
$questionHandler = new Question($pdo);
$answerHandler = new Answer($pdo);
$userModuleProgressHandler = new UserModuleProgress($pdo);
$quizResultHandler = new QuizResult($pdo);
$moduleHandler = new Module($pdo);


// Validate attempt_id from GET parameter
if (!isset($_GET['attempt_id']) || !filter_var($_GET['attempt_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid attempt ID specified.";
    redirect('dashboard.php');
}
$attempt_id = (int)$_GET['attempt_id'];

// Fetch the quiz attempt details
$attempt = $quizResultHandler->getAttemptById($attempt_id); // This method was added in QuizResult.php

if (!$attempt) {
    $_SESSION['error_message'] = "Quiz attempt not found.";
    redirect('dashboard.php');
}

// Ensure the attempt belongs to the logged-in user
if ($attempt->user_id != $user_id) {
    $_SESSION['error_message'] = "You are not authorized to view this quiz summary.";
    redirect('dashboard.php');
}

$quiz_id = $attempt->quiz_id;
$quiz = $quizHandler->getQuizById($quiz_id); // Fetches quiz details including passing_threshold
$module_id = $attempt->module_id; // from getAttemptById which joins modules
$module = $moduleHandler->getModuleById($module_id);


if (!$quiz || !$module) {
    $_SESSION['error_message'] = "Quiz or module details could not be loaded for this attempt.";
    redirect('dashboard.php');
}
$page_title = "Summary for: " . escape_html($quiz->title);

// --- Scoring Logic & DB Update (Run only if not already scored) ---
// Check if completed_at is set. If it is, results are already processed.
if ($attempt->completed_at === null) {
    $all_questions_for_quiz = $questionHandler->getQuestionsByQuizId($quiz_id);
    $total_questions = count($all_questions_for_quiz);
    $user_submitted_answers_for_attempt = $quizResultHandler->getSubmittedAnswersForAttempt($attempt_id);

    $correct_answers_count = 0;
    foreach ($user_submitted_answers_for_attempt as $submitted_answer) {
        if ($submitted_answer->is_correct) {
            $correct_answers_count++;
        }
    }

    $score_percentage = ($total_questions > 0) ? round(($correct_answers_count / $total_questions) * 100, 2) : 0;
    $passed_status = ($score_percentage >= $quiz->passing_threshold);

    // Update quiz_results table
    $quizResultHandler->updateAttemptScoreAndStatus($attempt_id, $score_percentage, $passed_status);

    // Update user_module_progress table
    $new_module_status = $passed_status ? 'passed' : 'failed';
    $userModuleProgressHandler->updateModuleStatus($user_id, $module_id, $new_module_status);

    // Refresh attempt data after updates
    $attempt = $quizResultHandler->getAttemptById($attempt_id);
    $_SESSION['message'] = "Your quiz has been scored!"; // Flash message for first time view
}

// Data for displaying feedback (fetched again or using already fetched $attempt)
$score_percentage = $attempt->score;
$passed_status = (bool)$attempt->passed;
$user_submitted_answers_details = $quizResultHandler->getSubmittedAnswersForAttempt($attempt_id); // Fetches with text
$all_questions_for_quiz_display = $questionHandler->getQuestionsByQuizId($quiz_id);


// Helper to quickly find user's answer for a question_id
$user_answers_map = [];
foreach ($user_submitted_answers_details as $sub_ans) {
    $user_answers_map[$sub_ans->question_id] = $sub_ans;
}


include_once '../../src/includes/employee_header.php';
?>

<h1 class="mb-3"><?php echo escape_html($quiz->title); ?> - Attempt #<?php echo escape_html($attempt->attempt_number); ?></h1>
<p class="text-muted">Module: <?php echo escape_html($module->title); ?></p>

<div class="card shadow-sm mb-4 <?php echo $passed_status ? 'border-success' : 'border-danger'; ?>">
    <div class="card-header text-white <?php echo $passed_status ? 'bg-success' : 'bg-danger'; ?>">
        <h4 class="mb-0">Quiz Result</h4>
    </div>
    <div class="card-body text-center">
        <h2 class="card-title">Your Score: <span class="<?php echo $passed_status ? 'text-success' : 'text-danger'; ?>"><?php echo escape_html($score_percentage); ?>%</span></h2>
        <h3>Status:
            <?php if ($passed_status): ?>
                <span class="badge bg-success fs-5">Passed</span>
            <?php else: ?>
                <span class="badge bg-danger fs-5">Failed</span>
            <?php endif; ?>
        </h3>
        <p class="card-text">Passing Threshold: <?php echo escape_html($quiz->passing_threshold); ?>%</p>
    </div>
</div>

<h2 class="mt-4 mb-3">Detailed Breakdown:</h2>
<?php if (empty($all_questions_for_quiz_display)): ?>
    <div class="alert alert-info">No questions found for this quiz to display breakdown.</div>
<?php else: ?>
    <?php foreach ($all_questions_for_quiz_display as $index => $question): ?>
        <?php
            $user_answer_for_this_question = $user_answers_map[$question->question_id] ?? null;
            $is_user_correct = $user_answer_for_this_question && $user_answer_for_this_question->is_correct;
            $card_border_class = $is_user_correct ? 'border-success' : ($user_answer_for_this_question ? 'border-danger' : 'border-secondary');
        ?>
        <div class="card mb-3 <?php echo $card_border_class; ?>">
            <div class="card-header <?php echo $is_user_correct ? 'bg-success text-white' : ($user_answer_for_this_question ? 'bg-danger text-white' : 'bg-light'); ?>">
                <h5 class="mb-0">Question <?php echo $index + 1; ?>: <?php echo nl2br(escape_html($question->question_text)); ?></h5>
            </div>
            <div class="card-body">
                <?php
                $possible_answers = $answerHandler->getAnswersByQuestionId($question->question_id);
                $correct_answer_text = 'Not defined'; // Should always be defined if answers exist
                foreach($possible_answers as $pa) { if ($pa->is_correct) $correct_answer_text = $pa->answer_text; }
                ?>

                <?php if ($user_answer_for_this_question): ?>
                    <p>
                        <strong>Your Answer:</strong> <?php echo escape_html($user_answer_for_this_question->chosen_answer_text); ?>
                        <?php if ($is_user_correct): ?>
                            <span class="badge bg-success">Correct</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Incorrect</span>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="text-muted"><em>You did not answer this question.</em></p>
                <?php endif; ?>

                <?php if (!$is_user_correct): ?>
                    <p class="text-success"><strong>Correct Answer:</strong> <?php echo escape_html($correct_answer_text); ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>


<div class="mt-4 pt-3 border-top">
    <?php if (!$passed_status): ?>
        <?php if ($quizResultHandler->canRetakeQuiz($user_id, $quiz_id, $quizHandler)): ?>
            <a href="take_quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-warning btn-lg me-2">Retake Quiz</a>
        <?php else:
            $latest_attempt_for_cooldown = $quizResultHandler->getLatestAttempt($user_id, $quiz_id);
            if ($latest_attempt_for_cooldown && $latest_attempt_for_cooldown->completed_at) {
                 $completed_at_ts = strtotime($latest_attempt_for_cooldown->completed_at);
                 $cooldown_ends_ts = $completed_at_ts + ($quiz->cooldown_period_hours * 3600);
                 $cooldown_ends_date = date('Y-m-d H:i:s', $cooldown_ends_ts);
                 $time_remaining_seconds = $cooldown_ends_ts - time();

                 $hours = floor($time_remaining_seconds / 3600);
                 $minutes = floor(($time_remaining_seconds % 3600) / 60);

                echo '<div class="alert alert-warning">You can retake this quiz after the cooldown period. ';
                if ($time_remaining_seconds > 0) {
                    echo 'Time remaining: approximately ' . $hours . ' hours and ' . $minutes . ' minutes (available after ' . $cooldown_ends_date . ').';
                } else {
                    echo 'Cooldown should be over. Try refreshing or <a href="take_quiz.php?quiz_id='.$quiz_id.'" class="alert-link">click here to try retake</a>.';
                }
                echo '</div>';
            }
        ?>
        <?php endif; ?>
    <?php endif; ?>
    <a href="view_module.php?module_id=<?php echo escape_html($module_id); ?>" class="btn btn-outline-primary me-2">Back to Module</a>
    <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
</div>


<?php
// The main container div is opened in employee_header.php and should be closed here.
echo "</div>"; // Close .container from employee_header.php
ob_end_flush();
?>
</body>
</html>
