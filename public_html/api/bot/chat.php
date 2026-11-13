<?php
/**
 * Chat endpoint: POST JSON {message, lang, url, title, history}
 *                 GET  ?action=get_lang
 */

require_once __DIR__ . '/../../../core/bot/bootstrap.php';

// Optional CORS for sites where the widget and the API live on different hosts.
$corsOrigin = (string) bot_config('widget.cors_origin', '');
if ($corsOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $corsOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

$action = isset($_GET['action']) ? preg_replace('/[^a-z_]/', '', (string) $_GET['action']) : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $action !== 'get_lang') {
    bot_json_response(['error' => 'Method not allowed'], 405);
}

$cfg = bot_config();

if ($action === 'get_lang') {
    require_once BOT_ROOT . '/core/bot/ChatHandler.php';
    bot_json_response(['lang' => (new ChatHandler())->getIpLang()]);
}

// ---------------------------------------------------------------- rate limit
$limit = bot_rate_limit('chat');
if (!$limit['allowed']) {
    header('Retry-After: ' . $limit['retry_after']);
    bot_json_response([
        'reply'    => 'Too many requests. Please wait a moment.',
        'is_final' => false,
        'error'    => 'rate_limited',
    ], 429);
}

// ------------------------------------------------------------------- input
$input   = bot_json_input();
$message = trim((string) ($input['message'] ?? ''));
$lang    = preg_replace('/[^a-zA-Z\-]/', '', (string) ($input['lang'] ?? $cfg['widget']['default_lang']));
$history = array_slice(
    is_array($input['history'] ?? null) ? $input['history'] : [],
    -(int) $cfg['widget']['max_history'] * 3
);
$context = [
    'url'   => substr((string) ($input['url'] ?? ''), 0, 500),
    'title' => substr((string) ($input['title'] ?? ''), 0, 200),
];

if ($message === '') {
    bot_json_response(['reply' => '', 'is_final' => false, 'error' => 'empty_message'], 422);
}

$message = substr($message, 0, (int) $cfg['widget']['max_input']);

// ------------------------------------------------------------------- answer
require_once BOT_ROOT . '/core/bot/ChatHandler.php';
require_once BOT_ROOT . '/core/bot/lead_worker.php';

try {
    $handler = new ChatHandler();
    $result  = $handler->getResponse($message, $lang, $context, $history);

    bot_handle_lead([
        'message' => $message,
        'lang'    => $lang,
        'context' => $context,
        'history' => $history,
        'result'  => $result,
    ]);

    $status = ($result['error'] ?? null) === 'rate_limited' ? 429 : 200;
    bot_json_response($result, $status);
} catch (\Throwable $e) {
    bot_log('chat.php: ' . $e->getMessage());
    bot_json_response([
        'reply'    => '',
        'is_final' => false,
        'error'    => 'internal_error',
    ], 500);
}
