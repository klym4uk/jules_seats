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

<p><a href="reports.php" class="button-link secondary">&laquo; Back to Reports</a></p>
<h1><?php echo $page_title; ?></h1>

<p style="margin-bottom: 20px;">
    <a href="export_handler.php?report=user_progress" class="button-link success">Export User Progress to CSV</a>
</p>

<?php if (empty($employees)): ?>
    <div class="message info">No employees found in the system.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>User ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Overall Progress (%)</th>
                <th>Modules Passed</th>
                <th>Total Active Modules</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $employee): ?>
                <?php
                    $user_id = $employee->user_id;
                    // Use the new method to count passed (and active) modules for this user
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
                    <td><?php echo $overallProgressPercentage; ?>%</td>
                    <td><?php echo $passedModulesCount; ?></td>
                    <td><?php echo $totalActiveModulesCount; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
echo "</main>"; // Close main.container from header
ob_end_flush();
?>
</body>
</html>
