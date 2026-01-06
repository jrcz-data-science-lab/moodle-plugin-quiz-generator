<?php

defined('MOODLE_INTERNAL') || exit;

function autogenquiz_generate_fib_questions(string $text, int $count): string
{
    require __DIR__ . '/config_local.php';

    $prompt = <<<PROMPT
You are a professional educational quiz generator.

TASK:
- Create exactly {$count} fill-in-the-blank questions.
- Use ONLY the TEXT SOURCE.
- Each question MUST contain exactly ONE blank.
- Represent the blank using exactly five underscores: _____

STRICT JSON OUTPUT:
- Output ONLY a JSON array.
- No markdown, no explanation.

SCHEMA:
[
  {
    "id": 1,
    "type": "fib",
    "question": "The capital of France is _____.",
    "answers": ["Paris"]
  }
]

RULES:
- The blank must replace a concrete factual term.
- Answers must be short and exact.
- answers MUST be an array.
- Do not generate ambiguous blanks.

TEXT SOURCE:
{$text}
PROMPT;

    $payload = json_encode([
        'model' => 'gpt-oss:20b',
        'prompt' => $prompt,
        'stream' => false,
    ]);

    $curl = curl_init($AUTOGENQUIZ_API_URL);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 120,
    ]);

    $res = curl_exec($curl);
    if (curl_errno($curl)) {
        return json_encode(['connection_error' => true]);
    }
    curl_close($curl);

    return $res ?: json_encode(['connection_error' => true]);
}
