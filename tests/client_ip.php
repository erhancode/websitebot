<?php
/**
 * Checks bot_client_ip() with and without trusted-proxy mode.
 *
 *   php tests/client_ip.php            # BOT_TRUST_PROXY from .env (off by default)
 *   BOT_TRUST_PROXY=true php tests/client_ip.php
 */
require_once __DIR__ . '/../core/bot/bootstrap.php';

$_SERVER['REMOTE_ADDR']          = '172.70.1.1';            // proxy address
$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.9';          // real visitor
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 172.70.1.1';

$trust = filter_var(bot_config('leads.trust_proxy', false), FILTER_VALIDATE_BOOLEAN);
$ip    = bot_client_ip();
$want  = $trust ? '203.0.113.9' : '172.70.1.1';

printf("trust_proxy=%s ip=%s expected=%s %s\n", $trust ? 'on' : 'off', $ip, $want, $ip === $want ? 'OK' : 'FAIL');
exit($ip === $want ? 0 : 1);
