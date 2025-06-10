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

<h1 class="mb-4">Welcome, <?php echo escape_html($_SESSION['first_name']); ?>!</h1>
<p class="lead">Here are your available training modules. Click on a module to view its lessons and start learning.</p>

<?php if (empty($activeModules)): ?>
    <div class="alert alert-info">No training modules are currently available. Please check back later.</div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($activeModules as $module): ?>
            <?php
                $progress = $moduleProgressMap[$module->module_id] ?? null;
                $status_text = 'Not Started';
                $status_key = 'not_started'; // Used for CSS class mapping
                $badge_bg_class = 'bg-secondary'; // Default for not_started

                if ($progress) {
                    $status_text = ucwords(str_replace('_', ' ', $progress->status));
                    $status_key = strtolower($progress->status);
                    switch ($status_key) {
                        case 'in_progress': $badge_bg_class = 'bg-warning text-dark'; break;
                        case 'training_completed': $badge_bg_class = 'bg-info'; break;
                        case 'quiz_available': $badge_bg_class = 'bg-orange'; break; // Custom class defined in style.css
                        case 'quiz_in_progress': $badge_bg_class = 'bg-info text-dark'; break;
                        case 'passed': $badge_bg_class = 'bg-success'; break;
                        case 'failed': $badge_bg_class = 'bg-danger'; break;
                        default: $badge_bg_class = 'bg-secondary'; break;
                    }
                }
            ?>
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            <a href="view_module.php?module_id=<?php echo escape_html($module->module_id); ?>" class="text-decoration-none">
                                <?php echo escape_html($module->title); ?>
                            </a>
                        </h5>
                        <p class="card-text flex-grow-1"><?php echo escape_html(substr($module->description, 0, 120) . (strlen($module->description) > 120 ? '...' : '')); ?></p>
                        <p class="mb-2"><strong>Status:</strong> <span class="status-badge <?php echo $badge_bg_class; ?>"><?php echo escape_html($status_text); ?></span></p>
                        <a href="view_module.php?module_id=<?php echo escape_html($module->module_id); ?>" class="btn btn-primary mt-auto">View Module</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
// The main container div is opened in employee_header.php and should be closed here.
echo "</div>"; // Close .container from employee_header.php
ob_end_flush();
?>
</body>
</html>
