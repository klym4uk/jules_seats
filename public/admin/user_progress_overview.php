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

$page_title = "User Progress Overview";

// Instantiate handlers
$userHandler = new User($pdo);
$moduleHandler = new Module($pdo);
$userModuleProgressHandler = new UserModuleProgress($pdo);

$employees = $userHandler->getAllUsers(); // Assuming this gets all users; filter for role 'Employee'
$activeModules = $moduleHandler->getAllModules(true); // true for activeOnly
$totalActiveModulesCount = count($activeModules);

include_once '../../src/includes/admin_header.php';
?>

<h1><?php echo $page_title; ?></h1>

<?php if (empty($employees)): ?>
    <div class="message info">No users found in the system.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Employee Name</th>
                <th>Email</th>
                <th>Overall Progress %</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $employee): ?>
                <?php if ($employee->role !== 'Employee') continue; // Skip non-employees ?>
                <?php
                    $user_id = $employee->user_id;
                    $userProgressRecords = $userModuleProgressHandler->getAllModulesProgressForUser($user_id);

                    $passedActiveModulesCount = 0;
                    if ($totalActiveModulesCount > 0) {
                        $progressMap = [];
                        foreach($userProgressRecords as $p) {
                            $progressMap[$p->module_id] = $p;
                        }
                        foreach($activeModules as $activeMod) {
                            if(isset($progressMap[$activeMod->module_id]) && $progressMap[$activeMod->module_id]->status === 'passed') {
                                $passedActiveModulesCount++;
                            }
                        }
                        $overallProgressPercentage = round(($passedActiveModulesCount / $totalActiveModulesCount) * 100, 2);
                    } else {
                        $overallProgressPercentage = 0;
                    }
                ?>
                <tr>
                    <td><?php echo escape_html($employee->first_name . ' ' . $employee->last_name); ?></td>
                    <td><?php echo escape_html($employee->email); ?></td>
                    <td><?php echo $overallProgressPercentage; ?>%</td>
                    <td>
                        <a href="view_employee_progress.php?user_id=<?php echo $employee->user_id; ?>" class="button-link">View Details</a>
                    </td>
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
