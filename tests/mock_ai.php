<?php
/**
 * Mock OpenAI-compatible endpoint for local tests. Never deploy this.
 *
 *   php -S 127.0.0.1:8124 tests/mock_ai.php
 *   AI_BASE_URL=http://127.0.0.1:8124/v1 AI_API_KEY=test php -S 127.0.0.1:8123 -t public_html
 *
 * Responds with a normal assistant message, or with a lead JSON object when the
 * request is the extraction call.
 */

header('Content-Type: application/json');
$raw  = file_get_contents('php://input');
$data = json_decode((string) $raw, true);

$messages = $data['messages'] ?? [];
$first    = strtolower((string) ($messages[0]['content'] ?? ''));
$last     = (string) (end($messages)['content'] ?? '');

if (strpos($first, 'json data extractor') !== false) {
    $phone = '';
    if (preg_match('/\+?\d[\d\s\-().]{6,}\d/', $last, $m)) {
        $phone = trim($m[0]);
    }
    $content = json_encode([
        'name'     => 'Test Visitor',
        'phone'    => $phone,
        'email'    => '',
        'language' => 'en',
        'inquiry'  => 'Mock inquiry for local testing',
    ]);
} else {
    $content = 'Mock assistant reply to: ' . substr($last, 0, 80);
}

echo json_encode([
    'id'      => 'mock-' . time(),
    'object'  => 'chat.completion',
    'created' => time(),
    'model'   => $data['model'] ?? 'mock',
    'choices' => [
        [
            'index'         => 0,
            'message'       => ['role' => 'assistant', 'content' => $content],
            'finish_reason' => 'stop',
        ],
    ],
    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
], JSON_UNESCAPED_UNICODE);
