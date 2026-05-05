<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'program_head', 'dean', 'student']);

$role = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// If not admin, show user dashboard view
if ($role !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

// Get statistics (admin only)
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers WHERE is_active = 1")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1")->fetchColumn();
$totalEvaluations = $pdo->query("SELECT COUNT(*) FROM evaluations")->fetchColumn();
$activePeriod = $pdo->query("SELECT * FROM evaluation_periods WHERE is_active = 1 ORDER BY end_date DESC LIMIT 1")->fetch();

// Get department stats
$deptStats = $pdo->query("
    SELECT d.name, d.department_code, COUNT(DISTINCT t.id) as teacher_count, COUNT(e.id) as eval_count,
           AVG((COALESCE(e.teaching_clarity,0) + COALESCE(e.engagement,0) + COALESCE(e.fairness,0) + 
                COALESCE(e.curriculum,0) + COALESCE(e.assessment,0) + COALESCE(e.mentoring,0) + 
                COALESCE(e.attendance,0) + COALESCE(e.commitment,0) + COALESCE(e.quality,0)) / 
               (CASE WHEN e.teaching_clarity IS NOT NULL THEN 1 ELSE 0 END + 
                CASE WHEN e.engagement IS NOT NULL THEN 1 ELSE 0 END + 
                CASE WHEN e.fairness IS NOT NULL THEN 1 ELSE 0 END + 
                CASE WHEN e.curriculum IS NOT NULL THEN 1 ELSE 0 END + 
                CASE WHEN e.assessment IS NOT NULL THEN 1 ELSE 0 END + 
                CASE WHEN e.mentoring IS NOT NULL THEN 1 ELSE 0 END + 
                CASE WHEN e.attendance IS NOT NULL THEN 1 ELSE 0 END + 
                CASE WHEN e.commitment IS NOT NULL THEN 1 ELSE 0 END + 
                CASE WHEN e.quality IS NOT NULL THEN 1 ELSE 0 END)) as avg_score
    FROM departments d
    LEFT JOIN teachers t ON d.id = t.department_id AND t.is_active = 1
    LEFT JOIN evaluations e ON t.id = e.teacher_id
    GROUP BY d.id
")->fetchAll();

// Get recent evaluations
$recentEvals = $pdo->query("
    SELECT e.*, t.full_name as teacher_name, u.full_name as rater_name
    FROM evaluations e
    JOIN teachers t ON e.teacher_id = t.id
    LEFT JOIN users u ON e.rater_id = u.id
    ORDER BY e.submitted_at DESC
    LIMIT 10
")->fetchAll();

// Get top teachers
$topTeachers = $pdo->query("
    SELECT t.id, t.full_name, d.name as dept_name,
           AVG((COALESCE(e.teaching_clarity,0) + COALESCE(e.engagement,0) + COALESCE(e.fairness,0)) / 3) * 0.5 +
           AVG((COALESCE(e.curriculum,0) + COALESCE(e.assessment,0) + COALESCE(e.mentoring,0)) / 3) * 0.3 +
           AVG((COALESCE(e.attendance,0) + COALESCE(e.commitment,0) + COALESCE(e.quality,0)) / 3) * 0.2 as composite
    FROM teachers t
    JOIN departments d ON t.department_id = d.id
    LEFT JOIN evaluations e ON t.id = e.teacher_id
    WHERE t.is_active = 1
    GROUP BY t.id
    HAVING composite > 0
    ORDER BY composite DESC
    LIMIT 5
")->fetchAll();

$periods = $pdo->query("SELECT * FROM evaluation_periods ORDER BY start_date DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #666; text-transform: uppercase; }
        .stat-card .big { font-size: 36px; font-weight: bold; color: #dc3545; margin: 0; }
        .stat-card .small { font-size: 12px; color: #999; margin: 5px 0 0 0; }
        .section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .export-buttons { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .export-buttons .btn { background: #dc3545; }
        .export-buttons .btn:hover { background: #dc3545; }
        .dept-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .dept-card { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545; }
        .dept-card h4 { margin: 0 0 10px 0; }
        .dept-card p { margin: 5px 0; font-size: 13px; }
        .top-list { list-style: none; padding: 0; }
        .top-list li { padding: 10px; background: #f8f9fa; margin-bottom: 8px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        .top-list .rank { background: #dc3545; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; }
        .top-list .score { font-weight: bold; color: #dc3545; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background: #f8f9fa; font-weight: 600; }
        .badge { padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .badge-student { background: #36a2eb; color: white; }
        .badge-ph { background: #ffcd56; color: #333; }
        .badge-dean { background: #ff6384; color: white; }
        .chart-container { max-width: 500px; margin: 0 auto; }
    </style>
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
    <h2>Admin Dashboard</h2>
    
    <?php if ($activePeriod): ?>
    <div class="alert success">
        <strong>Active Period:</strong> <?php echo htmlspecialchars($activePeriod['title']); ?> 
        (<?php echo date('M d', strtotime($activePeriod['start_date'])); ?> - <?php echo date('M d, Y', strtotime($activePeriod['end_date'])); ?>)
    </div>
    <?php endif; ?>
    
    <!-- Key Statistics -->
    <div class="dashboard-grid">
        <div class="stat-card">
            <h3>Total Teachers</h3>
            <p class="big"><?php echo $totalTeachers; ?></p>
            <p class="small">Active faculty members</p>
        </div>
        <div class="stat-card">
            <h3>Total Students</h3>
            <p class="big"><?php echo $totalStudents; ?></p>
            <p class="small">Enrolled evaluators</p>
        </div>
        <div class="stat-card">
            <h3>Total Evaluations</h3>
            <p class="big"><?php echo $totalEvaluations; ?></p>
            <p class="small">Completed assessments</p>
        </div>
        <div class="stat-card">
            <h3>Active Periods</h3>
            <p class="big"><?php echo count(array_filter($periods, fn($p) => $p['is_active'])); ?></p>
            <p class="small">Open for evaluation</p>
        </div>
    </div>
    
    <!-- Export Buttons -->
    <div class="section">
        <h2>Data Export</h2>
        <div class="export-buttons">
            <a href="export_csv.php?type=teachers" class="btn">Export Teachers CSV</a>
            <a href="export_csv.php?type=evaluations" class="btn">Export Evaluations CSV</a>
            <a href="export_csv.php?type=audit" class="btn">Export Audit Logs CSV</a>
        </div>
    </div>
    
    <!-- Department Performance -->
    <div class="section">
        <h2>Department Performance</h2>
        <div class="dept-grid">
            <?php foreach ($deptStats as $dept): ?>
            <div class="dept-card">
                <h4><?php echo htmlspecialchars($dept['name']); ?></h4>
                <p><strong><?php echo $dept['teacher_count']; ?></strong> Teachers</p>
                <p><strong><?php echo $dept['eval_count']; ?></strong> Evaluations</p>
                <p>Avg Score: <strong><?php echo $dept['avg_score'] ? round($dept['avg_score'], 2) : '0.00'; ?>/5</strong></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Top Performing Teachers -->
        <div class="section">
            <h2>Top Performing Teachers</h2>
            <ol class="top-list">
                <?php $rank = 1; foreach ($topTeachers as $teacher): ?>
                <li>
                    <div style="display: flex; align-items: center;">
                        <span class="rank"><?php echo $rank++; ?></span>
                        <div>
                            <strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($teacher['dept_name']); ?></small>
                        </div>
                    </div>
                    <span class="score"><?php echo $teacher['composite'] ? round($teacher['composite'], 2) : '0.00'; ?></span>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        
        <!-- Recent Activity -->
        <div class="section">
            <h2>Recent Evaluations</h2>
            <table>
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Rater</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentEvals as $eval): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($eval['teacher_name']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $eval['rater_role'] === 'student' ? 'student' : ($eval['rater_role'] === 'program_head' ? 'ph' : 'dean'); ?>">
                                <?php echo $eval['rater_name'] ?: 'Anonymous'; ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, H:i', strtotime($eval['submitted_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
