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
include_once '../../src/includes/admin_header.php';
?>

<div class="card <?php echo $edit_module_data ? 'border-primary' : 'border-secondary'; ?> mb-4">
    <div class="card-header">
        <h3 class="mb-0"><?php echo $edit_module_data ? 'Edit Module: ' . escape_html($edit_module_data->title) : 'Create New Module'; ?></h3>
    </div>
    <div class="card-body">
        <form action="manage_modules.php" method="POST">
            <!-- <input type="hidden" name="csrf_token" value="<?php // echo $_SESSION['csrf_token']; ?>"> -->
            <?php if ($edit_module_data): ?>
                <input type="hidden" name="module_id" value="<?php echo escape_html($edit_module_data->module_id); ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="title" class="form-label">Module Title:</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo escape_html($edit_module_data->title ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description:</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?php echo escape_html($edit_module_data->description ?? ''); ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="deadline" class="form-label">Deadline (Optional):</label>
                    <input type="date" class="form-control" id="deadline" name="deadline" value="<?php echo escape_html($edit_module_data->deadline ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Status:</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="inactive" <?php echo (isset($edit_module_data->status) && $edit_module_data->status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="active" <?php echo (isset($edit_module_data->status) && $edit_module_data->status == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="archived" <?php echo (isset($edit_module_data->status) && $edit_module_data->status == 'archived') ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn <?php echo $edit_module_data ? 'btn-primary' : 'btn-success'; ?>"><?php echo $form_button_text; ?></button>
            <?php if ($edit_module_data): ?>
                <a href="manage_modules.php" class="btn btn-secondary ms-2">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>
</div>


<h2 class="mt-4 mb-3">Existing Modules</h2>
<?php if (empty($allModules)): ?>
    <div class="alert alert-info">No modules found. Create one above!</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
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
                        <td><span class="badge bg-<?php echo ($module->status === 'active' ? 'success' : ($module->status === 'archived' ? 'secondary' : 'warning text-dark')); ?>"><?php echo escape_html(ucfirst($module->status)); ?></span></td>
                        <td><?php echo escape_html(date('Y-m-d H:i', strtotime($module->created_at))); ?></td>
                        <td><?php echo escape_html(date('Y-m-d H:i', strtotime($module->updated_at))); ?></td>
                        <td>
                            <a href="manage_lessons.php?module_id=<?php echo escape_html($module->module_id); ?>" class="btn btn-sm btn-info mb-1">Lessons</a>
                            <a href="manage_quizzes.php?module_id=<?php echo escape_html($module->module_id); ?>" class="btn btn-sm btn-purple mb-1" style="background-color:#6f42c1; color:white;">Quizzes</a>
                            <a href="manage_modules.php?edit_id=<?php echo escape_html($module->module_id); ?>" class="btn btn-sm btn-warning mb-1">Edit</a>
                            <a href="manage_modules.php?delete_id=<?php echo escape_html($module->module_id); ?>&csrf_token_get=<?php // echo $_SESSION['csrf_token_get']; ?>"
                               class="btn btn-sm btn-danger mb-1"
                               onclick="return confirm('Are you sure you want to delete this module? This may also delete related lessons and quizzes.');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
echo "</div>"; // Close .container from admin_header.php
ob_end_flush();
?>
</body>
</html>
