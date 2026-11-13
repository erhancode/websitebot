<?php
/**
 * Drop-in include for any PHP page.
 *
 *   <?php $botLang = 'de'; include '/path/to/embed/bot-embed.php'; ?>
 *
 * Prints the widget configuration, the stylesheet and the script. Include it
 * once per page, right before </body>. All values come from config/bot_config.php
 * (.env overrides), so nothing here needs editing per site.
 */

require_once __DIR__ . '/../core/bot/bootstrap.php';

$botLang = isset($botLang) && is_string($botLang) && $botLang !== ''
    ? preg_replace('/[^a-zA-Z\-]/', '', $botLang)
    : (string) bot_config('widget.default_lang', 'en');

$botWidgetConfig = [
    'brandName'       => (string) bot_config('brand.name', 'Your Company'),
    'assistantName'   => (string) bot_config('brand.assistant', 'Ava'),
    'subtitle'        => (string) bot_config('brand.subtitle', 'AI Assistant'),
    'avatar'          => bot_avatar_url(),
    'primaryColor'    => (string) bot_config('brand.color', '#004d40'),
    'accentColor'     => (string) bot_config('brand.color_accent', '#c6a15b'),
    'apiChat'         => bot_api_base() . 'chat.php',
    'lang'            => $botLang,
    'position'        => (string) bot_config('widget.position', 'left'),
    'autoOpen'        => (bool) bot_config('widget.auto_open', false),
    'openDelay'       => (int) bot_config('widget.open_delay_ms', 25000),
    'scrollThreshold' => (float) bot_config('widget.scroll_threshold', 0.5),
    'maxHistory'      => (int) bot_config('widget.max_history', 6),
    'maxInput'        => (int) bot_config('widget.max_input', 1000),
    'showLauncher'    => (bool) bot_config('widget.show_launcher', true),
    'hideSelectors'   => (array) bot_config('widget.hide_selectors', []),
    'bannerClass'     => (string) bot_config('widget.banner_class', 'cc-active'),
];

$botAsset   = bot_asset_base();
$botCssUrl  = $botAsset . 'css/bot-widget.css';
$botJsUrl   = $botAsset . 'js/bot-widget.js';
?>
<script>window.BOT_WIDGET_CONFIG=<?= json_encode($botWidgetConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<link rel="stylesheet" href="<?= htmlspecialchars($botCssUrl, ENT_QUOTES, 'UTF-8') ?>" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="<?= htmlspecialchars($botCssUrl, ENT_QUOTES, 'UTF-8') ?>"></noscript>
<script src="<?= htmlspecialchars($botJsUrl, ENT_QUOTES, 'UTF-8') ?>" defer></script>
