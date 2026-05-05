<?php
require_once 'config/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireRole(['student','program_head','dean']);

$role = $_SESSION['role'];
$userId = $_SESSION['user_id'];
$teacherId = intval($_GET['teacher_id'] ?? 0);

if (!$teacherId) {
    header("Location: dashboard.php");
    exit;
}

$teacher = $pdo->prepare("SELECT * FROM teachers WHERE id = ? AND is_active = 1");
$teacher->execute([$teacherId]);
$teacher = $teacher->fetch();
if (!$teacher) {
    header("Location: dashboard.php");
    exit;
}

// For students, verify they are enrolled in the teacher's section
if ($role === 'student') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM section_students ss
        JOIN section_assignments sa ON ss.section_id = sa.section_id
        WHERE ss.student_id = ? AND sa.teacher_id = ?
    ");
    $stmt->execute([$userId, $teacherId]);
    $inSection = $stmt->fetchColumn() > 0;
    
    if (!$inSection) {
        die("You cannot evaluate this teacher. You are not enrolled in their section.");
    }
}

// For deans and program heads, verify the teacher is in their department
if ($role === 'dean' || $role === 'program_head') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM teachers t
        JOIN users u ON u.department_id = t.department_id
        WHERE t.id = ? AND u.id = ?
    ");
    $stmt->execute([$teacherId, $userId]);
    $inDepartment = $stmt->fetchColumn() > 0;
    
    if (!$inDepartment) {
        die("You cannot evaluate this teacher. They are not in your department.");
    }
}

$periodStmt = $pdo->query("SELECT * FROM evaluation_periods WHERE is_active = 1 ORDER BY end_date DESC LIMIT 1");
$activePeriod = $periodStmt->fetch();
if (!$activePeriod) {
    die("No active evaluation period.");
}
$periodId = $activePeriod['id'];

// Check already evaluated
if ($role !== 'student') {
    $check = $pdo->prepare("SELECT id FROM evaluations WHERE teacher_id = ? AND rater_id = ? AND evaluation_period_id = ?");
    $check->execute([$teacherId, $userId, $periodId]);
    if ($check->fetch()) {
        die("You have already evaluated this teacher for the current period.");
    }
} else {
    // For anonymous students, use session-based duplicate prevention
    $evalKey = "eval_{$teacherId}_{$periodId}";
    if (!empty($_SESSION[$evalKey])) {
        die("You have already evaluated this teacher for the current period.");
    }
}

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'teacher_id' => $teacherId,
        'rater_id' => ($role === 'student') ? null : $userId,
        'rater_role' => $role,
        'evaluation_period_id' => $periodId,
        'teaching_clarity' => $role === 'student' ? ($_POST['teaching_clarity'] ?? null) : null,
        'engagement' => $role === 'student' ? ($_POST['engagement'] ?? null) : null,
        'fairness' => $role === 'student' ? ($_POST['fairness'] ?? null) : null,
        'curriculum' => $role === 'program_head' ? ($_POST['curriculum'] ?? null) : null,
        'assessment' => $role === 'program_head' ? ($_POST['assessment'] ?? null) : null,
        'mentoring' => $role === 'program_head' ? ($_POST['mentoring'] ?? null) : null,
        'attendance' => $role === 'dean' ? ($_POST['attendance'] ?? null) : null,
        'commitment' => $role === 'dean' ? ($_POST['commitment'] ?? null) : null,
        'quality' => $role === 'dean' ? ($_POST['quality'] ?? null) : null,
        'comments' => $_POST['comments'] ?? ''
    ];
    
    $stmt = $pdo->prepare("INSERT INTO evaluations 
        (teacher_id, rater_id, rater_role, evaluation_period_id, teaching_clarity, engagement, fairness, curriculum, assessment, mentoring, attendance, commitment, quality, comments)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['teacher_id'], $data['rater_id'], $data['rater_role'], $data['evaluation_period_id'],
        $data['teaching_clarity'], $data['engagement'], $data['fairness'],
        $data['curriculum'], $data['assessment'], $data['mentoring'],
        $data['attendance'], $data['commitment'], $data['quality'],
        $data['comments']
    ]);
    
    auditLog($userId, 'EVALUATION_SUBMIT', "Submitted evaluation for teacher {$teacherId} as {$role}");
    
    if ($role === 'student') {
        $_SESSION[$evalKey] = true;
    }
    
    $success = "Evaluation submitted successfully!";
}

function ratingInput($name, $label) {
    $html = "<label>$label</label><div class='rating-group'>";
    for ($i = 1; $i <= 5; $i++) {
        $html .= "<label class='rating-label'><input type='radio' name='$name' value='$i' required> $i</label>";
    }
    $html .= "</div>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Evaluate - <?php echo htmlspecialchars($teacher['full_name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
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
    <h2>Evaluate: <?php echo htmlspecialchars($teacher['full_name']); ?></h2>
    <p>Period: <?php echo htmlspecialchars($activePeriod['title']); ?> | Your Role: <strong><?php echo ucfirst($role); ?></strong></p>
    <?php if ($success): ?><div class="alert success"><?php echo $success; ?></div><?php endif; ?>
    
    <form method="POST" action="">
        <?php if ($role === 'student'): ?>
        <fieldset>
            <legend>Student Criteria (50% Weight)</legend>
            <?php echo ratingInput('teaching_clarity', 'Teaching Clarity'); ?>
            <?php echo ratingInput('engagement', 'Engagement'); ?>
            <?php echo ratingInput('fairness', 'Fairness'); ?>
        </fieldset>
        <?php elseif ($role === 'program_head'): ?>
        <fieldset>
            <legend>Program Head Criteria (30% Weight)</legend>
            <?php echo ratingInput('curriculum', 'Curriculum'); ?>
            <?php echo ratingInput('assessment', 'Assessment'); ?>
            <?php echo ratingInput('mentoring', 'Mentoring'); ?>
        </fieldset>
        <?php elseif ($role === 'dean'): ?>
        <fieldset>
            <legend>Dean Criteria (20% Weight)</legend>
            <?php echo ratingInput('attendance', 'Attendance'); ?>
            <?php echo ratingInput('commitment', 'Commitment'); ?>
            <?php echo ratingInput('quality', 'Quality'); ?>
        </fieldset>
        <?php endif; ?>
        
        <label>Comments (Optional)</label>
        <textarea name="comments" rows="4"></textarea>
        
        <button type="submit">Submit Evaluation</button>
    </form>
</div>
</body>
</html>
