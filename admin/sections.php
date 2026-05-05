<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$msg = '';

// Create section with teacher assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_section'])) {
    $deptId = $_POST['department_id'];
    $yearLevel = $_POST['year_level'];
    $sectionNumber = $_POST['section_number'];
    $academicYear = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $teacherId = $_POST['teacher_id'];
    
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
        
        // Assign the teacher to this section
        $stmt = $pdo->prepare("INSERT INTO section_assignments (section_id, teacher_id) VALUES (?, ?)");
        $stmt->execute([$sectionId, $teacherId]);
        
        $pdo->commit();
        auditLog($_SESSION['user_id'], 'SECTION_CREATE', "Created section $sectionName with teacher $teacherId");
        $msg = "Section created with teacher assigned.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Error creating section: " . $e->getMessage();
    }
}

// Change teacher assignment for section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_teacher'])) {
    $sectionId = $_POST['section_id'];
    $teacherId = $_POST['teacher_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE section_assignments SET teacher_id = ? WHERE section_id = ?");
        $stmt->execute([$teacherId, $sectionId]);
        auditLog($_SESSION['user_id'], 'SECTION_TEACHER_CHANGE', "Changed teacher for section $sectionId to $teacherId");
        $msg = "Teacher assignment updated.";
    } catch (Exception $e) {
        $msg = "Error updating teacher: " . $e->getMessage();
    }
}

// Enroll student in section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    $sectionId = $_POST['section_id'];
    $studentId = $_POST['student_id'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO section_students (section_id, student_id) VALUES (?, ?)");
        $stmt->execute([$sectionId, $studentId]);
        auditLog($_SESSION['user_id'], 'SECTION_STUDENT_ENROLL', "Enrolled student $studentId in section $sectionId");
        $msg = "Student enrolled in section.";
    } catch (Exception $e) {
        $msg = "Student already enrolled in this section.";
    }
}

// Unenroll student from section
if (isset($_GET['unenroll'])) {
    $id = intval($_GET['unenroll']);
    $pdo->prepare("DELETE FROM section_students WHERE id = ?")->execute([$id]);
    auditLog($_SESSION['user_id'], 'SECTION_STUDENT_UNENROLL', "Unenrolled student from section");
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
// Get all students with their department and year level
$allStudents = $pdo->query("SELECT u.*, d.name as dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.role = 'student' AND u.is_active = 1 ORDER BY u.full_name")->fetchAll();

// Get all students already enrolled in ANY section
$enrolledStudentIds = $pdo->query("SELECT DISTINCT student_id FROM section_students")->fetchAll(PDO::FETCH_COLUMN);

// Get teacher and students for each section
$sectionDetails = [];
foreach ($sections as $s) {
    // Get assigned teacher
    $stmt = $pdo->prepare("
        SELECT t.id, t.full_name 
        FROM section_assignments sa
        JOIN teachers t ON sa.teacher_id = t.id 
        WHERE sa.section_id = ?
    ");
    $stmt->execute([$s['id']]);
    $sectionDetails[$s['id']]['teacher'] = $stmt->fetch();
    
    // Get enrolled students
    $stmt = $pdo->prepare("
        SELECT ss.id, u.full_name 
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
    <?php if ($msg): ?><div class="alert success"><?php echo $msg; ?></div><?php endif; ?>
    
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
                <select name="department_id" id="department_id" required onchange="updateTeachers()">
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
            <div><label>Assign Teacher</label>
                <select name="teacher_id" id="teacher_id" required>
                    <option value="">Select Department First</option>
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
    var allTeachers = <?php echo json_encode($teachers); ?>;
    
    function updateTeachers() {
        var deptId = document.getElementById('department_id').value;
        var teacherSelect = document.getElementById('teacher_id');
        
        teacherSelect.innerHTML = '<option value="">Select a Teacher</option>';
        
        if (deptId === '') {
            teacherSelect.innerHTML = '<option value="">Select Department First</option>';
            return;
        }
        
        var filteredTeachers = allTeachers.filter(function(t) {
            return t.department_id == deptId;
        });
        
        if (filteredTeachers.length === 0) {
            teacherSelect.innerHTML = '<option value="">No teachers available for this department</option>';
            return;
        }
        
        filteredTeachers.forEach(function(t) {
            var option = document.createElement('option');
            option.value = t.id;
            option.text = t.full_name;
            teacherSelect.appendChild(option);
        });
    }
    
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
            <!-- Assigned Teacher -->
            <div class="card">
                <h4>Teacher</h4>
                <?php if ($sectionDetails[$s['id']]['teacher']): ?>
                    <p><strong><?php echo htmlspecialchars($sectionDetails[$s['id']]['teacher']['full_name']); ?></strong></p>
                    <form method="POST" action="" class="filter-form">
                        <input type="hidden" name="section_id" value="<?php echo $s['id']; ?>">
                        <select name="teacher_id" required>
                            <?php foreach ($teachers as $t): ?>
                                <?php if ($t['department_id'] == $s['department_id']): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo ($t['id'] == $sectionDetails[$s['id']]['teacher']['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['full_name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="change_teacher" class="btn">Change</button>
                    </form>
                <?php else: ?>
                    <p>No teacher assigned</p>
                <?php endif; ?>
            </div>
            
            <!-- Enrolled Students -->
            <div class="card">
                <h4>Students (<?php echo count($sectionDetails[$s['id']]['students']); ?>)</h4>
                <form method="POST" action="" class="filter-form">
                    <input type="hidden" name="section_id" value="<?php echo $s['id']; ?>">
                    <select name="student_id" required>
                        <option value="">Select Student</option>
                        <?php 
                        $eligibleStudents = array_filter($allStudents, function($st) use ($s, $enrolledStudentIds) {
                            // Must have this section assigned to them (section_id matches)
                            $hasSectionAssigned = $st['section_id'] == $s['id'];
                            // Must NOT be already enrolled in this section
                            $notEnrolled = !in_array($st['id'], $enrolledStudentIds);
                            return $hasSectionAssigned && $notEnrolled;
                        });
                        if (empty($eligibleStudents)): 
                        ?>
                        <option value="" disabled>No eligible students (Dept: <?php echo htmlspecialchars($s['dept_name']); ?>, Year: <?php echo $s['year_level']; ?>)</option>
                        <?php else: 
                            foreach ($eligibleStudents as $st): 
                                // Check if already enrolled
                                $alreadyEnrolled = false;
                                foreach ($sectionDetails[$s['id']]['students'] as $enrolled) {
                                    if ($enrolled['id'] == $st['id']) {
                                        $alreadyEnrolled = true;
                                        break;
                                    }
                                }
                                if (!$alreadyEnrolled):
                        ?>
                        <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['full_name']); ?> (<?php echo htmlspecialchars($st['dept_name']); ?>, <?php echo $st['year_level']; ?>th Year)</option>
                        <?php 
                                endif;
                            endforeach;
                        endif;
                        ?>
                    </select>
                    <button type="submit" name="enroll_student" class="btn" <?php echo empty($eligibleStudents) ? 'disabled' : ''; ?>>Enroll</button>
                </form>
                <ul>
                    <?php foreach ($sectionDetails[$s['id']]['students'] as $st): ?>
                    <li>
                        <?php echo htmlspecialchars($st['full_name']); ?> 
                        <a href="?unenroll=<?php echo $st['id']; ?>" onclick="return confirm('Unenroll?')">[x]</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <p><a href="?delete=<?php echo $s['id']; ?>" onclick="return confirm('Delete?')" class="btn" style="background:#dc3545">Delete</a></p>
    </fieldset>
    <?php endforeach; ?>
</div>
</body>
</html>
