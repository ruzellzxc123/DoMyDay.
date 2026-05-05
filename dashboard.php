<?php
require_once 'config/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireRole(['student', 'program_head', 'dean']);
 
$role = $_SESSION['role'];
$userId = $_SESSION['user_id'];
 
// Get active period
$activePeriod = $pdo->query("SELECT * FROM evaluation_periods WHERE is_active = 1 ORDER BY end_date DESC LIMIT 1")->fetch();
$periodId = $activePeriod['id'] ?? null;
 
// Get teachers based on role
$teachers = [];
if ($role === 'student') {
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.*, d.name as dept_name, s.section_name
        FROM teachers t
        JOIN section_assignments sa ON t.id = sa.teacher_id
        JOIN sections s ON sa.section_id = s.id
        JOIN section_students ss ON s.id = ss.section_id
        JOIN departments d ON t.department_id = d.id
        WHERE ss.student_id = ? AND t.is_active = 1
        ORDER BY t.full_name
    ");
    $stmt->execute([$userId]);
    $teachers = $stmt->fetchAll();
} elseif ($role === 'program_head') {
    $userDept = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
    $userDept->execute([$userId]);
    $deptId = $userDept->fetchColumn();
 
    $stmt = $pdo->prepare("
        SELECT t.*, d.name as dept_name
        FROM teachers t
        JOIN departments d ON t.department_id = d.id
        WHERE t.department_id = ? AND t.is_active = 1
        ORDER BY t.full_name
    ");
    $stmt->execute([$deptId]);
    $teachers = $stmt->fetchAll();
} elseif ($role === 'dean') {
    // Dean sees only teachers in their assigned department
    $userDept = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
    $userDept->execute([$userId]);
    $deptId = $userDept->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT t.*, d.name as dept_name
        FROM teachers t
        JOIN departments d ON t.department_id = d.id
        WHERE t.department_id = ? AND t.is_active = 1
        ORDER BY t.full_name
    ");
    $stmt->execute([$deptId]);
    $teachers = $stmt->fetchAll();
}
 
// Check evaluated teachers
$evaluatedTeachers = [];
if ($periodId && $role !== 'student') {
    $stmt = $pdo->prepare("SELECT teacher_id FROM evaluations WHERE rater_id = ? AND evaluation_period_id = ?");
    $stmt->execute([$userId, $periodId]);
    $evaluatedTeachers = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
 
$stats = ['total' => count($teachers), 'evaluated' => count($evaluatedTeachers), 'pending' => count($teachers) - count($evaluatedTeachers)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?php echo ucfirst($role); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .welcome { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; }
        .stats { display: flex; gap: 20px; margin-bottom: 30px; }
        .stat-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); flex: 1; text-align: center; }
        .stat-box .number { font-size: 36px; font-weight: bold; color: #dc3545; }
        .teacher-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .teacher-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #dc3545; }
        .btn-evaluate { display: block; margin-top: 15px; text-align: center; padding: 10px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="brand">EvalSystem</div>
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>
 
<div class="container">
    <div class="welcome">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
        <p>Role: <strong><?php echo ucfirst(str_replace('_', ' ', $role)); ?></strong></p>
        <?php if ($activePeriod): ?>
        <p>Active Period: <strong><?php echo htmlspecialchars($activePeriod['title']); ?></strong></p>
        <?php endif; ?>
    </div>
 
    <div class="stats">
        <div class="stat-box"><h3>Total Teachers</h3><div class="number"><?php echo $stats['total']; ?></div></div>
        <div class="stat-box"><h3>Evaluated</h3><div class="number" style="color: #28a745;"><?php echo $stats['evaluated']; ?></div></div>
        <div class="stat-box"><h3>Pending</h3><div class="number" style="color: #ffc107;"><?php echo $stats['pending']; ?></div></div>
    </div>
 
    <h3>Teachers to Evaluate</h3>
    <div class="teacher-grid">
        <?php foreach ($teachers as $teacher): 
            $isEvaluated = in_array($teacher['id'], $evaluatedTeachers);
        ?>
        <div class="teacher-card">
            <h4><?php echo htmlspecialchars($teacher['full_name']); ?></h4>
            <p style="color: #dc3545; font-weight: 600;"><?php echo htmlspecialchars($teacher['dept_name']); ?></p>
            <?php if (!$isEvaluated): ?>
            <a href="evaluate.php?teacher_id=<?php echo $teacher['id']; ?>" class="btn-evaluate">Evaluate Now</a>
            <?php else: ?>
            <span style="display: block; margin-top: 15px; padding: 10px; background: #d4edda; color: #155724; text-align: center; border-radius: 5px;">✓ Evaluated</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
