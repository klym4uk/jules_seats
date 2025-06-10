<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
require_once '../../src/User.php'; // For require_login context
require_once '../../src/Module.php';
require_once '../../src/Lesson.php';
require_once '../../src/UserModuleProgress.php';
require_once '../../src/UserLessonProgress.php';

start_session_if_not_started();
require_login('Employee');

$user_id = $_SESSION['user_id'];

if (!isset($_GET['lesson_id']) || !filter_var($_GET['lesson_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid lesson specified.";
    redirect('dashboard.php'); // Or back to module if possible
}
$lesson_id = (int)$_GET['lesson_id'];

$lessonHandler = new Lesson($pdo);
$userLessonProgressHandler = new UserLessonProgress($pdo);
$userModuleProgressHandler = new UserModuleProgress($pdo); // For updating module status

$lesson = $lessonHandler->getLessonById($lesson_id);
if (!$lesson) {
    $_SESSION['error_message'] = "Lesson not found.";
    redirect('dashboard.php'); // Or back to module if possible
}

// Fetch module for context and navigation
$moduleHandler = new Module($pdo);
$module = $moduleHandler->getModuleById($lesson->module_id);
if (!$module) {
    $_SESSION['error_message'] = "Module for this lesson not found.";
    redirect('dashboard.php');
}


$page_title = "Lesson: " . escape_html($lesson->title);

// Mark lesson as 'viewed' (or 'in_progress') when user accesses it
$userLessonProgressHandler->startLesson($user_id, $lesson_id);
$lesson_progress = $userLessonProgressHandler->getLessonProgress($user_id, $lesson_id);

// Handle "Mark as Complete" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_complete') {
    // CSRF token check would be good here
    if ($userLessonProgressHandler->markLessonComplete($user_id, $lesson_id)) {
        $_SESSION['message'] = "Lesson marked as complete!";
        // Check if all lessons in the module are now complete
        if ($userModuleProgressHandler->areAllLessonsCompleted($user_id, $lesson->module_id)) {
            // areAllLessonsCompleted already updates module status (e.g., to 'quiz_available' or 'training_completed')
            $_SESSION['message'] .= " All lessons in the module completed.";
             $updated_module_progress = $userModuleProgressHandler->getModuleProgress($user_id, $lesson->module_id);
             if ($updated_module_progress && $updated_module_progress->status === 'quiz_available') {
                 $_SESSION['message'] .= " Quiz is now available!";
             } elseif ($updated_module_progress && $updated_module_progress->status === 'training_completed') {
                  $_SESSION['message'] .= " Module training complete!";
             }
        }
    } else {
        $_SESSION['error_message'] = "Failed to mark lesson as complete.";
    }
    redirect("view_lesson.php?lesson_id=" . $lesson_id); // Redirect to refresh status and show message
}

// Navigation: Previous/Next Lesson
$all_lessons_in_module = $lessonHandler->getLessonsByModuleId($lesson->module_id); // Assumes ordered by order_in_module
$current_lesson_index = -1;
$prev_lesson_id = null;
$next_lesson_id = null;

foreach ($all_lessons_in_module as $index => $l) {
    if ($l->lesson_id == $lesson_id) {
        $current_lesson_index = $index;
        break;
    }
}

if ($current_lesson_index > 0) {
    $prev_lesson_id = $all_lessons_in_module[$current_lesson_index - 1]->lesson_id;
}
if ($current_lesson_index < (count($all_lessons_in_module) - 1) && $current_lesson_index != -1) {
    $next_lesson_id = $all_lessons_in_module[$current_lesson_index + 1]->lesson_id;
}


include_once '../../src/includes/employee_header.php';

$lesson_status_key = strtolower($lesson_progress->status ?? 'not_viewed');
$lesson_badge_bg_class = 'bg-light text-dark'; // Default for not_viewed
$icon_status = '<i class="fas fa-eye-slash me-2"></i>'; // Placeholder

switch ($lesson_status_key) {
    case 'viewed': $lesson_badge_bg_class = 'bg-warning text-dark'; $icon_status = '<i class="fas fa-eye me-2"></i>'; break;
    case 'completed': $lesson_badge_bg_class = 'bg-success'; $icon_status = '<i class="fas fa-check-circle me-2"></i>'; break;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><?php echo escape_html($lesson->title); ?></h1>
    <a href="view_module.php?module_id=<?php echo escape_html($lesson->module_id); ?>" class="btn btn-outline-secondary">&laquo; Back to Module: <?php echo escape_html($module->title); ?></a>
</div>


<div class="alert alert-light d-flex align-items-center" role="alert">
   <?php // echo $icon_status; // Icon would show here ?>
   <strong class="me-2">Status:</strong>
   <span class="status-badge <?php echo $lesson_badge_bg_class; ?>">
        <?php echo escape_html(ucwords(str_replace('_', ' ', $lesson_progress->status ?? 'Not Viewed'))); ?>
   </span>
</div>


<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Lesson Content</h5>
    </div>
    <div class="card-body">
        <?php if ($lesson->content_type === 'text'): ?>
            <div class="lesson-content-text">
                <?php echo nl2br(escape_html($lesson->content_text)); ?>
            </div>
        <?php elseif ($lesson->content_type === 'video' && !empty($lesson->content_url)): ?>
            <div class="lesson-content-video embed-responsive embed-responsive-16by9">
                <?php
                if (strpos($lesson->content_url, 'youtube.com/watch?v=') !== false) {
                    $video_id = substr($lesson->content_url, strpos($lesson->content_url, 'v=') + 2);
                    $embed_url = 'https://www.youtube.com/embed/' . $video_id;
                    echo '<iframe class="embed-responsive-item" src="' . escape_html($embed_url) . '" allowfullscreen style="width:100%; min-height: 400px;"></iframe>';
                } elseif (strpos($lesson->content_url, 'youtu.be/') !== false) {
                    $video_id = substr($lesson->content_url, strpos($lesson->content_url, 'youtu.be/') + 9);
                    $embed_url = 'https://www.youtube.com/embed/' . $video_id;
                    echo '<iframe class="embed-responsive-item" src="' . escape_html($embed_url) . '" allowfullscreen style="width:100%; min-height: 400px;"></iframe>';
                } else {
                    echo '<div class="alert alert-warning">Video URL is not a supported YouTube format for embedding. <a href="'.escape_html($lesson->content_url).'" target="_blank" class="alert-link">Watch video directly</a></div>';
                }
                ?>
            </div>
        <?php elseif ($lesson->content_type === 'image' && !empty($lesson->content_url)): ?>
            <div class="lesson-content-image text-center">
                <img src="<?php echo escape_html($lesson->content_url); ?>" alt="<?php echo escape_html($lesson->title); ?>" class="img-fluid rounded shadow-sm" style="max-height: 600px;">
            </div>
        <?php else: ?>
            <div class="alert alert-info">No content available for this lesson type or URL is missing.</div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4 mb-3 text-center">
    <?php if ($lesson_progress && $lesson_progress->status !== 'completed'): ?>
        <form action="view_lesson.php?lesson_id=<?php echo $lesson_id; ?>" method="POST" class="d-inline-block">
            <input type="hidden" name="action" value="mark_complete">
            <button type="submit" class="btn btn-success btn-lg">
                 <i class="fas fa-check-square me-2"></i>Mark as Complete
            </button>
        </form>
    <?php else: ?>
        <div class="alert alert-success d-inline-block"><i class="fas fa-check-circle me-2"></i>You have completed this lesson.</div>
    <?php endif; ?>
</div>

<nav aria-label="Lesson navigation" class="mt-4">
    <ul class="pagination justify-content-between">
        <li class="page-item <?php if (!$prev_lesson_id) echo 'disabled'; ?>">
            <a class="page-link" href="<?php if ($prev_lesson_id) echo 'view_lesson.php?lesson_id='.$prev_lesson_id; else echo '#'; ?>" tabindex="-1" aria-disabled="<?php echo !$prev_lesson_id ? 'true' : 'false'; ?>">
                &laquo; Previous Lesson
            </a>
        </li>
        <li class="page-item <?php if (!$next_lesson_id) echo 'disabled'; ?>">
            <a class="page-link" href="<?php if ($next_lesson_id) echo 'view_lesson.php?lesson_id='.$next_lesson_id; else echo '#'; ?>">
                Next Lesson &raquo;
            </a>
        </li>
    </ul>
</nav>


<?php
// The main container div is opened in employee_header.php and should be closed here.
echo "</div>"; // Close .container from employee_header.php
ob_end_flush();
?>
</body>
</html>
