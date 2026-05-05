<?php
require_once __DIR__ . '/db.php';

function auditLog($userId, $action, $details = '') {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $details, $ip]);
}

function requireRole($roles) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }
    if (!in_array($_SESSION['role'], (array)$roles)) {
        header("Location: " . BASE_URL . "/dashboard.php");
        exit;
    }
}

function getTeacherScore($teacherId, $periodId = null) {
    global $pdo;
    $sql = "SELECT 
        rater_role,
        AVG(teaching_clarity) as tc, AVG(engagement) as en, AVG(fairness) as fa,
        AVG(curriculum) as cu, AVG(assessment) as asmt, AVG(mentoring) as me,
        AVG(attendance) as at, AVG(commitment) as co, AVG(quality) as qu,
        COUNT(*) as cnt
    FROM evaluations WHERE teacher_id = ?";
    $params = [$teacherId];
    if ($periodId) {
        $sql .= " AND evaluation_period_id = ?";
        $params[] = $periodId;
    }
    $sql .= " GROUP BY rater_role";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    
    $studentAvg = 0; $studentCount = 0;
    $phAvg = 0; $phCount = 0;
    $deanAvg = 0; $deanCount = 0;
    
    foreach ($rows as $r) {
        if ($r['rater_role'] == 'student') {
            $studentAvg = ($r['tc'] + $r['en'] + $r['fa']) / 3;
            $studentCount = $r['cnt'];
        } elseif ($r['rater_role'] == 'program_head') {
            $phAvg = ($r['cu'] + $r['asmt'] + $r['me']) / 3;
            $phCount = $r['cnt'];
        } elseif ($r['rater_role'] == 'dean') {
            $deanAvg = ($r['at'] + $r['co'] + $r['qu']) / 3;
            $deanCount = $r['cnt'];
        }
    }
    
    $composite = 0;
    $totalWeight = 0;
    if ($studentCount > 0) { $composite += $studentAvg * WEIGHT_STUDENT; $totalWeight += WEIGHT_STUDENT; }
    if ($phCount > 0) { $composite += $phAvg * WEIGHT_PROGRAM_HEAD; $totalWeight += WEIGHT_PROGRAM_HEAD; }
    if ($deanCount > 0) { $composite += $deanAvg * WEIGHT_DEAN; $totalWeight += WEIGHT_DEAN; }
    if ($totalWeight > 0) $composite = $composite / $totalWeight;
    
    return [
        'student_avg' => round($studentAvg, 2),
        'student_count' => $studentCount,
        'ph_avg' => round($phAvg, 2),
        'ph_count' => $phCount,
        'dean_avg' => round($deanAvg, 2),
        'dean_count' => $deanCount,
        'composite' => round($composite, 2)
    ];
}

function sendReminderEmails() {
    global $pdo;
    // Simplified: queue reminders for users who haven't evaluated in active period
    $stmt = $pdo->query("SELECT id FROM evaluation_periods WHERE is_active = 1 LIMIT 1");
    $period = $stmt->fetch();
    if (!$period) return;
    
    $periodId = $period['id'];
    $users = $pdo->query("SELECT id, email, full_name, role FROM users WHERE role != 'admin' AND is_active = 1")->fetchAll();
    
    foreach ($users as $u) {
        $checked = $pdo->prepare("SELECT COUNT(*) FROM evaluations WHERE rater_id = ? AND evaluation_period_id = ?");
        $checked->execute([$u['id'], $periodId]);
        if ($checked->fetchColumn() == 0) {
            $subject = "Reminder: Teacher Evaluation Pending";
            $body = "Hi {$u['full_name']},\n\nPlease complete your teacher evaluation for the current period.\n\nLogin at: http://{$_SERVER['HTTP_HOST']}" . BASE_URL . "/index.php";
            $stmt = $pdo->prepare("INSERT INTO email_queue (user_id, subject, body) VALUES (?, ?, ?)");
            $stmt->execute([$u['id'], $subject, $body]);
        }
    }
}
?>

