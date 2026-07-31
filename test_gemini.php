<?php
require_once __DIR__ . '/config/gemini.php';
$ch = curl_init();
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_URL, 'https://generativelanguage.googleapis.com/v1beta/models?key=' . GEMINI_API_KEY . '&pageSize=100');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
$data = json_decode($response, true);
if (isset($data['models'])) {
    foreach ($data['models'] as $m) {
        if (strpos($m['name'], 'gemini') !== false && in_array('generateContent', $m['supportedGenerationMethods'] ?? [])) {
            echo $m['name'] . "\n";
        }
    }
}
