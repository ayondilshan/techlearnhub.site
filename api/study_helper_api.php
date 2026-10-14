<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/ai_helper.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
if (!$message) {
    echo json_encode(['error' => 'Empty message']);
    exit;
}

$systemPrompt = "You are a helpful study assistant for TechLearn Hub, a study platform for Sri Lankan students taking "
    . "SFT (Software Technology), ICT, ET (Engineering Technology), BST (Bio System Technology) and English. "
    . "IMPORTANT — STAY ON TOPIC: Only answer questions related to these 5 subjects (SFT, ICT, ET, BST, English) "
    . "or general study skills (exam tips, how to revise, time management for studying). "
    . "If the student asks something unrelated to studies (e.g. entertainment, sports, politics, personal advice, "
    . "general chit-chat, coding help outside the syllabus, etc.), politely decline and redirect them back to their "
    . "subjects. Reply in the SAME language as the student's question (Sinhala or English — if they write in Sinhala "
    . "or Singlish, reply in proper Sinhala script; if English, reply in English). "
    . "Give clear, concise, exam-focused explanations. Use simple language and short examples. "
    . "FORMAT every answer in clean Markdown so it is easy to read: start with a one-line direct answer; then use "
    . "short paragraphs separated by BLANK lines; put every list item on its OWN line (numbered list for steps or "
    . "ordered items, '- ' bullets for points); use **bold** only for key terms; write HTML tags, code and commands "
    . "inside `backticks`, and multi-line code inside fenced code blocks; give one small example when helpful. "
    . "Keep the whole answer under about 250 words unless the student asks for more detail, and always finish "
    . "your last sentence.";

$result = call_claude($systemPrompt, $message, 1500);

if (!is_array($result)) {
    echo json_encode(['error' => 'The AI service did not respond. Please try again.']);
    exit;
}
if (isset($result['error'])) {
    echo json_encode(['error' => $result['error']]);
    exit;
}

// Log conversation (never let a logging problem break the reply)
try {
    $stmt = $pdo->prepare("INSERT INTO chatbot_logs (user_id, message, response) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $message, $result['text']]);
} catch (Throwable $e) {
    error_log('chatbot log error: ' . $e->getMessage());
}

echo json_encode(['reply' => $result['text']], JSON_UNESCAPED_UNICODE);