<?php
/**
 * Minimal mailer: PHP mail() first, socket SMTP as fallback.
 *
 * All connection details come from configuration (mail.*). When SMTP is not
 * configured the mailer reports failure without throwing, so lead storage keeps
 * working on a fresh install.
 */

require_once __DIR__ . '/bot/bootstrap.php';

class BotMailer
{
    /**
     * Send an HTML e-mail.
     *
     * @return bool true when a transport accepted the message.
     */
    public static function send($to, $subject, $message, $fromName = null)
    {
        $to = trim((string) $to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::logError('invalid recipient: ' . $to);
            return false;
        }

        $host      = (string) bot_config('mail.smtp_host', '');
        $user      = (string) bot_config('mail.smtp_user', '');
        $pass      = (string) bot_config('mail.smtp_pass', '');
        $port      = (int) bot_config('mail.smtp_port', 587);
        $fromEmail = (string) bot_config('mail.from_email', 'no-reply@localhost');
        $fromName  = $fromName ?: (string) bot_config('mail.from_name', 'Bot');

        $subject = self::encodeHeader((string) $subject);
        $from    = self::encodeHeader((string) $fromName) . ' <' . $fromEmail . '>';

        // Method 1: local mail() (works when the host has an MTA).
        try {
            $headers  = 'From: ' . $from . "\r\n";
            $headers .= 'Reply-To: ' . $fromEmail . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "X-Mailer: BotMailer\r\n";

            if (@mail($to, $subject, $message, $headers)) {
                return true;
            }
        } catch (\Throwable $e) {
            self::logError('mail() failed: ' . $e->getMessage());
        }

        if ($host === '' || $user === '') {
            self::logError('SMTP not configured, mail() unavailable');
            return false;
        }

        // Method 2: socket SMTP with STARTTLS.
        try {
            $verify  = (bool) bot_config('mail.ssl_verify', true);
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => $verify,
                    'verify_peer_name'  => $verify,
                    'allow_self_signed' => !$verify,
                ],
            ]);

            $socket = @stream_socket_client(
                'tcp://' . $host . ':' . $port,
                $errno,
                $errstr,
                15,
                STREAM_CLIENT_CONNECT,
                $context
            );
            if (!$socket) {
                throw new \RuntimeException("connect failed: $errstr ($errno)");
            }

            self::expect($socket, '220');

            $hostname = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            fwrite($socket, 'EHLO ' . $hostname . "\r\n");
            self::expect($socket, '250');

            fwrite($socket, "STARTTLS\r\n");
            self::expect($socket, '220');
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('STARTTLS failed');
            }

            fwrite($socket, 'EHLO ' . $hostname . "\r\n");
            self::expect($socket, '250');

            fwrite($socket, 'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass) . "\r\n");
            self::expect($socket, '235');

            fwrite($socket, 'MAIL FROM: <' . $fromEmail . ">\r\n");
            self::expect($socket, '250');
            fwrite($socket, 'RCPT TO: <' . $to . ">\r\n");
            self::expect($socket, '250');
            fwrite($socket, "DATA\r\n");
            self::expect($socket, '354');

            $body  = 'To: <' . $to . ">\r\n";
            $body .= 'From: ' . $from . "\r\n";
            $body .= 'Subject: ' . $subject . "\r\n";
            $body .= "MIME-Version: 1.0\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $body .= str_replace("\n.", "\n..", (string) $message) . "\r\n.\r\n";

            fwrite($socket, $body);
            self::expect($socket, '250');
            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            return true;
        } catch (\Throwable $e) {
            self::logError('SMTP error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Read an SMTP reply and assert its status code.
     */
    private static function expect($socket, string $code): void
    {
        $response = '';
        while ($line = @fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        if (substr($response, 0, 3) !== $code) {
            throw new \RuntimeException('SMTP unexpected response: ' . trim($response));
        }
    }

    private static function encodeHeader(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);
        return preg_match('/[\x80-\xFF]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    public static function logError($msg): void
    {
        bot_log('BotMailer: ' . $msg);
    }
}
