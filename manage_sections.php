<?php
require_once 'config/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireRole('student');

$userId = $_SESSION['user_id'];
$msg = '';

// Handle unenroll
if (isset($_GET['unenroll'])) {
    $sectionId = intval($_GET['unenroll']);
    $stmt = $pdo->prepare("DELETE FROM section_students WHERE section_id = ? AND student_id = ?");
    $stmt->execute([$sectionId, $userId]);
    
    // Also clear section_id from users table if it matches
    $pdo->prepare("UPDATE users SET section_id = NULL WHERE id = ? AND section_id = ?")
        ->execute([$userId, $sectionId]);
    
    auditLog($userId, 'SECTION_UNENROLL', "Student unenrolled from section $sectionId");
    header("Location: manage_sections.php");
    exit;
}

// Handle enroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    $sectionId = intval($_POST['section_id']);
    
    // Check if already enrolled
    $check = $pdo->prepare("SELECT COUNT(*) FROM section_students WHERE section_id = ? AND student_id = ?");
    $check->execute([$sectionId, $userId]);
    
    if ($check->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO section_students (section_id, student_id) VALUES (?, ?)");
        $stmt->execute([$sectionId, $userId]);
        
        // Update user's primary section_id
        $pdo->prepare("UPDATE users SET section_id = ? WHERE id = ?")
            ->execute([$sectionId, $userId]);
        
        auditLog($userId, 'SECTION_ENROLL', "Student enrolled in section $sectionId");
        $msg = "Successfully enrolled in section!";
    } else {
        $msg = "You are already enrolled in this section.";
    }
}

// Get student's current enrollments
$enrollments = $pdo->prepare("
    SELECT s.id, s.section_name, s.year_level, s.academic_year, s.semester,
           d.name as dept_name, d.department_code,
           t.full_name as teacher_name
    FROM section_students ss
    JOIN sections s ON ss.section_id = s.id
    JOIN departments d ON s.department_id = d.id
    LEFT JOIN section_assignments sa ON s.id = sa.section_id
    LEFT JOIN teachers t ON sa.teacher_id = t.id
    WHERE ss.student_id = ?
    ORDER BY s.year_level, s.section_name
");
$enrollments->execute([$userId]);
$mySections = $enrollments->fetchAll();

// Get available sections for enrollment (same department and year level as student)
$userInfo = $pdo->prepare("SELECT department_id, year_level FROM users WHERE id = ?");
$userInfo->execute([$userId]);
$user = $userInfo->fetch();

$availableSections = [];
if ($user['department_id'] && $user['year_level']) {
    // Get sections matching student's department and year level
    // Exclude already enrolled sections
    $enrolledSectionIds = array_column($mySections, 'id');
    $placeholders = $enrolledSectionIds ? implode(',', array_fill(0, count($enrolledSectionIds), '?')) : '0';
    
    $sql = "
        SELECT s.*, d.name as dept_name, d.department_code,
               t.full_name as teacher_name
        FROM sections s
        JOIN departments d ON s.department_id = d.id
        LEFT JOIN section_assignments sa ON s.id = sa.section_id
        LEFT JOIN teachers t ON sa.teacher_id = t.id
        WHERE s.department_id = ? AND s.year_level = ?
    ";
    $params = [$user['department_id'], $user['year_level']];
    
    if ($enrolledSectionIds) {
        $sql .= " AND s.id NOT IN ($placeholders)";
        $params = array_merge($params, $enrolledSectionIds);
    }
    
    $sql .= " ORDER BY s.section_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $availableSections = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Sections - Student</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .section-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #dc3545;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .section-name {
            font-size: 1.25rem;
            font-weight: bold;
            color: #dc3545;
        }
        .section-meta {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .teacher-info {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .btn-unenroll {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .btn-unenroll:hover {
            background: #c82333;
        }
        .enroll-form {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 15px;
            color: #ccc;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #dc3545;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="brand">EvalSystem</div>
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="manage_sections.php">My Sections</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    <h2>My Section Enrollments</h2>
    
    <?php if ($msg): ?>
    <div class="alert <?php echo strpos($msg, 'already') !== false ? 'error' : 'success'; ?>">
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>
    
    <!-- Enroll in New Section -->
    <?php if (!empty($availableSections)): ?>
    <div class="enroll-form">
        <h3>Add Section Enrollment</h3>
        <form method="POST" action="" class="filter-form" style="margin: 0;">
            <div style="flex: 1;">
                <label>Select Available Section</label>
                <select name="section_id" required style="width: 100%;">
                    <option value="">Choose a section...</option>
                    <?php foreach ($availableSections as $s): ?>
                    <option value="<?php echo $s['id']; ?>">
                        <?php echo htmlspecialchars($s['section_name']); ?> - 
                        <?php echo htmlspecialchars($s['dept_name']); ?> 
                        (Teacher: <?php echo htmlspecialchars($s['teacher_name'] ?? 'Not Assigned'); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>&nbsp;</label>
                <button type="submit" name="enroll" class="btn">Enroll</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Current Enrollments -->
    <h3>Currently Enrolled Sections</h3>
    
    <?php if (empty($mySections)): ?>
    <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
        <p>You are not enrolled in any sections yet.</p>
        <?php if (empty($availableSections)): ?>
        <p style="font-size: 0.9rem; margin-top: 10px;">
            Contact your administrator to assign you to a section.
        </p>
        <?php endif; ?>
    </div>
    <?php else: ?>
        <?php foreach ($mySections as $section): ?>
        <div class="section-card">
            <div class="section-header">
                <div>
                    <div class="section-name"><?php echo htmlspecialchars($section['section_name']); ?></div>
                    <div class="section-meta">
                        <?php echo htmlspecialchars($section['dept_name']); ?> | 
                        <?php echo $section['year_level']; ?>th Year | 
                        <?php echo htmlspecialchars($section['academic_year']); ?> - 
                        <?php echo htmlspecialchars($section['semester']); ?>
                    </div>
                </div>
                <a href="?unenroll=<?php echo $section['id']; ?>" 
                   class="btn-unenroll" 
                   onclick="return confirm('Are you sure you want to unenroll from this section?')">
                   Remove
                </a>
            </div>
            <?php if ($section['teacher_name']): ?>
            <div class="teacher-info">
                <strong>Teacher:</strong> <?php echo htmlspecialchars($section['teacher_name']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
