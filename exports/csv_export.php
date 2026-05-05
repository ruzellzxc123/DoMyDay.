<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['program_head','dean','admin']);

$type = $_GET['type'] ?? 'scores';
$teacherId = intval($_GET['teacher_id'] ?? 0);
$periodId = intval($_GET['period_id'] ?? 0);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="export_' . $type . '_' . date('Ymd') . '.csv"');

$output = fopen('php://output', 'w');

if ($type === 'scores') {
    fputcsv($output, ['Evaluation ID','Teacher','Period','Rater Role','Rater Name','Teaching Clarity','Engagement','Fairness','Curriculum','Assessment','Mentoring','Attendance','Commitment','Quality','Comments','Submitted At']);
    $sql = "SELECT e.*, t.full_name as teacher_name, p.title as period_name, u.full_name as rater_name 
            FROM evaluations e 
            JOIN teachers t ON e.teacher_id = t.id 
            JOIN evaluation_periods p ON e.evaluation_period_id = p.id 
            LEFT JOIN users u ON e.rater_id = u.id 
            WHERE 1=1";
    $params = [];
    if ($teacherId) { $sql .= " AND e.teacher_id = ?"; $params[] = $teacherId; }
    if ($periodId) { $sql .= " AND e.evaluation_period_id = ?"; $params[] = $periodId; }
    $sql .= " ORDER BY e.submitted_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'], $row['teacher_name'], $row['period_name'], $row['rater_role'],
            $row['rater_name'] ?? 'Anonymous',
            $row['teaching_clarity'] ?? '', $row['engagement'] ?? '', $row['fairness'] ?? '',
            $row['curriculum'] ?? '', $row['assessment'] ?? '', $row['mentoring'] ?? '',
            $row['attendance'] ?? '', $row['commitment'] ?? '', $row['quality'] ?? '',
            $row['comments'] ?? '', $row['submitted_at']
        ]);
    }
} elseif ($type === 'audit') {
    fputcsv($output, ['Log ID','User','Action','Details','IP Address','Created At']);
    $sql = "SELECT a.*, u.full_name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 5000";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [$row['id'], $row['user_name'] ?? 'System/Guest', $row['action'], $row['details'], $row['ip_address'], $row['created_at']]);
    }
}

fclose($output);
auditLog($_SESSION['user_id'], 'EXPORT_CSV', "Exported $type CSV");
exit;
?>

