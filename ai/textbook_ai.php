<?php
declare(strict_types=1);
// textbook_ai.php - Groq chat completions proxy for your textbook AI UI
// WARNING: API key is embedded for local testing. Revoke/regenerate and use an env var for production.

header('Content-Type: application/json; charset=utf-8');

// Allow your HTML to call this endpoint (CORS)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// respond to preflight and exit
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ================== CONFIG ================== */
// your Groq API key (as provided)
$GROQ_API_KEY = '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'>

// default model to request (you can change to a smaller model if needed)
// check available models via GET https://api.groq.com/openai/v1/models (requires key).
$MODEL = 'llama-3.1-8b-instant'; // reasonable default; change if you prefer another. 3
$CONNECT_TIMEOUT = 10;
$REQUEST_TIMEOUT = 70;
$RETURN_RAW_SNIPPET_CHARS = 1200;
/* =========================================== */

// helper: read input
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // fallback to $_POST if form-encoded
    $input = $_POST;
}
$question = isset($input['question']) ? trim((string)$input['question']) : '';

if ($question === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No question provided.']);
    exit;
}

// Build chat-style payload (OpenAI-compatible)
$messages = [
    ['role' => 'system', 'content' => "You are a clear, patient textbook-style tutor for Nigerian secondary school students (WAEC/JAMB). Explain simply, use short paragraphs, include a brief example if relevant, and finish with a 1-2 line summary."],
    ['role' => 'user', 'content' => $question]
];

$payload = [
    'model' => $MODEL,
    'messages' => $messages,
    'temperature' => 0.2,
    'max_tokens' => 512,
    'top_p' => 0.95
];

// cURL request to Groq (OpenAI-compatible endpoint)
$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$GROQ_API_KEY}",
        "Content-Type: application/json",
        "User-Agent: ExcellentAcademy/1.0"
    ],
    CURLOPT_CONNECTTIMEOUT => $CONNECT_TIMEOUT,
    CURLOPT_TIMEOUT => $REQUEST_TIMEOUT,
    // Force IPv4 to avoid Termux/PHP IPv6/DNS issues
    CURLOPT_IPRESOLVE => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 0,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$curlErr = $errno ? curl_error($ch) : null;
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
@curl_close($ch);

// handle transport error
if ($curlErr) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'Network error: ' . $curlErr,
        'http_code' => $httpCode
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Try to parse JSON (Groq uses OpenAI-like responses)
$parsed = json_decode($response, true);

// robust extraction of answer text from common shapes
$answer = null;

if (is_array($parsed)) {
    // chat completions style: choices[0].message.content
    if (isset($parsed['choices'][0]['message']['content'])) {
        $answer = trim((string)$parsed['choices'][0]['message']['content']);
    }
    // older variants: choices[0].text
    elseif (isset($parsed['choices'][0]['text'])) {
        $answer = trim((string)$parsed['choices'][0]['text']);
    }
    // some responses use an 'output_text' / 'output' field
    elseif (isset($parsed['output_text'])) {
        $answer = trim((string)$parsed['output_text']);
    } elseif (isset($parsed['output']) && is_string($parsed['output'])) {
        $answer = trim($parsed['output']);
    }
}

// fallback: if raw response is a string, use it (trim)
if ($answer === null && is_string($response) && trim($response) !== '') {
    $candidate = trim($response);
    // reject trivial "Not Found" / HTML etc
    $low = strtolower($candidate);
    if (strlen($candidate) > 10 && strpos($low, '<html') === false && strpos($low, 'not found') === false) {
        $answer = $candidate;
    }
}

// if still empty, return debug
if (empty($answer)) {
    http_response_code(502);
    $snippet = $response ? mb_substr($response, 0, $RETURN_RAW_SNIPPET_CHARS) : null;
    echo json_encode([
        'success' => false,
        'error' => 'No valid text returned by Groq.',
        'http_code' => $httpCode,
        'raw_snippet' => $snippet
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// success
echo json_encode([
    'success' => true,
    'model' => $MODEL,
    'answer' => $answer
], JSON_UNESCAPED_UNICODE);
exit;
