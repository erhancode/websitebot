<?php
/**
 * Integration demo. Proves the widget runs inside a normal PHP page.
 * Open /demo.php on the local server.
 */
require_once __DIR__ . '/../core/bot/bootstrap.php';
$botLang = isset($_GET['lang']) ? preg_replace('/[^a-zA-Z\-]/', '', (string) $_GET['lang']) : null;
$brand   = bot_config('brand.name', 'Your Company');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($botLang ?: bot_config('widget.default_lang', 'en'), ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> - integration demo</title>
<style>
  :root { color-scheme: light; }
  body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f4f7f8; color: #16303a; }
  main { max-width: 760px; margin: 0 auto; padding: 56px 24px 120px; }
  h1 { font-size: 26px; margin: 0 0 10px; }
  p  { line-height: 1.65; color: #4a6470; }
  code { background: #e6eef1; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
  .card { background: #fff; border: 1px solid #dbe5ea; border-radius: 12px; padding: 20px 22px; margin-top: 24px; }
  .row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
  .row a { text-decoration: none; padding: 7px 12px; border: 1px solid #cfdde3; border-radius: 999px; font-size: 13px; color: #16303a; background: #fff; }
  .row a:hover { border-color: #004d40; }
</style>
</head>
<body>
<main>
  <h1><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> - assistant demo</h1>
  <p>This page is a plain PHP template with the assistant included at the bottom. Everything the widget needs is
  printed by <code>embed/bot-embed.php</code>; the answers come from <code>public_html/api/bot/chat.php</code>.</p>

  <div class="card">
    <p><strong>Check the backend:</strong> <a href="api/bot/health.php">api/bot/health.php</a> reports whether the AI key is set,
    whether the data directory is writable and whether e-mail notifications are on.</p>
    <p><strong>Preview a language:</strong> the widget picks the first URL segment, the browser language or the
    <code>?lang=</code> parameter.</p>
    <div class="row">
      <?php foreach (['en', 'tr', 'de', 'fr', 'ru', 'ar', 'es'] as $code): ?>
        <a href="demo.php?lang=<?= $code ?>"><?= strtoupper($code) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../embed/bot-embed.php'; ?>
</body>
</html>
