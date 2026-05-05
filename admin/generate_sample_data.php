<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
requireRole('admin');

$msg = '';
$generated = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $count = intval($_POST['count'] ?? 100);
    $periodId = intval($_POST['period_id'] ?? 0);
    
    if (!$periodId) {
        $activePeriod = $pdo->query("SELECT id FROM evaluation_periods WHERE is_active = 1 ORDER BY end_date DESC LIMIT 1")->fetch();
        $periodId = $activePeriod['id'] ?? null;
    }
    
    if (!$periodId) {
        $msg = "No active evaluation period found. Please create a period first.";
    } else {
        // Get all teachers
        $teachers = $pdo->query("SELECT id, department_id FROM teachers WHERE is_active = 1")->fetchAll();
        
        // Get students
        $students = $pdo->query("SELECT id, department_id, year_level FROM users WHERE role = 'student' AND is_active = 1")->fetchAll();
        
        // Get program heads and deans
        $programHeads = $pdo->query("SELECT id, department_id FROM users WHERE role = 'program_head' AND is_active = 1")->fetchAll();
        $deans = $pdo->query("SELECT id, department_id FROM users WHERE role = 'dean' AND is_active = 1")->fetchAll();
        
        if (empty($teachers)) {
            $msg = "No active teachers found.";
        } else {
            $pdo->beginTransaction();
            
            for ($i = 0; $i < $count; $i++) {
                $teacher = $teachers[array_rand($teachers)];
                $role = ['student', 'student', 'student', 'program_head', 'dean'][array_rand([0,0,0,1,2])]; // 60% students, 20% PH, 20% dean
                
                $data = [
                    'teacher_id' => $teacher['id'],
                    'rater_id' => null,
                    'rater_role' => $role,
                    'evaluation_period_id' => $periodId,
                    'teaching_clarity' => null,
                    'engagement' => null,
                    'fairness' => null,
                    'curriculum' => null,
                    'assessment' => null,
                    'mentoring' => null,
                    'attendance' => null,
                    'commitment' => null,
                    'quality' => null,
                    'comments' => ''
                ];
                
                // Generate random scores based on role (higher scores for better teachers)
                $baseScore = rand(30, 50) / 10; // 3.0 to 5.0
                
                if ($role === 'student') {
                    $data['rater_id'] = $students[array_rand($students)]['id'] ?? null;
                    $data['teaching_clarity'] = min(5, max(1, round($baseScore + (rand(-10, 10) / 10), 0)));
                    $data['engagement'] = min(5, max(1, round($baseScore + (rand(-10, 10) / 10), 0)));
                    $data['fairness'] = min(5, max(1, round($baseScore + (rand(-10, 10) / 10), 0)));
                    
                    $comments = [
                        "Great teacher! Explains concepts clearly.",
                        "Very engaging lectures.",
                        "Fair grading system.",
                        "Needs to improve pacing.",
                        "Excellent mentor.",
                        "Makes class interesting.",
                        "Very knowledgeable in the subject.",
                        " approachable and helpful.",
                        "Clear explanations, good examples.",
                        "Encourages participation."
                    ];
                    $data['comments'] = rand(0, 2) === 0 ? $comments[array_rand($comments)] : '';
                    
                } elseif ($role === 'program_head') {
                    $data['rater_id'] = $programHeads[array_rand($programHeads)]['id'] ?? null;
                    $data['curriculum'] = min(5, max(1, round($baseScore + (rand(-5, 5) / 10), 0)));
                    $data['assessment'] = min(5, max(1, round($baseScore + (rand(-5, 5) / 10), 0)));
                    $data['mentoring'] = min(5, max(1, round($baseScore + (rand(-5, 5) / 10), 0)));
                    
                    $comments = [
                        "Follows curriculum guidelines well.",
                        "Assessment methods are appropriate.",
                        "Good mentoring of students.",
                        "Participates in department activities.",
                        "Keeps up with teaching standards."
                    ];
                    $data['comments'] = rand(0, 1) === 0 ? $comments[array_rand($comments)] : '';
                    
                } else { // dean
                    $data['rater_id'] = $deans[array_rand($deans)]['id'] ?? null;
                    $data['attendance'] = min(5, max(1, round($baseScore + (rand(-3, 3) / 10), 0)));
                    $data['commitment'] = min(5, max(1, round($baseScore + (rand(-3, 3) / 10), 0)));
                    $data['quality'] = min(5, max(1, round($baseScore + (rand(-3, 3) / 10), 0)));
                    
                    $comments = [
                        "Punctual and committed.",
                        "High quality teaching standards.",
                        "Dedicated to student success.",
                        "Professional conduct.",
                        "Contributes to institutional goals."
                    ];
                    $data['comments'] = rand(0, 1) === 0 ? $comments[array_rand($comments)] : '';
                }
                
                // Random date within last 30 days
                $randomDate = date('Y-m-d H:i:s', strtotime("-" . rand(0, 30) . " days -" . rand(0, 23) . " hours"));
                
                $stmt = $pdo->prepare("
                    INSERT INTO evaluations 
                    (teacher_id, rater_id, rater_role, evaluation_period_id, 
                     teaching_clarity, engagement, fairness, 
                     curriculum, assessment, mentoring, 
                     attendance, commitment, quality, comments, submitted_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $data['teacher_id'], $data['rater_id'], $data['rater_role'], $data['evaluation_period_id'],
                    $data['teaching_clarity'], $data['engagement'], $data['fairness'],
                    $data['curriculum'], $data['assessment'], $data['mentoring'],
                    $data['attendance'], $data['commitment'], $data['quality'],
                    $data['comments'], $randomDate
                ]);
                
                $generated++;
            }
            
            $pdo->commit();
            auditLog($_SESSION['user_id'], 'GENERATE_SAMPLE_DATA', "Generated $generated sample evaluations");
            $msg = "Successfully generated $generated sample evaluations!";
        }
    }
}

$periods = $pdo->query("SELECT * FROM evaluation_periods ORDER BY start_date DESC")->fetchAll();
$currentCount = $pdo->query("SELECT COUNT(*) FROM evaluations")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Sample Data</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="brand">EvalSystem</div>
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="teachers.php">Teachers</a></li>
        <li><a href="users.php">Users</a></li>
        <li><a href="sections.php">Sections</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <h2>Generate Sample Evaluation Data</h2>
    
    <?php if ($msg): ?>
    <div class="alert success"><?php echo $msg; ?></div>
    <?php endif; ?>
    
    <div class="cards" style="margin-bottom: 30px;">
        <div class="card">
            <h3>Current Evaluations</h3>
            <p class="big"><?php echo $currentCount; ?></p>
        </div>
    </div>
    
    <form method="POST" action="" style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Number of Evaluations to Generate</label>
            <input type="number" name="count" value="100" min="1" max="1000" style="padding: 10px; width: 200px; font-size: 16px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Evaluation Period</label>
            <select name="period_id" style="padding: 10px; width: 300px;">
                <option value="">Use Active Period</option>
                <?php foreach ($periods as $p): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?> (<?php echo $p['is_active'] ? 'Active' : 'Inactive'; ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <strong>⚠️ Warning:</strong> This will generate realistic sample evaluation data including:
            <ul style="margin: 10px 0;">
                <li>Random scores (1-5) for all criteria based on rater role</li>
                <li>Realistic comments from each rater type</li>
                <li>Distributed across all teachers and departments</li>
                <li>Random dates within the last 30 days</li>
            </ul>
            This data will appear in all reports and dashboards.
        </div>
        
        <button type="submit" name="generate" class="btn" style="font-size: 16px; padding: 15px 30px;">
            Generate Sample Data
        </button>
    </form>
</div>

</body>
</html>
