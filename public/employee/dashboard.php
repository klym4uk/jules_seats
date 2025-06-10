<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
require_once '../../src/User.php'; // For require_login context
require_once '../../src/Module.php';
require_once '../../src/UserModuleProgress.php';

start_session_if_not_started();
require_login('Employee'); // Ensure only logged-in Employees can access

$user_id = $_SESSION['user_id'];
$page_title = "My Dashboard";

$moduleHandler = new Module($pdo);
$userModuleProgressHandler = new UserModuleProgress($pdo);

// Fetch all 'active' modules. In a more complex system, this might be "assigned" modules.
$activeModules = [];
$allDbModules = $moduleHandler->getAllModules(); // In future, filter by status = 'active' in the query itself
foreach ($allDbModules as $mod) {
    if ($mod->status === 'active') {
        $activeModules[] = $mod;
    }
}

// Fetch progress for these modules for the current user
$userProgressRecords = $userModuleProgressHandler->getAllModulesProgressForUser($user_id);
$moduleProgressMap = [];
foreach ($userProgressRecords as $progress) {
    $moduleProgressMap[$progress->module_id] = $progress;
}


include_once '../../src/includes/employee_header.php';
?>

<h1>Welcome, <?php echo escape_html($_SESSION['first_name']); ?>!</h1>
<p>Here are your available training modules. Click on a module to view its lessons and start learning.</p>

<?php if (empty($activeModules)): ?>
    <div class="message info">No training modules are currently available. Please check back later.</div>
<?php else: ?>
    <ul class="module-list">
        <?php foreach ($activeModules as $module): ?>
            <?php
                $progress = $moduleProgressMap[$module->module_id] ?? null;
                $status_text = 'Not Started';
                $status_class = 'status-not_started';
                if ($progress) {
                    $status_text = ucwords(str_replace('_', ' ', $progress->status));
                    $status_class = 'status-' . strtolower($progress->status);
                }
            ?>
            <li class="module-item">
                <h3>
                    <a href="view_module.php?module_id=<?php echo escape_html($module->module_id); ?>">
                        <?php echo escape_html($module->title); ?>
                    </a>
                </h3>
                <p><?php echo escape_html(substr($module->description, 0, 150) . (strlen($module->description) > 150 ? '...' : '')); ?></p>
                <p><strong>Status:</strong> <span class="status-badge <?php echo $status_class; ?>"><?php echo escape_html($status_text); ?></span></p>
                <a href="view_module.php?module_id=<?php echo escape_html($module->module_id); ?>" class="e-button">View Module</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php
echo "</main>"; // Close main.e-container from header
ob_end_flush();
?>
</body>
</html>
