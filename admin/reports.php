<?php
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/gemini.php';
requireRole(['program_head','dean','admin']);

$role = $_SESSION['role'];
$teacherId = intval($_GET['teacher_id'] ?? 0);
$periodId = intval($_GET['period_id'] ?? 0);

$periods = $pdo->query("SELECT * FROM evaluation_periods ORDER BY start_date DESC")->fetchAll();
if (!$periodId && $periods) $periodId = $periods[0]['id'];

$teachers = [];
if ($role === 'admin' || $role === 'dean') {
    $teachers = $pdo->query("SELECT * FROM teachers WHERE is_active = 1")->fetchAll();
} elseif ($role === 'program_head') {
    $stmt = $pdo->prepare("SELECT t.* FROM teachers t JOIN users u ON t.department_id = u.department_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $teachers = $stmt->fetchAll();
}

$selectedTeacher = null;
if ($teacherId) {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt->execute([$teacherId]);
    $selectedTeacher = $stmt->fetch();
}

$evaluations = [];
if ($teacherId && $periodId) {
    $stmt = $pdo->prepare("SELECT e.*, u.full_name as rater_name 
        FROM evaluations e 
        LEFT JOIN users u ON e.rater_id = u.id 
        WHERE e.teacher_id = ? AND e.evaluation_period_id = ? ORDER BY e.submitted_at DESC");
    $stmt->execute([$teacherId, $periodId]);
    $evaluations = $stmt->fetchAll();
}

// Handle AI summary generation
$aiError = '';
$aiSuccess = '';
$generatedSummary = null;
$savedSummary = null;

if ($teacherId && $periodId && isset($_POST['generate_ai_summary'])) {
    $result = generateGeminiSummary($teacherId, $periodId);
    if ($result['success']) {
        $generatedSummary = $result['summary'];
        saveTeacherSummary($teacherId, $periodId, $generatedSummary, $_SESSION['user_id']);
        auditLog($_SESSION['user_id'], 'AI_SUMMARY_GENERATED', "Generated AI summary for teacher $teacherId, period $periodId");
        $aiSuccess = "AI summary generated and saved successfully!";
    } else {
        $aiError = $result['error'];
        auditLog($_SESSION['user_id'], 'AI_SUMMARY_FAILED', "Failed to generate AI summary for teacher $teacherId: " . $result['error']);
    }
}

if ($teacherId && $periodId) {
    $savedSummary = getTeacherSummary($teacherId, $periodId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Teacher Evaluation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .ai-summary-panel {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            margin: 1rem 0;
            display: flex;
            flex-direction: column;
            max-height: 520px;
        }
        .ai-summary-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #bae6fd;
            background: #e0f2fe;
            border-radius: 8px 8px 0 0;
            flex-shrink: 0;
        }
        .ai-summary-header h4 {
            margin: 0;
            color: #0369a1;
            font-size: 1rem;
        }
        .ai-summary-body {
            padding: 1rem 1.5rem;
            overflow-y: auto;
            flex: 1 1 auto;
            line-height: 1.7;
        }
        .ai-summary-body h3 {
            color: #1e3a5f;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            font-size: 1.05rem;
        }
        .ai-summary-body ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
        .ai-summary-body p {
            margin: 0.6rem 0;
        }
        .ai-summary-body strong {
            color: #1e3a5f;
        }
        .ai-controls {
            display: flex;
            gap: 0.6rem;
            align-items: center;
            margin: 1rem 0;
            flex-wrap: wrap;
        }
        .ai-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .ai-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.8rem 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }
        .ai-success {
            background: #dcfce7;
            color: #166534;
            padding: 0.8rem 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }
        /* scrollbar styling for summary box */
        .ai-summary-body::-webkit-scrollbar {
            width: 8px;
        }
        .ai-summary-body::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .ai-summary-body::-webkit-scrollbar-thumb {
            background: #93c5fd;
            border-radius: 4px;
        }
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
    <h2>Reports & Exports</h2>
    <form method="GET" action="" class="filter-form">
        <label>Teacher</label>
        <select name="teacher_id">
            <option value="">Select Teacher</option>
            <?php foreach ($teachers as $t): ?>
            <option value="<?php echo $t['id']; ?>" <?php if($teacherId==$t['id']) echo 'selected'; ?>><?php echo htmlspecialchars($t['full_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <label>Period</label>
        <select name="period_id">
            <?php foreach ($periods as $p): ?>
            <option value="<?php echo $p['id']; ?>" <?php if($periodId==$p['id']) echo 'selected'; ?>><?php echo htmlspecialchars($p['title']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">View</button>
    </form>

    <?php if ($selectedTeacher): 
        $scores = getTeacherScore($teacherId, $periodId);
    ?>
    <h3><?php echo htmlspecialchars($selectedTeacher['full_name']); ?> - Score Summary</h3>
    <div class="cards">
        <div class="card"><h3>Composite</h3><p class="big"><?php echo $scores['composite']; ?></p></div>
        <div class="card"><h3>Student (50%)</h3><p class="big"><?php echo $scores['student_avg']; ?> <small>(n=<?php echo $scores['student_count']; ?>)</small></p></div>
        <div class="card"><h3>Program Head (30%)</h3><p class="big"><?php echo $scores['ph_avg']; ?> <small>(n=<?php echo $scores['ph_count']; ?>)</small></p></div>
        <div class="card"><h3>Dean (20%)</h3><p class="big"><?php echo $scores['dean_avg']; ?> <small>(n=<?php echo $scores['dean_count']; ?>)</small></p></div>
    </div>

    <div class="chart-container" style="max-width:500px;">
        <canvas id="breakdownChart"></canvas>
    </div>
    <script>
    new Chart(document.getElementById('breakdownChart'), {
        type: 'doughnut',
        data: {
            labels: ['Student (50%)','Program Head (30%)','Dean (20%)'],
            datasets: [{
                data: [<?php echo $scores['student_avg']; ?>, <?php echo $scores['ph_avg']; ?>, <?php echo $scores['dean_avg']; ?>],
                backgroundColor: ['#36a2eb','#ffcd56','#ff6384']
            }]
        }
    });
    </script>

    <!-- AI Summary Section -->
    <h3>AI-Generated Performance Summary</h3>
    <?php if ($aiError): ?>
        <div class="ai-error"><?php echo htmlspecialchars($aiError); ?></div>
    <?php endif; ?>
    <?php if ($aiSuccess): ?>
        <div class="ai-success"><?php echo htmlspecialchars($aiSuccess); ?></div>
    <?php endif; ?>

    <div class="ai-controls">
        <form method="POST" action="" style="display:inline;">
            <input type="hidden" name="teacher_id" value="<?php echo $teacherId; ?>">
            <input type="hidden" name="period_id" value="<?php echo $periodId; ?>">
            <button type="submit" name="generate_ai_summary" class="btn">Generate AI Summary</button>
        </form>
        <?php if ($savedSummary): ?>
            <span class="ai-badge">Last generated: <?php echo $savedSummary['generated_at']; ?></span>
        <?php endif; ?>
    </div>

    <?php if ($savedSummary || $generatedSummary): 
        $summaryText = $generatedSummary ?? $savedSummary['summary_text'];
    ?>
    <div class="ai-summary-panel">
        <div class="ai-summary-header">
            <h4>Generated by Gemini Flash 2.5 AI</h4>
        </div>
        <div class="ai-summary-body">
            <?php 
            $md = htmlspecialchars($summaryText);
            $md = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $md);
            $md = preg_replace('/^#{1,6}\s+(.*)$/m', '<h3>$1</h3>', $md);
            $md = preg_replace('/^\*\s+(.*)$/m', '<li>$1</li>', $md);
            $md = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $md);
            $md = nl2br($md);
            echo $md;
            ?>
        </div>
    </div>
    <?php else: ?>
        <p>No AI summary generated yet. Click "Generate AI Summary" to create an automated strengths, weaknesses, and recommendations report using Gemini Flash 2.5.</p>
    <?php endif; ?>

    <h3>Detailed Evaluations</h3>
    <div class="export-bar">
        <a class="btn" href="../exports/csv_export.php?teacher_id=<?php echo $teacherId; ?>&period_id=<?php echo $periodId; ?>&type=scores">Export Scores CSV</a>
        <a class="btn" href="../exports/csv_export.php?teacher_id=<?php echo $teacherId; ?>&period_id=<?php echo $periodId; ?>&type=audit">Export Audit CSV</a>
        <a class="btn" href="../exports/pdf_export.php?teacher_id=<?php echo $teacherId; ?>&period_id=<?php echo $periodId; ?>">Export PDF Report</a>
    </div>
    <table class="data-table">
        <thead>
            <tr><th>Date</th><th>Role</th><th>Rater</th>
            <th>Clarity</th><th>Engage</th><th>Fair</th>
            <th>Curr</th><th>Assess</th><th>Mentor</th>
            <th>Attend</th><th>Commit</th><th>Qual</th>
            <th>Comments</th></tr>
        </thead>
        <tbody>
        <?php foreach ($evaluations as $e): ?>
        <tr>
            <td><?php echo $e['submitted_at']; ?></td>
            <td><?php echo ucfirst($e['rater_role']); ?></td>
            <td><?php echo $e['rater_name'] ?? '<em>Anonymous</em>'; ?></td>
            <td><?php echo $e['teaching_clarity'] ?? '-'; ?></td>
            <td><?php echo $e['engagement'] ?? '-'; ?></td>
            <td><?php echo $e['fairness'] ?? '-'; ?></td>
            <td><?php echo $e['curriculum'] ?? '-'; ?></td>
            <td><?php echo $e['assessment'] ?? '-'; ?></td>
            <td><?php echo $e['mentoring'] ?? '-'; ?></td>
            <td><?php echo $e['attendance'] ?? '-'; ?></td>
            <td><?php echo $e['commitment'] ?? '-'; ?></td>
            <td><?php echo $e['quality'] ?? '-'; ?></td>
            <td><?php echo htmlspecialchars($e['comments'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php elseif ($teacherId === 0): ?>
        <p>Please select a teacher to view report.</p>
    <?php endif; ?>
</div>
</body>
</html>

