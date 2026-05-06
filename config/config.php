<?php
session_start();
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'teacher_eval');
define('BASE_URL', '/HACKATHON');

// Role weights
define('WEIGHT_STUDENT', 0.50);
define('WEIGHT_PROGRAM_HEAD', 0.30);
define('WEIGHT_DEAN', 0.20);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Manila');

// Gemini AI Configuration
// Set your API key here or leave empty and configure via environment variable
// Get your key from: https://aistudio.google.com/app/apikey
// Model: use 'gemini-flash-2.5' or the latest flash model available
define('GEMINI_API_KEY', '');
define('GEMINI_MODEL', 'gemini-2.5-flash');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');

// Gmail SMTP Configuration
// To get App Password: Google Account → Security → 2-Step Verification → App passwords
define('GMAIL_USER', 'ruzellgarcia@gmail.com');
define('GMAIL_APP_PASSWORD', 'pprr-qgrp-irut-ohmk'); // 16-character app password
?>

