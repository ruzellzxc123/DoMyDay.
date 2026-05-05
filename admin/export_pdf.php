<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$teacherId = $_GET['teacher_id'] ?? null;
$periodId = $_GET['period_id'] ?? null;

if (!$teacherId) {
    die('Teacher ID required');
}

// Get teacher info
$teacher = $pdo->prepare("SELECT t.*, d.name as dept_name, d.department_code FROM teachers t JOIN departments d ON t.department_id = d.id WHERE t.id = ?");
$teacher->execute([$teacherId]);
$teacher = $teacher->fetch();

if (!$teacher) {
    die('Teacher not found');
}

// Get period info
if ($periodId) {
    $period = $pdo->prepare("SELECT * FROM evaluation_periods WHERE id = ?");
    $period->execute([$periodId]);
    $period = $period->fetch();
}
if (!$period) {
    $period = $pdo->query("SELECT * FROM evaluation_periods WHERE is_active = 1 ORDER BY end_date DESC LIMIT 1")->fetch();
}

$periodId = $period['id'] ?? null;

// Get scores
$scores = getTeacherScore($teacherId, $periodId);

// Get detailed evaluations
$evaluations = $pdo->prepare("
    SELECT e.*, u.full_name as rater_name
    FROM evaluations e
    LEFT JOIN users u ON e.rater_id = u.id
    WHERE e.teacher_id = ? AND e.evaluation_period_id = ?
    ORDER BY e.submitted_at DESC
");
$evaluations->execute([$teacherId, $periodId]);
$evaluations = $evaluations->fetchAll();

// Generate HTML for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Teacher Evaluation Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #dc3545; padding-bottom: 20px; }
        .header h1 { margin: 0; color: #dc3545; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        .section { margin-bottom: 25px; }
        .section h2 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; font-size: 16px; }
        .score-grid { display: table; width: 100%; margin-bottom: 20px; }
        .score-row { display: table-row; }
        .score-cell { display: table-cell; padding: 15px; text-align: center; border: 1px solid #ddd; }
        .score-cell h3 { margin: 0 0 10px 0; font-size: 14px; }
        .score-big { font-size: 36px; font-weight: bold; color: #dc3545; }
        .score-label { font-size: 10px; color: #666; }
        .criteria-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .criteria-table th, .criteria-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        .criteria-table th { background: #f5f5f5; font-weight: bold; }
        .criteria-table .avg { text-align: center; font-weight: bold; }
        .comments { margin-top: 20px; }
        .comment-box { background: #f9f9f9; padding: 10px; margin-bottom: 10px; border-left: 3px solid #dc3545; }
        .comment-meta { font-size: 10px; color: #666; margin-bottom: 5px; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; }
        .badge-student { background: #36a2eb; color: white; }
        .badge-ph { background: #ffcd56; color: #333; }
        .badge-dean { background: #ff6384; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Teacher Evaluation Report</h1>
        <p><strong>' . htmlspecialchars($teacher['full_name']) . '</strong></p>
        <p>' . htmlspecialchars($teacher['dept_name']) . ' | ' . htmlspecialchars($period['title']) . '</p>
        <p>Generated: ' . date('F j, Y g:i A') . '</p>
    </div>
    
    <div class="section">
        <h2>Composite Score Summary</h2>
        <div class="score-grid">
            <div class="score-row">
                <div class="score-cell" style="background: #dc3545; color: white;">
                    <h3 style="color: white;">OVERALL COMPOSITE</h3>
                    <div class="score-big" style="color: white;">' . $scores['composite'] . '</div>
                    <div class="score-label" style="color: #ffcccc;">out of 5.00</div>
                </div>
                <div class="score-cell">
                    <h3>Student (50%)</h3>
                    <div class="score-big">' . $scores['student_avg'] . '</div>
                    <div class="score-label">n=' . $scores['student_count'] . ' responses</div>
                </div>
                <div class="score-cell">
                    <h3>Program Head (30%)</h3>
                    <div class="score-big">' . $scores['ph_avg'] . '</div>
                    <div class="score-label">n=' . $scores['ph_count'] . ' responses</div>
                </div>
                <div class="score-cell">
                    <h3>Dean (20%)</h3>
                    <div class="score-big">' . $scores['dean_avg'] . '</div>
                    <div class="score-label">n=' . $scores['dean_count'] . ' responses</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <h2>Detailed Criteria Breakdown</h2>
        <table class="criteria-table">
            <thead>
                <tr>
                    <th>Rater Group</th>
                    <th>Criterion</th>
                    <th>Average Score</th>
                    <th>Weight</th>
                </tr>
            </thead>
            <tbody>';

// Calculate averages per criteria
$studentCriteria = ['Teaching Clarity' => 'teaching_clarity', 'Engagement' => 'engagement', 'Fairness' => 'fairness'];
$phCriteria = ['Curriculum' => 'curriculum', 'Assessment' => 'assessment', 'Mentoring' => 'mentoring'];
$deanCriteria = ['Attendance' => 'attendance', 'Commitment' => 'commitment', 'Quality' => 'quality'];

foreach ($studentCriteria as $label => $field) {
    $avg = $pdo->prepare("SELECT AVG($field) FROM evaluations WHERE teacher_id = ? AND evaluation_period_id = ? AND rater_role = 'student' AND $field IS NOT NULL");
    $avg->execute([$teacherId, $periodId]);
    $val = round($avg->fetchColumn(), 2);
    $html .= '<tr><td><span class="badge badge-student">Student</span></td><td>' . $label . '</td><td class="avg">' . $val . '</td><td>50%</td></tr>';
}

foreach ($phCriteria as $label => $field) {
    $avg = $pdo->prepare("SELECT AVG($field) FROM evaluations WHERE teacher_id = ? AND evaluation_period_id = ? AND rater_role = 'program_head' AND $field IS NOT NULL");
    $avg->execute([$teacherId, $periodId]);
    $val = round($avg->fetchColumn(), 2);
    $html .= '<tr><td><span class="badge badge-ph">Program Head</span></td><td>' . $label . '</td><td class="avg">' . $val . '</td><td>30%</td></tr>';
}

foreach ($deanCriteria as $label => $field) {
    $avg = $pdo->prepare("SELECT AVG($field) FROM evaluations WHERE teacher_id = ? AND evaluation_period_id = ? AND rater_role = 'dean' AND $field IS NOT NULL");
    $avg->execute([$teacherId, $periodId]);
    $val = round($avg->fetchColumn(), 2);
    $html .= '<tr><td><span class="badge badge-dean">Dean</span></td><td>' . $label . '</td><td class="avg">' . $val . '</td><td>20%</td></tr>';
}

$html .= '</tbody></table></div>';

// Comments section
$html .= '<div class="section comments"><h2>Qualitative Feedback</h2>';

$studentComments = array_filter($evaluations, fn($e) => $e['rater_role'] === 'student' && !empty($e['comments']));
$phComments = array_filter($evaluations, fn($e) => $e['rater_role'] === 'program_head' && !empty($e['comments']));
$deanComments = array_filter($evaluations, fn($e) => $e['rater_role'] === 'dean' && !empty($e['comments']));

if (!empty($studentComments)) {
    $html .= '<h3>Student Comments</h3>';
    foreach (array_slice($studentComments, 0, 5) as $c) {
        $html .= '<div class="comment-box"><div class="comment-meta">Anonymous Student | ' . date('M d, Y', strtotime($c['submitted_at'])) . '</div>' . htmlspecialchars($c['comments']) . '</div>';
    }
}

if (!empty($phComments)) {
    $html .= '<h3>Program Head Comments</h3>';
    foreach ($phComments as $c) {
        $html .= '<div class="comment-box"><div class="comment-meta">' . htmlspecialchars($c['rater_name']) . ' | ' . date('M d, Y', strtotime($c['submitted_at'])) . '</div>' . htmlspecialchars($c['comments']) . '</div>';
    }
}

if (!empty($deanComments)) {
    $html .= '<h3>Dean Comments</h3>';
    foreach ($deanComments as $c) {
        $html .= '<div class="comment-box"><div class="comment-meta">' . htmlspecialchars($c['rater_name']) . ' | ' . date('M d, Y', strtotime($c['submitted_at'])) . '</div>' . htmlspecialchars($c['comments']) . '</div>';
    }
}

$html .= '</div>';

$html .= '
    <div class="footer">
        <p>This report was generated automatically by the EvalSystem.</p>
        <p>© ' . date('Y') . ' Global Reciprocal Colleges - Teacher Evaluation System</p>
    </div>
</body>
</html>
';

// Output as PDF using mPDF or save as HTML for printing
// For now, output as HTML with print-friendly CSS
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="teacher_evaluation_' . $teacherId . '_' . date('Ymd') . '.html"');
echo $html;
