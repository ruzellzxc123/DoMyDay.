<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>Database Info:</h3>";
    echo "<p>Database: " . DB_NAME . "</p>";
    echo "<p>Host: " . DB_HOST . "</p>";
    
    // Read the SQL file
    $sqlFile = __DIR__ . '/../sql/seed_teachers.sql';
    $sql = file_get_contents($sqlFile);
    
    if ($sql === false) {
        die("<p style='color:red'>Error: Could not read seed_teachers.sql file at: " . $sqlFile . "</p>");
    }
    
    echo "<h3>SQL Content:</h3>";
    echo "<pre>" . htmlspecialchars($sql) . "</pre>";
    
    // Execute the SQL
    $pdo->exec($sql);
    
    echo "<h2 style='color:green'>Success!</h2>";
    echo "<p>Teachers have been added to the database.</p>";
    
    // Count teachers
    $count = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    echo "<p>Total teachers in database: <strong>" . $count . "</strong></p>";
    
    echo "<p><a href='teachers.php'>View Teachers</a> | <a href='dashboard.php'>Back to Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color:red'>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='dashboard.php'>Back to Dashboard</a></p>";
}
?>
