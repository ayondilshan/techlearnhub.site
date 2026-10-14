<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../db.php'; 
require_once __DIR__ . '/api/ai_helper.php';

$topic = "HTML Basics"; // try a normal subject topic

$systemPrompt = "You generate mind-map structures for Sri Lankan A/L (Advanced Level) students. "
    . "IMPORTANT — STAY ON TOPIC: Only generate mind-maps for topics related to these 5 A/L subjects: "
    . "SFT (Software Technology), ICT, ET (Engineering Technology), BST (Bio System Technology), and English. "
    . "If the requested topic is clearly unrelated to these subjects, respond with ONLY this exact JSON: "
    . '{ "error": "off_topic" } — nothing else. '
    . "Otherwise, detect the language of the topic given by the user — if it's in Sinhala or Singlish, write every "
    . "title in Sinhala script; if it's in English, write them in English. "
    . "Respond ONLY with valid JSON, no markdown fences, no extra text, no trailing commas. "
    . "STRICT RULES: exactly 2 levels of nesting only. Maximum 5 main branches. Maximum 4 sub-points per branch. "
    . "Keep every title under 5 words. "
    . 'The JSON shape is: { "title": string, "children": [ { "title": string, "children": [ { "title": string, "children": [] } ] } ] }';

$userMsg = "Create a mind-map for the topic \"$topic\". Return ONLY the JSON object.";

$result = call_claude($systemPrompt, $userMsg, 2500);

echo "<pre>";
print_r($result);
echo "</pre>";