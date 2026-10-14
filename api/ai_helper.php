<?php
/**
 * DEBUG VERSION - shows detailed connection error info.
 * Replace ai_helper.php with this temporarily, test the chat,
 * then switch back to the original once the issue is found.
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
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // DEBUG: return full diagnostic info instead of a generic message
    if ($curlError) {
        return [
            'error' => 'DEBUG Connection error',
            'debug_curl_errno' => $curlErrno,
            'debug_curl_error' => $curlError,
            'debug_url_host' => parse_url(AI_API_URL, PHP_URL_HOST),
            'debug_key_set' => (AI_API_KEY && AI_API_KEY !== 'PUT_YOUR_GEMINI_API_KEY_HERE'),
        ];
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200) {
        $msg = $data['error']['message'] ?? 'Unknown API error';
        return [
            'error' => 'AI API error: ' . $msg,
            'debug_http_code' => $httpCode,
            'debug_raw_response' => substr($response, 0, 500),
        ];
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$text) {
        $finishReason = $data['candidates'][0]['finishReason'] ?? '';
        if ($finishReason === 'MAX_TOKENS') {
            return ['error' => 'The response was cut off (too long). Try a shorter/simpler request.'];
        }
        return ['error' => 'AI returned an empty response. Please try again.', 'debug_raw_response' => substr($response, 0, 500)];
    }
    return ['text' => $text];
}

function extract_json($text) {
    $clean = preg_replace('/```json|```/i', '', $text);
    $clean = trim($clean);

    $decoded = json_decode($clean, true);
    if ($decoded !== null) {
        return $decoded;
    }

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
    return json_decode($sub, true);
}