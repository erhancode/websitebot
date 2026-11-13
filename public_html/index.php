<?php
/**
 * Landing page: shows the widget status and links to the demos.
 * Delete this file on a production site.
 */
require_once __DIR__ . '/../core/bot/bootstrap.php';

$brand   = (string) bot_config('brand.name', 'Your Company');
$apiBase = bot_api_base();
$hasKey  = (string) bot_config('ai.api_key', '') !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> - website bot</title>
<style>
  body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f4f7f8; color: #16303a; }
  main { max-width: 720px; margin: 0 auto; padding: 64px 24px 140px; }
  h1 { font-size: 28px; margin: 0 0 8px; }
  .lead { color: #4a6470; line-height: 1.65; }
  .card { background: #fff; border: 1px solid #dbe5ea; border-radius: 12px; padding: 20px 22px; margin-top: 24px; }
  .card h2 { font-size: 15px; margin: 0 0 10px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
  ul { margin: 0; padding-left: 18px; line-height: 1.9; }
  a { color: #00695c; }
  code { background: #e6eef1; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
  .status { font-weight: 700; }
  .ok { color: #147d3f; }
  .warn { color: #b45309; }
</style>
</head>
<body>
<main>
  <h1><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> - website bot</h1>
  <p class="lead">A self-contained support assistant for any website: a floating chat widget, a small PHP
  backend and lead capture. Nothing to install beyond PHP 8 and an OpenAI-compatible API key.</p>

  <div class="card">
    <h2>Status</h2>
    <p>AI key: <span class="status <?= $hasKey ? 'ok' : 'warn' ?>"><?= $hasKey ? 'configured' : 'missing' ?></span><?php if (!$hasKey): ?>
      - add <code>AI_API_KEY</code> to <code>.env</code> to get real answers.<?php endif; ?></p>
    <ul>
      <li><a href="<?= htmlspecialchars($apiBase . 'health.php', ENT_QUOTES, 'UTF-8') ?>">health check (JSON)</a></li>
      <li><a href="demo.php">PHP integration demo</a></li>
      <li><a href="demo.html">static HTML demo</a></li>
    </ul>
  </div>
</main>
<?php include __DIR__ . '/../embed/bot-embed.php'; ?>
</body>
</html>
