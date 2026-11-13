<?php
/**
 * Minimal .env loader (no dependencies).
 *
 * Values are exposed through $_ENV, $_SERVER and getenv(), so the rest of the
 * project reads them with bot_env('KEY', 'default'). Real environment variables
 * always win over the .env file.
 */

if (!function_exists('bot_env')) {
    /**
     * Read a configuration value from the environment.
     *
     * @param string $key
     * @param mixed  $default Returned when the variable is unset or empty.
     * @return mixed
     */
    function bot_env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }
}

if (!function_exists('bot_load_env')) {
    /**
     * Load a .env file into the environment. Silently does nothing when the
     * file is missing, so a fresh checkout still boots.
     */
    function bot_load_env(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            $length = strlen($value);
            if ($length >= 2) {
                $first = $value[0];
                $last  = $value[$length - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                } else {
                    $comment = strpos($value, ' #');
                    if ($comment !== false) {
                        $value = rtrim(substr($value, 0, $comment));
                    }
                }
            }

            // A real environment variable always wins over the file.
            if (getenv($key) !== false) {
                continue;
            }

            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}
