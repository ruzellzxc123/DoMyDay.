<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $firstNames = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica', 'Thomas', 'Sarah', 'Charles', 'Karen', 'Christopher', 'Nancy', 'Daniel', 'Lisa', 'Matthew', 'Betty', 'Anthony', 'Margaret', 'Mark', 'Sandra', 'Donald', 'Ashley', 'Steven', 'Dorothy', 'Paul', 'Emily', 'Andrew', 'Donna', 'Joshua', 'Michelle', 'Kenneth', 'Carol', 'Kevin', 'Amanda', 'Brian', 'Melissa', 'George', 'Deborah', 'Edward', 'Stephanie'];
    
    $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker', 'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores', 'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts'];
    
    $titles = ['Dr.', 'Prof.', 'Assoc. Prof.', 'Asst. Prof.'];
    
    // Get current count to generate unique emails
    $currentCount = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    
    $teachersToAdd = [];
    $numToAdd = 50;
    
    for ($i = 0; $i < $numToAdd; $i++) {
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $title = $titles[array_rand($titles)];
        $fullName = $title . ' ' . $firstName . ' ' . $lastName;
        $email = strtolower($firstName . '.' . $lastName . ($currentCount + $i + 1) . '@school.edu');
        $dept = rand(1, 5); // Random department 1-5
        
        $teachersToAdd[] = [$fullName, $email, $dept];
    }
    
    $added = 0;
    $skipped = 0;
    
    foreach ($teachersToAdd as $teacher) {
        $stmt = $pdo->prepare("INSERT INTO teachers (full_name, email, department_id, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
        try {
            $stmt->execute([$teacher[0], $teacher[1], $teacher[2]]);
            $added++;
        } catch (PDOException $e) {
            // Skip duplicates
            $skipped++;
        }
    }
    
    $newTotal = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    
    echo "<h2 style='color:green'>Success!</h2>";
    echo "<p><strong>Added:</strong> $added teachers</p>";
    echo "<p><strong>Skipped (duplicates):</strong> $skipped teachers</p>";
    echo "<p><strong>Total teachers in database:</strong> $newTotal</p>";
    
    echo "<h3>Sample of Added Teachers:</h3>";
    echo "<ul>";
    $count = 0;
    foreach ($teachersToAdd as $teacher) {
        if ($count < 10) {
            echo "<li>" . htmlspecialchars($teacher[0]) . " - " . htmlspecialchars($teacher[1]) . " (Dept " . $teacher[2] . ")</li>";
            $count++;
        }
    }
    echo "</ul>";
    
    echo "<p><a href='teachers.php'>View All Teachers</a> | <a href='dashboard.php'>Back to Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color:red'>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='dashboard.php'>Back to Dashboard</a></p>";
}
?>
