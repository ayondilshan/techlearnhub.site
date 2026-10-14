<?php
/**
 * Shared helper to call the Google Gemini API (free tier available).
 * Get a free key at https://aistudio.google.com/app/apikey
 * Requires AI_API_KEY / AI_API_URL from config.php
 */
function call_claude($systemPrompt, $userMessage, $maxTokens = 1000, $forceJson = false) {
    if (AI_API_KEY === 'PUT_YOUR_GEMINI_API_KEY_HERE' || !AI_API_KEY) {
        return ['error' => 'AI API key not configured. Set AI_API_KEY in config/config.php'];
    }

    $url = AI_API_URL . '?key=' . AI_API_KEY;

    $generationConfig = [
        'maxOutputTokens' => $maxTokens,
        'temperature' => 0.7
    ];
    if ($forceJson) {
        // Ask Gemini to return ONLY valid JSON, no stray preamble/markdown text.
        $generationConfig['responseMimeType'] = 'application/json';
    }

    $payload = [
        'system_instruction' => [
            'parts' => [ ['text' => $systemPrompt] ]
        ],
        'contents' => [
            [ 'role' => 'user', 'parts' => [ ['text' => $userMessage] ] ]
        ],
        'generationConfig' => $generationConfig
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 40,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        return ['error' => 'Connection error: ' . $curlError];
    }
    $data = json_decode($response, true);
    if ($httpCode !== 200) {
        $msg = $data['error']['message'] ?? 'Unknown API error';
        return ['error' => 'AI API error: ' . $msg];
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$text) {
        $finishReason = $data['candidates'][0]['finishReason'] ?? '';
        if ($finishReason === 'MAX_TOKENS') {
            return ['error' => 'The response was cut off (too long). Try a shorter/simpler request.'];
        }
        return ['error' => 'AI returned an empty response. Please try again.'];
    }
    return ['text' => $text];
}

/** Strip ```json fences etc and safely parse JSON from a model response */
function extract_json($text) {
    $clean = preg_replace('/```json|```/i', '', $text);
    $clean = trim($clean);

    $decoded = json_decode($clean, true);
    if ($decoded !== null) {
        return $decoded;
    }

    // Fallback: if the model added any stray text around the JSON,
    // extract the substring from the first { or [ to the matching last } or ].
    $firstObj = strpos($clean, '{');
    $firstArr = strpos($clean, '[');
    if ($firstObj === false && $firstArr === false) {
        return null;
    }
    $start = ($firstObj === false) ? $firstArr : (($firstArr === false) ? $firstObj : min($firstObj, $firstArr));
    $isObject = $clean[$start] === '{';
    $end = $isObject ? strrpos($clean, '}') : strrpos($clean, ']');
    if ($end === false || $end <= $start) {
        return null;
    }
    $sub = substr($clean, $start, $end - $start + 1);
    return json_decode($sub, true); // null on failure
}