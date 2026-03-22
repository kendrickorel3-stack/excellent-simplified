<?php
// videos/watchvideos.php
// Show incomplete / in-progress videos for the current user

session_start();
require_once __DIR__ . "/../config/db.php";

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Continue Watching — EXCELLENT SIMPLIFIED</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{
  --accent1:#ff4d4d;
  --accent2:#0066ff;
  --card:linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
  --muted:#94a3b8;
  --bg:#071129;
  --text:#fff;
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,Segoe UI,Arial;background:linear-gradient(180deg,var(--bg),#0b1220);color:var(--text);min-height:100vh}
.header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;background:linear-gradient(90deg,var(--accent1),var(--accent2));position:sticky;top:0;z-index:20}
.header .brand{font-weight:800}
.wrap{max-width:1100px;margin:14px auto;padding:12px}
.grid{display:grid;gap:12px}
@media(min-width:900px){ .grid{grid-template-columns:repeat(3,1fr)} }
@media(min-width:600px) and (max-width:899px){ .grid{grid-template-columns:repeat(2,1fr)} }
.card{background:var(--card);padding:12px;border-radius:12px;display:flex;flex-direction:column;gap:8px}
.thumb{width:100%;height:160px;object-fit:cover;border-radius:8px;background:#111}
.title{font-weight:800}
.meta{color:var(--muted);font-size:13px}
.progress-wrap{width:100%;background:rgba(255,255,255,0.04);height:10px;border-radius:999px;overflow:hidden}
.progress-bar{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--accent1),var(--accent2));width:0%;transition:width .4s ease}
.row{display:flex;justify-content:space-between;align-items:center;gap:8px}
.btn{padding:8px 10px;border-radius:8px;border:0;cursor:pointer;font-weight:700;background:linear-gradient(90deg,var(--accent1),var(--accent2));color:#fff}
.small{font-size:13px;color:var(--muted)}
.empty{padding:40px;text-align:center;color:var(--muted);background:rgba(255,255,255,0.02);border-radius:10px}
.badge{font-size:12px;padding:6px 8px;border-radius:999px;background:rgba(255,255,255,0.03)}
</style>
</head>
<body>
<header class="header">
  <div class="brand">🎓 EXCELLENT SIMPLIFIED</div>
  <div>
    <?php if ($user_id): ?>
      <a href="/dashboard.php" style="color:#fff;text-decoration:none;font-weight:700;margin-right:12px">Dashboard</a>
      <a href="/videos/lessons.php" style="color:#fff;text-decoration:none;font-weight:700">All Lessons</a>
    <?php else: ?>
      <a href="/login.html" style="color:#fff;text-decoration:none;font-weight:700">Login</a>
    <?php endif;?>
  </div>
</header>

<div class="wrap">
  <h2 style="margin-top:0">Continue watching — Incomplete videos</h2>
  <p class="small">This list shows videos you haven't completed yet (not started or partially watched).</p>

<?php
if (!$user_id) {
    echo '<div class="empty">You need to <a href="/login.html" style="color:#fff">login</a> to see your progress.</div>';
    echo '</div></body></html>';
    exit;
}

/*
 SQL logic:
 - left join video_progress for this user
 - include records where progress row is null (not started) OR completed = 0 OR watch_percent < 100
 - order by last_watched desc (partials first) then created_at desc
*/
$sql = "
  SELECT v.id, v.title, v.youtube_link, v.created_at,
         s.name AS subject,
         COALESCE(vp.watch_percent, 0) AS watch_percent,
         COALESCE(vp.completed, 0) AS completed,
         vp.last_watched
  FROM videos v
  LEFT JOIN subjects s ON v.subject_id = s.id
  LEFT JOIN video_progress vp ON vp.video_id = v.id AND vp.user_id = ?
  WHERE (vp.id IS NULL) OR (vp.completed = 0) OR (vp.watch_percent < 100)
  ORDER BY
     CASE WHEN vp.last_watched IS NULL THEN 0 ELSE 1 END DESC,
     vp.last_watched DESC,
     v.created_at DESC
  LIMIT 200
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo '<div class="empty">Database error preparing query.</div>';
    echo '</div></body></html>';
    exit;
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();

$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($rows)) {
    echo '<div class="empty">Well done — no incomplete videos found. Try a lesson from <a href="/videos/lessons.php" style="color:#fff">Lessons</a>.</div>';
    echo '</div></body></html>';
    exit;
}

// helper to get youtube thumbnail
function yt_thumb($link){
    // attempt to extract id
    if (!$link) return "https://via.placeholder.com/480x360?text=No+Thumbnail";
    if (preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_-]{6,50})/',$link,$m)) {
        $id = $m[1];
        return "https://img.youtube.com/vi/{$id}/hqdefault.jpg";
    }
    return "https://via.placeholder.com/480x360?text=Video";
}

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'utf-8'); }
?>

<div class="grid" role="list">
<?php foreach($rows as $v): 
    $thumb = yt_thumb($v['youtube_link']);
    $pct = (int)($v['watch_percent'] ?? 0);
    $comp = (int)($v['completed'] ?? 0);
    $sub = $v['subject'] ?? 'General';
    $last = $v['last_watched'] ? date("M j, Y H:i", strtotime($v['last_watched'])) : 'Not started';
?>
  <div class="card" role="listitem" id="video-<?php echo (int)$v['id'] ?>">
    <a href="watch_video.php?id=<?php echo (int)$v['id'] ?>" style="text-decoration:none;color:inherit">
      <img class="thumb" src="<?php echo esc($thumb) ?>" alt="<?php echo esc($v['title']) ?>">
    </a>

    <div class="title"><?php echo esc($v['title']) ?></div>
    <div class="meta"><?php echo esc($sub) ?> • <span class="small"><?php echo esc($last) ?></span></div>

    <div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
        <div class="badge"><?php echo $comp ? 'Incomplete (not marked done)' : ($pct ? 'In progress' : 'Not started') ?></div>
        <div class="small"><?php echo $pct ?>%</div>
      </div>

      <div class="progress-wrap" aria-hidden="true" style="margin-top:8px">
        <div class="progress-bar" style="width:<?php echo $pct ?>%"></div>
      </div>

      <div class="row" style="margin-top:10px">
        <a class="btn" href="watch_video.php?id=<?php echo (int)$v['id'] ?>">▶ Resume</a>
        <button class="btn" onclick="markComplete(<?php echo (int)$v['id'] ?>, this)" <?php echo $pct >= 100 || $comp ? 'disabled' : '' ?>>Mark complete</button>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

</div>

<script>
async function markComplete(videoId, btn){
  if(!confirm('Mark this video as completed?')) return;
  btn.disabled = true;
  try{
    const res = await fetch('/api/mark_complete.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ video_id: videoId, completed: 1 })
    });
    const j = await res.json();
    if (j.success) {
      // remove card or update UI
      const el = document.getElementById('video-' + videoId);
      if (el) {
        el.style.transition = 'opacity .3s, transform .3s';
        el.style.opacity = '0';
        el.style.transform = 'translateY(10px)';
        setTimeout(()=> el.remove(), 350);
      } else {
        location.reload();
      }
    } else {
      alert(j.error || 'Could not mark complete');
      btn.disabled = false;
    }
  } catch(e){
    console.error(e);
    alert('Network error');
    btn.disabled = false;
  }
}
</script>
</body>
</html>
