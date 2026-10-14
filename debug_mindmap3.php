<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
require_once __DIR__ . '/api/ai_helper.php';

$topic = "HTML Basics";

$systemPrompt = "You generate DETAILED mind-map structures for Sri Lankan A/L (Advanced Level) students. "
    . "IMPORTANT — STAY ON TOPIC: Only generate mind-maps for topics related to these 5 A/L subjects: "
    . "SFT (Software Technology), ICT, ET (Engineering Technology), BST (Bio System Technology), and English. "
    . "If the requested topic is clearly unrelated to these subjects, respond with ONLY this exact JSON: "
    . '{ "error": "off_topic" } — nothing else. '
    . "Otherwise, detect the language of the topic given by the user — if it's in Sinhala or Singlish, write every "
    . "title in Sinhala script; if it's in English, write them in English. "
    . "Respond ONLY with valid JSON, no markdown fences, no extra text, no trailing commas. "
    . "STRICT RULES: exactly 2 levels of nesting only (main branches, and their sub-points — sub-points must "
    . "always have an empty children array). Be THOROUGH and DETAILED: include 6-8 main branches covering the "
    . "topic comprehensively, and 4-6 sub-points under each branch covering all the important details a student "
    . "needs for their exam. Keep every title under 6 words but be specific (not vague). "
    . "Do NOT nest a third level under any circumstances. "
    . 'The JSON shape is: { "title": string, "children": [ { "title": string, "children": [ { "title": string, "children": [] } ] } ] }';

$userMsg = "Create a detailed, comprehensive mind-map for the topic \"$topic\" covering everything a student "
    . "would need to know for an exam on this. Return ONLY the JSON object.";

$result = call_claude($systemPrompt, $userMsg, 4000);

echo "<h3>Raw text length: " . strlen($result['text'] ?? '') . " chars</h3>";
echo "<pre>";
print_r($result);
echo "</pre>";

if (isset($result['text'])) {
    $tree = json_decode(preg_replace('/```json|```/i', '', trim($result['text'])), true);
    if ($tree) {
        echo "<h3>Main branches count: " . count($tree['children'] ?? []) . "</h3>";
        foreach (($tree['children'] ?? []) as $c) {
            echo clean_e($c['title']) . " (" . count($c['children'] ?? []) . " sub-points)<br>";
        }
    }
}
function clean_e($s) { return htmlspecialchars($s); }