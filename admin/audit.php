<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$logs = $pdo->query("SELECT a.*, u.full_name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 2000")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="brand">EvalSystem</div>
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="teachers.php">Teachers</a></li>
        <li><a href="periods.php">Periods</a></li>
        <li><a href="users.php">Users</a></li>
        <li><a href="sections.php">Sections</a></li>
        <li><a href="reports.php">Reports</a></li>
        <li><a href="audit.php">Audit Logs</a></li>
        <li><a href="reminders.php">Reminders</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</nav>
<div class="container">
    <h2>Audit Logs</h2>
    <div class="export-bar">
        <a class="btn" href="../exports/csv_export.php?type=audit">Download Full Audit CSV</a>
    </div>
    <table class="data-table">
        <thead><tr><th>ID</th><th>User</th><th>Action</th><th>Details</th><th>IP</th><th>Timestamp</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
            <td><?php echo $l['id']; ?></td>
            <td><?php echo htmlspecialchars($l['user_name'] ?? 'Guest/System'); ?></td>
            <td><?php echo htmlspecialchars($l['action']); ?></td>
            <td><?php echo htmlspecialchars($l['details'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($l['ip_address']); ?></td>
            <td><?php echo $l['created_at']; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>

