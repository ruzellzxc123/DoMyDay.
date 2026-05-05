<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $deptId = $_POST['department_id'];
    
    $stmt = $pdo->prepare("INSERT INTO teachers (full_name, email, department_id) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $deptId]);
    auditLog($_SESSION['user_id'], 'TEACHER_CREATE', "Created teacher $email");
    $msg = "Teacher added.";
}

if (isset($_GET['toggle'])) {
    $tid = intval($_GET['toggle']);
    $pdo->prepare("UPDATE teachers SET is_active = NOT is_active WHERE id = ?")->execute([$tid]);
    auditLog($_SESSION['user_id'], 'TEACHER_TOGGLE', "Toggled teacher $tid");
    header("Location: teachers.php"); exit;
}

$teachers = $pdo->query("SELECT t.*, d.name as dept_name FROM teachers t LEFT JOIN departments d ON t.department_id = d.id ORDER BY t.id DESC")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Teachers</title>
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
    <h2>Manage Teachers</h2>
    <?php if ($msg): ?><div class="alert success"><?php echo $msg; ?></div><?php endif; ?>
    <form method="POST" action="" class="filter-form">
        <div><label>Full Name</label><input type="text" name="full_name" required></div>
        <div><label>Email</label><input type="email" name="email" required></div>
        <div><label>Department</label>
            <select name="department_id" required>
                <?php foreach ($departments as $de): ?><option value="<?php echo $de['id']; ?>"><?php echo htmlspecialchars($de['name']); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><button type="submit" name="add_teacher" class="btn">Add Teacher</button></div>
    </form>
    <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Department</th><th>Active</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($teachers as $t): ?>
        <tr>
            <td><?php echo htmlspecialchars($t['full_name']); ?></td>
            <td><?php echo htmlspecialchars($t['email']); ?></td>
            <td><?php echo htmlspecialchars($t['dept_name']); ?></td>
            <td><?php echo $t['is_active'] ? 'Yes' : 'No'; ?></td>
            <td><a class="btn" href="?toggle=<?php echo $t['id']; ?>">Toggle</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
