<?php
/**
 * HTTP smoke test for the chat API.
 *
 *   php tests/smoke.php [base-url]
 *
 * Expects a running app server and (for the reply assertion) the mock AI
 * endpoint; see README "Local check".
 */

$base = rtrim($argv[1] ?? getenv('APP_URL') ?: 'http://127.0.0.1:8123', '/');
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $fail;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'PASS' : 'FAIL') . ' - ' . $label . ($detail !== '' ? ' (' . $detail . ')' : '') . PHP_EOL;
}

function post_json(string $url, array $payload): array
{
    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'       => 30,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        return ['_http_error' => 'request failed'];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['_http_error' => 'invalid json', '_raw' => $raw];
}

function get_json(string $url): array
{
    $raw = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]));
    if ($raw === false) {
        return ['_http_error' => 'request failed'];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['_http_error' => 'invalid json'];
}

// 1. health
$health = get_json($base . '/api/bot/health.php');
check('health endpoint answers', isset($health['ok']), json_encode($health['error'] ?? $health['_http_error'] ?? ''));
check('data directory writable', !empty($health['checks']['data_dir_writable']));

// 2. language hint
$lang = get_json($base . '/api/bot/chat.php?action=get_lang');
check('language hint returns a code', !empty($lang['lang']), json_encode($lang));

// 3. plain message
$first = post_json($base . '/api/bot/chat.php', [
    'message' => 'Hello, I would like some information.',
    'lang'    => 'en',
    'url'     => $base . '/demo.php',
    'title'   => 'Demo',
    'history' => [],
]);
check('chat returns a reply', !empty($first['reply']), json_encode($first['error'] ?? ''));
check('first turn is not final', empty($first['is_final']));

// 4. contact details -> final lead
$second = post_json($base . '/api/bot/chat.php', [
    'message' => 'My name is Test Visitor and my phone is +90 555 111 22 33.',
    'lang'    => 'en',
    'url'     => $base . '/demo.php',
    'title'   => 'Demo',
    'history' => [
        ['role' => 'user', 'content' => 'Hello, I would like some information.'],
        ['role' => 'assistant', 'content' => (string) ($first['reply'] ?? '')],
    ],
]);
check('contact details mark the lead final', !empty($second['is_final']), json_encode($second['error'] ?? ''));

// 5. empty message is rejected
$empty = post_json($base . '/api/bot/chat.php', ['message' => '   ', 'lang' => 'en']);
check('empty message rejected', empty($empty['reply']), json_encode($empty));

echo PHP_EOL . ($fail === 0 ? 'ALL CHECKS PASSED' : $fail . ' CHECK(S) FAILED') . PHP_EOL;
exit($fail === 0 ? 0 : 1);
