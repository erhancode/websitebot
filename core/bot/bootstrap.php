<?php
/**
 * Shared bootstrap: paths, .env, configuration accessors.
 *
 * Every entry point (api/bot/*.php, embed/bot-embed.php, tests) includes this
 * file first. It defines BOT_ROOT once and loads the configuration lazily.
 */

if (!defined('BOT_ROOT')) {
    define('BOT_ROOT', realpath(__DIR__ . '/../..'));
}

require_once BOT_ROOT . '/config/env_loader.php';

if (!function_exists('bot_config')) {
    /**
     * Read configuration with dot notation: bot_config('ai.model').
     *
     * @param string|null $key     Dotted path, null for the whole array.
     * @param mixed       $default Value when the key is missing.
     * @return mixed
     */
    function bot_config(?string $key = null, $default = null)
    {
        static $config = null;

        if ($config === null) {
            bot_load_env(BOT_ROOT . '/.env');
            $config = require BOT_ROOT . '/config/bot_config.php';
        }

        if ($key === null) {
            return $config;
        }

        $value = $config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}

if (!function_exists('bot_asset_base')) {
    /** Public asset base URL, always with a trailing slash. */
    function bot_asset_base(): string
    {
        $base = (string) bot_config('widget.asset_base', '/assets/');
        return substr($base, -1) === '/' ? $base : $base . '/';
    }
}

if (!function_exists('bot_api_base')) {
    /** Public API base URL, always with a trailing slash. */
    function bot_api_base(): string
    {
        $base = (string) bot_config('widget.api_base', '/api/bot/');
        return substr($base, -1) === '/' ? $base : $base . '/';
    }
}

if (!function_exists('bot_avatar_url')) {
    /** Avatar URL: absolute URLs and root-relative paths are used as-is. */
    function bot_avatar_url(): string
    {
        $avatar = (string) bot_config('brand.avatar', 'img/bot-avatar.svg');
        if (preg_match('#^(https?:)?//#', $avatar) || (isset($avatar[0]) && $avatar[0] === '/')) {
            return $avatar;
        }
        return bot_asset_base() . ltrim($avatar, '/');
    }
}

if (!function_exists('bot_data_dir')) {
    /** Return a writable data sub-directory, creating it when needed. */
    function bot_data_dir(string $sub = ''): ?string
    {
        $root = (string) bot_config('paths.data');
        $path = $sub === '' ? $root : rtrim($root, '/\\') . '/' . trim($sub, '/\\');

        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
            return null;
        }
        return is_dir($path) && is_writable($path) ? $path : null;
    }
}

if (!function_exists('bot_log')) {
    /** Append a line to data/logs/bot.log. Never throws. */
    function bot_log(string $message): void
    {
        $dir = bot_data_dir('logs');
        if ($dir === null) {
            return;
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL;
        @file_put_contents($dir . '/bot.log', $line, FILE_APPEND);
    }
}

if (!function_exists('bot_json_response')) {
    /** Emit a JSON response and stop. */
    function bot_json_response(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('bot_json_input')) {
    /** Decode the JSON request body into an array (empty array on failure). */
    function bot_json_input(): array
    {
        $raw  = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('bot_client_ip')) {
    function bot_client_ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}

if (!function_exists('bot_rate_limit')) {
    /**
     * Simple file based rate limiter per IP.
     *
     * @return array{allowed:bool,remaining:int,retry_after:int}
     */
    function bot_rate_limit(string $bucket = 'chat'): array
    {
        $limit  = max(1, (int) bot_config('leads.rate_limit', 20));
        $window = max(1, (int) bot_config('leads.rate_window', 60));
        $dir    = bot_data_dir('locks');

        if ($dir === null) {
            // No writable storage: fail open rather than take the chat down.
            return ['allowed' => true, 'remaining' => $limit, 'retry_after' => 0];
        }

        $file = $dir . '/rate_' . $bucket . '_' . md5(bot_client_ip()) . '.json';
        $now  = time();
        $data = ['count' => 0, 'first' => $now];

        if (is_file($file)) {
            $stored = json_decode((string) @file_get_contents($file), true);
            if (is_array($stored) && isset($stored['first'], $stored['count'])) {
                $data = ['count' => (int) $stored['count'], 'first' => (int) $stored['first']];
            }
        }

        if ($now - $data['first'] > $window) {
            $data = ['count' => 1, 'first' => $now];
        } else {
            $data['count']++;
        }

        @file_put_contents($file, json_encode($data), LOCK_EX);

        $allowed = $data['count'] <= $limit;
        return [
            'allowed'     => $allowed,
            'remaining'   => max(0, $limit - $data['count']),
            'retry_after' => $allowed ? 0 : max(1, $window - ($now - $data['first'])),
        ];
    }
}
