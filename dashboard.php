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
$userSections = [];
if ($role === 'student') {
    // Get ONLY teachers assigned to student's enrolled sections
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.*, d.name as dept_name, s.id as section_id, s.section_name
        FROM section_students ss 
        JOIN sections s ON ss.section_id = s.id
        JOIN section_assignments sa ON s.id = sa.section_id
        JOIN teachers t ON sa.teacher_id = t.id
        JOIN departments d ON t.department_id = d.id
        WHERE ss.student_id = ? AND t.is_active = 1
        ORDER BY t.full_name
    ");
    $stmt->execute([$userId]);
    $teachers = $stmt->fetchAll();
    
    // Also get section information for display
    $sectionStmt = $pdo->prepare("
        SELECT s.id, s.section_name
        FROM section_students ss
        JOIN sections s ON ss.section_id = s.id
        WHERE ss.student_id = ?
    ");
    $sectionStmt->execute([$userId]);
    $userSections = $sectionStmt->fetchAll(PDO::FETCH_COLUMN, 1);
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
 
// Check evaluated teachers (for all roles including student)
$evaluatedTeachers = [];
if ($periodId) {
    if ($role === 'student') {
        // For students, check session-based evaluation tracking
        // Since evaluations are anonymous, we track via session
        foreach ($_SESSION as $key => $val) {
            if (strpos($key, 'eval_') === 0 && $val === true) {
                // Extract teacher_id from eval_{teacher_id}_{period_id}
                $parts = explode('_', $key);
                if (isset($parts[1]) && isset($parts[2]) && $parts[2] == $periodId) {
                    $evaluatedTeachers[] = intval($parts[1]);
                }
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT teacher_id FROM evaluations WHERE rater_id = ? AND evaluation_period_id = ?");
        $stmt->execute([$userId, $periodId]);
        $evaluatedTeachers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
 
$stats = ['total' => count($teachers), 'evaluated' => count($evaluatedTeachers), 'pending' => count($teachers) - count($evaluatedTeachers)];

// Handle evaluation history creation
$historyCreated = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_history'])) {
    if ($stats['pending'] == 0 && $stats['total'] > 0 && $periodId) {
        // Create evaluation history record for this period
        try {
            // Check if history already exists
            $checkHistory = $pdo->prepare("
                SELECT id FROM evaluation_history 
                WHERE user_id = ? AND evaluation_period_id = ? AND created_at >= DATE(NOW())
            ");
            $checkHistory->execute([$userId, $periodId]);
            
            if (!$checkHistory->fetch()) {
                // Create history record
                $insertHistory = $pdo->prepare("
                    INSERT INTO evaluation_history (user_id, evaluation_period_id, total_evaluations, status, notes)
                    VALUES (?, ?, ?, 'completed', 'All teachers evaluated for this period')
                ");
                $insertHistory->execute([$userId, $periodId, $stats['total']]);
                $historyCreated = true;
                auditLog($userId, 'EVALUATION_COMPLETED', "Completed evaluations for period $periodId with {$stats['total']} teachers");
            }
        } catch (Exception $e) {
            // If table doesn't exist, we'll just skip this
        }
    }
}
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
        .section-info { font-size: 0.8rem; color: #666; margin-top: 5px; }
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
    
    <?php if ($historyCreated): ?>
    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745;">
        <strong>✓ Success!</strong> Evaluation history created for this period. All teachers have been evaluated.
    </div>
    <?php endif; ?>
    
    <?php if ($stats['total'] > 0 && $stats['pending'] == 0 && $periodId): ?>
    <div style="background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #0c5460; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong>🎉 All teachers evaluated!</strong>
            <p style="margin-top: 5px;">You have completed evaluations for all <?php echo $stats['total']; ?> teachers assigned to your sections for the <?php echo htmlspecialchars($activePeriod['title']); ?> period.</p>
        </div>
        <form method="POST" style="display: inline;">
            <button type="submit" name="create_history" value="1" style="padding: 10px 20px; background: #0c5460; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
                Create History Record
            </button>
        </form>
    </div>
    <?php elseif ($stats['total'] > 0 && $stats['pending'] > 0): ?>
    <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
        <strong>Progress:</strong> You have evaluated <strong><?php echo $stats['evaluated']; ?> of <?php echo $stats['total']; ?></strong> teachers. 
        <strong><?php echo $stats['pending']; ?> remaining</strong>.
    </div>
    <?php endif; ?>
 
    <h3>Teachers to Evaluate</h3>
    <?php if ($role === 'student' && $stats['total'] == 0): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; border-left: 4px solid #f5c6cb;">
        <strong>No teachers assigned.</strong> You don't have any teachers assigned to your enrolled sections yet.
    </div>
    <?php endif; ?>
    <div class="teacher-grid">
        <?php foreach ($teachers as $teacher): 
            $isEvaluated = in_array($teacher['id'], $evaluatedTeachers);
        ?>
        <div class="teacher-card">
            <h4><?php echo htmlspecialchars($teacher['full_name']); ?></h4>
            <p style="color: #dc3545; font-weight: 600;"><?php echo htmlspecialchars($teacher['dept_name']); ?></p>
            <?php if ($role === 'student' && isset($teacher['section_name'])): ?>
            <p class="section-info">📚 Section: <?php echo htmlspecialchars($teacher['section_name']); ?></p>
            <?php endif; ?>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                <?php if ($isEvaluated): ?>
                <span style="display: block; padding: 8px; background: #d4edda; color: #155724; text-align: center; border-radius: 5px; font-weight: 500;">✓ Evaluated</span>
                <?php else: ?>
                <a href="evaluate.php?teacher_id=<?php echo $teacher['id']; ?>" class="btn-evaluate" style="text-decoration: none; display: block; margin-top: 0;">Evaluate Now</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
