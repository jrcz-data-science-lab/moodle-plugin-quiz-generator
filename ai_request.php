<?php

defined('MOODLE_INTERNAL') || exit;

/**
 * Generate True/False questions only.
 * $text = extracted + confirmed text
 * $count = number of questions to generate.
 */
function autogenquiz_generate_tf_questions($text, $count)
{
    global $CFG;

    $configfile = __DIR__.'/config_local.php'; // Load config_local.php
    if (!file_exists($configfile)) {
        return json_encode(['error' => 'config_local.php missing.']);
    }

    require $configfile;

    // Check that API URL is valid
    if (empty($AUTOGENQUIZ_API_URL)) {
        return json_encode(['error' => 'API URL not set.']);
    }

    // Build the AI prompt
    $prompt = <<<PROMPT
    You are a professional educational quiz generator.

    TASK (VERY IMPORTANT):
    - Create exactly {$count} True/False (T/F) questions ONLY from the TEXT SOURCE.
    - Your ENTIRE reply must be ONE JSON array. No text before or after the array.

    WHAT IS A T/F QUESTION?
    - The "question" field MUST be a factual statement, not a question sentence.
    - It should normally NOT end with a question mark "?".
    - Example of VALID T/F statement:
    "Git is a distributed version control system."
    - INVALID (DO NOT DO THIS):
    "What is Git?"

    STRICT JSON OUTPUT:
    - Output ONLY a valid JSON array.
    - No markdown, no code blocks, no summaries, no explanations.
    - No extra keys, no trailing commas.
    - Keys and all string values MUST use double quotes.
    - The output MUST start with "[" and end with "]".

    STRICT JSON SCHEMA (USE ONLY THESE 4 KEYS):
    Each item in the array MUST be:
    {
    "id": <number>,               // 1,2,3,...
    "type": "tf",                 // always exactly "tf"
    "question": "<statement>",    // factual statement, no "?" at the end
    "answer": "True" or "False"   // MUST be exactly one of these two strings
    }

    - NEVER put explanations in "answer".
    - "answer" MUST be EXACTLY "True" or "False" (capital T/F, no extra text).

    ANSWER VALIDATION:
    - Before output, check each statement using the TEXT SOURCE.
    - If the statement is correct according to the text, use "True".
    - If the statement contradicts the text, use "False".
    - Do NOT guess: if you are not sure, do not use that statement.

    CONTENT FILTER RULES:
    - First infer the most likely academic subject area.
    - Use ONLY academic / instructional content:
    definitions, theories, principles, frameworks, processes, domain concepts, and relevant examples.
    - Completely ignore:
    jokes, personal information, hobbies, pass rates, attendance, classroom statistics,
    course logistics, announcements, or any non-subject-related text.

    EXAMPLE (FOLLOW STRUCTURE EXACTLY):
    [
    {"id":1,"type":"tf","question":"Git is a distributed version control system.","answer":"True"},
    {"id":2,"type":"tf","question":"GitHub and Git are the same tool.","answer":"False"}
    ]

    TEXT SOURCE:
    {$text}
    PROMPT;

    // Build the JSON payload for the AI server
    $payload = json_encode([
        'model' => 'llama3.1:8b', // use 'llama3.1:8b' or 'gpt-oss:20b', based on setup
        'prompt' => $prompt,
        'stream' => false,
    ]);

    // Send the request to the AI server using cURL:
    // POST request, JSON body, 120-second timeout, Returns response as string
    $curl = curl_init($AUTOGENQUIZ_API_URL);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 120,
    ]);

    // Execute request + error handling:
    // If cURL fails: server unreachable, timeout, DNS error, SSL issue → returns a JSON error object.
    $response = curl_exec($curl);
    if (curl_errno($curl)) {
        $error = curl_error($curl);
        curl_close($curl);

        return json_encode(['connection_error' => true, 'error' => $error]);
    }
    curl_close($curl);

    // Return API response: If the server returned empty or null → send error. Otherwise → return the AI response as-is.
    return $response ?: json_encode(['connection_error' => true, 'error' => 'Empty response from API.']);
}
