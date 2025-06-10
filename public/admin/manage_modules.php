<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
require_once '../../src/User.php'; // For require_admin and session context
require_once '../../src/Module.php';

start_session_if_not_started();
require_admin();

$moduleHandler = new Module($pdo);
$userHandler = new User($pdo); // For session context if needed, already used by require_admin

$page_title = "Manage Modules";

// Handling form submissions for Create and Update
$edit_module_data = null; // For pre-filling edit form
$form_action = 'create_module'; // Default action
$form_button_text = 'Create Module';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token validation (basic example, consider a more robust solution)
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     $_SESSION['error_message'] = "CSRF token mismatch.";
    //     redirect('manage_modules.php');
    // }

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $deadline = !empty($_POST['deadline']) ? trim($_POST['deadline']) : null;
    $status = trim($_POST['status']);

    if (isset($_POST['module_id']) && !empty($_POST['module_id'])) { // UPDATE action
        $module_id = filter_input(INPUT_POST, 'module_id', FILTER_VALIDATE_INT);
        if ($module_id && $moduleHandler->updateModule($module_id, $title, $description, $deadline, $status)) {
            $_SESSION['message'] = "Module updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update module. Check input or logs.";
        }
    } else { // CREATE action
        $newModuleId = $moduleHandler->createModule($title, $description, $deadline, $status);
        if ($newModuleId) {
            $_SESSION['message'] = "Module created successfully! (ID: $newModuleId)";
        } else {
            $_SESSION['error_message'] = "Failed to create module. Check input or logs.";
        }
    }
    redirect('manage_modules.php'); // Redirect to clear POST data and show message
}

// Handling GET requests for Edit or Delete
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['edit_id'])) {
        $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
        if ($edit_id) {
            $edit_module_data = $moduleHandler->getModuleById($edit_id);
            if ($edit_module_data) {
                $form_action = 'update_module';
                $form_button_text = 'Update Module';
            } else {
                $_SESSION['error_message'] = "Module not found for editing.";
                redirect('manage_modules.php');
            }
        }
    } elseif (isset($_GET['delete_id'])) {
        // CSRF protection for GET delete (better to use POST for delete, but for simplicity with confirm link)
        // if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token_get']) {
        //      $_SESSION['error_message'] = "CSRF token mismatch for delete action.";
        //      redirect('manage_modules.php');
        // }
        $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
        if ($delete_id) {
            if ($moduleHandler->deleteModule($delete_id)) {
                $_SESSION['message'] = "Module deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to delete module. It might have related data that prevents deletion without cascade, or an error occurred.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid module ID for deletion.";
        }
        redirect('manage_modules.php'); // Redirect to clear GET params and show message
    }
}

// Generate CSRF tokens
// $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // For POST forms
// $_SESSION['csrf_token_get'] = bin2hex(random_bytes(32)); // For GET actions like delete

$allModules = $moduleHandler->getAllModules();
include_once '../../src/includes/admin_header.php'; // Header includes message display
?>

<?php if ($edit_module_data): ?>
<div class="edit-form-container">
    <h3>Edit Module: <?php echo escape_html($edit_module_data->title); ?></h3>
<?php else: ?>
    <h3>Create New Module</h3>
<?php endif; ?>

<form action="manage_modules.php" method="POST">
    <!-- <input type="hidden" name="csrf_token" value="<?php // echo $_SESSION['csrf_token']; ?>"> -->
    <?php if ($edit_module_data): ?>
        <input type="hidden" name="module_id" value="<?php echo escape_html($edit_module_data->module_id); ?>">
    <?php endif; ?>

    <div>
        <label for="title">Module Title:</label>
        <input type="text" id="title" name="title" value="<?php echo escape_html($edit_module_data->title ?? ''); ?>" required>
    </div>
    <div>
        <label for="description">Description:</label>
        <textarea id="description" name="description" rows="4"><?php echo escape_html($edit_module_data->description ?? ''); ?></textarea>
    </div>
    <div>
        <label for="deadline">Deadline (Optional):</label>
        <input type="date" id="deadline" name="deadline" value="<?php echo escape_html($edit_module_data->deadline ?? ''); ?>">
    </div>
    <div>
        <label for="status">Status:</label>
        <select id="status" name="status" required>
            <option value="inactive" <?php echo (isset($edit_module_data->status) && $edit_module_data->status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
            <option value="active" <?php echo (isset($edit_module_data->status) && $edit_module_data->status == 'active') ? 'selected' : ''; ?>>Active</option>
            <option value="archived" <?php echo (isset($edit_module_data->status) && $edit_module_data->status == 'archived') ? 'selected' : ''; ?>>Archived</option>
        </select>
    </div>
    <button type="submit"><?php echo $form_button_text; ?></button>
    <?php if ($edit_module_data): ?>
        <a href="manage_modules.php" class="button-link" style="background-color: #7f8c8d; margin-left:10px;">Cancel Edit</a>
    <?php endif; ?>
</form>
<?php if ($edit_module_data) echo '</div>'; // End edit-form-container ?>


<h2>Existing Modules</h2>
<?php if (empty($allModules)): ?>
    <p>No modules found. Create one above!</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Description</th>
                <th>Deadline</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allModules as $module): ?>
                <tr>
                    <td><?php echo escape_html($module->module_id); ?></td>
                    <td><?php echo escape_html($module->title); ?></td>
                    <td><?php echo escape_html(substr($module->description, 0, 50) . (strlen($module->description) > 50 ? '...' : '')); ?></td>
                    <td><?php echo escape_html($module->deadline ? date('M j, Y', strtotime($module->deadline)) : 'N/A'); ?></td>
                    <td><?php echo escape_html(ucfirst($module->status)); ?></td>
                    <td><?php echo escape_html(date('Y-m-d H:i', strtotime($module->created_at))); ?></td>
                    <td><?php echo escape_html(date('Y-m-d H:i', strtotime($module->updated_at))); ?></td>
                    <td class="action-links">
                        <a href="manage_lessons.php?module_id=<?php echo escape_html($module->module_id); ?>" class="button-link manage">Manage Lessons</a>
                        <a href="manage_quizzes.php?module_id=<?php echo escape_html($module->module_id); ?>" class="button-link manage" style="background-color:#9b59b6;">Manage Quizzes</a> <!-- Placeholder -->
                        <a href="manage_modules.php?edit_id=<?php echo escape_html($module->module_id); ?>" class="button-link edit">Edit</a>
                        <a href="manage_modules.php?delete_id=<?php echo escape_html($module->module_id); ?>&csrf_token_get=<?php // echo $_SESSION['csrf_token_get']; ?>"
                           class="button-link delete"
                           onclick="return confirm('Are you sure you want to delete this module? This may also delete related lessons and quizzes.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
echo "</main>"; // Close main.container from header
// include_once '../../src/includes/admin_footer.php';
ob_end_flush();
?>
</body>
</html>
