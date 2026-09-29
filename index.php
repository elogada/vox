<?php
declare(strict_types=1);

// Place this file in your PHP-served directory. The OSSEC log stays outside the web root.
const ALERTS_FILE = '/var/ossec/logs/alerts/alerts.json';
const MAX_CARDS = 50;

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

function escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (isset($_GET['download'])) {
    if (!is_file(ALERTS_FILE) || !is_readable(ALERTS_FILE)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Alerts file is unavailable.');
    }
    header('Content-Type: application/x-ndjson');
    header('Content-Disposition: attachment; filename="alerts.json"');
    $stream = fopen(ALERTS_FILE, 'rb');
    if ($stream === false) {
        http_response_code(500);
        exit('Could not open alerts file.');
    }
    fpassthru($stream);
    fclose($stream);
    exit;
}

$cards = [];
$invalidLines = 0;
$error = null;
$stream = @fopen(ALERTS_FILE, 'rb');
if ($stream === false) {
    $error = 'Cannot read ' . ALERTS_FILE . '. Check that OSSEC JSON output is enabled and the PHP/Apache account can read the file.';
} else {
    // Stream once per page request. Keep only the latest 50 valid JSON lines in memory.
    while (($line = fgets($stream)) !== false) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            if (trim($line) !== '') {
                $invalidLines++;
            }
            continue;
        }
        $cards[] = $decoded;
        if (count($cards) > MAX_CARDS) {
            array_shift($cards);
        }
    }
    if (!feof($stream)) {
        $error = 'The alert file could not be read completely. The entries shown may be incomplete.';
    }
    fclose($stream);
    $cards = array_reverse($cards); // Most recent first.
}

$payload = json_encode($cards, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
if ($payload === false) {
    $payload = '[]';
    $error = 'Could not prepare the alert details.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vox Visibility Frontend</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style>
    body { background: radial-gradient(ellipse at 15% 0%, #122d4c 0%, #091525 45%, #070e1c 100%); }
    .alert-card { opacity: 0; transform: translateY(12px); animation: enter .38s ease-out forwards; animation-delay: min(calc(var(--i) * 45ms), 1800ms); }
    @keyframes enter { to { opacity: 1; transform: translateY(0); } }
    @media (prefers-reduced-motion: reduce) { .alert-card { opacity: 1; transform: none; animation: none; } }
  </style>
</head>
<body class="min-h-screen text-slate-100 antialiased">
  <main class="mx-auto max-w-7xl px-5 py-10 sm:px-8">
    <header class="mb-9 border-b border-sky-300/15 pb-8">
      <div class="mb-3 flex items-center gap-3 text-xs font-semibold uppercase tracking-[.3em] text-cyan-300"><span class="h-2.5 w-2.5 rounded-full bg-cyan-400 shadow-[0_0_18px_#22d3ee]"></span>OSSEC alert viewer</div>
      <h1 class="text-3xl font-bold tracking-tight text-white sm:text-5xl">Vox <span class="text-sky-300">Visibility</span> Frontend</h1>
      <p class="mt-3 max-w-2xl text-sm text-slate-400">Latest alerts captured when this page loaded. Refresh to read the file again.</p>
      <div class="mt-6 flex flex-wrap items-center gap-4">
        <a href="?download=1" class="inline-flex items-center rounded-xl border border-sky-300/30 bg-sky-500/15 px-4 py-2.5 text-sm font-semibold text-sky-100 transition hover:bg-sky-500/25 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300">↓ &nbsp; Download alerts.json</a>
        <span class="text-xs text-slate-400"><?= count($cards) ?> shown · newest first · max <?= MAX_CARDS ?></span>
      </div>
    </header>

    <?php if ($error !== null): ?>
      <div class="mb-6 rounded-xl border border-amber-400/30 bg-amber-400/10 p-4 text-sm text-amber-100" role="alert"><?= escape($error) ?></div>
    <?php endif; ?>
    <?php if ($invalidLines > 0): ?>
      <div class="mb-6 rounded-xl border border-amber-400/20 bg-amber-400/5 p-3 text-sm text-amber-200" role="status"><?= $invalidLines ?> invalid or incomplete JSON line<?= $invalidLines === 1 ? '' : 's' ?> skipped.</div>
    <?php endif; ?>
    <?php if (!$cards && $error === null): ?>
      <div class="rounded-2xl border border-sky-300/15 bg-slate-900/50 p-10 text-center text-slate-400">No alerts yet. Refresh after OSSEC writes a JSON alert.</div>
    <?php endif; ?>

    <section class="grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" aria-label="Recent alerts">
      <?php foreach ($cards as $index => $alert):
          $rule = isset($alert['rule']) && is_array($alert['rule']) ? $alert['rule'] : [];
          $level = $rule['level'] ?? '?';
          $comment = $rule['comment'] ?? 'Untitled alert';
          $stamp = $alert['timestamp'] ?? (isset($alert['TimeStamp']) && is_numeric($alert['TimeStamp']) ? gmdate('Y-m-d H:i:s \U\T\C', (int) floor(((float) $alert['TimeStamp']) / 1000)) : 'Unknown time');
          $host = $alert['hostname'] ?? $alert['agent_name'] ?? 'Unknown host';
          $levelNumber = is_numeric($level) ? (int) $level : 0;
          $badge = $levelNumber >= 10 ? 'bg-rose-400/15 text-rose-200 border-rose-400/25' : ($levelNumber >= 7 ? 'bg-amber-400/15 text-amber-200 border-amber-400/25' : 'bg-sky-400/15 text-sky-200 border-sky-400/25');
      ?>
        <button type="button" data-index="<?= $index ?>" style="--i:<?= $index ?>" class="alert-card group flex min-h-28 w-full items-center gap-3 rounded-xl border border-sky-300/15 bg-slate-900/75 p-3 text-left shadow-lg shadow-black/15 transition-colors hover:border-cyan-300/55 hover:bg-slate-800/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300" aria-label="View raw JSON for <?= escape($comment) ?>">
          <span class="inline-flex shrink-0 rounded-lg border px-2 py-1 text-[11px] font-bold tracking-wide <?= $badge ?>">L<?= escape($level) ?></span>
          <span class="block min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold leading-snug text-white group-hover:text-cyan-100" title="<?= escape($comment) ?>"><?= escape($comment) ?></span>
            <span class="mt-2 grid min-w-0 grid-cols-1 gap-x-3 gap-y-1 text-[11px] leading-tight text-slate-400 sm:grid-cols-2">
              <span class="block min-w-0 truncate" title="<?= escape($stamp) ?>">◷ &nbsp;<?= escape($stamp) ?></span>
              <span class="block min-w-0 truncate" title="<?= escape($host) ?>">▣ &nbsp;<?= escape($host) ?></span>
            </span>
          </span>
        </button>
      <?php endforeach; ?>
    </section>
  </main>

  <div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="modal-title" tabindex="-1">
    <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-cyan-300/25 bg-[#0d1b2d] shadow-2xl shadow-black/50">
      <div class="flex items-center justify-between border-b border-sky-300/15 px-5 py-4"><h2 id="modal-title" class="font-semibold text-cyan-100">Raw alert JSON</h2><span class="text-xs text-slate-400">Click anywhere or press Esc to close</span></div>
      <pre id="modal-json" class="max-h-[70vh] overflow-auto p-5 text-xs leading-relaxed text-sky-100 sm:text-sm"></pre>
    </div>
  </div>
  <script id="alert-data" type="application/json"><?= $payload ?></script>
  <script>
    const alerts = JSON.parse(document.getElementById('alert-data').textContent);
    const modal = document.getElementById('modal');
    const jsonView = document.getElementById('modal-json');
    let previousFocus = null;
    document.querySelectorAll('[data-index]').forEach(card => {
      card.addEventListener('click', () => {
        previousFocus = card;
        jsonView.textContent = JSON.stringify(alerts[Number(card.dataset.index)], null, 2);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.focus();
      });
    });
    function closeModal() {
      if (modal.classList.contains('hidden')) return;
      modal.classList.remove('flex');
      modal.classList.add('hidden');
      jsonView.textContent = '';
      previousFocus?.focus();
    }
    modal.addEventListener('click', closeModal);
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(); });
  </script>
</body>
</html>
