<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $email = trim($_POST['email']);
    $name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $deptId = $_POST['department_id'] ?? null;
    $yearLevel = $_POST['year_level'] ?? null;
    $sectionId = $_POST['section_id'] ?? null;
    
    $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, role, department_id, year_level, section_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$email, $password, $name, $role, $deptId ?: null, $yearLevel ?: null, $sectionId ?: null]);
    auditLog($_SESSION['user_id'], 'USER_CREATE', "Created user $email as $role");
    $msg = "User added.";
}

if (isset($_GET['toggle'])) {
    $uid = intval($_GET['toggle']);
    $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$uid]);
    auditLog($_SESSION['user_id'], 'USER_TOGGLE', "Toggled user $uid");
    header("Location: users.php"); exit;
}

$users = $pdo->query("SELECT u.*, d.name as dept_name, s.section_name FROM users u LEFT JOIN departments d ON u.department_id = d.id LEFT JOIN sections s ON u.section_id = s.id ORDER BY u.id DESC")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments")->fetchAll();
$sections = $pdo->query("SELECT s.*, d.department_code FROM sections s JOIN departments d ON s.department_id = d.id ORDER BY s.section_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
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
    <h2>Manage Users</h2>
    <?php if ($msg): ?><div class="alert success"><?php echo $msg; ?></div><?php endif; ?>
    <form method="POST" action="" class="filter-form">
        <div><label>Email</label><input type="email" name="email" required></div>
        <div><label>Full Name</label><input type="text" name="full_name" required></div>
        <div><label>Password</label><input type="password" name="password" required></div>
        <div><label>Role</label>
            <select name="role" id="role" required onchange="toggleStudentFields()">
                <option value="student">Student</option>
                <option value="program_head">Program Head</option>
                <option value="dean">Dean</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div><label>Department</label>
            <select name="department_id" id="department_id"><option value="">None</option>
                <?php foreach ($departments as $de): ?><option value="<?php echo $de['id']; ?>"><?php echo htmlspecialchars($de['name']); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div id="student-fields" style="display: contents;">
            <div><label>Year Level</label>
                <select name="year_level" id="year_level" onchange="updateSections()"><option value="">None</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div><label>Section</label>
                <select name="section_id" id="section_id"><option value="">Select Department and Year First</option>
                </select>
            </div>
        </div>
        <div><button type="submit" name="add_user" class="btn">Add User</button></div>
    </form>
    
    <script>
    function toggleStudentFields() {
        var role = document.getElementById('role').value;
        var studentFields = document.getElementById('student-fields');
        if (role === 'student') {
            studentFields.style.display = 'contents';
        } else {
            studentFields.style.display = 'none';
            document.getElementById('year_level').value = '';
            document.getElementById('section_id').innerHTML = '<option value="">Select Department and Year First</option>';
        }
    }
    
    var allSections = <?php echo json_encode($sections); ?>;
    
    function updateSections() {
        var deptId = document.getElementById('department_id').value;
        var yearLevel = document.getElementById('year_level').value;
        var sectionSelect = document.getElementById('section_id');
        
        sectionSelect.innerHTML = '<option value="">Select a Section</option>';
        
        if (deptId === '' || yearLevel === '') {
            sectionSelect.innerHTML = '<option value="">Select Department and Year First</option>';
            return;
        }
        
        var filteredSections = allSections.filter(function(s) {
            return s.department_id == deptId && s.year_level == yearLevel;
        });
        
        if (filteredSections.length === 0) {
            sectionSelect.innerHTML = '<option value="">No sections available</option>';
            return;
        }
        
        filteredSections.forEach(function(s) {
            var option = document.createElement('option');
            option.value = s.id;
            option.text = s.section_name;
            sectionSelect.appendChild(option);
        });
    }
    
    document.getElementById('department_id').addEventListener('change', updateSections);
    </script>
    <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Dept</th><th>Year</th><th>Section</th><th>Active</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?php echo htmlspecialchars($u['full_name']); ?></td>
            <td><?php echo htmlspecialchars($u['email']); ?></td>
            <td><?php echo ucfirst($u['role']); ?></td>
            <td><?php echo htmlspecialchars($u['dept_name'] ?? '-'); ?></td>
            <td><?php 
                if ($u['year_level']) {
                    $suffix = ($u['year_level'] == 1) ? 'st' : (($u['year_level'] == 2) ? 'nd' : (($u['year_level'] == 3) ? 'rd' : 'th'));
                    echo $u['year_level'] . $suffix;
                } else {
                    echo '-';
                }
            ?></td>
            <td><?php echo htmlspecialchars($u['section_name'] ?? '-'); ?></td>
            <td><?php echo $u['is_active'] ? 'Yes' : 'No'; ?></td>
            <td><a class="btn" href="?toggle=<?php echo $u['id']; ?>">Toggle</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
