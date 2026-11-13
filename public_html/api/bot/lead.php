<?php
/**
 * Internal lead endpoint for server-side callers (CRM hooks, form bridges).
 *
 * Requires the shared secret in the X-Internal-Key header; the secret is
 * BOT_INTERNAL_KEY in .env. Without a configured secret every request is
 * refused, so the endpoint is unusable until you set one.
 *
 * The public widget does NOT call this file; it goes through chat.php.
 */

require_once __DIR__ . '/../../../core/bot/bootstrap.php';

$expected = (string) bot_config('leads.internal_key', '');
$given    = (string) ($_SERVER['HTTP_X_INTERNAL_KEY'] ?? '');

if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
    bot_json_response(['success' => false, 'message' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bot_json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = bot_json_input();
if (!$data) {
    bot_json_response(['success' => false, 'message' => 'Invalid JSON body'], 422);
}

$message = (string) ($data['message'] ?? '');
$name    = (string) ($data['name'] ?? '');

if ($message === '' && $name === '') {
    bot_json_response(['success' => false, 'message' => 'Empty lead'], 422);
}

require_once BOT_ROOT . '/core/bot/lead_worker.php';

$result = bot_handle_lead([
    'message' => $message,
    'lang'    => (string) ($data['lang'] ?? bot_config('widget.default_lang', 'en')),
    'context' => is_array($data['context'] ?? null) ? $data['context'] : [],
    'history' => is_array($data['history'] ?? null) ? $data['history'] : [],
    'result'  => is_array($data['result'] ?? null) ? $data['result'] : [],
]);

bot_json_response([
    'success' => true,
    'stored'  => $result['stored'],
    'mailed'  => $result['mailed'],
    'reason'  => $result['reason'],
]);
