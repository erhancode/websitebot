<?php
/**
 * Bot configuration.
 *
 * Every value can be overridden from .env (see .env.example). The defaults are
 * deliberately generic so the widget runs in any site; only AI_API_KEY has to
 * be filled in before the assistant can answer.
 *
 * Do not put secrets in this file - use .env.
 */

return [
    'version' => '1.0.0',

    // ---------------------------------------------------------------- branding
    'brand' => [
        'name'       => bot_env('BOT_BRAND_NAME', 'Your Company'),
        'assistant'  => bot_env('BOT_ASSISTANT_NAME', 'Assistant'),
        'role'       => bot_env('BOT_ASSISTANT_ROLE', 'AI support assistant'),
        'subtitle'   => bot_env('BOT_SUBTITLE', 'AI assistant'),
        'website'    => bot_env('BOT_WEBSITE', ''),
        // Relative to the asset base, or an absolute path/URL.
        'avatar'     => bot_env('BOT_AVATAR', 'img/bot-avatar.svg'),
        'color'      => bot_env('BOT_COLOR_PRIMARY', '#004d40'),
        'color_accent' => bot_env('BOT_COLOR_ACCENT', '#c6a15b'),
    ],

    // --------------------------------------------------------------------- AI
    // Any OpenAI-compatible endpoint works: Groq, OpenAI, OpenRouter, Together,
    // a local vLLM/Ollama gateway, etc. Change AI_BASE_URL and AI_MODEL.
    'ai' => [
        'base_url'     => rtrim((string) bot_env('AI_BASE_URL', 'https://api.groq.com/openai/v1'), '/'),
        'model'        => bot_env('AI_MODEL', 'qwen/qwen3.8-27b'),
        'api_key'      => bot_env('AI_API_KEY', ''),
        'max_tokens'   => (int) bot_env('AI_MAX_TOKENS', 250),
        'temperature'  => (float) bot_env('AI_TEMPERATURE', 0.5),
        'timeout'      => (int) bot_env('AI_TIMEOUT', 20),
        // Keep true in production. false only for a self-signed internal endpoint.
        'ssl_verify'   => filter_var(bot_env('AI_SSL_VERIFY', 'true'), FILTER_VALIDATE_BOOLEAN),
        // Optional extra instructions appended to the generated system prompt.
        'extra_prompt' => bot_env('BOT_EXTRA_PROMPT', ''),
        // Full system prompt override (empty = use the generated one).
        'system_prompt' => bot_env('BOT_SYSTEM_PROMPT', ''),
        // Extract a structured lead card once contact details appear.
        'extract_leads' => filter_var(bot_env('BOT_EXTRACT_LEADS', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],

    // --------------------------------------------------------------- languages
    // Languages the widget ships with. Remove ones you do not need.
    'languages' => [
        'tr', 'en', 'ru', 'ky', 'az', 'ar', 'fa', 'de', 'fr', 'es', 'it', 'uz',
        'kk', 'ro', 'bg', 'sq', 'sr', 'nl', 'pl', 'cs', 'da', 'el', 'fi', 'fr-ma',
        'hu', 'no', 'pt', 'sv',
    ],
    'rtl_languages' => ['ar', 'fa', 'he'],

    // ------------------------------------------------------------------ widget
    'widget' => [
        'default_lang'     => bot_env('BOT_DEFAULT_LANG', 'en'),
        'position'         => bot_env('BOT_POSITION', 'left'), // left | right
        'auto_open'        => filter_var(bot_env('BOT_AUTO_OPEN', 'false'), FILTER_VALIDATE_BOOLEAN),
        'open_delay_ms'    => (int) bot_env('BOT_OPEN_DELAY', 25000),
        'scroll_threshold' => (float) bot_env('BOT_SCROLL_THRESHOLD', 0.5),
        'max_history'      => (int) bot_env('BOT_MAX_HISTORY', 6),
        'max_input'        => (int) bot_env('BOT_MAX_INPUT', 1000),
        'show_launcher'    => filter_var(bot_env('BOT_SHOW_LAUNCHER', 'true'), FILTER_VALIDATE_BOOLEAN),
        // Elements the widget hides while the chat is open.
        'hide_selectors'   => [],
        // URL bases. Set these when the site lives in a subdirectory.
        'asset_base'       => rtrim((string) bot_env('BOT_ASSET_BASE', '/assets/'), '/') . '/',
        'api_base'         => rtrim((string) bot_env('BOT_API_BASE', '/api/bot'), '/') . '/',
        // Only needed when the widget runs on a different origin than the API.
        // Example: BOT_CORS_ORIGIN=https://www.example.com
        'cors_origin'      => (string) bot_env('BOT_CORS_ORIGIN', ''),
    ],

    // ------------------------------------------------------------------- leads
    'leads' => [
        // Append every conversation turn to data/bot_leads.json.
        'store_json'      => filter_var(bot_env('BOT_STORE_LEADS', 'true'), FILTER_VALIDATE_BOOLEAN),
        // Recipient of the lead card e-mail. Empty = e-mail notification off
        // (leads are still stored on disk).
        'notify_email'    => bot_env('LEAD_NOTIFY_EMAIL', ''),
        // Shared secret for the internal lead endpoint (api/bot/lead.php).
        'internal_key'    => bot_env('BOT_INTERNAL_KEY', ''),
        'rate_limit'      => (int) bot_env('BOT_RATE_LIMIT', 20),   // requests...
        'rate_window'     => (int) bot_env('BOT_RATE_WINDOW', 60),  // ...per seconds
        // Behind Cloudflare or another reverse proxy, set this to true so the
        // limiter uses the real visitor IP instead of the proxy address.
        'trust_proxy'     => filter_var(bot_env('BOT_TRUST_PROXY', 'false'), FILTER_VALIDATE_BOOLEAN),
        'dedupe_seconds'  => (int) bot_env('BOT_LEAD_DEDUPE', 600),
        'keep_records'    => (int) bot_env('BOT_KEEP_LEADS', 2000),
    ],

    // -------------------------------------------------------------------- mail
    'mail' => [
        'smtp_host'  => bot_env('SMTP_HOST', ''),
        'smtp_port'  => (int) bot_env('SMTP_PORT', 587),
        'smtp_user'  => bot_env('SMTP_USER', ''),
        'smtp_pass'  => bot_env('SMTP_PASS', ''),
        'from_email' => bot_env('FROM_EMAIL', 'no-reply@localhost'),
        'from_name'  => bot_env('FROM_NAME', bot_env('BOT_BRAND_NAME', 'Your Company')),
        'ssl_verify' => filter_var(bot_env('SMTP_SSL_VERIFY', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],

    // ------------------------------------------------------------------- paths
    'paths' => [
        'data'  => __DIR__ . '/../data',
        'leads' => __DIR__ . '/../data/bot_leads.json',
        'locks' => __DIR__ . '/../data/locks',
        'logs'  => __DIR__ . '/../data/logs',
    ],
];
