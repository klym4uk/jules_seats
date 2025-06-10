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
?>

<p><a href="view_module.php?module_id=<?php echo escape_html($lesson->module_id); ?>" class="e-button secondary">&laquo; Back to Module: <?php echo escape_html($module->title); ?></a></p>

<h1><?php echo escape_html($lesson->title); ?></h1>
<p class="e-button secondary" style="margin-bottom:20px; display:inline-block;">Status:
    <span class="status-badge status-<?php echo escape_html(strtolower($lesson_progress->status ?? 'not_viewed')); ?>">
        <?php echo escape_html(ucwords(str_replace('_', ' ', $lesson_progress->status ?? 'Not Viewed'))); ?>
    </span>
</p>

<div class="lesson-content">
    <?php if ($lesson->content_type === 'text'): ?>
        <div class="lesson-content-text">
            <?php echo nl2br(escape_html($lesson->content_text)); // Using escape_html for safety, consider Markdown parsing for rich text ?>
        </div>
    <?php elseif ($lesson->content_type === 'video' && !empty($lesson->content_url)): ?>
        <div class="lesson-content-video">
            <?php
            // Basic YouTube embed logic (can be expanded for Vimeo, etc.)
            if (strpos($lesson->content_url, 'youtube.com/watch?v=') !== false) {
                $video_id = substr($lesson->content_url, strpos($lesson->content_url, 'v=') + 2);
                $embed_url = 'https://www.youtube.com/embed/' . $video_id;
                echo '<iframe width="560" height="315" src="' . escape_html($embed_url) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            } elseif (strpos($lesson->content_url, 'youtu.be/') !== false) {
                $video_id = substr($lesson->content_url, strpos($lesson->content_url, 'youtu.be/') + 9);
                $embed_url = 'https://www.youtube.com/embed/' . $video_id;
                echo '<iframe width="560" height="315" src="' . escape_html($embed_url) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            } else {
                echo '<p>Video URL is not a supported format for embedding. <a href="'.escape_html($lesson->content_url).'" target="_blank">Watch video</a></p>';
            }
            ?>
        </div>
    <?php elseif ($lesson->content_type === 'image' && !empty($lesson->content_url)): ?>
        <div class="lesson-content-image">
            <img src="<?php echo escape_html($lesson->content_url); ?>" alt="<?php echo escape_html($lesson->title); ?>">
        </div>
    <?php else: ?>
        <p class="message info">No content available for this lesson type or URL is missing.</p>
    <?php endif; ?>
</div>

<div style="margin-top: 30px; padding-top:20px; border-top: 1px solid #eee;">
    <?php if ($lesson_progress && $lesson_progress->status !== 'completed'): ?>
        <form action="view_lesson.php?lesson_id=<?php echo $lesson_id; ?>" method="POST" style="display: inline;">
            <!-- CSRF token -->
            <input type="hidden" name="action" value="mark_complete">
            <button type="submit" class="e-button success">Mark as Complete</button>
        </form>
    <?php else: ?>
        <p class="message success">You have completed this lesson.</p>
    <?php endif; ?>
</div>

<div style="margin-top: 30px; overflow: hidden;">
    <?php if ($prev_lesson_id): ?>
        <a href="view_lesson.php?lesson_id=<?php echo $prev_lesson_id; ?>" class="e-button" style="float: left;">&laquo; Previous Lesson</a>
    <?php endif; ?>
    <?php if ($next_lesson_id): ?>
        <a href="view_lesson.php?lesson_id=<?php echo $next_lesson_id; ?>" class="e-button" style="float: right;">Next Lesson &raquo;</a>
    <?php endif; ?>
</div>


<?php
echo "</main>"; // Close main.e-container from header
ob_end_flush();
?>
</body>
</html>
