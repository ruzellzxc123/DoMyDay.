<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

echo "<h2>Fixing section_assignments table...</h2>";

try {
    // Check current indexes
    $stmt = $pdo->query("SHOW INDEX FROM section_assignments");
    $indexes = $stmt->fetchAll();
    
    echo "<h3>Current indexes:</h3><pre>";
    print_r($indexes);
    echo "</pre>";
    
    // Check if there's a wrong unique constraint on just section_id
    $hasWrongConstraint = false;
    foreach ($indexes as $index) {
        if ($index['Key_name'] !== 'PRIMARY' && $index['Key_name'] !== 'unique_section_teacher' && $index['Non_unique'] == 0) {
            $hasWrongConstraint = true;
            echo "<p>Found wrong constraint: {$index['Key_name']} on column: {$index['Column_name']}</p>";
        }
    }
    
    if ($hasWrongConstraint) {
        // Drop and recreate the table with correct constraints
        echo "<p>Fixing table structure...</p>";
        
        $pdo->exec("DROP TABLE IF EXISTS section_assignments");
        
        $pdo->exec("CREATE TABLE section_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_id INT NOT NULL,
            teacher_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_section_teacher (section_id, teacher_id),
            FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
        )");
        
        echo "<p style='color: green;'>✓ Table recreated successfully with correct constraints!</p>";
    } else {
        echo "<p style='color: green;'>✓ Table structure is correct.</p>";
    }
    
    // Verify
    $stmt = $pdo->query("SHOW INDEX FROM section_assignments");
    $newIndexes = $stmt->fetchAll();
    
    echo "<h3>Updated indexes:</h3><pre>";
    print_r($newIndexes);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<p><a href="sections.php" class="btn">Back to Sections</a></p>
