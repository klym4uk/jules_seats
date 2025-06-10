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
// A simple way to include a header, assuming you might create one later
// include '../../src/includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_html($page_title ?? "Admin - User Management"); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f4f4; color: #333; }
        .admin-nav { background-color: #333; color: white; padding: 10px 20px; overflow: hidden; }
        .admin-nav a { float: left; color: white; text-align: center; padding: 14px 16px; text-decoration: none; font-size: 17px; }
        .admin-nav a:hover { background-color: #ddd; color: black; }
        .admin-nav a.logout { float: right; }
        .container { padding: 20px; }
        h1 { color: #333; }
        .action-links a { margin-right: 10px; text-decoration: none; padding: 8px 12px; background-color: #007bff; color: white; border-radius: 4px; }
        .action-links a:hover { background-color: #0056b3; }
        .action-links a.edit { background-color: #ffc107; }
        .action-links a.edit:hover { background-color: #e0a800; }
        .action-links a.delete { background-color: #dc3545; }
        .action-links a.delete:hover { background-color: #c82333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f0f0f0; }
        tr:nth-child(even) { background-color: #f9f9f9; }
    </style>
</head>
<body>

<div class="admin-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Manage Users</a>
    <a href="manage_modules.php">Manage Modules</a> <!-- Placeholder -->
    <a href="../logout.php" class="logout">Logout (<?php echo escape_html($_SESSION['first_name'] ?? 'User'); ?>)</a>
</div>

<div class="container">
    <h1>User Management</h1>

    <div class="action-links" style="margin-bottom: 20px;">
        <!-- Link to register.php or a dedicated add_user.php script -->
        <a href="../register.php">Add New User</a>
    </div>

    <?php if (empty($allUsers)): ?>
        <p>No users found.</p>
    <?php else: ?>
        <table>
            <thead>
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
                        <td><?php echo escape_html($user->role); ?></td>
                        <td><?php echo escape_html($user->created_at ? date('Y-m-d H:i:s', strtotime($user->created_at)) : 'N/A'); ?></td>
                        <td><?php echo escape_html($user->last_login_at ? date('Y-m-d H:i:s', strtotime($user->last_login_at)) : 'Never'); ?></td>
                        <td class="action-links">
                            <a href="edit_user.php?user_id=<?php echo escape_html($user->user_id); ?>" class="edit">Edit (Placeholder)</a>
                            <a href="delete_user.php?user_id=<?php echo escape_html($user->user_id); ?>" class="delete" onclick="return confirm('Are you sure you want to delete this user?');">Delete (Placeholder)</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
// A simple way to include a footer, assuming you might create one later
// include '../../src/includes/footer.php';
ob_end_flush(); // Flush the output buffer
?>
</body>
</html>
