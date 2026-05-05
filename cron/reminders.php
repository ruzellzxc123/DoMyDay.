<?php
// CLI script for cron jobs: php cron/reminders.php queue
// php cron/reminders.php send

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

$action = $argv[1] ?? '';

if ($action === 'queue') {
    sendReminderEmails();
    echo "Reminders queued.\n";
} elseif ($action === 'send') {
    $pending = $pdo->query("SELECT q.*, u.email, u.full_name FROM email_queue q JOIN users u ON q.user_id = u.id WHERE q.status = 'pending' LIMIT 50")->fetchAll();
    $sent = 0;
    foreach ($pending as $q) {
        $headers = "From: noreply@school.edu\r\nContent-Type: text/plain; charset=UTF-8";
        if (mail($q['email'], $q['subject'], $q['body'], $headers)) {
            $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?")->execute([$q['id']]);
            $sent++;
        } else {
            $pdo->prepare("UPDATE email_queue SET status = 'failed' WHERE id = ?")->execute([$q['id']]);
        }
    }
    echo "Sent $sent emails.\n";
} else {
    echo "Usage: php cron/reminders.php [queue|send]\n";
}
?>

