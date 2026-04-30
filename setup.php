<?php
session_start();

require_once __DIR__ . '/biblioteca/setup-state.php';
require_once __DIR__ . '/biblioteca/template-bootstrap.php';

define('TERCES_FILE',         __DIR__ . '/data/terces');
define('SETUP_COMPLETE_FILE', __DIR__ . '/data/.setup_complete');
define('CONFIG_FILE',         __DIR__ . '/web-config.json');

header('Content-Type: text/html; charset=utf-8');

// Already fully set up — go to admin
if (bandpromo_is_setup_complete()) {
    header('Location: /admin.php');
    exit;
}

// ─── Auto-create required directories ────────────────────────────────────────
$requiredDirs = [
    'data',
    'log',
    'play',
    'media',
    'media/audio',
    'media/audio/original',
    'media/audio/optimal',
    'media/img',
    'media/img/original',
    'media/img/optimal',
    'media/photo',
    'media/video',
    'media/special',
];
$setupErrors = [];
// Dirs under media/ must be world-readable (0755) so the HTTP server can serve
// static files regardless of whether it runs as the same user as PHP.
// Sensitive dirs (data/, log/) keep 0750 — they are never served directly.
$sensitiveDirs = ['data', 'log'];
foreach ($requiredDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    $mode = in_array(explode('/', $dir)[0], $sensitiveDirs) ? 0750 : 0755;
    if (!is_dir($path)) {
        if (!mkdir($path, $mode, true)) {
            $setupErrors[] = "Could not create directory: $dir";
        }
    } else {
        // Fix permissions on existing directories (e.g., previously created with 0750).
        chmod($path, $mode);
    }
}

// Seed highscores file if missing (safe to do early, no sensitive data)
$highscoresFile = __DIR__ . '/data/highscores.json';
if (!file_exists($highscoresFile)) {
    file_put_contents($highscoresFile, '[]');
}

// Seed required runtime files from tracked templates.
// Missing or invalid templates are setup errors by design.
$setupErrors = array_merge($setupErrors, bandpromo_ensure_runtime_files_seeded());
// ─────────────────────────────────────────────────────────────────────────────

$hasSetupErrors = !empty($setupErrors);

// Read current config defaults for pre-filling fields
$config = [];
if (file_exists(CONFIG_FILE)) {
    $config = json_decode(file_get_contents(CONFIG_FILE), true) ?? [];
}

// Auto-derive site info from hostname if not already set
$host = $_SERVER['HTTP_HOST'] ?? '';
$hostNoPort = strtolower(preg_replace('/:\d+$/', '', $host));
// e.g. "myband.com" → "Myband"
function hostnameToTitle($host) {
    $base = preg_replace('/\.[^.]+$/', '', $host); // strip TLD
    $base = preg_replace('/[^a-z0-9]+/i', ' ', $base);
    return ucwords(trim($base));
}
$derivedName = hostnameToTitle($hostNoPort);
$derivedUrl  = 'https://' . $hostNoPort;

$siteName        = htmlspecialchars($config['site']['name']        ?? $derivedName);
$siteShortName   = htmlspecialchars($config['site']['short_name']  ?? $derivedName);
$siteDescription = htmlspecialchars($config['site']['description'] ?? '');
$siteUrl         = htmlspecialchars($config['site']['url']         ?? $derivedUrl);
$siteAuthor      = htmlspecialchars($config['site']['author']      ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Site Setup</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:      #0a0a0a;
      --surface: #141414;
      --border:  #2a2a2a;
      --text:    #e8e8e8;
      --muted:   #888;
      --accent:  #FF6B6B;
      --success: #4CAF50;
      --error:   #f44336;
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .wizard {
      width: 100%;
      max-width: 560px;
    }

    /* --- Step indicator --- */
    .steps {
      display: flex;
      align-items: center;
      margin-bottom: 36px;
      gap: 0;
    }
    .step-dot {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      border: 2px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 600;
      color: var(--muted);
      background: var(--surface);
      flex-shrink: 0;
      transition: all .3s;
    }
    .step-dot.active  { border-color: var(--accent); color: var(--accent); }
    .step-dot.done    { border-color: var(--success); background: var(--success); color: #fff; }
    .step-line {
      flex: 1;
      height: 2px;
      background: var(--border);
      transition: background .3s;
    }
    .step-line.done { background: var(--success); }

    /* --- Panel --- */
    .panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 36px;
      display: none;
    }
    .panel.active { display: block; }

    h1 { font-size: 22px; font-weight: 600; margin-bottom: 6px; }
    .subtitle { color: var(--muted); font-size: 14px; margin-bottom: 28px; }

    /* --- Form fields --- */
    .field { margin-bottom: 18px; }
    label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 6px; }
    input[type="text"],
    input[type="password"],
    input[type="url"],
    textarea {
      width: 100%;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 6px;
      color: var(--text);
      font-size: 14px;
      padding: 10px 12px;
      outline: none;
      transition: border-color .2s;
      font-family: inherit;
    }
    input:focus, textarea:focus { border-color: var(--accent); }
    textarea { resize: vertical; min-height: 70px; }

    .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

    .color-row {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .color-row input[type="color"] {
      width: 40px; height: 38px;
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 2px 4px;
      background: var(--bg);
      cursor: pointer;
      flex-shrink: 0;
    }
    .color-row input[type="text"] { flex: 1; }

    /* --- Upload zone --- */
    .drop-zone {
      border: 2px dashed var(--border);
      border-radius: 8px;
      padding: 28px;
      text-align: center;
      cursor: pointer;
      transition: border-color .2s, background .2s;
      margin-bottom: 14px;
    }
    .drop-zone:hover, .drop-zone.dragover {
      border-color: var(--accent);
      background: rgba(255,107,107,.04);
    }
    .drop-zone input[type="file"] { display: none; }
    .drop-label { font-size: 14px; color: var(--muted); }
    .drop-label strong { color: var(--text); }

    .file-list { font-size: 13px; color: var(--muted); margin-top: 8px; }
    .file-list .file-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 5px 0; border-bottom: 1px solid var(--border);
    }
    .file-item .fname { color: var(--text); }
    .file-item .ftype { color: var(--accent); font-size: 11px; text-transform: uppercase; }
    .file-item .fremove {
      cursor: pointer; color: var(--muted); font-size: 16px; line-height: 1;
      background: none; border: none; color: var(--error); padding: 0 4px;
    }

    /* --- Build log --- */
    #build-log {
      background: #000;
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 14px;
      font-family: 'Courier New', monospace;
      font-size: 12px;
      line-height: 1.5;
      color: #ccc;
      height: 260px;
      overflow-y: auto;
      white-space: pre-wrap;
      word-break: break-all;
    }
    #build-log .log-success { color: var(--success); }
    #build-log .log-error   { color: var(--error); }
    #build-log .log-info    { color: #60a5fa; }

    /* --- Buttons --- */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 11px 22px;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      border: none;
      transition: opacity .2s, transform .1s;
    }
    .btn:active { transform: scale(.98); }
    .btn:disabled { opacity: .5; cursor: not-allowed; }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-primary:hover:not(:disabled) { opacity: .9; }
    .btn-ghost {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--muted);
    }
    .btn-ghost:hover { border-color: var(--accent); color: var(--text); }

    .actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 28px;
      gap: 12px;
    }

    /* --- Messages --- */
    .msg {
      padding: 10px 14px;
      border-radius: 6px;
      font-size: 13px;
      margin-bottom: 16px;
      display: none;
    }
    .msg.error   { background: rgba(244,67,54,.1);  border: 1px solid rgba(244,67,54,.3);  color: var(--error); }
    .msg.success { background: rgba(76,175,80,.1);  border: 1px solid rgba(76,175,80,.3);  color: var(--success); }

    /* --- Done screen --- */
    .done-icon {
      font-size: 52px;
      text-align: center;
      margin-bottom: 16px;
    }
    .done-links {
      display: flex;
      gap: 12px;
      margin-top: 28px;
    }
    .done-links a {
      flex: 1;
      text-align: center;
      padding: 11px;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 500;
      text-decoration: none;
      transition: opacity .2s;
    }
    .done-links a:hover { opacity: .85; }
    .link-play  { background: var(--accent); color: #fff; }
    .link-admin { background: var(--surface); border: 1px solid var(--border); color: var(--text); }

    /* --- Upload progress --- */
    .upload-progress {
      margin-top: 10px;
      font-size: 13px;
      color: var(--muted);
      display: none;
    }
    .progress-bar-wrap {
      height: 4px;
      background: var(--border);
      border-radius: 2px;
      margin-top: 6px;
      overflow: hidden;
    }
    .progress-bar-fill {
      height: 100%;
      background: var(--accent);
      width: 0%;
      transition: width .3s;
    }

    /* spinner */
    @keyframes spin { to { transform: rotate(360deg); } }
    .spinner {
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .6s linear infinite;
      display: none;
    }
  </style>
</head>
<body>
<div class="wizard">

<?php if (!empty($setupErrors)): ?>
  <div style="background:rgba(244,67,54,.1);border:1px solid rgba(244,67,54,.3);color:#f44336;
              border-radius:8px;padding:20px 24px;margin-bottom:24px;font-size:14px;">
    <strong>Setup could not prepare the server environment.</strong><br>
    This is usually a file permissions issue. Contact your hosting provider and ask them to make
    the site folder writable by the web server.<br><br>
    <code style="font-size:12px;"><?= implode('<br>', array_map('htmlspecialchars', $setupErrors)) ?></code>
  </div>
<?php endif; ?>

  <!-- Step indicator -->
  <div class="steps" id="step-indicator">
    <div class="step-dot active" id="dot-1">1</div>
    <div class="step-line"            id="line-1"></div>
    <div class="step-dot"             id="dot-2">2</div>
    <div class="step-line"            id="line-2"></div>
    <div class="step-dot"             id="dot-3">3</div>
  </div>

  <!-- STEP 1: Admin account -->
  <div class="panel active" id="panel-1">
    <h1>Create admin account</h1>
    <p class="subtitle">This will be your login for the admin panel.</p>
    <div class="msg error" id="s1-error"></div>

    <div class="field">
      <label>Username</label>
      <input type="text" id="s1-username" autocomplete="username" placeholder="admin" />
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" id="s1-password" autocomplete="new-password" placeholder="••••••••" />
    </div>
    <div class="field">
      <label>Confirm password</label>
      <input type="password" id="s1-password2" autocomplete="new-password" placeholder="••••••••" />
    </div>

    <div class="actions">
      <span></span>
      <button class="btn btn-primary" id="s1-next" <?php echo $hasSetupErrors ? 'disabled' : ''; ?>>Next <div class="spinner" id="s1-spin"></div></button>
    </div>
  </div>

  <!-- STEP 2: Site config -->
  <div class="panel" id="panel-2">
    <h1>Site information</h1>
    <p class="subtitle">Confirm your site details — pre-filled from your domain.</p>
    <div class="msg error" id="s2-error"></div>

    <div class="row2">
      <div class="field">
        <label>Site name</label>
        <input type="text" id="s2-name" value="<?= $siteName ?>" placeholder="My Music Site" />
      </div>
      <div class="field">
        <label>Author / band name</label>
        <input type="text" id="s2-author" value="<?= $siteAuthor ?>" placeholder="Your name" />
      </div>
    </div>
    <div class="field">
      <label>Description</label>
      <textarea id="s2-desc" placeholder="A short description of your site…"><?= $siteDescription ?></textarea>
    </div>
    <div class="field">
      <label>Site URL</label>
      <input type="url" id="s2-url" value="<?= $siteUrl ?>" placeholder="https://example.com" />
    </div>

    <div class="actions">
      <button class="btn btn-ghost" id="s2-back">Back</button>
      <button class="btn btn-primary" id="s2-next" <?php echo $hasSetupErrors ? 'disabled' : ''; ?>>Next <div class="spinner" id="s2-spin"></div></button>
    </div>
  </div>

  <!-- STEP 3: Build -->
  <div class="panel" id="panel-3">
    <h1>Build site</h1>
    <p class="subtitle">Generate config and optimize media files.</p>
    <div class="msg error" id="s3-error"></div>

    <div id="build-log" style="margin-bottom:14px;"></div>

    <div class="actions">
      <button class="btn btn-ghost" id="s3-back">Back</button>
      <div style="display:flex;gap:10px;align-items:center;">
        <span id="build-status" style="font-size:13px;color:var(--muted);"></span>
        <button class="btn btn-primary" id="s3-build" <?php echo $hasSetupErrors ? 'disabled' : ''; ?>>Start build <div class="spinner" id="s3-spin"></div></button>
        <button class="btn btn-ghost"   id="s3-next" style="display:none;">Finish</button>
      </div>
    </div>
  </div>

  <!-- DONE -->
  <div class="panel" id="panel-done">
    <div class="done-icon">🎉</div>
    <h1 style="text-align:center;">Setup complete!</h1>
    <p class="subtitle" style="text-align:center;margin-top:8px;">
      Your site is ready. You can now manage tracks and settings from the admin panel.
    </p>
    <div class="done-links">
      <a href="/play/" class="link-play">Open player</a>
      <a href="/admin.php" class="link-admin">Admin panel</a>
    </div>
  </div>

</div><!-- /.wizard -->

<script>
const STEPS = 3;
let currentStep = 1;

// ─── Step indicator ─────────────────────────────────────────────────────────
function setStepUI(step) {
  for (let i = 1; i <= STEPS; i++) {
    const dot = document.getElementById('dot-' + i);
    dot.className = 'step-dot';
    if (i < step)  dot.classList.add('done');
    if (i === step) dot.classList.add('active');
    if (i < STEPS) {
      const line = document.getElementById('line-' + i);
      line.className = 'step-line' + (i < step ? ' done' : '');
    }
  }
  for (let i = 1; i <= STEPS; i++) {
    document.getElementById('panel-' + i).classList.toggle('active', i === step);
  }
  document.getElementById('panel-done').classList.remove('active');
  currentStep = step;
}
function showDone() {
  for (let i = 1; i <= STEPS; i++) {
    const dot = document.getElementById('dot-' + i);
    dot.className = 'step-dot done';
    if (i < STEPS) document.getElementById('line-' + i).className = 'step-line done';
    document.getElementById('panel-' + i).classList.remove('active');
  }
  document.getElementById('panel-done').classList.add('active');
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function showMsg(id, text, type) {
  const el = document.getElementById(id);
  el.textContent = text;
  el.className = 'msg ' + type;
  el.style.display = 'block';
}
function hideMsg(id) {
  const el = document.getElementById(id);
  el.style.display = 'none';
}
function setSpin(id, on) {
  document.getElementById(id).style.display = on ? 'inline-block' : 'none';
}
function setDisabled(id, dis) {
  document.getElementById(id).disabled = dis;
}

// ─── STEP 1: Create account ───────────────────────────────────────────────────
document.getElementById('s1-next').addEventListener('click', async () => {
  hideMsg('s1-error');
  const username  = document.getElementById('s1-username').value.trim();
  const password  = document.getElementById('s1-password').value;
  const password2 = document.getElementById('s1-password2').value;

  if (!username)           return showMsg('s1-error', 'Username is required.', 'error');
  if (password.length < 6) return showMsg('s1-error', 'Password must be at least 6 characters.', 'error');
  if (password !== password2) return showMsg('s1-error', 'Passwords do not match.', 'error');

  setDisabled('s1-next', true);
  setSpin('s1-spin', true);

  try {
    const res  = await fetch('/biblioteca/setup-init.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password }),
    });
    const data = await res.json();
    if (data.ok) {
      setStepUI(2);
    } else {
      showMsg('s1-error', data.error || 'Failed to create account.', 'error');
    }
  } catch (e) {
    showMsg('s1-error', 'Network error. Please try again.', 'error');
  } finally {
    setDisabled('s1-next', false);
    setSpin('s1-spin', false);
  }
});

// ─── STEP 2: Site config ──────────────────────────────────────────────────────
document.getElementById('s2-back').addEventListener('click', () => setStepUI(1));

document.getElementById('s2-next').addEventListener('click', async () => {
  hideMsg('s2-error');
  const name   = document.getElementById('s2-name').value.trim();
  const desc   = document.getElementById('s2-desc').value.trim();
  const url    = document.getElementById('s2-url').value.trim();
  const author = document.getElementById('s2-author').value.trim();

  if (!name) return showMsg('s2-error', 'Site name is required.', 'error');

  setDisabled('s2-next', true);
  setSpin('s2-spin', true);

  try {
    const res  = await fetch('/biblioteca/save-config.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        site: { name, short_name: name, description: desc, url, author },
      }),
    });
    const data = await res.json();
    if (data.ok) {
      setStepUI(3);
    } else {
      showMsg('s2-error', data.error || 'Failed to save config.', 'error');
    }
  } catch (e) {
    showMsg('s2-error', 'Network error. Please try again.', 'error');
  } finally {
    setDisabled('s2-next', false);
    setSpin('s2-spin', false);
  }
});

// ─── STEP 3: Build ───────────────────────────────────────────────────────────
document.getElementById('s3-back').addEventListener('click', () => setStepUI(2));
document.getElementById('s3-next').addEventListener('click', async () => {
  // Mark setup as complete before showing done screen
  try {
    await fetch('/biblioteca/complete-setup.php', { method: 'POST' });
  } catch (e) {
    console.warn('Could not write setup complete marker', e);
  }
  showDone();
});

let pollTimer = null;

function appendLog(text) {
  const log = document.getElementById('build-log');
  // Colour-code lines
  const span = document.createElement('span');
  if (/error|failed|exception|traceback/i.test(text)) span.className = 'log-error';
  else if (/done|success|complete|finish/i.test(text)) span.className = 'log-success';
  else if (/^\[|step|running|starting|building/i.test(text)) span.className = 'log-info';
  span.textContent = text + '\n';
  log.appendChild(span);
  log.scrollTop = log.scrollHeight;
}

async function pollLog() {
  try {
    const res  = await fetch('/biblioteca/get-build-log.php');
    const data = await res.json();

    // Replace log content
    const log = document.getElementById('build-log');
    log.innerHTML = '';
    if (data.content) {
      data.content.split('\n').forEach(line => {
        if (line !== '') appendLog(line);
      });
    }

    const status = document.getElementById('build-status');
    if (data.is_running) {
      status.textContent = 'Building\u2026';
      status.style.color = '#60a5fa';
    } else {
      clearInterval(pollTimer);
      pollTimer = null;
      setSpin('s3-spin', false);
      setDisabled('s3-build', false);

      const success = data.success === true;
      status.textContent = success ? 'Build complete!' : 'Build failed.';
      status.style.color = success ? 'var(--success)' : 'var(--error)';
      if (success) {
        document.getElementById('s3-next').style.display = 'inline-flex';
      }
    }
  } catch (e) {
    console.error('Poll error', e);
  }
}

document.getElementById('s3-build').addEventListener('click', async () => {
  const log = document.getElementById('build-log');
  log.innerHTML = '';
  document.getElementById('build-status').textContent = '';
  document.getElementById('s3-next').style.display = 'none';
  document.getElementById('s3-error').style.display = 'none';

  setDisabled('s3-build', true);
  setSpin('s3-spin', true);

  try {
    const res  = await fetch('/biblioteca/build.php', { method: 'POST' });
    const data = await res.json();
    if (!data.ok) {
      showMsg('s3-error', data.error || 'Could not start build.', 'error');
      setDisabled('s3-build', false);
      setSpin('s3-spin', false);
      return;
    }
    // Start polling
    clearInterval(pollTimer);
    document.getElementById('build-status').textContent = 'Building\u2026';
    document.getElementById('build-status').style.color = '#60a5fa';
    pollTimer = setInterval(pollLog, 2000);
    // First poll after a short delay (let the process start writing to log)
    setTimeout(pollLog, 1500);
  } catch (e) {
    showMsg('s3-error', 'Network error starting build.', 'error');
    setDisabled('s3-build', false);
    setSpin('s3-spin', false);
  }
});
</script>
</body>
</html>
