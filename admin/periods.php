<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if ($title && $start && $end) {
        if ($isActive) {
            $pdo->query("UPDATE evaluation_periods SET is_active = 0");
        }
        $stmt = $pdo->prepare("INSERT INTO evaluation_periods (title, start_date, end_date, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $start, $end, $isActive]);
        auditLog($_SESSION['user_id'], 'PERIOD_CREATE', "Created period $title");
        $msg = "Period created.";
    }
}

$periods = $pdo->query("SELECT * FROM evaluation_periods ORDER BY start_date DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Periods</title>
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
    <h2>Evaluation Periods</h2>
    <?php if ($msg): ?><div class="alert success"><?php echo $msg; ?></div><?php endif; ?>
    <form method="POST" action="" class="filter-form" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
        <div style="grid-column: 1 / -1;"><label>Title</label><input type="text" name="title" required style="width: 100%;"></div>
        <div><label>Start Date</label><input type="date" name="start_date" required style="width: 100%;"></div>
        <div><label>End Date</label><input type="date" name="end_date" required style="width: 100%;"></div>
        <div style="grid-column: 1 / -1;"><label><input type="checkbox" name="is_active" value="1"> Active</label></div>
        <div style="grid-column: 1 / -1;"><button type="submit" class="btn">Add Period</button></div>
    </form>
    <table class="data-table">
        <thead><tr><th>Title</th><th>Start Date</th><th>End Date</th><th>Active</th></tr></thead>
        <tbody>
        <?php foreach ($periods as $p): ?>
        <tr>
            <td><?php echo htmlspecialchars($p['title']); ?></td>
            <td><?php echo date('F j, Y', strtotime($p['start_date'])); ?></td>
            <td><?php echo date('F j, Y', strtotime($p['end_date'])); ?></td>
            <td><?php echo $p['is_active'] ? 'Yes' : 'No'; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>

