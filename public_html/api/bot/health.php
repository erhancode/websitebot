<?php
/**
 * Health / setup check. Safe to expose: never returns keys or secrets.
 *
 * GET /api/bot/health.php
 */

require_once __DIR__ . '/../../../core/bot/bootstrap.php';

$aiKey  = (string) bot_config('ai.api_key', '');
$mailTo = (string) bot_config('leads.notify_email', '');
$smtp   = (string) bot_config('mail.smtp_host', '');
$dataDir = bot_data_dir('');
$locksDir = bot_data_dir('locks');

$checks = [
    'ai_key_configured'   => $aiKey !== '',
    'ai_base_url'         => (string) bot_config('ai.base_url', ''),
    'ai_model'            => (string) bot_config('ai.model', ''),
    'lead_storage'        => (bool) bot_config('leads.store_json', true),
    'data_dir_writable'   => $dataDir !== null,
    'locks_dir_writable'  => $locksDir !== null,
    'email_notifications' => $mailTo !== '',
    'smtp_configured'     => $smtp !== '',
    'internal_key_set'    => (string) bot_config('leads.internal_key', '') !== '',
];

$ready = $checks['ai_key_configured'] && $checks['data_dir_writable'] && $checks['locks_dir_writable'];

bot_json_response([
    'ok'       => true,
    'ready'    => $ready,
    'version'  => (string) bot_config('version', '1.0.0'),
    'brand'    => (string) bot_config('brand.name', ''),
    'assistant' => (string) bot_config('brand.assistant', ''),
    'php'      => PHP_VERSION,
    'checks'   => $checks,
    'hint'     => $ready
        ? 'Configuration complete.'
        : 'Add AI_API_KEY to .env (see .env.example). Everything else already works.',
]);
