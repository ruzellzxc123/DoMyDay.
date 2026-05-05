<?php
require_once 'config/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        auditLog($user['id'], 'LOGIN', "User {$user['email']} logged in");
        
        // Redirect based on role
        if ($user['role'] === 'admin') {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Evaluation System - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-container">
    <h1>Teacher Evaluation System</h1>
    <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="POST" action="">
        <label>Email</label>
        <input type="email" name="email" required autofocus>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit">Login</button>
    </form>
    <div class="demo-accounts">
        <small>Demo accounts (password for all): <strong>password</strong><br>
        admin@school.edu | dean@school.edu | ph@school.edu | student@school.edu</small>
    </div>
</div>
</body>
</html>

