<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
require_once '../../src/User.php'; // For require_admin and session context
require_once '../../src/Module.php';
require_once '../../src/Lesson.php';

start_session_if_not_started();
require_admin();

$moduleHandler = new Module($pdo);
$lessonHandler = new Lesson($pdo);

$page_title = "Manage Lessons";

// Validate module_id from GET parameter
if (!isset($_GET['module_id']) || !filter_var($_GET['module_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid or missing module ID.";
    redirect('manage_modules.php');
}
$current_module_id = (int)$_GET['module_id'];
$current_module = $moduleHandler->getModuleById($current_module_id);

if (!$current_module) {
    $_SESSION['error_message'] = "Module not found.";
    redirect('manage_modules.php');
}

$page_title = "Manage Lessons for: " . escape_html($current_module->title);

// Handling form submissions for Create and Update Lessons
$edit_lesson_data = null;
$form_action = 'create_lesson';
$form_button_text = 'Create Lesson';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     $_SESSION['error_message'] = "CSRF token mismatch.";
    //     redirect("manage_lessons.php?module_id=" . $current_module_id);
    // }

    $module_id_form = filter_input(INPUT_POST, 'module_id', FILTER_VALIDATE_INT);
    if ($module_id_form !== $current_module_id) {
        $_SESSION['error_message'] = "Module ID mismatch in form submission.";
        redirect("manage_lessons.php?module_id=" . $current_module_id);
    }

    $title = trim($_POST['title']);
    $content_type = trim($_POST['content_type']);
    $content_text = ($content_type === 'text') ? trim($_POST['content_text']) : null;
    $content_url = ($content_type === 'video' || $content_type === 'image') ? trim($_POST['content_url']) : null;
    $order_in_module = filter_input(INPUT_POST, 'order_in_module', FILTER_VALIDATE_INT);

    if ($order_in_module === false || $order_in_module < 0) { // Check if filter_input failed or value is negative
        $_SESSION['error_message'] = "Order in module must be a non-negative integer.";
    } else {
        if (isset($_POST['lesson_id']) && !empty($_POST['lesson_id'])) { // UPDATE action
            $lesson_id = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);
            if ($lesson_id && $lessonHandler->updateLesson($lesson_id, $title, $content_type, $content_text, $content_url, $order_in_module)) {
                $_SESSION['message'] = "Lesson updated successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update lesson. Check input or logs.";
            }
        } else { // CREATE action
            $newLessonId = $lessonHandler->createLesson($current_module_id, $title, $content_type, $content_text, $content_url, $order_in_module);
            if ($newLessonId) {
                $_SESSION['message'] = "Lesson created successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to create lesson. Ensure all required fields for the content type are filled.";
            }
        }
    }
    redirect("manage_lessons.php?module_id=" . $current_module_id);
}

// Handling GET requests for Edit or Delete Lesson
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['module_id'])) { // module_id is already validated
    if (isset($_GET['edit_lesson_id'])) {
        $edit_lesson_id = filter_input(INPUT_GET, 'edit_lesson_id', FILTER_VALIDATE_INT);
        if ($edit_lesson_id) {
            $edit_lesson_data = $lessonHandler->getLessonById($edit_lesson_id);
            if ($edit_lesson_data && $edit_lesson_data->module_id == $current_module_id) { // Ensure lesson belongs to current module
                $form_action = 'update_lesson';
                $form_button_text = 'Update Lesson';
            } else {
                $_SESSION['error_message'] = "Lesson not found or does not belong to this module.";
                redirect("manage_lessons.php?module_id=" . $current_module_id);
            }
        }
    } elseif (isset($_GET['delete_lesson_id'])) {
        $delete_lesson_id = filter_input(INPUT_GET, 'delete_lesson_id', FILTER_VALIDATE_INT);
        if ($delete_lesson_id) {
            // Optional: Check if lesson belongs to module before deleting, though getLessonById could do this too.
            $lesson_to_delete = $lessonHandler->getLessonById($delete_lesson_id);
            if ($lesson_to_delete && $lesson_to_delete->module_id == $current_module_id) {
                if ($lessonHandler->deleteLesson($delete_lesson_id)) {
                    $_SESSION['message'] = "Lesson deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Failed to delete lesson.";
                }
            } else {
                 $_SESSION['error_message'] = "Lesson not found or not part of this module.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid lesson ID for deletion.";
        }
        redirect("manage_lessons.php?module_id=" . $current_module_id);
    }
}

// $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // For POST forms

$lessonsForModule = $lessonHandler->getLessonsByModuleId($current_module_id);
include_once '../../src/includes/admin_header.php';
?>

<p><a href="manage_modules.php" class="button-link" style="background-color: #7f8c8d;">&laquo; Back to Modules</a></p>

<?php if ($edit_lesson_data): ?>
<div class="edit-form-container">
    <h3>Edit Lesson: "<?php echo escape_html($edit_lesson_data->title); ?>"</h3>
<?php else: ?>
    <h3>Create New Lesson for "<?php echo escape_html($current_module->title); ?>"</h3>
<?php endif; ?>

<form action="manage_lessons.php?module_id=<?php echo $current_module_id; ?>" method="POST">
    <!-- <input type="hidden" name="csrf_token" value="<?php // echo $_SESSION['csrf_token']; ?>"> -->
    <input type="hidden" name="module_id" value="<?php echo $current_module_id; ?>">
    <?php if ($edit_lesson_data): ?>
        <input type="hidden" name="lesson_id" value="<?php echo escape_html($edit_lesson_data->lesson_id); ?>">
    <?php endif; ?>

    <div>
        <label for="title">Lesson Title:</label>
        <input type="text" id="title" name="title" value="<?php echo escape_html($edit_lesson_data->title ?? ''); ?>" required>
    </div>
    <div>
        <label for="order_in_module">Order in Module (e.g., 1, 2, 3):</label>
        <input type="number" id="order_in_module" name="order_in_module" value="<?php echo escape_html($edit_lesson_data->order_in_module ?? '0'); ?>" required min="0">
    </div>
    <div>
        <label for="content_type">Content Type:</label>
        <select id="content_type" name="content_type" required onchange="toggleContentFields()">
            <option value="text" <?php echo (isset($edit_lesson_data->content_type) && $edit_lesson_data->content_type == 'text') ? 'selected' : ''; ?>>Text</option>
            <option value="video" <?php echo (isset($edit_lesson_data->content_type) && $edit_lesson_data->content_type == 'video') ? 'selected' : ''; ?>>Video URL</option>
            <option value="image" <?php echo (isset($edit_lesson_data->content_type) && $edit_lesson_data->content_type == 'image') ? 'selected' : ''; ?>>Image URL</option>
        </select>
    </div>
    <div id="content_text_field">
        <label for="content_text">Content (Text):</label>
        <textarea id="content_text" name="content_text" rows="5"><?php echo escape_html($edit_lesson_data->content_text ?? ''); ?></textarea>
    </div>
    <div id="content_url_field">
        <label for="content_url">Content URL (for Video/Image):</label>
        <input type="text" id="content_url" name="content_url" value="<?php echo escape_html($edit_lesson_data->content_url ?? ''); ?>" placeholder="https://example.com/resource">
    </div>

    <button type="submit"><?php echo $form_button_text; ?></button>
    <?php if ($edit_lesson_data): ?>
        <a href="manage_lessons.php?module_id=<?php echo $current_module_id; ?>" class="button-link" style="background-color: #7f8c8d; margin-left:10px;">Cancel Edit</a>
    <?php endif; ?>
</form>
<?php if ($edit_lesson_data) echo '</div>'; // End edit-form-container ?>

<h2>Lessons in "<?php echo escape_html($current_module->title); ?>"</h2>
<?php if (empty($lessonsForModule)): ?>
    <p>No lessons found for this module. Create one above!</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Title</th>
                <th>Type</th>
                <th>Content Preview</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lessonsForModule as $lesson): ?>
                <tr>
                    <td><?php echo escape_html($lesson->order_in_module); ?></td>
                    <td><?php echo escape_html($lesson->title); ?></td>
                    <td><?php echo escape_html(ucfirst($lesson->content_type)); ?></td>
                    <td>
                        <?php
                        if ($lesson->content_type == 'text') {
                            echo escape_html(substr($lesson->content_text, 0, 50) . (strlen($lesson->content_text) > 50 ? '...' : ''));
                        } elseif ($lesson->content_url) {
                            echo '<a href="'.escape_html($lesson->content_url).'" target="_blank">View Resource</a>';
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                    <td class="action-links">
                        <a href="manage_lessons.php?module_id=<?php echo $current_module_id; ?>&edit_lesson_id=<?php echo escape_html($lesson->lesson_id); ?>" class="button-link edit">Edit</a>
                        <a href="manage_lessons.php?module_id=<?php echo $current_module_id; ?>&delete_lesson_id=<?php echo escape_html($lesson->lesson_id); ?>"
                           class="button-link delete"
                           onclick="return confirm('Are you sure you want to delete this lesson?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
function toggleContentFields() {
    var contentType = document.getElementById('content_type').value;
    var textField = document.getElementById('content_text_field');
    var urlField = document.getElementById('content_url_field');
    var textInput = document.getElementById('content_text');
    var urlInput = document.getElementById('content_url');

    if (contentType === 'text') {
        textField.style.display = 'block';
        textInput.required = true;
        urlField.style.display = 'none';
        urlInput.required = false;
    } else if (contentType === 'video' || contentType === 'image') {
        textField.style.display = 'none';
        textInput.required = false;
        urlField.style.display = 'block';
        urlInput.required = true;
    } else {
        textField.style.display = 'none';
        textInput.required = false;
        urlField.style.display = 'none';
        urlInput.required = false;
    }
}
// Initial call to set correct fields on page load (especially for edit)
document.addEventListener('DOMContentLoaded', function() {
    toggleContentFields();
});
</script>

<?php
echo "</main>"; // Close main.container from header
ob_end_flush();
?>
</body>
</html>
