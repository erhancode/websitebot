<?php
/**
 * Lead handling: store the conversation, optionally e-mail a lead card.
 *
 * Called from api/bot/chat.php on every turn (storage) and again when the
 * assistant detects contact details (e-mail notification).
 */

require_once __DIR__ . '/bootstrap.php';
require_once BOT_ROOT . '/core/mailer.php';

if (!function_exists('bot_handle_lead')) {
    /**
     * @param array $input {
     *   message: string, lang: string, context: array, history: array,
     *   result: array {reply,is_final,lead_details}
     * }
     * @return array{stored:bool,mailed:bool,reason:string}
     */
    function bot_handle_lead(array $input): array
    {
        $message = (string) ($input['message'] ?? '');
        $lang    = (string) ($input['lang'] ?? 'en');
        $context = is_array($input['context'] ?? null) ? $input['context'] : [];
        $history = is_array($input['history'] ?? null) ? $input['history'] : [];
        $result  = is_array($input['result'] ?? null) ? $input['result'] : [];
        $url     = (string) ($context['url'] ?? '');

        $esc = static function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };

        $out = ['stored' => false, 'mailed' => false, 'reason' => ''];

        // ---------------------------------------------------------- 1. storage
        if (bot_config('leads.store_json', true)) {
            $file = (string) bot_config('paths.leads', BOT_ROOT . '/data/bot_leads.json');
            $dir  = dirname($file);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $leads = [];
            if (is_file($file)) {
                $existing = json_decode((string) @file_get_contents($file), true);
                if (is_array($existing)) {
                    $leads = $existing;
                }
            }

            $leads[] = [
                'timestamp'    => date('Y-m-d H:i:s'),
                'message'      => $message,
                'reply'        => (string) ($result['reply'] ?? ''),
                'language'     => $lang,
                'is_final'     => !empty($result['is_final']),
                'lead_details' => $result['lead_details'] ?? null,
                'source_url'   => $url,
                'ip'           => bot_client_ip(),
            ];

            $keep = max(10, (int) bot_config('leads.keep_records', 2000));
            if (count($leads) > $keep) {
                $leads = array_slice($leads, -$keep);
            }

            $out['stored'] = @file_put_contents(
                $file,
                json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            ) !== false;
        }

        // -------------------------------------------------- 2. lead card e-mail
        $adminEmail = (string) bot_config('leads.notify_email', '');
        if (empty($result['is_final']) || $adminEmail === '') {
            $out['reason'] = $adminEmail === '' ? 'notify_disabled' : 'not_final';
            return $out;
        }

        $details   = is_array($result['lead_details'] ?? null) ? $result['lead_details'] : [];
        $leadName  = trim((string) ($details['name'] ?? ''));
        $leadPhone = trim((string) ($details['phone'] ?? ''));
        $leadEmail = trim((string) ($details['email'] ?? ''));
        $leadLang  = trim((string) ($details['language'] ?? $lang));
        $leadTopic = trim((string) ($details['inquiry'] ?? ''));

        // Dedupe: the same visitor within the configured window only mails once.
        $dedupe   = max(0, (int) bot_config('leads.dedupe_seconds', 600));
        $lockDir  = bot_data_dir('locks');
        $lockFile = $lockDir ? $lockDir . '/lead_' . md5($leadPhone . $leadName) . '.lock' : null;
        if ($lockFile && is_file($lockFile) && (time() - filemtime($lockFile) < $dedupe)) {
            $out['reason'] = 'duplicate';
            return $out;
        }
        if ($lockFile) {
            @touch($lockFile);
        }

        $transcript = '';
        foreach ($history as $entry) {
            $role        = (($entry['role'] ?? '') === 'user') ? 'Visitor' : 'Assistant';
            $transcript .= '[' . $role . ']: ' . $esc($entry['text'] ?? $entry['content'] ?? '') . '<br>';
        }
        $transcript .= '[Visitor]: ' . $esc($message) . '<br>';
        $transcript .= '[Assistant]: ' . $esc($result['reply'] ?? '');

        $brand    = (string) bot_config('brand.name', 'Your Company');
        $color    = (string) bot_config('brand.color', '#004d40');
        $accent   = (string) bot_config('brand.color_accent', '#c6a15b');

        $displayName = $leadName !== '' ? $leadName : 'Name not provided';
        $displayPhone = $leadPhone !== '' ? $leadPhone : 'Not provided';
        $displayTopic = $leadTopic !== '' ? $leadTopic : 'See transcript';
        $subject = '[' . $brand . '] New lead: ' . $displayName . ' - ' . date('Y-m-d H:i');

        $html = "<div style=\"font-family:Arial,Helvetica,sans-serif;max-width:640px;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden\">"
            . "<div style=\"background:" . $esc($color) . ";color:#fff;padding:22px 26px\">"
            . "<h1 style=\"margin:0;font-size:20px\">" . $esc($brand) . " - Lead card</h1>"
            . "<p style=\"margin:6px 0 0;font-size:13px;opacity:.85\">" . $esc(date('Y-m-d H:i:s')) . "</p></div>"
            . "<div style=\"padding:26px\"><table width=\"100%\" style=\"border-collapse:collapse;font-size:14px\">"
            . "<tr><td style=\"padding:10px 0;color:#64748b;width:38%\">Name</td><td style=\"padding:10px 0;font-weight:bold\">" . $esc($displayName) . "</td></tr>"
            . "<tr><td style=\"padding:10px 0;color:#64748b\">Phone</td><td style=\"padding:10px 0;font-weight:bold\">" . $esc($displayPhone) . "</td></tr>"
            . "<tr><td style=\"padding:10px 0;color:#64748b\">E-mail</td><td style=\"padding:10px 0\">" . $esc($leadEmail !== '' ? $leadEmail : 'Not provided') . "</td></tr>"
            . "<tr><td style=\"padding:10px 0;color:#64748b\">Topic</td><td style=\"padding:10px 0;color:" . $esc($accent) . ";font-weight:bold\">" . $esc($displayTopic) . "</td></tr>"
            . "<tr><td style=\"padding:10px 0;color:#64748b\">Language</td><td style=\"padding:10px 0\">" . $esc($leadLang) . "</td></tr>"
            . "<tr><td style=\"padding:10px 0;color:#64748b\">Source page</td><td style=\"padding:10px 0;font-size:12px;color:#94a3b8\">" . $esc($url) . "</td></tr>"
            . "</table>"
            . "<h3 style=\"margin:26px 0 8px;font-size:15px;color:" . $esc($color) . "\">Conversation</h3>"
            . "<div style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;font-size:13px;line-height:1.6;color:#334155\">" . $transcript . "</div>"
            . "</div></div>";

        $out['mailed'] = BotMailer::send($adminEmail, $subject, $html, $brand);
        $out['reason'] = $out['mailed'] ? 'sent' : 'mail_failed';

        return $out;
    }
}
