<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$type = $_GET['type'] ?? 'teachers';
$periodId = $_GET['period_id'] ?? null;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="export_' . $type . '_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');

if ($type === 'teachers') {
    // Export all teachers with their scores
    fputcsv($output, ['Teacher Name', 'Department', 'Composite Score', 'Student Avg', 'Student Count', 'Program Head Avg', 'PH Count', 'Dean Avg', 'Dean Count']);
    
    $teachers = $pdo->query("SELECT t.*, d.name as dept_name FROM teachers t JOIN departments d ON t.department_id = d.id WHERE t.is_active = 1")->fetchAll();
    
    foreach ($teachers as $t) {
        $scores = getTeacherScore($t['id'], $periodId);
        fputcsv($output, [
            $t['full_name'],
            $t['dept_name'],
            $scores['composite'],
            $scores['student_avg'],
            $scores['student_count'],
            $scores['ph_avg'],
            $scores['ph_count'],
            $scores['dean_avg'],
            $scores['dean_count']
        ]);
    }
    
} elseif ($type === 'evaluations') {
    // Export all evaluation responses
    fputcsv($output, ['ID', 'Teacher', 'Rater Role', 'Rater Name', 'Period', 'Teaching Clarity', 'Engagement', 'Fairness', 'Curriculum', 'Assessment', 'Mentoring', 'Attendance', 'Commitment', 'Quality', 'Comments', 'Submitted At']);
    
    $sql = "
        SELECT e.*, t.full_name as teacher_name, u.full_name as rater_name, ep.title as period_title
        FROM evaluations e
        JOIN teachers t ON e.teacher_id = t.id
        LEFT JOIN users u ON e.rater_id = u.id
        JOIN evaluation_periods ep ON e.evaluation_period_id = ep.id
    ";
    if ($periodId) {
        $sql .= " WHERE e.evaluation_period_id = ?";
    }
    $sql .= " ORDER BY e.submitted_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($periodId ? [$periodId] : []);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['teacher_name'],
            $row['rater_role'],
            $row['rater_name'] ?: 'Anonymous',
            $row['period_title'],
            $row['teaching_clarity'] ?: '-',
            $row['engagement'] ?: '-',
            $row['fairness'] ?: '-',
            $row['curriculum'] ?: '-',
            $row['assessment'] ?: '-',
            $row['mentoring'] ?: '-',
            $row['attendance'] ?: '-',
            $row['commitment'] ?: '-',
            $row['quality'] ?: '-',
            $row['comments'] ?: '',
            $row['submitted_at']
        ]);
    }
    
} elseif ($type === 'audit') {
    // Export audit logs
    fputcsv($output, ['ID', 'User', 'Action', 'Details', 'IP Address', 'Timestamp']);
    
    $logs = $pdo->query("SELECT a.*, u.full_name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 10000")->fetchAll();
    
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['user_name'] ?: 'System',
            $log['action'],
            $log['details'],
            $log['ip_address'],
            $log['created_at']
        ]);
    }
}

fclose($output);
exit;
