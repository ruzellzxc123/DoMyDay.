<?php
require_once 'config/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireRole(['student', 'program_head', 'dean']);

$role = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Get all evaluation periods
$periods = $pdo->query("SELECT * FROM evaluation_periods ORDER BY end_date DESC")->fetchAll();

// Get completion history (records of when user completed all evaluations)
$completionHistory = [];
try {
    $stmt = $pdo->prepare("
        SELECT eh.*, ep.title as period_title, ep.end_date as period_end
        FROM evaluation_history eh
        JOIN evaluation_periods ep ON eh.evaluation_period_id = ep.id
        WHERE eh.user_id = ?
        ORDER BY eh.created_at DESC
    ");
    $stmt->execute([$userId]);
    $completionHistory = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist yet
}

// Get evaluation history based on role
$history = [];
if ($role === 'student') {
    // For students, get evaluations from session tracking since they're anonymous
    // We'll need to match by session keys or create a different tracking method
    // For now, let's show all evaluations and filter by department
    
    // Get student's department from enrolled section
    $deptStmt = $pdo->prepare("
        SELECT DISTINCT s.department_id 
        FROM section_students ss 
        JOIN sections s ON ss.section_id = s.id 
        WHERE ss.student_id = ? 
        LIMIT 1
    ");
    $deptStmt->execute([$userId]);
    $studentDeptId = $deptStmt->fetchColumn();
    
    if ($studentDeptId) {
        // Get all evaluations for teachers in student's department
        $stmt = $pdo->prepare("
            SELECT e.*, t.full_name as teacher_name, d.name as dept_name, 
                   ep.title as period_title, ep.end_date as period_end
            FROM evaluations e
            JOIN teachers t ON e.teacher_id = t.id
            JOIN departments d ON t.department_id = d.id
            JOIN evaluation_periods ep ON e.evaluation_period_id = ep.id
            WHERE t.department_id = ? AND e.rater_role = 'student'
            ORDER BY ep.end_date DESC, e.created_at DESC
        ");
        $stmt->execute([$studentDeptId]);
        $history = $stmt->fetchAll();
    }
} else {
    // For program_head and dean, get their evaluations
    $stmt = $pdo->prepare("
        SELECT e.*, t.full_name as teacher_name, d.name as dept_name, 
               ep.title as period_title, ep.end_date as period_end
        FROM evaluations e
        JOIN teachers t ON e.teacher_id = t.id
        JOIN departments d ON t.department_id = d.id
        JOIN evaluation_periods ep ON e.evaluation_period_id = ep.id
        WHERE e.rater_id = ? AND e.rater_role = ?
        ORDER BY ep.end_date DESC, e.created_at DESC
    ");
    $stmt->execute([$userId, $role]);
    $history = $stmt->fetchAll();
}

// Group history by period
$historyByPeriod = [];
foreach ($history as $h) {
    $periodId = $h['evaluation_period_id'];
    if (!isset($historyByPeriod[$periodId])) {
        $historyByPeriod[$periodId] = [
            'title' => $h['period_title'],
            'end_date' => $h['period_end'],
            'evaluations' => []
        ];
    }
    $historyByPeriod[$periodId]['evaluations'][] = $h;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Evaluation History</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .period-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .period-header {
            border-bottom: 2px solid #dc3545;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .period-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #dc3545;
        }
        .period-date {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .history-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        .history-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .history-table tr:last-child td {
            border-bottom: none;
        }
        .score-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.85rem;
        }
        .score-high { background: #d4edda; color: #155724; }
        .score-medium { background: #fff3cd; color: #856404; }
        .score-low { background: #f8d7da; color: #721c24; }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #dc3545;
            text-decoration: none;
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="brand">EvalSystem</div>
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="evaluation_history.php">History</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    <h2>Evaluation History</h2>
    
    <?php if (!empty($completionHistory)): ?>
    <div style="background: #d1ecf1; border-radius: 10px; padding: 25px; margin-bottom: 30px; border-left: 4px solid #0c5460;">
        <h3 style="color: #0c5460; margin-top: 0;">✓ Evaluation Completion Records</h3>
        <p style="color: #0c5460;">You have completed evaluations for the following periods:</p>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
            <?php foreach ($completionHistory as $record): ?>
            <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.1);">
                <strong style="color: #0c5460;"><?php echo htmlspecialchars($record['period_title']); ?></strong>
                <p style="margin: 8px 0 0 0; font-size: 0.9rem; color: #666;">
                    Completed: <?php echo date('F j, Y', strtotime($record['created_at'])); ?><br>
                    Teachers: <?php echo $record['total_evaluations']; ?> evaluated<br>
                    <em><?php echo htmlspecialchars($record['notes']); ?></em>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (empty($historyByPeriod)): ?>
    <div class="empty-state">
        <p>No evaluation history found.</p>
    </div>
    <?php else: ?>
        <h3>Individual Evaluations</h3>
        <?php foreach ($historyByPeriod as $periodId => $period): ?>
        <div class="period-section">
            <div class="period-header">
                <div class="period-title"><?php echo htmlspecialchars($period['title']); ?></div>
                <div class="period-date">Ended: <?php echo date('F j, Y', strtotime($period['end_date'])); ?></div>
            </div>
            
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th>Score</th>
                        <th>Comments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($period['evaluations'] as $eval): 
                        // Calculate score based on role
                        $score = 0;
                        $count = 0;
                        if ($eval['rater_role'] === 'student') {
                            if ($eval['teaching_clarity']) { $score += $eval['teaching_clarity']; $count++; }
                            if ($eval['engagement']) { $score += $eval['engagement']; $count++; }
                            if ($eval['fairness']) { $score += $eval['fairness']; $count++; }
                        } elseif ($eval['rater_role'] === 'program_head') {
                            if ($eval['curriculum']) { $score += $eval['curriculum']; $count++; }
                            if ($eval['assessment']) { $score += $eval['assessment']; $count++; }
                            if ($eval['mentoring']) { $score += $eval['mentoring']; $count++; }
                        } elseif ($eval['rater_role'] === 'dean') {
                            if ($eval['attendance']) { $score += $eval['attendance']; $count++; }
                            if ($eval['commitment']) { $score += $eval['commitment']; $count++; }
                            if ($eval['quality']) { $score += $eval['quality']; $count++; }
                        }
                        $avgScore = $count > 0 ? round($score / $count, 1) : 0;
                        
                        // Determine score class
                        $scoreClass = $avgScore >= 4 ? 'score-high' : ($avgScore >= 2.5 ? 'score-medium' : 'score-low');
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($eval['teacher_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($eval['dept_name']); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($eval['created_at'])); ?></td>
                        <td><span class="score-badge <?php echo $scoreClass; ?>"><?php echo $avgScore; ?>/5</span></td>
                        <td><?php echo htmlspecialchars($eval['comments'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
