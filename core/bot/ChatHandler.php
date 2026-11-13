<?php
/**
 * Chat logic for the site assistant.
 *
 * Provider agnostic: talks to any OpenAI-compatible /chat/completions endpoint
 * configured through AI_BASE_URL / AI_MODEL / AI_API_KEY.
 */

require_once __DIR__ . '/bootstrap.php';

class ChatHandler
{
    /** @var array */
    private $ai;

    /** @var string|null Last transport/model error, for logs and health checks. */
    public $lastError = null;

    public function __construct(?array $aiConfig = null)
    {
        $this->ai = $aiConfig ?? (array) bot_config('ai', []);
    }

    /**
     * Produce the assistant reply for one turn.
     *
     * @param string $message     Current user message.
     * @param string $lang        Language code selected by the widget.
     * @param array  $pageContext ['url' => string, 'title' => string]
     * @param array  $history     Previous turns as [['role'=>'user|assistant','content'=>string], ...]
     * @return array{reply:string,is_final:bool,lead_details:?array,error:?string}
     */
    public function getResponse($message, $lang, $pageContext = [], $history = [])
    {
        $lang = (string) $lang;

        if (empty($this->ai['api_key'])) {
            $this->lastError = 'missing_api_key';
            return [
                'reply'        => $this->getFallbackReply($lang),
                'is_final'     => false,
                'lead_details' => null,
                'error'        => 'missing_api_key',
            ];
        }

        try {
            $messages = [['role' => 'system', 'content' => $this->generateSystemPrompt($lang, $pageContext)]];

            foreach ($history as $entry) {
                if (is_array($entry) && isset($entry['role'], $entry['content'])) {
                    $role = $entry['role'] === 'assistant' ? 'assistant' : 'user';
                    $messages[] = ['role' => $role, 'content' => (string) $entry['content']];
                }
            }
            $messages[] = ['role' => 'user', 'content' => (string) $message];

            $output = $this->cleanOutput($this->callModel($messages));

            // Contact detection: fire the lead once, when contact information
            // appears in this turn and never appeared earlier in the conversation.
            $isFinal    = false;
            $leadData   = null;
            $hasContact = $this->containsContactInfo($message) || $this->containsContactInfo($output);
            $hadContact = $this->historyHasContact($history);

            if ($hasContact && !$hadContact) {
                $isFinal = true;
                if (!empty($this->ai['extract_leads'])) {
                    $leadData = $this->extractLeadDetails(
                        array_merge($history, [
                            ['role' => 'user', 'content' => $message],
                            ['role' => 'assistant', 'content' => $output],
                        ]),
                        $pageContext
                    );
                }
            }

            return [
                'reply'        => trim($output),
                'is_final'     => $isFinal,
                'lead_details' => $leadData,
                'error'        => $this->lastError,
            ];
        } catch (\Throwable $e) {
            $this->lastError = 'exception: ' . $e->getMessage();
            bot_log('ChatHandler: ' . $e->getMessage());
            return [
                'reply'        => $this->getFallbackReply($lang),
                'is_final'     => false,
                'lead_details' => null,
                'error'        => 'exception',
            ];
        }
    }

    /**
     * Build the system prompt from configuration.
     */
    public function generateSystemPrompt($lang, $pageContext = [], $userMessage = '')
    {
        $override = trim((string) ($this->ai['system_prompt'] ?? ''));
        if ($override !== '') {
            return $override;
        }

        $brand     = (string) bot_config('brand.name', 'Your Company');
        $assistant = (string) bot_config('brand.assistant', 'Assistant');
        $role      = (string) bot_config('brand.role', 'AI support assistant');
        $website   = (string) bot_config('brand.website', '');
        $pageUrl   = (string) ($pageContext['url'] ?? '');
        $pageTitle = (string) ($pageContext['title'] ?? '');

        $contextLines = [];
        if ($pageUrl !== '') {
            $contextLines[] = '- Page URL: ' . $pageUrl;
        }
        if ($pageTitle !== '') {
            $contextLines[] = '- Page title: ' . $pageTitle;
        }
        $context = $contextLines ? "\n        CURRENT PAGE:\n        " . implode("\n        ", $contextLines) . "\n" : '';

        $scriptHint = ($lang === 'sr-cyrl')
            ? "For Serbian messages, use Serbian Cyrillic (ћирилица) and preserve official brand names.\n        "
            : '';

        $prompt = $scriptHint . "You are {$assistant}, the {$role} for {$brand}"
            . ($website !== '' ? " ({$website})" : '') . ".
        {$context}
        TONE: Professional, warm, concise and precise.

        GOAL: Understand what the visitor needs, answer briefly, and collect the details a human team member needs to follow up.

        RULES:
        1. NO INVENTED FACTS: never invent staff names, prices, availability or policies. If you do not know, say so and offer to connect the visitor with the team.
        2. BE CONCISE: at most 2-3 short sentences per reply. No long mission statements.
        3. LEAD CAPTURE: ask for the visitor's name, what they are interested in, and an e-mail or phone number so the team can follow up.
        4. PERSONALIZATION: address the visitor by name only after they gave it themselves. Never guess a name.
        5. MIRRORING: reply in the exact language the visitor wrote in. No mixing of languages.
        6. BRANDING: the organization is '{$brand}'.
        7. TERMINATION: append '!!!DONE!!!' only in your final goodbye, after you have usable contact details.
        8. NO PRESSURE: if the visitor says goodbye or thanks without leaving contact details, politely explain that contact details are needed for a follow-up.
        9. NO IDENTITY REPETITION: do not repeat 'I am {$assistant}' in every message.
        10. LANGUAGE PURITY: no characters or words from unrelated languages or scripts.
        11. NO ASSUMPTIONS: do not assume a specific product or service when the visitor mentions only a broad category; ask naturally.
        12. NO BINDING COMMITMENTS: do not confirm prices, deadlines or guarantees; offer to have the team confirm.
        13. REDUNDANCY CONTROL: once the conversation is going, skip formal introductions.";

        $extra = trim((string) ($this->ai['extra_prompt'] ?? ''));
        if ($extra !== '') {
            $prompt .= "\n\n        ADDITIONAL INSTRUCTIONS:\n        " . $extra;
        }

        return $prompt;
    }

    /**
     * Strip reasoning blocks and control tokens from model output.
     */
    private function cleanOutput($text)
    {
        $text = (string) $text;
        $text = preg_replace('/^.*?<\/think>/s', '', $text);
        $text = str_replace(['!!!DONE!!!', '```'], '', $text);
        return trim($text);
    }

    /**
     * Extract a structured lead card from the transcript.
     */
    private function extractLeadDetails($fullHistory, $pageContext = [])
    {
        $transcript = '';
        foreach ($fullHistory as $entry) {
            $role        = ($entry['role'] === 'user') ? 'User' : 'Assistant';
            $transcript .= '[' . $role . ']: ' . (string) ($entry['content'] ?? $entry['text'] ?? '') . "\n";
        }

        $pageUrl   = (string) ($pageContext['url'] ?? 'Unknown');
        $pageTitle = (string) ($pageContext['title'] ?? '');

        $prompt = "You are a precise data analyst. Extract a structured lead card from the transcript below.
        CONTEXT:
        - Page URL: {$pageUrl}
        - Page title: {$pageTitle}

        TRANSCRIPT:
        {$transcript}

        TASK: return ONLY a JSON object with these fields:
        1. name: full name (empty string if not stated).
        2. phone: phone or WhatsApp number in international format (empty string if not stated).
        3. email: e-mail address (empty string if not stated).
        4. language: the language the user wrote in.
        5. inquiry: one sentence describing what the user asked for; fall back to the page title when the transcript is vague.

        OUTPUT RULES: valid JSON only, no preamble, no markdown.";

        $raw = $this->callModel([
            ['role' => 'system', 'content' => 'You are a precise JSON data extractor.'],
            ['role' => 'user', 'content' => $prompt],
        ], 400);

        $json = trim($this->cleanOutput($raw));
        $json = trim(str_replace(['```json', '```'], '', $json));

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [
                'name'     => '',
                'phone'    => '',
                'email'    => '',
                'language' => '',
                'inquiry'  => $pageTitle,
                'raw'      => $json,
            ];
        }
        return $data;
    }

    /**
     * Detect a phone-like number (at least 7 characters of digits/separators).
     */
    private function containsContactInfo($text)
    {
        $text = (string) $text;
        // E-mail also counts as usable contact information.
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text)) {
            return true;
        }
        return (bool) preg_match('/(?:\+?\d[\d\s\-().]{6,18}\d)/', $text);
    }

    private function historyHasContact($history)
    {
        foreach ($history as $entry) {
            if ($this->containsContactInfo($entry['content'] ?? '')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fallback language hint for the browser, used when no page language is known.
     */
    public function getIpLang()
    {
        $header    = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en');
        $candidate = strtolower(substr($header, 0, 2));
        $supported = (array) bot_config('languages', ['en']);
        return in_array($candidate, $supported, true) ? $candidate : (string) bot_config('widget.default_lang', 'en');
    }

    /**
     * Call the configured OpenAI-compatible endpoint.
     *
     * @return string Assistant message content.
     */
    private function callModel(array $messages, ?int $maxTokens = null)
    {
        $url  = (string) $this->ai['base_url'] . '/chat/completions';
        $body = json_encode([
            'model'       => (string) $this->ai['model'],
            'messages'    => $messages,
            'max_tokens'  => $maxTokens ?? (int) $this->ai['max_tokens'],
            'temperature' => (float) $this->ai['temperature'],
        ], JSON_UNESCAPED_UNICODE);

        $timeout = max(5, (int) $this->ai['timeout']);
        $verify  = (bool) $this->ai['ssl_verify'];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->ai['api_key'],
                ],
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => $verify,
                CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            ]);
            $raw = curl_exec($ch);
            if ($raw === false) {
                $this->lastError = 'transport: ' . curl_error($ch);
                curl_close($ch);
                throw new \RuntimeException($this->lastError);
            }
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/json\r\nAuthorization: Bearer " . $this->ai['api_key'] . "\r\n",
                    'content'       => $body,
                    'timeout'       => $timeout,
                    'ignore_errors' => true,
                ],
                'ssl'  => [
                    'verify_peer'      => $verify,
                    'verify_peer_name' => $verify,
                ],
            ]);

            $raw = @file_get_contents($url, false, $context);
            if ($raw === false || $raw === '') {
                $this->lastError = 'transport: request failed';
                throw new \RuntimeException($this->lastError);
            }
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $this->lastError = 'transport: invalid JSON response';
            throw new \RuntimeException($this->lastError);
        }

        if (isset($decoded['error'])) {
            $this->lastError = 'api: ' . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']));
            bot_log('AI API error: ' . $this->lastError);
            throw new \RuntimeException($this->lastError);
        }

        $content = $decoded['choices'][0]['message']['content'] ?? '';
        if ($content === '') {
            $this->lastError = 'api: empty completion';
            bot_log('AI empty completion: ' . substr((string) $raw, 0, 300));
        }

        return (string) $content;
    }

    /**
     * Localised "service unavailable" answer used when the model cannot be called.
     */
    private function getFallbackReply($lang)
    {
        $replies = [
            'en' => 'Thank you for your message. Our team will get back to you shortly.',
            'tr' => 'Mesajınız için teşekkürler. Ekibimiz kısa süre içinde size dönüş yapacak.',
            'de' => 'Danke für Ihre Nachricht. Unser Team meldet sich in Kürze bei Ihnen.',
            'fr' => 'Merci pour votre message. Notre équipe vous répondra sous peu.',
            'es' => 'Gracias por su mensaje. Nuestro equipo le responderá en breve.',
            'it' => 'Grazie per il messaggio. Il nostro team la ricontatterà a breve.',
            'nl' => 'Bedankt voor uw bericht. Ons team neemt binnenkort contact met u op.',
            'pl' => 'Dziękujemy za wiadomość. Nasz zespół wkrótce się z Tobą skontaktuje.',
            'pt' => 'Obrigado pela sua mensagem. A nossa equipa entrará em contacto em breve.',
            'ro' => 'Vă mulțumim pentru mesaj. Echipa noastră vă va contacta în curând.',
            'ru' => 'Спасибо за сообщение. Наша команда свяжется с вами в ближайшее время.',
            'ar' => 'شكراً لرسالتكم. سيتواصل معكم فريقنا قريباً.',
            'fa' => 'از پیام شما سپاسگزاریم. تیم ما به زودی با شما تماس خواهد گرفت.',
        ];
        return $replies[$lang] ?? $replies['en'];
    }
}
