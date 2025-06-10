<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
require_once '../../src/User.php'; // For require_login context
require_once '../../src/Module.php';
require_once '../../src/Lesson.php';
require_once '../../src/Quiz.php'; // To get quiz_id for the module
require_once '../../src/UserModuleProgress.php';
require_once '../../src/UserLessonProgress.php';

start_session_if_not_started();
require_login('Employee');

$user_id = $_SESSION['user_id'];

if (!isset($_GET['module_id']) || !filter_var($_GET['module_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid module specified.";
    redirect('dashboard.php');
}
$module_id = (int)$_GET['module_id'];

$moduleHandler = new Module($pdo);
$lessonHandler = new Lesson($pdo);
$quizHandler = new Quiz($pdo); // Instantiate QuizHandler
$userModuleProgressHandler = new UserModuleProgress($pdo);
$userLessonProgressHandler = new UserLessonProgress($pdo);

$module = $moduleHandler->getModuleById($module_id);
if (!$module || $module->status !== 'active') { // Also check if module is active
    $_SESSION['error_message'] = "Module not found or is not currently active.";
    redirect('dashboard.php');
}

$page_title = "Module: " . escape_html($module->title);

// Attempt to start the module for the user (creates progress record if not exists, or sets to 'in_progress')
$userModuleProgressHandler->startModule($user_id, $module_id);
$module_progress = $userModuleProgressHandler->getModuleProgress($user_id, $module_id);


$lessons = $lessonHandler->getLessonsByModuleId($module_id);
$lessonProgressMap = [];
foreach ($lessons as $lesson) {
    $progress = $userLessonProgressHandler->getLessonProgress($user_id, $lesson->lesson_id);
    if ($progress) {
        $lessonProgressMap[$lesson->lesson_id] = $progress->status;
    } else {
        $lessonProgressMap[$lesson->lesson_id] = 'not_viewed';
    }
}

// Check if all lessons are completed to potentially unlock the quiz
$all_lessons_completed = $userModuleProgressHandler->areAllLessonsCompleted($user_id, $module_id);
// Fetch the current module progress again, as areAllLessonsCompleted might have updated it
$module_progress = $userModuleProgressHandler->getModuleProgress($user_id, $module_id);


$quiz_available_for_module = false;
$quiz_id_for_module = null;
if ($module_progress && $module_progress->status === 'quiz_available') {
    $quizzesInModule = $quizHandler->getQuizzesByModuleId($module_id); // Assuming one quiz per module for now
    if (!empty($quizzesInModule)) {
        $quiz_available_for_module = true;
        $quiz_id_for_module = $quizzesInModule[0]->quiz_id; // Take the first quiz found
    }
}


include_once '../../src/includes/employee_header.php';

$module_status_key = strtolower($module_progress->status ?? 'not_started');
$module_badge_bg_class = 'bg-secondary'; // Default for not_started
switch ($module_status_key) {
    case 'in_progress': $module_badge_bg_class = 'bg-warning text-dark'; break;
    case 'training_completed': $module_badge_bg_class = 'bg-info'; break;
    case 'quiz_available': $module_badge_bg_class = 'bg-orange'; break;
    case 'quiz_in_progress': $module_badge_bg_class = 'bg-info text-dark'; break;
    case 'passed': $module_badge_bg_class = 'bg-success'; break;
    case 'failed': $module_badge_bg_class = 'bg-danger'; break;
}

?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><?php echo escape_html($module->title); ?></h1>
    <a href="dashboard.php" class="btn btn-outline-secondary">&laquo; Back to Dashboard</a>
</div>

<div class="alert alert-light d-flex align-items-center" role="alert">
   <strong class="me-2">Module Status:</strong>
   <span class="status-badge <?php echo $module_badge_bg_class; ?>">
        <?php echo escape_html(ucwords(str_replace('_', ' ', $module_progress->status ?? 'Not Started'))); ?>
   </span>
</div>

<p class="lead"><?php echo nl2br(escape_html($module->description)); ?></p>

<h2 class="mt-4 mb-3">Lessons</h2>
<?php if (empty($lessons)): ?>
    <div class="alert alert-info">This module currently has no lessons.</div>
<?php else: ?>
    <div class="list-group shadow-sm">
        <?php foreach ($lessons as $lesson): ?>
            <?php
                $status = $lessonProgressMap[$lesson->lesson_id] ?? 'not_viewed';
                $lesson_status_key = strtolower($status);
                $lesson_badge_bg_class = 'bg-light text-dark'; // Default for not_viewed
                $icon = '<i class="fas fa-eye-slash me-2"></i>'; // Placeholder for FontAwesome

                switch ($lesson_status_key) {
                    case 'viewed': $lesson_badge_bg_class = 'bg-warning text-dark'; $icon = '<i class="fas fa-eye me-2"></i>'; break;
                    case 'completed': $lesson_badge_bg_class = 'bg-success'; $icon = '<i class="fas fa-check-circle me-2"></i>'; break;
                }
            ?>
            <a href="view_lesson.php?lesson_id=<?php echo escape_html($lesson->lesson_id); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <?php // echo $icon; // Icon would show here ?>
                    <?php echo escape_html($lesson->title); ?>
                    <small class="d-block text-muted">Type: <?php echo escape_html(ucfirst($lesson->content_type)); ?></small>
                </div>
                <span class="status-badge <?php echo $lesson_badge_bg_class; ?>">
                    <?php echo escape_html(ucwords(str_replace('_', ' ', $status))); ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card mt-4 shadow-sm">
    <div class="card-header">
        <h3 class="mb-0">Module Quiz</h3>
    </div>
    <div class="card-body text-center">
        <?php if ($module_progress && $module_progress->status === 'passed'): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Congratulations! You have passed the quiz for this module.</div>
        <?php elseif ($module_progress && $module_progress->status === 'failed'): ?>
            <div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>You have previously failed the quiz for this module.</div>
            <?php if ($quiz_available_for_module && $quiz_id_for_module): ?>
                 <a href="take_quiz.php?quiz_id=<?php echo escape_html($quiz_id_for_module); ?>" class="btn btn-warning btn-lg mt-2">Attempt Quiz Again</a>
            <?php else: ?>
                <p class="mt-2">Please check the cooldown period or ensure all prerequisite lessons are completed.</p>
            <?php endif; ?>
        <?php elseif ($quiz_available_for_module && $quiz_id_for_module): ?>
            <p>You have completed all lessons. The quiz is now available.</p>
            <a href="take_quiz.php?quiz_id=<?php echo escape_html($quiz_id_for_module); ?>" class="btn btn-success btn-lg mt-2">
                <i class="fas fa-play-circle me-2"></i>Start Quiz
            </a>
        <?php elseif ($all_lessons_completed && !$quiz_available_for_module): ?>
             <div class="alert alert-info">You have completed all training for this module. No quiz is associated with this module.</div>
        <?php else: ?>
            <div class="alert alert-info">Please complete all lessons in this module to unlock the quiz.</div>
        <?php endif; ?>
    </div>
</div>

<?php
// The main container div is opened in employee_header.php and should be closed here.
echo "</div>"; // Close .container from employee_header.php
ob_end_flush();
?>
</body>
</html>
