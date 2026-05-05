<?php
/**
 * Simple Gmail SMTP Mailer
 * Requires Gmail App Password for security
 */

function sendGmailEmail($toEmail, $subject, $body, $gmailUser, $gmailAppPassword) {
    // Gmail SMTP settings
    $smtpHost = 'smtp.gmail.com';
    $smtpPort = 587;
    $smtpUsername = $gmailUser;
    $smtpPassword = $gmailAppPassword;
    
    // Sender info
    $fromEmail = $gmailUser;
    $fromName = 'EvalSystem';
    
    // Generate boundary
    $boundary = md5(time());
    
    // Email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $headers .= "From: $fromName <$fromEmail>\r\n";
    $headers .= "Reply-To: $fromEmail\r\n";
    
    // Email body
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $body . "\r\n\r\n";
    $message .= "--$boundary--";
    
    // SMTP connection
    $socket = fsockopen('ssl://' . $smtpHost, 465, $errno, $errstr, 30);
    
    if (!$socket) {
        return [false, "Failed to connect to SMTP server: $errstr ($errno)"];
    }
    
    // Read greeting
    $response = fgets($socket, 515);
    
    // EHLO
    fputs($socket, "EHLO $smtpHost\r\n");
    $response = fgets($socket, 515);
    
    // AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket, 515);
    
    // Username (base64 encoded)
    fputs($socket, base64_encode($smtpUsername) . "\r\n");
    $response = fgets($socket, 515);
    
    // Password (base64 encoded)
    fputs($socket, base64_encode($smtpPassword) . "\r\n");
    $response = fgets($socket, 515);
    
    if (strpos($response, '235') !== 0) {
        fclose($socket);
        return [false, "Authentication failed: $response"];
    }
    
    // MAIL FROM
    fputs($socket, "MAIL FROM:<$fromEmail>\r\n");
    $response = fgets($socket, 515);
    
    // RCPT TO
    fputs($socket, "RCPT TO:<$toEmail>\r\n");
    $response = fgets($socket, 515);
    
    // DATA
    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);
    
    // Send message
    fputs($socket, "Subject: $subject\r\n");
    fputs($socket, $headers);
    fputs($socket, "\r\n");
    fputs($socket, $message);
    fputs($socket, "\r\n.\r\n");
    $response = fgets($socket, 515);
    
    // QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    if (strpos($response, '250') === 0) {
        return [true, "Email sent successfully"];
    } else {
        return [false, "Failed to send email: $response"];
    }
}
?>
