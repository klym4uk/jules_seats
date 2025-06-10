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
?>

<h1><?php echo escape_html($module->title); ?></h1>
<p class="e-button secondary" style="margin-bottom:20px; display:inline-block;">Current Status:
    <span class="status-badge status-<?php echo escape_html(strtolower($module_progress->status ?? 'not_started')); ?>">
        <?php echo escape_html(ucwords(str_replace('_', ' ', $module_progress->status ?? 'Not Started'))); ?>
    </span>
</p>
<p><?php echo nl2br(escape_html($module->description)); ?></p>

<h2 style="margin-top:30px;">Lessons</h2>
<?php if (empty($lessons)): ?>
    <div class="message info">This module currently has no lessons.</div>
<?php else: ?>
    <ul class="lesson-list">
        <?php foreach ($lessons as $lesson): ?>
            <?php
                $status = $lessonProgressMap[$lesson->lesson_id] ?? 'not_viewed';
                $status_class = 'status-' . strtolower($status);
            ?>
            <li class="lesson-item">
                <h4>
                    <a href="view_lesson.php?lesson_id=<?php echo escape_html($lesson->lesson_id); ?>">
                        <?php echo escape_html($lesson->title); ?>
                    </a>
                    <span class="status-badge <?php echo $status_class; ?>" style="font-size: 0.7em; margin-left: 10px;">
                        <?php echo escape_html(ucwords(str_replace('_', ' ', $status))); ?>
                    </span>
                </h4>
                <p style="font-size: 0.9em;">Type: <?php echo escape_html(ucfirst($lesson->content_type)); ?></p>
                <a href="view_lesson.php?lesson_id=<?php echo escape_html($lesson->lesson_id); ?>" class="e-button">View Lesson</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<div style="margin-top: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">
    <h3>Module Quiz</h3>
    <?php if ($module_progress && $module_progress->status === 'passed'): ?>
        <p class="message success">Congratulations! You have passed the quiz for this module.</p>
    <?php elseif ($module_progress && $module_progress->status === 'failed'): ?>
        <p class="message error">You have previously failed the quiz for this module. Check cooldown period if applicable.</p>
        <?php if ($quiz_available_for_module && $quiz_id_for_module): ?>
             <a href="take_quiz.php?quiz_id=<?php echo escape_html($quiz_id_for_module); ?>" class="e-button">Attempt Quiz Again</a>
        <?php else: ?>
            <p>All lessons must be completed to make the quiz available.</p>
        <?php endif; ?>
    <?php elseif ($quiz_available_for_module && $quiz_id_for_module): ?>
        <p>You have completed all lessons. The quiz is now available.</p>
        <a href="take_quiz.php?quiz_id=<?php echo escape_html($quiz_id_for_module); ?>" class="e-button success">Start Quiz</a>
    <?php elseif ($all_lessons_completed && !$quiz_available_for_module): ?>
         <p class="message info">You have completed all training for this module. No quiz is associated with this module.</p>
    <?php else: ?>
        <p class="message info">Please complete all lessons in this module to unlock the quiz.</p>
    <?php endif; ?>
</div>


<p style="margin-top: 30px;"><a href="dashboard.php" class="e-button secondary">&laquo; Back to Dashboard</a></p>

<?php
echo "</main>"; // Close main.e-container from header
ob_end_flush();
?>
</body>
</html>
