<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gmail_mailer.php';
requireRole('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminders'])) {
    sendReminderEmails();
    $msg = "Reminder emails queued successfully.";
    auditLog($_SESSION['user_id'], 'REMINDERS_QUEUED', "Admin triggered reminder emails");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_queue'])) {
    // Gmail credentials - configure these in config.php or here
    $gmailUser = defined('GMAIL_USER') ? GMAIL_USER : 'your-email@gmail.com';
    $gmailAppPassword = defined('GMAIL_APP_PASSWORD') ? GMAIL_APP_PASSWORD : 'your-app-password';
    
    if ($gmailUser === 'your-email@gmail.com') {
        $msg = "Please configure GMAIL_USER and GMAIL_APP_PASSWORD in config.php";
    } else {
        $pending = $pdo->query("SELECT q.*, u.email, u.full_name FROM email_queue q JOIN users u ON q.user_id = u.id WHERE q.status = 'pending' LIMIT 10")->fetchAll();
        $sent = 0;
        $failed = 0;
        
        foreach ($pending as $q) {
            // Send via Gmail SMTP
            $result = sendGmailEmail($q['email'], $q['subject'], $q['body'], $gmailUser, $gmailAppPassword);
            
            if ($result[0]) {
                $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?")->execute([$q['id']]);
                $sent++;
            } else {
                $pdo->prepare("UPDATE email_queue SET status = 'failed' WHERE id = ?")->execute([$q['id']]);
                $failed++;
            }
        }
        
        $msg = "Sent: $sent, Failed: $failed. Check your Gmail Sent folder.";
        auditLog($_SESSION['user_id'], 'EMAILS_PROCESSED', "Sent $sent emails via Gmail SMTP");
    }
}

// Test email to admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $gmailUser = defined('GMAIL_USER') ? GMAIL_USER : 'your-email@gmail.com';
    $gmailAppPassword = defined('GMAIL_APP_PASSWORD') ? GMAIL_APP_PASSWORD : 'your-app-password';
    
    if ($gmailUser === 'your-email@gmail.com') {
        $msg = "Please configure GMAIL_USER and GMAIL_APP_PASSWORD in config.php";
    } else {
        $testEmail = $_POST['test_email_address'];
        $subject = "Test Email from EvalSystem";
        $body = "Hi!\n\nThis is a test email from the EvalSystem.\n\nIf you received this, the email system is working correctly.";
        
        $result = sendGmailEmail($testEmail, $subject, $body, $gmailUser, $gmailAppPassword);
        
        if ($result[0]) {
            $msg = "Test email sent successfully to $testEmail. Check your inbox!";
        } else {
            $msg = "Failed to send test email: " . $result[1];
        }
    }
}

$pendingCount = $pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();
$sentCount = $pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'sent'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Reminders</title>
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
    <h2>Automated Email Reminders</h2>
    <?php if ($msg): ?><div class="alert success"><?php echo $msg; ?></div><?php endif; ?>
    <div class="cards">
        <div class="card"><h3>Pending</h3><p class="big"><?php echo $pendingCount; ?></p></div>
        <div class="card"><h3>Sent</h3><p class="big"><?php echo $sentCount; ?></p></div>
    </div>
    <form method="POST" action="">
        <button type="submit" name="send_reminders" class="btn">Queue Reminders for Non-Respondents</button>
    </form>
    <form method="POST" action="" style="margin-top:1rem;">
        <button type="submit" name="process_queue" class="btn">Process Pending Emails (Send via mail())</button>
    </form>
    <p class="small">You can also set up a cron job to hit this page or call sendReminderEmails() automatically.</p>
    
    <!-- Test Email Section -->
    <fieldset style="margin-top: 2rem;">
        <legend>Send Test Email</legend>
        <form method="POST" action="">
            <div style="display: flex; gap: 10px; align-items: flex-end;">
                <div>
                    <label>Your Email Address</label>
                    <input type="email" name="test_email_address" placeholder="youremail@gmail.com" required style="padding: 8px; width: 250px;">
                </div>
                <button type="submit" name="test_email" class="btn" style="margin-bottom: 0;">Send Test</button>
            </div>
        </form>
    </fieldset>
    
    <?php
    // Display email log if exists
    $logFile = __DIR__ . '/../logs/email_log.txt';
    if (file_exists($logFile) && filesize($logFile) > 0):
        $logContent = file_get_contents($logFile);
        $logEntries = array_filter(explode("----------------------------------------", $logContent));
        $recentEntries = array_slice(array_reverse($logEntries), 0, 5);
    ?>
    <!-- Gmail Setup Instructions -->
    <fieldset style="margin-top: 2rem;">
        <legend>Gmail Setup Instructions</legend>
        <div style="background: #fff3cd; padding: 15px; border-radius: 5px;">
            <p><strong>To send real emails via Gmail:</strong></p>
            <ol>
                <li>Go to your Google Account settings</li>
                <li>Enable 2-Step Verification (required for App Passwords)</li>
                <li>Go to Security → App passwords</li>
                <li>Generate an App Password for "Mail"</li>
                <li>Add to config.php:<br>
                    <code>define('GMAIL_USER', 'your-email@gmail.com');</code><br>
                    <code>define('GMAIL_APP_PASSWORD', 'xxxx-xxxx-xxxx-xxxx');</code>
                </li>
            </ol>
        </div>
    </fieldset>
    
    <fieldset style="margin-top: 2rem;">
        <legend>Recent Email Log (Last 5)</legend>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto;">
            <?php foreach ($recentEntries as $entry): ?>
                <pre style="background: white; padding: 10px; margin-bottom: 10px; border-left: 3px solid #dc3545; font-size: 12px;"><?php echo htmlspecialchars(trim($entry)); ?></pre>
            <?php endforeach; ?>
        </div>
        <p class="small">Full log location: <code>logs/email_log.txt</code></p>
    </fieldset>
    <?php endif; ?>
</div>
</body>
</html>

