<?php
// Simple setup script to initialize database
require_once 'config/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Run full schema
    $sql = file_get_contents('sql/schema.sql');
    $pdo->exec($sql);
    
    // Check if teacher_summaries table exists; if not, create it (for existing DBs)
    $check = $pdo->query("SHOW TABLES IN " . DB_NAME . " LIKE 'teacher_summaries'");
    if ($check->rowCount() === 0) {
        $pdo->exec("USE " . DB_NAME);
        $pdo->exec("CREATE TABLE teacher_summaries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            evaluation_period_id INT NOT NULL,
            summary_text TEXT NOT NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            generated_by INT NULL,
            UNIQUE KEY unique_summary (teacher_id, evaluation_period_id),
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            FOREIGN KEY (evaluation_period_id) REFERENCES evaluation_periods(id) ON DELETE CASCADE,
            FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
        )");
    }
    
    // Ensure departments exist
    $pdo->exec("USE " . DB_NAME);
    $departmentCheck = $pdo->query("SELECT COUNT(*) FROM departments")->fetch();
    if ($departmentCheck[0] === 0) {
        $pdo->exec("INSERT INTO departments (name) VALUES
            ('College of Business Administration'),
            ('College of Entrepreneurship'),
            ('College of Accountancy'),
            ('College of Education'),
            ('College of Computer Studies')");
    }
    
    echo "<h2>Setup Complete</h2><p>Database and tables created successfully.</p>";
    echo "<p><a href='index.php'>Go to Login</a></p>";
    echo "<p>Demo accounts (password: <strong>password</strong>):<br>admin@school.edu | dean@school.edu | ph@school.edu | student@school.edu</p>";
} catch (Exception $e) {
    echo "<h2>Setup Failed</h2><p>" . $e->getMessage() . "</p>";
}
?>
