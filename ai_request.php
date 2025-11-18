<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Generate True/False questions only
 */
function autogenquiz_generate_tf_questions($text, $count) {
    global $CFG;

    $configfile = __DIR__ . '/config_local.php';
    if (!file_exists($configfile)) {
        return json_encode(['error' => 'config_local.php missing.']);
    }

    require($configfile);
    if (empty($AUTOGENQUIZ_API_URL)) {
        return json_encode(['error' => 'API URL not set.']);
    }

    $prompt = <<<PROMPT
    You are a professional educational quiz generator.

    TASK:
    Create exactly {$count} True/False (T/F) questions only from the provided text.

    STRICT JSON REQUIREMENTS:
    - Output ONLY a valid JSON array.
    - No code blocks.
    - No explanations.
    - No trailing commas.
    - Every object must have commas between properties.
    - Keys and strings MUST use double quotes.
    - The final output MUST be a JSON array starting with `[` and ending with `]`.
    - Validate your JSON before responding. If invalid, FIX IT before output.

    STRICT JSON SCHEMA:
    Each item in the array must be:
    {
    "id": <number>,
    "type": "tf",
    "question": "<statement>",
    "answer": "True" or "False"
    }

    EXAMPLE (follow structure exactly):
    [
    {"id":1,"type":"tf","question":"Example statement.","answer":"True"},
    {"id":2,"type":"tf","question":"Another example.","answer":"False"}
    ]

    TEXT SOURCE:
    {$text}
    PROMPT;

$payload = json_encode([
    'model' => 'gpt-oss:20b', // or use 'mistral', 'gpt-oss:20b', etc. based on your setup
    'prompt' => $prompt,
    'stream' => false
]);

$curl = curl_init($AUTOGENQUIZ_API_URL);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 120
]);

$response = curl_exec($curl);
if (curl_errno($curl)) {
    $error = curl_error($curl);
    curl_close($curl);
    return json_encode(['error' => $error]);
}
curl_close($curl);

return $response ?: json_encode(['error' => 'Empty response from API.']);
}