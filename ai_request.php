<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Send extracted text and parameters to local LLM API.
 *
 * @param string $text Extracted teaching material
 * @param string $question_type e.g. 'tf', 'mcq', 'short'
 * @param int $count number of questions
 * @return string raw JSON response or error message
 */
function autogenquiz_generate_questions($text, $question_type, $count) {
    global $CFG;

    $configfile = __DIR__ . '/config_local.php';
    if (!file_exists($configfile)) {
        return json_encode(['error' => 'config_local.php missing.']);
    }

    require($configfile);
    if (empty($AUTOGENQUIZ_API_URL)) {
        return json_encode(['error' => 'API URL not set.']);
    }

    $system_prompt = <<<PROMPT
You are an educational quiz generator.
Your task is to create accurate, factual quiz questions from teaching materials.

TASK:
The following text comes from lecture slides or PDF pages.
Each new section starts with a line like:
[Slide 1] or [Page 1]

The text may have small typos, missing symbols, or broken line order.
Read it carefully and infer the correct meaning before writing questions.
Do NOT invent new information that isn't supported by the text.

GOAL:
Understand each [Slide] or [Page] as one topic.
Use your best reasoning to correct small spelling or formula errors.
Treat consecutive slides that discuss the same topic as part of one idea, but keep unrelated topics separate.
Generate quiz questions based on the selected type: {$question_type}.
Ensure all facts are scientifically or academically correct.
Return only valid JSON — no explanations, no extra text.

OUTPUT FORMAT:
Return a JSON array.
Each object must include:

"id": integer
"type": "{$question_type}"
"question": string
"options": array (for all types, may be empty for short answers)
"answer": string or array depending on question type

TEXT INPUT:
{$text}
PROMPT;

    $payload = json_encode([
        'model' => 'phi3:mini',
        'prompt' => $system_prompt,
        'stream' => false  // disable streaming mode
    ]);

    $response = '';
    $curl = curl_init($AUTOGENQUIZ_API_URL);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, // now we just wait for full JSON
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
