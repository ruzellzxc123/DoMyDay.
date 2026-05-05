<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

// Get all departments
$departments = $pdo->query("SELECT * FROM departments ORDER BY id LIMIT 5")->fetchAll();

if (count($departments) < 5) {
    echo "Need at least 5 departments. Found: " . count($departments);
    exit;
}

// Sample teachers data - one per department
$teachers = [
    [
        'full_name' => 'Prof. Juan Dela Cruz',
        'email' => 'juan.delacruz@school.edu',
        'department_id' => $departments[0]['id'],
        'dept_name' => $departments[0]['name']
    ],
    [
        'full_name' => 'Prof. Maria Santos',
        'email' => 'maria.santos@school.edu',
        'department_id' => $departments[1]['id'],
        'dept_name' => $departments[1]['name']
    ],
    [
        'full_name' => 'Prof. Pedro Reyes',
        'email' => 'pedro.reyes@school.edu',
        'department_id' => $departments[2]['id'],
        'dept_name' => $departments[2]['name']
    ],
    [
        'full_name' => 'Prof. Ana Garcia',
        'email' => 'ana.garcia@school.edu',
        'department_id' => $departments[3]['id'],
        'dept_name' => $departments[3]['name']
    ],
    [
        'full_name' => 'Prof. Jose Mendoza',
        'email' => 'jose.mendoza@school.edu',
        'department_id' => $departments[4]['id'],
        'dept_name' => $departments[4]['name']
    ]
];

$created = 0;

echo "Creating 5 teacher accounts\n\n";

foreach ($teachers as $t) {
    // Check if email already exists
    $check = $pdo->prepare("SELECT id FROM teachers WHERE email = ?");
    $check->execute([$t['email']]);
    
    if ($check->fetch()) {
        echo "SKIP: {$t['email']} already exists\n";
        continue;
    }
    
    $stmt = $pdo->prepare("INSERT INTO teachers (email, full_name, department_id, is_active) VALUES (?, ?, ?, 1)");
    $stmt->execute([$t['email'], $t['full_name'], $t['department_id']]);
    
    echo "CREATED: {$t['full_name']} ({$t['email']}) - {$t['dept_name']}\n";
    $created++;
}

echo "\n========================================\n";
echo "Total teachers created: $created\n";
echo "========================================\n";
?>
