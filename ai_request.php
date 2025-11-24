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

    TASK:
    Create exactly {$count} True/False (T/F) questions only from the provided text.

    IMPORTANT CONTENT RULES:
    - First identify the most likely academic subject area (e.g., psychology, engineering, business, biology, computer science).
    - Use ONLY content that is academic, instructional, or related to the course subject.
    - Completely ignore all non-academic or irrelevant content, including:
      * jokes or humor
      * teacher personal information, hobbies, self-introductions
      * student attendance, pass rates, or classroom statistics
      * course logistics, announcements, or administrative notes
      * any slide text not related to the subject's concepts
    - Focus ONLY on definitions, theories, principles, frameworks, processes, domain concepts, and instructional examples.

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

    // Build the JSON payload for the AI server
    $payload = json_encode([
        'model' => 'mistral', // use 'mistral' or 'gpt-oss:20b', based on setup
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
