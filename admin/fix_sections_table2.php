<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

// Get foreign key constraint name for program_id
$stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'sections' AND COLUMN_NAME = 'program_id' AND CONSTRAINT_SCHEMA = 'teacher_eval'");
$fk = $stmt->fetch();

if ($fk) {
    // Drop the foreign key constraint first
    $pdo->exec("ALTER TABLE sections DROP FOREIGN KEY " . $fk['CONSTRAINT_NAME']);
    echo "Dropped foreign key: " . $fk['CONSTRAINT_NAME'] . "\n";
}

// Check if program_id column exists
$stmt = $pdo->query("SHOW COLUMNS FROM sections LIKE 'program_id'");
$hasProgramId = $stmt->fetch();

if ($hasProgramId) {
    // Drop the program_id column
    $pdo->exec("ALTER TABLE sections DROP COLUMN program_id");
    echo "Dropped program_id column from sections table.\n";
} else {
    echo "program_id column does not exist.\n";
}

// Check if year_level column exists
$stmt = $pdo->query("SHOW COLUMNS FROM sections LIKE 'year_level'");
$hasYearLevel = $stmt->fetch();

if (!$hasYearLevel) {
    $pdo->exec("ALTER TABLE sections ADD COLUMN year_level INT NULL AFTER department_id");
    echo "Added year_level column to sections table.\n";
} else {
    echo "year_level column already exists.\n";
}

echo "Sections table fixed.";
