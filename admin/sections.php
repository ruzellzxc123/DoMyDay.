<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$msg = $_GET['msg'] ?? '';
if ($msg) $msg = urldecode($msg);
$error = $_GET['error'] ?? '';
if ($error) $error = urldecode($error);

// Create section with auto teacher assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_section'])) {
    $deptId = $_POST['department_id'];
    $yearLevel = $_POST['year_level'];
    $sectionNumber = $_POST['section_number'];
    $academicYear = $_POST['academic_year'];
    $semester = $_POST['semester'];
    
    // Get department code
    $stmt = $pdo->prepare("SELECT department_code FROM departments WHERE id = ?");
    $stmt->execute([$deptId]);
    $dept = $stmt->fetch();
    
    // Auto-generate section name: CODE-SECTION (e.g., BSIT-101, BSIT-306)
    $sectionName = $dept['department_code'] . '-' . $sectionNumber;
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO sections (section_name, department_id, year_level, academic_year, semester) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sectionName, $deptId, $yearLevel, $academicYear, $semester]);
        $sectionId = $pdo->lastInsertId();
        
        // Auto-assign ALL active teachers from this department
        $teacherStmt = $pdo->prepare("SELECT id, full_name FROM teachers WHERE department_id = ? AND is_active = 1 ORDER BY full_name");
        $teacherStmt->execute([$deptId]);
        $teachers = $teacherStmt->fetchAll();
        
        $assignmentStmt = $pdo->prepare("INSERT INTO section_assignments (section_id, teacher_id) VALUES (?, ?)");
        $assignedCount = 0;
        
        foreach ($teachers as $teacher) {
            try {
                $assignmentStmt->execute([$sectionId, $teacher['id']]);
                $assignedCount++;
            } catch (PDOException $e) {
                // Skip if duplicate assignment (shouldn't happen but just in case)
                if (strpos($e->getMessage(), 'unique_section_teacher') === false) {
                    throw $e;
                }
            }
        }
        
        // Auto-enroll all matching students (same department and year level)
        $studentStmt = $pdo->prepare("
            SELECT u.id, u.full_name
            FROM users u
            WHERE u.role = 'student' 
              AND u.department_id = ? 
              AND u.year_level = ?
              AND u.is_active = 1
        ");
        $studentStmt->execute([$deptId, $yearLevel]);
        $studentsToEnroll = $studentStmt->fetchAll();
        
        // Enroll all matching students
        $enrollStmt = $pdo->prepare("INSERT INTO section_students (section_id, student_id) VALUES (?, ?)");
        $enrolledCount = 0;
        
        foreach ($studentsToEnroll as $student) {
            try {
                $enrollStmt->execute([$sectionId, $student['id']]);
                $enrolledCount++;
            } catch (PDOException $e) {
                // Skip if duplicate (shouldn't happen but just in case)
                if (strpos($e->getMessage(), 'unique_section_student') === false) {
                    throw $e;
                }
            }
        }
        
        if ($assignedCount > 0) {
            auditLog($_SESSION['user_id'], 'SECTION_CREATE', "Created section $sectionName with $assignedCount auto-assigned teachers and $enrolledCount auto-enrolled students");
            $msg = "Section created with $assignedCount teachers assigned and $enrolledCount students enrolled.";
        } else {
            auditLog($_SESSION['user_id'], 'SECTION_CREATE', "Created section $sectionName (no teacher available in department)");
            $msg = "Section created but no teachers available in department to assign.";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Error creating section: " . $e->getMessage();
    }
}

// Auto-assign teachers to individual section
if (isset($_GET['auto_assign_section'])) {
    $sectionId = intval($_GET['auto_assign_section']);
    
    try {
        // Get section details
        $sectionStmt = $pdo->prepare("SELECT id, section_name, department_id, year_level FROM sections WHERE id = ?");
        $sectionStmt->execute([$sectionId]);
        $section = $sectionStmt->fetch();
        
        if (!$section) {
            $error = "Section ID $sectionId not found.";
            header("Location: sections.php?error=" . urlencode($error));
            exit;
        } else {
            // Start transaction
            $pdo->beginTransaction();
            
            // Get ALL active teachers from this section's department
            $teacherStmt = $pdo->prepare("SELECT id, full_name FROM teachers WHERE department_id = ? AND is_active = 1 ORDER BY full_name");
            $teacherStmt->execute([$section['department_id']]);
            $teachers = $teacherStmt->fetchAll();
            
            if (empty($teachers)) {
                $pdo->rollBack();
                $error = "No active teachers found in department ID {$section['department_id']}.";
                header("Location: sections.php?error=" . urlencode($error));
                exit;
            } else {
                // Assign ALL teachers to this section
                $assignmentStmt = $pdo->prepare("INSERT INTO section_assignments (section_id, teacher_id) VALUES (?, ?)");
                $assignedCount = 0;
                $duplicateCount = 0;
                
                foreach ($teachers as $teacher) {
                    try {
                        $assignmentStmt->execute([$sectionId, $teacher['id']]);
                        $assignedCount++;
                    } catch (PDOException $e) {
                        // Count duplicate assignments (already assigned)
                        if (strpos($e->getMessage(), 'unique_section_teacher') !== false) {
                            $duplicateCount++;
                        } else {
                            throw $e;
                        }
                    }
                }
                
                // Auto-enroll all matching students (same department and year level) who aren't already enrolled
                $studentStmt = $pdo->prepare("
                    SELECT u.id, u.full_name
                    FROM users u
                    WHERE u.role = 'student' 
                      AND u.department_id = ? 
                      AND u.year_level = ?
                      AND u.is_active = 1
                      AND u.id NOT IN (
                        SELECT student_id FROM section_students WHERE section_id = ?
                      )
                ");
                $studentStmt->execute([$section['department_id'], $section['year_level'], $sectionId]);
                $studentsToEnroll = $studentStmt->fetchAll();
                
                // Enroll all matching students
                $enrollStmt = $pdo->prepare("INSERT INTO section_students (section_id, student_id) VALUES (?, ?)");
                $enrolledCount = 0;
                
                foreach ($studentsToEnroll as $student) {
                    try {
                        $enrollStmt->execute([$sectionId, $student['id']]);
                        $enrolledCount++;
                    } catch (PDOException $e) {
                        // Skip if duplicate (shouldn't happen but just in case)
                        if (strpos($e->getMessage(), 'unique_section_student') === false) {
                            throw $e;
                        }
                    }
                }
                
                $pdo->commit();
                
                auditLog($_SESSION['user_id'], 'SECTION_AUTO_ASSIGN', "Auto-assigned $assignedCount teachers to section {$section['section_name']} and enrolled $enrolledCount students");
                $msg = "Successfully assigned $assignedCount teachers to {$section['section_name']}.";
                if ($enrolledCount > 0) {
                    $msg .= " ($enrolledCount students auto-enrolled)";
                }
                if ($duplicateCount > 0) {
                    $msg .= " ($duplicateCount teachers were already assigned)";
                }
                header("Location: sections.php?msg=" . urlencode($msg));
                exit;
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error auto-assigning teachers: " . $e->getMessage();
        header("Location: sections.php?error=" . urlencode($error));
        exit;
    }
}

// Auto-assign teachers to all sections without teachers
if (isset($_GET['auto_assign_all'])) {
    try {
        // Get all sections without any teachers
        $stmt = $pdo->query("
            SELECT s.id, s.department_id, s.section_name 
            FROM sections s 
            LEFT JOIN section_assignments sa ON s.id = sa.section_id 
            WHERE sa.id IS NULL
        ");
        $sectionsWithoutTeachers = $stmt->fetchAll();
        
        $totalAssignments = 0;
        $skipped = 0;
        $assignmentDetails = [];
        
        foreach ($sectionsWithoutTeachers as $section) {
            // Get ALL active teachers from this section's department
            $teacherStmt = $pdo->prepare("SELECT id, full_name FROM teachers WHERE department_id = ? AND is_active = 1 ORDER BY full_name");
            $teacherStmt->execute([$section['department_id']]);
            $teachers = $teacherStmt->fetchAll();
            
            if (!empty($teachers)) {
                // Assign ALL teachers from this department to the section
                $assignmentStmt = $pdo->prepare("INSERT INTO section_assignments (section_id, teacher_id) VALUES (?, ?)");
                $sectionAssignments = 0;
                
                foreach ($teachers as $teacher) {
                    try {
                        $assignmentStmt->execute([$section['id'], $teacher['id']]);
                        $totalAssignments++;
                        $sectionAssignments++;
                    } catch (PDOException $e) {
                        // Skip if duplicate (shouldn't happen but just in case)
                        if (strpos($e->getMessage(), 'unique_section_teacher') === false) {
                            throw $e;
                        }
                    }
                }
                
                $assignmentDetails[] = "{$section['section_name']} ({$sectionAssignments} teachers)";
            } else {
                $skipped++;
            }
        }
        
        $pdo->commit();
        
        $detailsStr = !empty($assignmentDetails) ? ": " . implode(", ", $assignmentDetails) : "";
        auditLog($_SESSION['user_id'], 'AUTO_ASSIGN_TEACHERS', "Auto-assigned $totalAssignments teacher assignments to " . count($assignmentDetails) . " sections, skipped $skipped");
        $msg = "Auto-assignment complete: $totalAssignments teacher assignments added to " . count($assignmentDetails) . " sections, $skipped skipped (no teachers in department).$detailsStr";
    } catch (Exception $e) {
        $error = "Error auto-assigning teachers: " . $e->getMessage();
    }
    header("Location: sections.php" . ($error ? "?error=" . urlencode($error) : ""));
    exit;
}

// Unenroll student from section
if (isset($_GET['unenroll'])) {
    $id = intval($_GET['unenroll']);
    $pdo->prepare("DELETE FROM section_students WHERE id = ?")->execute([$id]);
    auditLog($_SESSION['user_id'], 'SECTION_STUDENT_UNENROLL', "Unenrolled student from section");
    header("Location: sections.php");
    exit;
}

// Remove teacher from section by section_id and teacher_id
if (isset($_GET['remove_teacher'])) {
    $sectionId = intval($_GET['section_id']);
    $teacherId = intval($_GET['teacher_id']);
    try {
        $pdo->prepare("DELETE FROM section_assignments WHERE section_id = ? AND teacher_id = ?")->execute([$sectionId, $teacherId]);
        auditLog($_SESSION['user_id'], 'SECTION_TEACHER_REMOVE', "Removed teacher $teacherId from section $sectionId");
        $msg = "Teacher removed from section.";
    } catch (Exception $e) {
        $msg = "Error removing teacher: " . $e->getMessage();
    }
    header("Location: sections.php");
    exit;
}

// Assign teacher to section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_teacher'])) {
    $sectionId = intval($_POST['section_id']);
    $teacherId = intval($_POST['teacher_id']);
    
    if (!$teacherId) {
        $msg = "Please select a teacher to assign.";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert teacher assignment
            $pdo->prepare("INSERT INTO section_assignments (section_id, teacher_id) VALUES (?, ?)")
                ->execute([$sectionId, $teacherId]);
            
            // Get section details
            $sectionStmt = $pdo->prepare("SELECT department_id, year_level FROM sections WHERE id = ?");
            $sectionStmt->execute([$sectionId]);
            $section = $sectionStmt->fetch();
            
            // Auto-enroll all matching students (same department and year level) who aren't already enrolled
            $studentStmt = $pdo->prepare("
                SELECT u.id, u.full_name
                FROM users u
                WHERE u.role = 'student' 
                  AND u.department_id = ? 
                  AND u.year_level = ?
                  AND u.is_active = 1
                  AND u.id NOT IN (
                    SELECT student_id FROM section_students WHERE section_id = ?
                  )
            ");
            $studentStmt->execute([$section['department_id'], $section['year_level'], $sectionId]);
            $studentsToEnroll = $studentStmt->fetchAll();
            
            // Enroll all matching students
            $enrollStmt = $pdo->prepare("INSERT INTO section_students (section_id, student_id) VALUES (?, ?)");
            $enrolledCount = 0;
            
            foreach ($studentsToEnroll as $student) {
                try {
                    $enrollStmt->execute([$sectionId, $student['id']]);
                    $enrolledCount++;
                } catch (PDOException $e) {
                    // Skip if duplicate (shouldn't happen but just in case)
                    if (strpos($e->getMessage(), 'unique_section_student') === false) {
                        throw $e;
                    }
                }
            }
            
            $pdo->commit();
            
            auditLog($_SESSION['user_id'], 'SECTION_TEACHER_ASSIGN', "Assigned teacher $teacherId to section $sectionId and auto-enrolled $enrolledCount students");
            $msg = "Teacher assigned successfully.";
            if ($enrolledCount > 0) {
                $msg .= " ($enrolledCount students auto-enrolled)";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'unique_section_teacher') !== false) {
                $msg = "This teacher is already assigned to this section.";
            } else {
                $msg = "Error assigning teacher: " . $e->getMessage();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Error assigning teacher: " . $e->getMessage();
        }
    }
    header("Location: sections.php");
    exit;
}

// Delete section
if (isset($_GET['delete'])) {
    $sid = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM sections WHERE id = ?")->execute([$sid]);
    auditLog($_SESSION['user_id'], 'SECTION_DELETE', "Deleted section $sid");
    $msg = "Section deleted.";
    header("Location: sections.php");
    exit;
}

// Get data - only departments now
$sections = $pdo->query("
    SELECT s.*, d.name as dept_name 
    FROM sections s 
    JOIN departments d ON s.department_id = d.id 
    ORDER BY s.id DESC
")->fetchAll();

$departments = $pdo->query("SELECT * FROM departments")->fetchAll();
$teachers = $pdo->query("SELECT * FROM teachers WHERE is_active = 1 ORDER BY full_name")->fetchAll();

// Get teacher and students for each section
$sectionDetails = [];
foreach ($sections as $s) {
    // Get ALL teachers from this department (for display)
    $stmt = $pdo->prepare("
        SELECT t.id, t.full_name, t.email
        FROM teachers t
        WHERE t.department_id = ? AND t.is_active = 1
        ORDER BY t.full_name
    ");
    $stmt->execute([$s['department_id']]);
    $allDepartmentTeachers = $stmt->fetchAll();
    $sectionDetails[$s['id']]['teachers'] = $allDepartmentTeachers;
    
    // Get assigned teacher IDs - returns array of teacher IDs
    $stmt = $pdo->prepare("
        SELECT DISTINCT sa.teacher_id
        FROM section_assignments sa
        WHERE sa.section_id = ?
    ");
    $stmt->execute([$s['id']]);
    $assignedTeacherIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $sectionDetails[$s['id']]['assignedIds'] = $assignedTeacherIds; // Simple array of IDs
    
    // Get unassigned teachers for dropdown
    $unassignedList = [];
    foreach ($allDepartmentTeachers as $teacher) {
        if (!in_array($teacher['id'], $assignedTeacherIds)) {
            $unassignedList[] = $teacher;
        }
    }
    $sectionDetails[$s['id']]['unassigned'] = $unassignedList;
    
    // Get assigned teachers with full details
    $assignedList = [];
    foreach ($allDepartmentTeachers as $teacher) {
        if (in_array($teacher['id'], $assignedTeacherIds)) {
            $assignedList[] = $teacher;
        }
    }
    $sectionDetails[$s['id']]['assignedTeachers'] = $assignedList;
    
    // Get enrolled students
    $stmt = $pdo->prepare("
        SELECT ss.id, ss.student_id, u.full_name 
        FROM section_students ss 
        JOIN users u ON ss.student_id = u.id 
        WHERE ss.section_id = ?
    ");
    $stmt->execute([$s['id']]);
    $sectionDetails[$s['id']]['students'] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Sections</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .student-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .student-item:last-child {
            border-bottom: none;
        }
        .student-name {
            font-weight: 500;
        }
        .student-actions {
            display: flex;
            gap: 8px;
        }
        .btn-action {
            padding: 4px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .btn-action.edit {
            background: #ffc107;
            color: #212529;
        }
        .btn-action.edit:hover {
            background: #e0a800;
        }
        .btn-action.delete {
            background: #dc3545;
            color: white;
        }
        .btn-action.delete:hover {
            background: #c82333;
        }
        .teacher-list {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 250px;
            overflow-y: auto;
        }
        .teacher-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .teacher-item:last-child {
            border-bottom: none;
        }
        .teacher-item.assigned {
            background: #d4edda;
            margin: 2px -5px;
            padding: 8px 5px;
            border-radius: 4px;
        }
        .teacher-item.unassigned {
            opacity: 0.7;
        }
        .teacher-name {
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="brand">EvalSystem</div>
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="teachers.php">Teachers</a></li>
        <li><a href="periods.php">Periods</a></li>
        <li><a href="users.php">Users</a></li>
        <li><a href="sections.php">Sections</a></li>
        <li><a href="reports.php">Reports</a></li>
        <li><a href="audit.php">Audit Logs</a></li>
        <li><a href="reminders.php">Reminders</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <h2>Manage Sections</h2>
    <p>
        <a href="?auto_assign_all" class="btn" style="background: #28a745;" onclick="return confirm('This will assign ALL teachers from each department to sections that have no teachers. Continue?')">Auto-Assign All Teachers to All Sections</a>
    </p>
    <?php if ($msg): ?><div class="alert success"><?php echo $msg; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?php echo $error; ?></div><?php endif; ?>
    
    <!-- Department Filter Buttons -->
    <fieldset>
        <legend>Filter by Department</legend>
        <div class="dept-buttons" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
            <button type="button" class="btn dept-btn active" data-dept="all" onclick="filterByDept('all')">All Departments</button>
            <?php foreach ($departments as $d): ?>
            <button type="button" class="btn dept-btn" data-dept="<?php echo $d['id']; ?>" onclick="filterByDept(<?php echo $d['id']; ?>)" style="background: #6c757d;"><?php echo htmlspecialchars($d['name']); ?></button>
            <?php endforeach; ?>
        </div>
        
        <!-- Year Level Filter Buttons -->
        <div id="year-buttons" style="display: none; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
            <button type="button" class="btn year-btn active" data-year="all" onclick="filterByYear('all')">All Years</button>
            <button type="button" class="btn year-btn" data-year="1" onclick="filterByYear(1)" style="background: #17a2b8;">1st Year</button>
            <button type="button" class="btn year-btn" data-year="2" onclick="filterByYear(2)" style="background: #17a2b8;">2nd Year</button>
            <button type="button" class="btn year-btn" data-year="3" onclick="filterByYear(3)" style="background: #17a2b8;">3rd Year</button>
            <button type="button" class="btn year-btn" data-year="4" onclick="filterByYear(4)" style="background: #17a2b8;">4th Year</button>
        </div>
    </fieldset>
    
    <!-- Create Section with Teacher -->
    <fieldset>
        <legend>Create New Section</legend>
        <form method="POST" action="" class="filter-form">
            <div><label>Department</label>
                <select name="department_id" id="department_id" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Year Level</label>
                <select name="year_level" id="year_level" required onchange="updateSectionCodes()">
                    <option value="">Select Year</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div><label>Section Code</label>
                <select name="section_number" id="section_number" required>
                    <option value="">Select Year Level First</option>
                </select>
            </div>
            <div><label>Academic Year</label><input type="text" name="academic_year" placeholder="e.g., 2024-2025" required></div>
            <div><label>Semester</label>
                <select name="semester" required>
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                    <option value="Summer">Summer</option>
                </select>
            </div>
            <div><button type="submit" name="create_section" class="btn">Create Section</button></div>
        </form>
    </fieldset>
    
    <script>
    function updateSectionCodes() {
        var yearLevel = document.getElementById('year_level').value;
        var sectionSelect = document.getElementById('section_number');
        
        sectionSelect.innerHTML = '';
        
        if (yearLevel === '') {
            var option = document.createElement('option');
            option.value = '';
            option.text = 'Select Year Level First';
            sectionSelect.appendChild(option);
            return;
        }
        
        // Generate section codes: 101-109, 201-209, 301-309, 401-409
        for (var i = 1; i <= 9; i++) {
            var option = document.createElement('option');
            option.value = yearLevel + '0' + i;
            option.text = yearLevel + '0' + i;
            sectionSelect.appendChild(option);
        }
    }
    </script>
    
    <script>
    var currentDept = 'all';
    var currentYear = 'all';
    
    function filterByDept(deptId) {
        currentDept = deptId;
        
        // Update button styles
        document.querySelectorAll('.dept-btn').forEach(btn => {
            btn.style.background = (btn.dataset.dept == deptId) ? '#dc3545' : '#6c757d';
        });
        
        // Show/hide year buttons
        var yearButtons = document.getElementById('year-buttons');
        if (deptId === 'all') {
            yearButtons.style.display = 'none';
            currentYear = 'all';
            // Reset year button styles
            document.querySelectorAll('.year-btn').forEach(btn => {
                btn.style.background = (btn.dataset.year == 'all') ? '#dc3545' : '#17a2b8';
            });
        } else {
            yearButtons.style.display = 'flex';
        }
        
        applyFilters();
    }
    
    function filterByYear(year) {
        currentYear = year;
        
        // Update button styles
        document.querySelectorAll('.year-btn').forEach(btn => {
            btn.style.background = (btn.dataset.year == year) ? '#dc3545' : '#17a2b8';
        });
        
        applyFilters();
    }
    
    function applyFilters() {
        var sections = document.querySelectorAll('.section-card');
        
        sections.forEach(function(section) {
            var sectionDept = section.dataset.department;
            var sectionYear = section.dataset.year;
            
            var showByDept = (currentDept === 'all' || sectionDept == currentDept);
            var showByYear = (currentYear === 'all' || sectionYear == currentYear);
            
            if (showByDept && showByYear) {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        });
    }
    </script>
    
    <!-- List of Sections -->
    <?php foreach ($sections as $s): ?>
    <fieldset class="section-card" data-department="<?php echo $s['department_id']; ?>" data-year="<?php echo $s['year_level']; ?>">
        <legend><?php echo htmlspecialchars($s['section_name']); ?> - <?php echo htmlspecialchars($s['dept_name']); ?> (<?php echo $s['academic_year']; ?> <?php echo $s['semester']; ?>)</legend>
        
        <div class="cards">
            <!-- Department Teachers -->
            <div class="card">
                <h4>Teachers (<?php echo count($sectionDetails[$s['id']]['assignedIds']); ?>/<?php echo count($sectionDetails[$s['id']]['teachers']); ?> assigned)</h4>
                
                <!-- Assign Teacher Form -->
                <form method="POST" action="" style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="section_id" value="<?php echo $s['id']; ?>">
                    <select name="teacher_id" required style="flex: 1; min-width: 200px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem;">
                        <option value="">Select teacher to assign...</option>
                        <?php foreach ($sectionDetails[$s['id']]['unassigned'] as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="assign_teacher" class="btn" style="padding: 8px 20px; font-size: 0.9rem; font-weight: 600; min-width: 100px; background: #dc3545;" <?php echo empty($sectionDetails[$s['id']]['unassigned']) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>Assign</button>
                    <a href="?auto_assign_section=<?php echo $s['id']; ?>" class="btn" style="padding: 8px 20px; font-size: 0.9rem; font-weight: 600; min-width: 160px; background: #28a745; text-decoration: none; display: inline-block; text-align: center;" onclick="return confirm('Auto-assign ALL teachers from this department to this section?')">Auto-Assign All</a>
                </form>
                
                <?php if (empty($sectionDetails[$s['id']]['teachers'])): ?>
                    <p style="color: #666; font-style: italic;">No teachers in this department</p>
                <?php else: ?>
                <ul class="teacher-list">
                    <!-- Show Assigned Teachers First -->
                    <?php foreach ($sectionDetails[$s['id']]['assignedTeachers'] as $t): ?>
                    <li class="teacher-item assigned">
                        <span class="teacher-name">
                            <?php echo htmlspecialchars($t['full_name']); ?>
                            <span class="badge">Assigned</span>
                        </span>
                        <a href="?remove_teacher=1&section_id=<?php echo $s['id']; ?>&teacher_id=<?php echo $t['id']; ?>" 
                           class="btn-action delete" 
                           title="Remove from section"
                           onclick="return confirm('Remove <?php echo htmlspecialchars($t['full_name']); ?> from this section?')">Delete</a>
                    </li>
                    <?php endforeach; ?>
                    
                    <!-- Show Unassigned Teachers -->
                    <?php foreach ($sectionDetails[$s['id']]['unassigned'] as $t): ?>
                    <li class="teacher-item">
                        <span class="teacher-name">
                            <?php echo htmlspecialchars($t['full_name']); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            
            <!-- Enrolled Students -->
            <div class="card">
                <h4>Students (<?php echo count($sectionDetails[$s['id']]['students']); ?>)</h4>
                <?php if (empty($sectionDetails[$s['id']]['students'])): ?>
                <p style="color: #666; font-style: italic;">No students enrolled</p>
                <?php else: ?>
                <ul class="student-list">
                    <?php foreach ($sectionDetails[$s['id']]['students'] as $st): ?>
                    <li class="student-item">
                        <span class="student-name"><?php echo htmlspecialchars($st['full_name']); ?></span>
                        <div class="student-actions">
                            <a href="users.php?edit=<?php echo $st['student_id']; ?>" class="btn-action edit" title="Edit Student">Edit</a>
                            <a href="?unenroll=<?php echo $st['id']; ?>" class="btn-action delete" title="Unenroll Student" onclick="return confirm('Unenroll this student from section?')">Delete</a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <p><a href="?delete=<?php echo $s['id']; ?>" onclick="return confirm('Delete?')" class="btn" style="background:#dc3545">Delete</a></p>
    </fieldset>
    <?php endforeach; ?>
</div>
</body>
</html>
