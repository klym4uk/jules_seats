<?php
ob_start(); // Start output buffering
require_once '../../config/database.php'; // Adjusted path
require_once '../../src/User.php';       // Adjusted path
require_once '../../src/includes/functions.php'; // Adjusted path

start_session_if_not_started();
require_admin(); // Ensures only Admin users can access this page

$userHandler = new User($pdo);
$allUsers = $userHandler->getAllUsers();

$page_title = "Manage Users";
include_once '../../src/includes/admin_header.php';
?>

<h1 class="mb-4">User Management</h1>

<div class="mb-3">
    <a href="../register.php" class="btn btn-primary">Add New User</a>
    <!-- Consider moving register.php to /admin/add_user.php and protecting it -->
</div>

<?php if (empty($allUsers)): ?>
    <div class="alert alert-info">No users found.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Registered At</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allUsers as $user): ?>
                    <tr>
                        <td><?php echo escape_html($user->user_id); ?></td>
                        <td><?php echo escape_html($user->first_name); ?></td>
                        <td><?php echo escape_html($user->last_name); ?></td>
                        <td><?php echo escape_html($user->email); ?></td>
                        <td><span class="badge bg-<?php echo ($user->role === 'Admin' ? 'danger' : 'secondary'); ?>"><?php echo escape_html($user->role); ?></span></td>
                        <td><?php echo escape_html($user->created_at ? date('Y-m-d H:i:s', strtotime($user->created_at)) : 'N/A'); ?></td>
                        <td><?php echo escape_html($user->last_login_at ? date('Y-m-d H:i:s', strtotime($user->last_login_at)) : 'Never'); ?></td>
                        <td>
                            <a href="edit_user.php?user_id=<?php echo escape_html($user->user_id); ?>" class="btn btn-sm btn-warning me-1">Edit (PH)</a>
                            <a href="delete_user.php?user_id=<?php echo escape_html($user->user_id); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete (PH)</a>
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
