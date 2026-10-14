<?php
require_once __DIR__ . '/ai_helper.php';
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$topic = trim($input['topic'] ?? '');
$subjectId = (int)($input['subject'] ?? 0) ?: null;
$difficulty = $input['difficulty'] ?? 'Medium';
$count = max(3, min(10, (int)($input['count'] ?? 5)));
$language = $input['language'] ?? 'auto';

if (!$topic) {
    echo json_encode(['error' => 'Topic is required']);
    exit;
}

$languageInstruction = match($language) {
    'sinhala' => "IMPORTANT: Write the questions, options and explanations in proper Sinhala script (සිංහල), regardless of what language the topic was typed in. Keep well-known English technical terms as-is within the Sinhala text where that reads more naturally.",
    'english' => "IMPORTANT: Write the questions, options and explanations in English, regardless of what language the topic was typed in.",
    default => "Detect the language of the topic given by the user — if it's in Sinhala or Singlish, write the questions, options and explanations in Sinhala script; if it's in English, write them in English.",
};

$systemPrompt = "You are a quiz-generating engine for a Sri Lankan A/L (Advanced Level) student study platform. "
    . "IMPORTANT — STAY ON TOPIC: Only generate quiz questions related to these 5 A/L subjects: "
    . "SFT (Software Technology), ICT, ET (Engineering Technology), BST (Bio System Technology), and English. "
    . "If the requested topic is clearly unrelated to these subjects (e.g. entertainment, sports, celebrities, "
    . "general trivia, or any topic with no connection to the A/L syllabus for these subjects), respond with "
    . 'ONLY this exact JSON: { "error": "off_topic" } — nothing else. '
    . "Otherwise, $languageInstruction "
    . "Respond ONLY with valid JSON, no markdown fences, no extra text, no trailing commas. "
    . "The JSON must be an array of question objects, each with keys: "
    . "question, option_a, option_b, option_c, option_d, correct_option (one of 'a','b','c','d'), explanation.";

$userMsg = "Create $count multiple-choice questions on the topic \"$topic\" at $difficulty difficulty, suitable for a school/college student. Return ONLY the JSON array.";

$result = call_claude($systemPrompt, $userMsg, 2000, true);
if (isset($result['error'])) {
    echo json_encode(['error' => $result['error']]);
    exit;
}

$questions = extract_json($result['text']);

// AI flagged the topic as unrelated to the 5 A/L subjects
if (is_array($questions) && isset($questions['error']) && $questions['error'] === 'off_topic') {
    echo json_encode(['error' => 'This topic doesn\'t seem related to SFT, ICT, ET, BST or English. Please enter a topic from your A/L syllabus.']);
    exit;
}

if (!is_array($questions)) {
    echo json_encode(['error' => 'AI returned an unexpected format. Please try again.']);
    exit;
}

// Save quiz + questions
$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO quizzes (user_id, subject_id, topic, difficulty) VALUES (?, ?, ?, ?)");
$stmt->execute([$_SESSION['user_id'], $subjectId, $topic, $difficulty]);
$quizId = $pdo->lastInsertId();

$qStmt = $pdo->prepare("INSERT INTO quiz_questions
    (quiz_id, question, option_a, option_b, option_c, option_d, correct_option, explanation)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($questions as $q) {
    if (!isset($q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_option'])) continue;
    $qStmt->execute([
        $quizId, $q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'],
        strtolower($q['correct_option']), $q['explanation'] ?? ''
    ]);
}
$pdo->commit();

echo json_encode(['quiz_id' => $quizId, 'questions' => $questions]);