<?php
require_once __DIR__ . '/db.php';

/**
 * Call Google Gemini API to generate a teacher performance summary.
 *
 * @param int $teacherId
 * @param int $periodId
 * @return array ['success' => bool, 'summary' => string|null, 'error' => string|null]
 */
function generateGeminiSummary($teacherId, $periodId) {
    global $pdo;

    $apiKey = GEMINI_API_KEY;
    if (empty($apiKey)) {
        return ['success' => false, 'summary' => null, 'error' => 'Gemini API key is not configured. Please set GEMINI_API_KEY in config/config.php'];
    }

    // Fetch teacher info
    $tStmt = $pdo->prepare("SELECT t.*, d.name AS dept_name FROM teachers t LEFT JOIN departments d ON t.department_id = d.id WHERE t.id = ?");
    $tStmt->execute([$teacherId]);
    $teacher = $tStmt->fetch();
    if (!$teacher) {
        return ['success' => false, 'summary' => null, 'error' => 'Teacher not found.'];
    }

    // Fetch period info
    $pStmt = $pdo->prepare("SELECT * FROM evaluation_periods WHERE id = ?");
    $pStmt->execute([$periodId]);
    $period = $pStmt->fetch();
    if (!$period) {
        return ['success' => false, 'summary' => null, 'error' => 'Evaluation period not found.'];
    }

    // Fetch aggregated scores
    $scores = getTeacherScore($teacherId, $periodId);

    // Fetch raw evaluations for comments and detailed breakdown
    $eStmt = $pdo->prepare("SELECT e.*, u.full_name AS rater_name FROM evaluations e LEFT JOIN users u ON e.rater_id = u.id WHERE e.teacher_id = ? AND e.evaluation_period_id = ? ORDER BY e.rater_role, e.submitted_at DESC");
    $eStmt->execute([$teacherId, $periodId]);
    $evaluations = $eStmt->fetchAll();

    // Build detailed per-criterion averages
    $detailStmt = $pdo->prepare("SELECT
        rater_role,
        AVG(teaching_clarity) AS avg_tc, AVG(engagement) AS avg_en, AVG(fairness) AS avg_fa,
        AVG(curriculum) AS avg_cu, AVG(assessment) AS avg_as, AVG(mentoring) AS avg_me,
        AVG(attendance) AS avg_at, AVG(commitment) AS avg_co, AVG(quality) AS avg_qu,
        COUNT(*) AS cnt
    FROM evaluations WHERE teacher_id = ? AND evaluation_period_id = ? GROUP BY rater_role");
    $detailStmt->execute([$teacherId, $periodId]);
    $details = [];
    foreach ($detailStmt->fetchAll() as $row) {
        $details[$row['rater_role']] = $row;
    }

    // Group comments by role
    $commentsByRole = ['student' => [], 'program_head' => [], 'dean' => []];
    foreach ($evaluations as $e) {
        if (!empty($e['comments'])) {
            $commentsByRole[$e['rater_role']][] = '- ' . trim($e['comments']);
        }
    }

    // Build the prompt
    $prompt = buildGeminiPrompt($teacher, $period, $scores, $details, $commentsByRole);

    // Call Gemini API
    $url = GEMINI_API_URL . '?key=' . urlencode($apiKey);
    $payload = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 2048,
            'topP' => 0.8,
            'topK' => 40
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'summary' => null, 'error' => 'cURL Error: ' . $curlError];
    }

    if ($httpCode !== 200) {
        $decoded = json_decode($response, true);
        $errMsg = $decoded['error']['message'] ?? "HTTP $httpCode";
        return ['success' => false, 'summary' => null, 'error' => 'Gemini API Error: ' . $errMsg];
    }

    $decoded = json_decode($response, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (empty($text)) {
        return ['success' => false, 'summary' => null, 'error' => 'Empty response from Gemini API.'];
    }

    return ['success' => true, 'summary' => $text, 'error' => null];
}

/**
 * Build the structured prompt for Gemini Flash.
 */
function buildGeminiPrompt($teacher, $period, $scores, $details, $commentsByRole) {
    $studentDetail = $details['student'] ?? null;
    $phDetail = $details['program_head'] ?? null;
    $deanDetail = $details['dean'] ?? null;

    $studentCommentsText = count($commentsByRole['student']) > 0
        ? implode("\n", array_slice($commentsByRole['student'], 0, 10))
        : 'No comments provided.';

    $phCommentsText = count($commentsByRole['program_head']) > 0
        ? implode("\n", array_slice($commentsByRole['program_head'], 0, 10))
        : 'No comments provided.';

    $deanCommentsText = count($commentsByRole['dean']) > 0
        ? implode("\n", array_slice($commentsByRole['dean'], 0, 10))
        : 'No comments provided.';

    $stTc = $studentDetail ? round($studentDetail['avg_tc'], 2) : 'N/A';
    $stEn = $studentDetail ? round($studentDetail['avg_en'], 2) : 'N/A';
    $stFa = $studentDetail ? round($studentDetail['avg_fa'], 2) : 'N/A';

    $phCu = $phDetail ? round($phDetail['avg_cu'], 2) : 'N/A';
    $phAs = $phDetail ? round($phDetail['avg_as'], 2) : 'N/A';
    $phMe = $phDetail ? round($phDetail['avg_me'], 2) : 'N/A';

    $dnAt = $deanDetail ? round($deanDetail['avg_at'], 2) : 'N/A';
    $dnCo = $deanDetail ? round($deanDetail['avg_co'], 2) : 'N/A';
    $dnQu = $deanDetail ? round($deanDetail['avg_qu'], 2) : 'N/A';

    $prompt = "You are an expert academic evaluator and instructional coach analyzing a university teacher's performance evaluation data. Your task is to generate a professional, balanced, and actionable performance summary.\n\n"
        . "## TEACHER PROFILE\n"
        . "Name: " . $teacher['full_name'] . "\n"
        . "Department: " . $teacher['dept_name'] . "\n"
        . "Evaluation Period: " . $period['title'] . " (" . $period['start_date'] . " to " . $period['end_date'] . ")\n\n"
        . "## COMPOSITE PERFORMANCE SCORE\n"
        . "Overall Composite: " . $scores['composite'] . " / 5.00\n\n"
        . "## DETAILED BREAKDOWN BY RATER GROUP\n\n"
        . "### Student Evaluations (50% weight)\n"
        . "Number of respondents: " . $scores['student_count'] . "\n"
        . "- Teaching Clarity: " . $stTc . " / 5\n"
        . "- Engagement: " . $stEn . " / 5\n"
        . "- Fairness: " . $stFa . " / 5\n"
        . "- Average: " . $scores['student_avg'] . " / 5\n\n"
        . "### Program Head Evaluations (30% weight)\n"
        . "Number of respondents: " . $scores['ph_count'] . "\n"
        . "- Curriculum: " . $phCu . " / 5\n"
        . "- Assessment: " . $phAs . " / 5\n"
        . "- Mentoring: " . $phMe . " / 5\n"
        . "- Average: " . $scores['ph_avg'] . " / 5\n\n"
        . "### Dean Evaluations (20% weight)\n"
        . "Number of respondents: " . $scores['dean_count'] . "\n"
        . "- Attendance: " . $dnAt . " / 5\n"
        . "- Commitment: " . $dnCo . " / 5\n"
        . "- Quality: " . $dnQu . " / 5\n"
        . "- Average: " . $scores['dean_avg'] . " / 5\n\n"
        . "## QUALITATIVE FEEDBACK\n\n"
        . "### Student Comments:\n" . $studentCommentsText . "\n\n"
        . "### Program Head Comments:\n" . $phCommentsText . "\n\n"
        . "### Dean Comments:\n" . $deanCommentsText . "\n\n"
        . "## INSTRUCTIONS\n"
        . "Please generate a comprehensive teacher evaluation summary with the following sections:\n\n"
        . "1. **EXECUTIVE SUMMARY** (2-3 paragraphs): Provide an overall assessment of this teacher's performance based on the quantitative scores and qualitative feedback. Mention the composite score and how it reflects across different evaluation dimensions.\n\n"
        . "2. **KEY STRENGTHS** (3-5 bullet points): Identify specific strengths with evidence from the data. Cite actual scores and comments to support each strength.\n\n"
        . "3. **AREAS FOR IMPROVEMENT** (3-5 bullet points): Identify specific weaknesses or growth areas with evidence from the data. Be constructive and professional. Cite actual scores and comments.\n\n"
        . "4. **ACTIONABLE RECOMMENDATIONS** (3-4 bullet points): Provide concrete, specific recommendations that the teacher can implement to improve their performance. Tailor recommendations to the lowest-scoring criteria.\n\n"
        . "5. **CONCLUSION** (1 paragraph): End with an encouraging, balanced closing statement that acknowledges both achievements and the potential for growth.\n\n"
        . "Format your response using Markdown. Use **bold** for headings, bullet points for lists, and keep the tone professional, objective, and encouraging. Do not use generic statements—always tie observations back to the specific data provided.";

    return $prompt;
}

/**
 * Save or update a generated summary in the database.
 */
function saveTeacherSummary($teacherId, $periodId, $summaryText, $userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO teacher_summaries (teacher_id, evaluation_period_id, summary_text, generated_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE summary_text = VALUES(summary_text), generated_at = CURRENT_TIMESTAMP, generated_by = VALUES(generated_by)");
        $stmt->execute([$teacherId, $periodId, $summaryText, $userId]);
    } catch (PDOException $e) {
        // Table may not exist; silently fail
    }
}

/**
 * Retrieve a saved summary.
 */
function getTeacherSummary($teacherId, $periodId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM teacher_summaries WHERE teacher_id = ? AND evaluation_period_id = ?");
        $stmt->execute([$teacherId, $periodId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        // Table may not exist; return null
        return null;
    }
}
