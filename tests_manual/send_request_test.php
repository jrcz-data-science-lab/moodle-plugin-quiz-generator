<?php

require __DIR__.'/../config_local.php';
require __DIR__.'/../vendor/autoload.php';

$URL = $AUTOGENQUIZ_API_URL;

$payload = [
    'model' => 'gpt-oss:20b',
    'prompt' => 'Generate 2 simple True/False statements about UX.',
    'stream' => false,
];

echo "Connecting to LLM server...\n";

// Send request
$ch = curl_init($URL);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);

if (!$response) {
    echo '❌ CURL error: '.curl_error($ch)."\n";
    exit;
}

curl_close($ch);

// Only show first 300 chars
$preview = substr($response, 0, 300);

echo "\n--- Response Preview ---\n";
echo $preview."\n";
echo "------------------------\n\n";

// Try to decode JSON
$json = json_decode($response, true);

// PASS if it is valid JSON
if (is_array($json)) {
    echo "✔ TEST PASSED (LLM connection OK)\n";
} else {
    echo "✘ TEST FAILED (Invalid JSON)\n";
}
