# websitebot

**A drop-in AI support assistant for any website.** A floating chat widget, a small
dependency-free PHP backend, 28 UI languages and lead capture — one API key and it works.

![The widget answering a visitor](docs/screenshot.jpg)

## Why this one

- **No build step, no framework, no Composer.** Plain PHP 8 and vanilla JavaScript.
- **Two ways to install:** one `include` line in PHP, or three HTML tags on any site
  (static HTML, WordPress, Shopify, a landing page built with anything).
- **Any OpenAI-compatible API.** Groq, OpenAI, OpenRouter, Together, DeepSeek, or a local
  vLLM / Ollama / LM Studio gateway. Only `AI_BASE_URL` and `AI_MODEL` change.
- **The key never reaches the browser.** All model calls happen server-side.
- **Lead capture built in.** Contact details are detected, stored as JSON, and optionally
  mailed to you as a lead card.
- **28 languages with RTL support.** The widget follows the page language and the model
  answers in the language the visitor writes in.

## Quick start

```bash
git clone https://github.com/erhancode/websitebot.git
cd websitebot
cp .env.example .env          # set AI_API_KEY, optionally AI_MODEL
php -S 127.0.0.1:8123 -t public_html
```

Open <http://127.0.0.1:8123>: the landing page shows the widget and whether it is
configured. `GET /api/bot/health.php` reports the setup state as JSON and never exposes
a secret.

Two demos are included: `demo.php` (PHP include, `?lang=tr` switches language) and
`demo.html` (static snippet, no PHP).

## Requirements

- PHP 8.0+ (`json` is bundled). `curl` **or** `openssl` is needed for real HTTPS AI calls;
  virtually every host ships both.
- A writable `data/` directory.
- Any OpenAI-compatible `/chat/completions` endpoint.

## Project layout

`public_html/` is the document root. Everything else must sit **next to it**, not inside it:

```
websitebot/
├── public_html/            document root
│   ├── index.php           landing page with setup status (demo only)
│   ├── demo.php            PHP include demo
│   ├── demo.html           static HTML demo
│   ├── assets/             widget JS, CSS, avatar
│   └── api/bot/            chat.php, health.php, lead.php
├── core/                   ChatHandler, lead worker, mailer, bootstrap
├── config/                 bot_config.php, env_loader.php
├── embed/                  bot-embed.php (one-line PHP include)
├── tests/                  mock AI endpoint, smoke test, proxy IP check
├── data/                   leads, rate-limit locks, logs (kept out of the web root)
└── .env.example
```

## Install it on your site

### PHP

Before `</body>`:

```php
<?php $botLang = $lang ?? 'en'; include '/path/to/websitebot/embed/bot-embed.php'; ?>
```

`bot-embed.php` prints the config, the CSS link and the script tag, with avatars and API
URLs resolved from your configuration.

### Any other site

```html
<script>
  window.BOT_WIDGET_CONFIG = {
    brandName: "Your Company",
    assistantName: "Assistant",
    apiChat: "https://your-server.com/api/bot/chat.php",
    lang: document.documentElement.lang || "en"
  };
</script>
<link rel="stylesheet" href="https://your-server.com/assets/css/bot-widget.css">
<script src="https://your-server.com/assets/js/bot-widget.js" defer></script>
```

Widget on a different origin than the API? Set `BOT_CORS_ORIGIN=https://yoursite.com`.
Installed in a sub-directory? Set `BOT_ASSET_BASE` and `BOT_API_BASE`.

## Configuration

Everything is read from `.env` (see `.env.example`) and can be overridden in
`config/bot_config.php`. `AI_API_KEY` is the only value without a usable default; the rest
fall back to neutral placeholders (`Your Company`, `Assistant`).

| Group | Keys |
| --- | --- |
| AI | `AI_API_KEY`, `AI_BASE_URL`, `AI_MODEL`, `AI_MAX_TOKENS`, `AI_TEMPERATURE`, `AI_TIMEOUT`, `AI_SSL_VERIFY`, `BOT_EXTRACT_LEADS` |
| Branding | `BOT_BRAND_NAME`, `BOT_ASSISTANT_NAME`, `BOT_ASSISTANT_ROLE`, `BOT_SUBTITLE`, `BOT_WEBSITE`, `BOT_AVATAR`, `BOT_COLOR_PRIMARY`, `BOT_COLOR_ACCENT` |
| Widget | `BOT_DEFAULT_LANG`, `BOT_POSITION`, `BOT_AUTO_OPEN`, `BOT_OPEN_DELAY`, `BOT_SCROLL_THRESHOLD`, `BOT_MAX_HISTORY`, `BOT_MAX_INPUT`, `BOT_SHOW_LAUNCHER`, `BOT_ASSET_BASE`, `BOT_API_BASE`, `BOT_CORS_ORIGIN` |
| Leads | `BOT_STORE_LEADS`, `LEAD_NOTIFY_EMAIL`, `BOT_INTERNAL_KEY`, `BOT_RATE_LIMIT`, `BOT_RATE_WINDOW`, `BOT_TRUST_PROXY`, `BOT_LEAD_DEDUPE`, `BOT_KEEP_LEADS` |
| Mail | `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `FROM_EMAIL`, `FROM_NAME`, `SMTP_SSL_VERIFY` |
| Prompt | `BOT_EXTRA_PROMPT` (appends rules), `BOT_SYSTEM_PROMPT` (replaces the generated prompt) |

Widget-only options without an environment variable — `hide_selectors` (elements hidden while
the chat is open) and the language list — are set in `config/bot_config.php`.

The generated system prompt already forbids invented facts, prices, deadlines and
guarantees, and tells the model to collect name plus one contact channel.

### UI strings

All widget copy lives in `public_html/assets/js/bot-widget.js` → `var I18N = { ... }`.
Every string supports `{brand}` and `{assistant}` placeholders. Override one language for a
single page without touching the file:

```js
window.BOT_WIDGET_CONFIG = {
  strings: { en: { welcome: "Hi! How can we help?", close: "Close", send: "Send" } }
};
```

Shipped languages: `tr en ru ky az ar fa de fr es it uz kk ro bg sq sr nl pl cs da el fi
fr-ma hu no pt sv`. Remove unused ones from `languages` in `config/bot_config.php` — a
shorter list means a smaller payload for the browser.

## Endpoints

| Endpoint | Method | Purpose |
| --- | --- | --- |
| `/api/bot/chat.php` | `POST` | `{message, lang, url, title, history}` → `{reply, is_final, lead_details}` |
| `/api/bot/chat.php?action=get_lang` | `GET` | language hint from the browser |
| `/api/bot/health.php` | `GET` | setup status, no secrets |
| `/api/bot/lead.php` | `POST` | server-to-server lead push, requires `X-Internal-Key` |

`is_final` turns true once the visitor left a phone number or e-mail address; the widget
then shows the closing message instead of asking again.

## Leads

- Every turn is appended to `data/bot_leads.json` (`BOT_STORE_LEADS=false` turns this off).
- A lead card e-mail goes to `LEAD_NOTIFY_EMAIL` when contact details appear, at most once
  per visitor per `BOT_LEAD_DEDUPE` seconds (default 600).
- E-mail is **off by default**: leave `LEAD_NOTIFY_EMAIL` empty and leads are still stored.
- The log keeps the newest `BOT_KEEP_LEADS` records (default 2000).
- `data/` must be writable and must stay outside the document root.

## Tests

```bash
# terminal 1 — fake OpenAI-compatible endpoint
php -S 127.0.0.1:8124 tests/mock_ai.php

# terminal 2 — the app, pointed at the fake endpoint
AI_BASE_URL=http://127.0.0.1:8124/v1 AI_API_KEY=test php -S 127.0.0.1:8123 -t public_html

# terminal 3
php tests/smoke.php http://127.0.0.1:8123   # 7 end-to-end HTTP checks
php tests/client_ip.php                      # proxy header handling
```

On PowerShell the second line is:

```powershell
$env:AI_BASE_URL='http://127.0.0.1:8124/v1'; $env:AI_API_KEY='test'; php -S 127.0.0.1:8123 -t public_html
```

## Security

- The API key stays server-side; the browser only talks to `chat.php`.
- Rate limit per IP, default 20 requests / 60 s, stored in `data/locks/`.
- Behind Cloudflare or nginx, set `BOT_TRUST_PROXY=true` so the limiter sees the visitor IP
  instead of the proxy address. Leave it off when the app is reached directly.
- `.env` is git-ignored; no credentials are shipped in this repository.
- `lead.php` refuses every request until `BOT_INTERNAL_KEY` is set.
- `AI_SSL_VERIFY=true` by default — disable only for a self-signed internal endpoint.

### Hosting notes

- Cloudflare cannot run this backend: Pages and Workers serve static files and JavaScript,
  not PHP. Cloudflare in front of a PHP host works fine (Full SSL, cache `/assets/*`,
  bypass cache for `/api/bot/*`, plus an edge rate-limit rule if you want one).
- The lead store is a JSON file, so the app expects a normal single-server PHP host and a
  writable `data/`. Horizontal scaling would need a shared store.

## License

MIT — see [LICENSE](LICENSE).
