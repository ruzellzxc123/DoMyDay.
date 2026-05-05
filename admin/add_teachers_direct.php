<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 15 Teachers (3 per department)
    $teachers = [
        // Department 1
        ['Dr. Maria Santos', 'maria.santos1@school.edu', 1],
        ['Prof. Robert Smith', 'robert.smith1@school.edu', 1],
        ['Dr. Jessica Martinez', 'jessica.martinez1@school.edu', 1],
        // Department 2
        ['Prof. Juan Dela Cruz', 'juan.delacruz1@school.edu', 2],
        ['Dr. Sarah Johnson', 'sarah.johnson1@school.edu', 2],
        ['Prof. James Taylor', 'james.taylor1@school.edu', 2],
        // Department 3
        ['Dr. Ana Reyes', 'ana.reyes1@school.edu', 3],
        ['Prof. Michael Brown', 'michael.brown1@school.edu', 3],
        ['Dr. Jennifer Anderson', 'jennifer.anderson1@school.edu', 3],
        // Department 4
        ['Prof. Pedro Garcia', 'pedro.garcia1@school.edu', 4],
        ['Dr. Emily Davis', 'emily.davis1@school.edu', 4],
        ['Prof. Christopher Thomas', 'christopher.thomas1@school.edu', 4],
        // Department 5
        ['Dr. Elena Lopez', 'elena.lopez1@school.edu', 5],
        ['Prof. David Wilson', 'david.wilson1@school.edu', 5],
        ['Dr. Amanda Jackson', 'amanda.jackson1@school.edu', 5],
    ];
    
    $added = 0;
    $skipped = 0;
    
    foreach ($teachers as $teacher) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO teachers (full_name, email, department_id, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
        $stmt->execute([$teacher[0], $teacher[1], $teacher[2]]);
        
        if ($stmt->rowCount() > 0) {
            $added++;
        } else {
            $skipped++;
        }
    }
    
    echo "<h2 style='color:green'>Success!</h2>";
    echo "<p><strong>Added:</strong> $added teachers</p>";
    echo "<p><strong>Skipped (already exist):</strong> $skipped teachers</p>";
    
    $count = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    echo "<p><strong>Total teachers in database:</strong> $count</p>";
    
    echo "<p><a href='teachers.php'>View Teachers</a> | <a href='dashboard.php'>Back to Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color:red'>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='dashboard.php'>Back to Dashboard</a></p>";
}
?>
