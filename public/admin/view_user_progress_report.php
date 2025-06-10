<?php
ob_start();
require_once '../../config/database.php';
require_once '../../src/includes/functions.php';
// Models
require_once '../../src/User.php';
require_once '../../src/Module.php';
require_once '../../src/UserModuleProgress.php';

start_session_if_not_started();
require_admin();

$page_title = "User Progress Report";

// Instantiate handlers
$userHandler = new User($pdo);
$moduleHandler = new Module($pdo);
$userModuleProgressHandler = new UserModuleProgress($pdo);

$employees = $userHandler->getAllUsers('Employee'); // Get only 'Employee' role
$activeModules = $moduleHandler->getAllModules(true); // true for activeOnly
$totalActiveModulesCount = count($activeModules);

include_once '../../src/includes/admin_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><?php echo $page_title; ?></h1>
    <a href="reports.php" class="btn btn-secondary">&laquo; Back to Reports</a>
</div>


<div class="mb-3">
    <a href="export_handler.php?report=user_progress" class="btn btn-success">
        <i class="fas fa-file-csv me-2"></i>Export User Progress to CSV
    </a>
</div>

<?php if (empty($employees)): ?>
    <div class="alert alert-info">No employees found in the system.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Overall Progress (%)</th>
                    <th>Modules Passed (Active)</th>
                    <th>Total Active Modules</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $employee): ?>
                    <?php
                        $user_id = $employee->user_id;
                        $passedModulesCount = $userModuleProgressHandler->getCountPassedModulesForUser($user_id);

                        $overallProgressPercentage = 0;
                        if ($totalActiveModulesCount > 0) {
                            $overallProgressPercentage = round(($passedModulesCount / $totalActiveModulesCount) * 100, 2);
                        }
                    ?>
                    <tr>
                        <td><?php echo escape_html($employee->user_id); ?></td>
                        <td><?php echo escape_html($employee->first_name . ' ' . $employee->last_name); ?></td>
                        <td><?php echo escape_html($employee->email); ?></td>
                        <td>
                            <div class="progress" style="height: 20px;" title="<?php echo $overallProgressPercentage; ?>%">
                                <div class="progress-bar <?php
                                    if ($overallProgressPercentage >= 75) echo 'bg-success';
                                    elseif ($overallProgressPercentage >= 50) echo 'bg-info';
                                    elseif ($overallProgressPercentage >= 25) echo 'bg-warning';
                                    else echo 'bg-danger';
                                ?>" role="progressbar" style="width: <?php echo $overallProgressPercentage; ?>%;" aria-valuenow="<?php echo $overallProgressPercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo $overallProgressPercentage; ?>%
                                </div>
                            </div>
                        </td>
                        <td><?php echo $passedModulesCount; ?></td>
                        <td><?php echo $totalActiveModulesCount; ?></td>
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
