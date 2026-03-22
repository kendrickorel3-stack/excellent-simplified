<?php
// admin/debug.php — Excellent Simplified Academy
// DELETE THIS FILE after you're done debugging!
session_start();
require_once __DIR__ . '/../config/db.php';

// Admin guard
$adminId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$_chk = $conn->prepare("SELECT is_admin, google_name, email FROM users WHERE id=? LIMIT 1");
$_chk->bind_param('i', $adminId); $_chk->execute();
$_row = $_chk->get_result()->fetch_assoc(); $_chk->close();
$isAdmin = (int)($_row['is_admin'] ?? 0);

function ok($msg)   { return "<span style='color:#10b981'>✅ $msg</span>"; }
function fail($msg) { return "<span style='color:#f43f5e'>❌ $msg</span>"; }
function warn($msg) { return "<span style='color:#f59e0b'>⚠️ $msg</span>"; }
function info($msg) { return "<span style='color:#60a5fa'>ℹ️ $msg</span>"; }

function tableExists($conn, $t) {
    return (bool)$conn->query("SHOW TABLES LIKE '$t'")->num_rows;
}
function columnExists($conn, $t, $c) {
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0];
    $s  = $conn->prepare("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->bind_param('sss',$db,$t,$c); $s->execute();
    return (int)$s->get_result()->fetch_assoc()['n'] > 0;
}

// Run YouTube test if requested
$ytResult = null;
if (isset($_GET['test_yt'])) {
    ob_start();
    // Temporarily override session check for the test
    $_SESSION['user_id'] = $adminId;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'http' . (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'s':'') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/youtube_sync.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Cookie: ' . ($_SERVER['HTTP_COOKIE'] ?? '')],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $ytRaw = curl_exec($ch);
    $ytErr = curl_error($ch);
    curl_close($ch);
    ob_end_clean();
    $ytResult = ['raw' => $ytRaw, 'err' => $ytErr, 'json' => json_decode($ytRaw, true)];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Debug — Excellent Simplified</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#07080f;color:#eef0f8;font-family:'DM Sans',sans-serif;padding:24px;line-height:1.6}
h1{font-family:'Space Mono',monospace;font-size:16px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#a78bfa;margin-bottom:4px}
.subtitle{font-size:12px;color:#6b7280;margin-bottom:28px;font-family:'Space Mono',monospace}
.warn-banner{background:rgba(244,63,94,.12);border:1px solid rgba(244,63,94,.3);border-radius:10px;padding:12px 16px;font-size:12px;color:#fca5a5;margin-bottom:24px;display:flex;align-items:center;gap:10px}
.section{background:#0d0f18;border:1px solid rgba(255,255,255,0.07);border-radius:12px;margin-bottom:16px;overflow:hidden}
.section-head{padding:12px 18px;background:rgba(255,255,255,0.03);border-bottom:1px solid rgba(255,255,255,0.06);font-family:'Space Mono',monospace;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9ca3af;display:flex;align-items:center;gap:8px}
.section-head i{font-size:10px}
.rows{padding:4px 0}
.row{display:flex;align-items:baseline;gap:12px;padding:8px 18px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:13px}
.row:last-child{border-bottom:none}
.row-label{color:#6b7280;font-size:11px;min-width:180px;flex-shrink:0;font-family:'Space Mono',monospace}
.row-val{flex:1;word-break:break-all}
.badge{font-size:10px;padding:2px 8px;border-radius:20px;font-weight:700;font-family:'Space Mono',monospace;letter-spacing:.04em}
.badge.ok{background:rgba(16,185,129,.15);color:#34d399;border:1px solid rgba(16,185,129,.25)}
.badge.fail{background:rgba(244,63,94,.15);color:#fca5a5;border:1px solid rgba(244,63,94,.25)}
.badge.warn{background:rgba(245,158,11,.15);color:#fcd34d;border:1px solid rgba(245,158,11,.25)}
.code{background:#12151f;border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:12px 14px;font-family:'Space Mono',monospace;font-size:11px;color:#94a3b8;overflow-x:auto;white-space:pre-wrap;word-break:break-all;margin:4px 0}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:9px;border:none;cursor:pointer;font-family:inherit;font-size:13px;font-weight:700;transition:all .18s;text-decoration:none}
.btn-violet{background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;box-shadow:0 4px 16px rgba(124,58,237,.35)}
.btn-violet:hover{transform:translateY(-1px);box-shadow:0 6px 24px rgba(124,58,237,.5)}
.btn-red{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;box-shadow:0 4px 16px rgba(220,38,38,.3)}
.btn-red:hover{transform:translateY(-1px)}
.btn-ghost{background:rgba(255,255,255,0.06);color:#9ca3af;border:1px solid rgba(255,255,255,0.1)}
.btn-ghost:hover{background:rgba(255,255,255,0.1);color:#fff}
.actions{display:flex;gap:10px;flex-wrap:wrap;padding:16px 18px;border-top:1px solid rgba(255,255,255,0.06)}
.tag-ok{color:#10b981}.tag-fail{color:#f43f5e}.tag-warn{color:#f59e0b}.tag-info{color:#60a5fa}
</style>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<h1>🔧 Admin Debug Panel</h1>
<div class="subtitle">Excellent Simplified Academy · <?= date('D j M Y, H:i:s') ?></div>

<div class="warn-banner">
  <i class="fa fa-triangle-exclamation"></i>
  <strong>Delete this file after debugging!</strong> &nbsp;It exposes sensitive server info. URL: <code>admin/debug.php</code>
</div>

<!-- ── SESSION ── -->
<div class="section">
  <div class="section-head"><i class="fa fa-user-shield"></i> Session &amp; Auth</div>
  <div class="rows">
    <div class="row"><span class="row-label">Logged in</span><span class="row-val"><?= $adminId ? ok('Yes (user_id = '.$adminId.')') : fail('No session — not logged in') ?></span></div>
    <div class="row"><span class="row-label">Name</span><span class="row-val"><?= htmlspecialchars($_row['google_name'] ?? $_SESSION['google_name'] ?? '—') ?></span></div>
    <div class="row"><span class="row-label">Email</span><span class="row-val"><?= htmlspecialchars($_row['email'] ?? '—') ?></span></div>
    <div class="row"><span class="row-label">is_admin flag</span><span class="row-val"><?= $isAdmin ? ok('1 — Admin access confirmed') : fail('0 — Not marked as admin in DB') ?></span></div>
    <div class="row"><span class="row-label">Session ID</span><span class="row-val" style="font-family:'Space Mono',monospace;font-size:11px;color:#6b7280"><?= session_id() ?></span></div>
    <div class="row">
      <span class="row-label">All session keys</span>
      <span class="row-val" style="font-family:'Space Mono',monospace;font-size:11px;color:#94a3b8">
        <?php foreach($_SESSION as $k=>$v): ?>
          <div><strong style="color:#a78bfa"><?= htmlspecialchars($k) ?></strong>: <?= htmlspecialchars(is_array($v)?json_encode($v):(string)$v) ?></div>
        <?php endforeach; ?>
      </span>
    </div>
  </div>
</div>

<!-- ── DATABASE ── -->
<div class="section">
  <div class="section-head"><i class="fa fa-database"></i> Database Connection</div>
  <div class="rows">
    <?php
    $dbOk = ($conn && !$conn->connect_error);
    $dbName = $dbOk ? ($conn->query("SELECT DATABASE()")->fetch_row()[0] ?? '?') : '—';
    $dbVer  = $dbOk ? ($conn->query("SELECT VERSION()")->fetch_row()[0] ?? '?') : '—';
    ?>
    <div class="row"><span class="row-label">Connection</span><span class="row-val"><?= $dbOk ? ok('Connected') : fail('FAILED — '.$conn->connect_error) ?></span></div>
    <div class="row"><span class="row-label">Database name</span><span class="row-val" style="font-family:'Space Mono',monospace;color:#60a5fa"><?= htmlspecialchars($dbName) ?></span></div>
    <div class="row"><span class="row-label">MySQL version</span><span class="row-val"><?= htmlspecialchars($dbVer) ?></span></div>
    <div class="row"><span class="row-label">Host</span><span class="row-val" style="font-family:'Space Mono',monospace;font-size:11px;color:#6b7280"><?= htmlspecialchars($conn->host_info ?? '—') ?></span></div>
  </div>
</div>

<!-- ── TABLES ── -->
<div class="section">
  <div class="section-head"><i class="fa fa-table"></i> Database Tables</div>
  <div class="rows">
    <?php
    $tables = ['users','videos','questions','subjects','scores','chat_messages','chat_participants','announcements','announcement_reads','video_progress','video_watches','yt_config'];
    foreach($tables as $t):
        $exists = tableExists($conn, $t);
        $count  = $exists ? (int)$conn->query("SELECT COUNT(*) AS c FROM `$t`")->fetch_assoc()['c'] : null;
    ?>
    <div class="row">
      <span class="row-label"><?= $t ?></span>
      <span class="row-val">
        <?= $exists ? ok($count . ' rows') : ($t === 'yt_config' ? warn('Missing — will be created on first YouTube sync') : warn('Table does not exist')) ?>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── VIDEOS TABLE COLUMNS ── -->
<?php if(tableExists($conn,'videos')): ?>
<div class="section">
  <div class="section-head"><i class="fa fa-film"></i> videos table — column check</div>
  <div class="rows">
    <?php
    $importantCols = ['id','title','youtube_link','youtube_url','youtube_id','embed_url','subject_id','description','thumbnail','created_at','source'];
    foreach($importantCols as $col):
        $has = columnExists($conn,'videos',$col);
        $note = match($col) {
            'youtube_link' => ' (used by your save_video.php form)',
            'youtube_id'   => ' (needed for clean duplicate detection)',
            'thumbnail'    => ' (YouTube sync imports this automatically)',
            'source'       => ' (marks auto-imported videos)',
            default        => ''
        };
    ?>
    <div class="row">
      <span class="row-label"><?= $col ?></span>
      <span class="row-val"><?= $has ? ok('exists') : warn('missing'.$note) ?></span>
    </div>
    <?php endforeach; ?>

    <?php
    // Show actual column list
    $cols = $conn->query("SHOW COLUMNS FROM videos");
    $colNames = [];
    while($c = $cols->fetch_assoc()) $colNames[] = $c['Field'];
    ?>
    <div class="row">
      <span class="row-label">All columns</span>
      <span class="row-val" style="font-family:'Space Mono',monospace;font-size:11px;color:#94a3b8"><?= implode(', ', $colNames) ?></span>
    </div>

    <?php
    // Show last 3 videos
    $recent = $conn->query("SELECT * FROM videos ORDER BY id DESC LIMIT 3");
    if($recent && $recent->num_rows):
    ?>
    <div class="row" style="flex-direction:column;align-items:flex-start">
      <span class="row-label" style="margin-bottom:6px">Last 3 videos in DB</span>
      <?php while($v = $recent->fetch_assoc()): ?>
      <div class="code"><?= htmlspecialchars(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></div>
      <?php endwhile; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── PHP & SERVER ── -->
<div class="section">
  <div class="section-head"><i class="fa fa-server"></i> PHP &amp; Server</div>
  <div class="rows">
    <div class="row"><span class="row-label">PHP version</span><span class="row-val"><?= PHP_VERSION ?> <?= version_compare(PHP_VERSION,'7.4','>=') ? ok('OK') : fail('Too old — needs 7.4+') ?></span></div>
    <div class="row"><span class="row-label">cURL</span><span class="row-val"><?= function_exists('curl_init') ? ok('Available (needed for YouTube sync)') : fail('NOT available — YouTube sync will fail') ?></span></div>
    <div class="row"><span class="row-label">file_get_contents URL</span><span class="row-val"><?= ini_get('allow_url_fopen') ? ok('Enabled (fallback for YouTube sync)') : warn('Disabled') ?></span></div>
    <div class="row"><span class="row-label">HTTPS</span><span class="row-val"><?= (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on') ? ok('Yes') : warn('No — running over HTTP') ?></span></div>
    <div class="row"><span class="row-label">Server software</span><span class="row-val"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '—') ?></span></div>
    <div class="row"><span class="row-label">Document root</span><span class="row-val" style="font-family:'Space Mono',monospace;font-size:11px;color:#6b7280"><?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? '—') ?></span></div>
    <div class="row"><span class="row-label">This file path</span><span class="row-val" style="font-family:'Space Mono',monospace;font-size:11px;color:#6b7280"><?= htmlspecialchars(__FILE__) ?></span></div>
    <div class="row"><span class="row-label">Memory limit</span><span class="row-val"><?= ini_get('memory_limit') ?></span></div>
    <div class="row"><span class="row-label">Max execution time</span><span class="row-val"><?= ini_get('max_execution_time') ?>s</span></div>
  </div>
</div>

<!-- ── FILE CHECK ── -->
<div class="section">
  <div class="section-head"><i class="fa fa-folder-open"></i> Required Files</div>
  <div class="rows">
    <?php
    $files = [
        '../config/db.php'            => 'DB config',
        'youtube_sync.php'            => 'YouTube sync script',
        'leaderboard.php'             => 'User monitor',
        'live_brainstorm_control.php' => 'Live brainstorm control',
        'save_video.php'              => 'Save video endpoint',
        'create_question.php'         => 'Create question endpoint',
        'create_subject.php'          => 'Create subject endpoint',
        'delete_video.php'            => 'Delete video endpoint',
        'delete_question.php'         => 'Delete question endpoint',
        'delete_subject.php'          => 'Delete subject endpoint',
        'export_csv.php'              => 'Export CSV',
    ];
    foreach($files as $path => $label):
        $full   = __DIR__ . '/' . $path;
        $exists = file_exists($full);
        $size   = $exists ? round(filesize($full)/1024,1).'KB' : '';
    ?>
    <div class="row">
      <span class="row-label"><?= $label ?></span>
      <span class="row-val"><?= $exists ? ok($path . ' ('.$size.')') : warn($path . ' — not found') ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── YOUTUBE SYNC ── -->
<div class="section">
  <div class="section-head"><i class="fa fa-brands fa-youtube" style="color:#ff2e2e"></i> YouTube Sync Status</div>
  <div class="rows">
    <?php
    $ytFile = __DIR__ . '/youtube_sync.php';
    $ytExists = file_exists($ytFile);
    if($ytExists) {
        $ytContent = file_get_contents($ytFile);
        $hasKey    = strpos($ytContent, 'PASTE_YOUR_API_KEY_HERE') === false && preg_match('/AIza[A-Za-z0-9_-]{35}/', $ytContent);
        $hasHandle = strpos($ytContent, 'PASTE_YOUR_CHANNEL') === false && strpos($ytContent, 'ExcellentSimplified') !== false;
        $ytCached  = null;
        if(tableExists($conn,'yt_config')) {
            $r = $conn->query("SELECT `v` FROM yt_config WHERE `k`='channel_id' LIMIT 1");
            $ytCached = $r ? ($r->fetch_assoc()['v'] ?? null) : null;
        }
    }
    ?>
    <div class="row"><span class="row-label">youtube_sync.php</span><span class="row-val"><?= $ytExists ? ok('File exists') : fail('File missing — upload it to admin/') ?></span></div>
    <?php if($ytExists): ?>
    <div class="row"><span class="row-label">API Key configured</span><span class="row-val"><?= $hasKey ? ok('Filled in') : fail('Still placeholder — open youtube_sync.php and fill in YT_API_KEY') ?></span></div>
    <div class="row"><span class="row-label">Channel handle</span><span class="row-val"><?= $hasHandle ? ok('@ExcellentSimplifiedacademy found') : warn('Handle not found in file') ?></span></div>
    <div class="row"><span class="row-label">Channel ID cached</span><span class="row-val"><?= $ytCached ? ok('Yes: '.$ytCached) : warn('Not yet — will be resolved on first sync') ?></span></div>
    <?php endif; ?>
  </div>
  <div class="actions">
    <a href="?test_yt=1" class="btn btn-red"><i class="fa fa-brands fa-youtube"></i> Test YouTube Sync Now</a>
    <a href="dashboard.php" class="btn btn-ghost"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
  </div>
</div>

<!-- ── YOUTUBE TEST RESULT ── -->
<?php if($ytResult !== null): ?>
<div class="section">
  <div class="section-head"><i class="fa fa-flask"></i> YouTube Sync Test Result</div>
  <div class="rows">
    <?php if($ytResult['err']): ?>
    <div class="row"><span class="row-label">cURL error</span><span class="row-val" class="tag-fail"><?= htmlspecialchars($ytResult['err']) ?></span></div>
    <?php endif; ?>

    <?php if($ytResult['json']): $j = $ytResult['json']; ?>
    <div class="row"><span class="row-label">Success</span><span class="row-val"><?= $j['success'] ? ok('true') : fail('false') ?></span></div>
    <?php if(!$j['success']): ?>
    <div class="row"><span class="row-label">Error message</span><span class="row-val" style="color:#fca5a5;font-weight:600"><?= htmlspecialchars($j['error'] ?? '—') ?></span></div>
    <?php else: ?>
    <div class="row"><span class="row-label">Message</span><span class="row-val" style="color:#34d399"><?= htmlspecialchars($j['message'] ?? '—') ?></span></div>
    <div class="row"><span class="row-label">Videos checked</span><span class="row-val"><?= (int)($j['checked']??0) ?></span></div>
    <div class="row"><span class="row-label">New videos added</span><span class="row-val"><?= (int)($j['added']??0) ?></span></div>
    <div class="row"><span class="row-label">Already in DB</span><span class="row-val"><?= (int)($j['skipped']??0) ?></span></div>
    <?php if(!empty($j['new_titles'])): ?>
    <div class="row"><span class="row-label">New titles</span><span class="row-val" style="color:#a78bfa"><?= htmlspecialchars(implode(', ', $j['new_titles'])) ?></span></div>
    <?php endif; ?>
    <?php endif; ?>
    <?php else: ?>
    <div class="row"><span class="row-label">Raw response</span><span class="row-val"><div class="code"><?= htmlspecialchars($ytResult['raw'] ?? 'empty') ?></div></span></div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── QUICK SQL ── -->
<div class="section">
  <div class="section-head"><i class="fa fa-terminal"></i> Quick SQL Runner <?= $isAdmin ? '' : warn('(admin only)') ?></div>
  <?php if($isAdmin): ?>
  <div style="padding:16px 18px">
    <textarea id="sqlInput" style="width:100%;background:#12151f;border:1px solid rgba(255,255,255,0.1);border-radius:9px;color:#eef0f8;font-family:'Space Mono',monospace;font-size:12px;padding:12px;outline:none;resize:vertical;min-height:80px" placeholder="SELECT * FROM videos ORDER BY id DESC LIMIT 5;"></textarea>
    <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap">
      <button onclick="runSQL()" class="btn btn-violet"><i class="fa fa-play"></i> Run Query</button>
      <button onclick="document.getElementById('sqlInput').value='SELECT * FROM videos ORDER BY id DESC LIMIT 5'" class="btn btn-ghost">Recent Videos</button>
      <button onclick="document.getElementById('sqlInput').value='SHOW COLUMNS FROM videos'" class="btn btn-ghost">Video Columns</button>
      <button onclick="document.getElementById('sqlInput').value='SELECT id,google_name,email,is_admin,created_at FROM users ORDER BY id DESC LIMIT 10'" class="btn btn-ghost">Recent Users</button>
      <button onclick="document.getElementById('sqlInput').value='SELECT * FROM yt_config'" class="btn btn-ghost">YT Config</button>
    </div>
    <div id="sqlResult" style="margin-top:14px"></div>
  </div>
  <?php else: ?>
  <div style="padding:16px 18px;color:#6b7280;font-size:13px">You need to be logged in as admin to run SQL queries.</div>
  <?php endif; ?>
</div>

<div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
  <a href="dashboard.php" class="btn btn-violet"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
  <a href="?test_yt=1" class="btn btn-red"><i class="fa fa-brands fa-youtube"></i> Test YouTube Sync</a>
  <a href="?" class="btn btn-ghost"><i class="fa fa-rotate"></i> Refresh</a>
</div>

<script>
async function runSQL() {
  const sql = document.getElementById('sqlInput').value.trim();
  const res = document.getElementById('sqlResult');
  if (!sql) return;
  res.innerHTML = '<div style="color:#6b7280;font-size:12px;font-family:\'Space Mono\',monospace">Running…</div>';
  try {
    const fd = new FormData();
    fd.append('sql', sql);
    const r = await fetch('?run_sql=1', { method:'POST', body:fd, credentials:'same-origin' });
    const j = await r.json();
    if (j.error) {
      res.innerHTML = `<div style="color:#fca5a5;font-family:'Space Mono',monospace;font-size:12px;background:rgba(244,63,94,.08);border:1px solid rgba(244,63,94,.2);padding:12px;border-radius:8px">❌ ${escHtml(j.error)}</div>`;
      return;
    }
    if (!j.rows || j.rows.length === 0) {
      res.innerHTML = `<div style="color:#10b981;font-family:'Space Mono',monospace;font-size:12px;padding:10px">✅ Query OK — ${j.affected ?? 0} row(s) affected.</div>`;
      return;
    }
    // Build table
    const cols = Object.keys(j.rows[0]);
    let html = `<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:12px;font-family:'Space Mono',monospace">`;
    html += '<thead><tr style="background:#12151f">' + cols.map(c=>`<th style="padding:8px 12px;text-align:left;color:#9ca3af;border-bottom:1px solid rgba(255,255,255,0.07);white-space:nowrap">${escHtml(c)}</th>`).join('') + '</tr></thead><tbody>';
    j.rows.forEach((row,i) => {
      html += `<tr style="background:${i%2?'rgba(255,255,255,0.01)':'transparent'}">`;
      cols.forEach(c => { html += `<td style="padding:7px 12px;border-bottom:1px solid rgba(255,255,255,0.04);color:#e2e8f0;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(String(row[c]??''))}">${escHtml(String(row[c]??''))}</td>`; });
      html += '</tr>';
    });
    html += `</tbody></table></div><div style="font-size:11px;color:#6b7280;margin-top:8px;font-family:'Space Mono',monospace">${j.rows.length} row(s)</div>`;
    res.innerHTML = html;
  } catch(e) {
    res.innerHTML = `<div style="color:#fca5a5;font-size:12px">Network error: ${escHtml(e.message)}</div>`;
  }
}
function escHtml(s){ return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[c])); }
document.getElementById('sqlInput')?.addEventListener('keydown', e => { if(e.ctrlKey && e.key==='Enter') runSQL(); });
</script>

<?php
// Handle SQL POST
if(isset($_GET['run_sql']) && $isAdmin && isset($_POST['sql'])) {
    header('Content-Type: application/json');
    $sql = trim($_POST['sql']);
    // Block dangerous operations
    $blocked = ['DROP TABLE','DROP DATABASE','TRUNCATE','DELETE FROM users','UPDATE users SET is_admin'];
    foreach($blocked as $b) {
        if(stripos($sql, $b) !== false) {
            echo json_encode(['error' => "Blocked: '$b' is not allowed here for safety."]); exit;
        }
    }
    try {
        $result = $conn->query($sql);
        if($result === true) {
            echo json_encode(['rows'=>[],'affected'=>$conn->affected_rows]); exit;
        }
        if($result === false) {
            echo json_encode(['error' => $conn->error]); exit;
        }
        $rows = [];
        while($r = $result->fetch_assoc()) $rows[] = $r;
        echo json_encode(['rows' => $rows]);
    } catch(Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
</body>
</html>
