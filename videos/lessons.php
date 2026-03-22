<?php
// videos/lessons.php (upgraded)
// - prepared statements
// - per-user completed/bookmarked flags
// - safe output
session_start();
require_once "../config/db.php";

$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$search_raw = $_GET['search'] ?? '';
$search = trim($search_raw);
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// fetch subject list (safe)
$subjectList = $conn->query("SELECT id,name FROM subjects ORDER BY name");

// build main query dynamically with prepared statement params
$sql = "
SELECT
  v.id, v.title, v.youtube_link, s.name AS subject,
  COALESCE((SELECT COUNT(*) FROM video_progress WHERE video_id = v.id AND completed=1),0) AS completed_count,
  COALESCE((SELECT COUNT(*) FROM bookmarks WHERE video_id = v.id),0) AS bookmarks_count,
  COALESCE((SELECT completed FROM video_progress WHERE video_id = v.id AND user_id = ? LIMIT 1),0) AS user_completed,
  COALESCE((SELECT 1 FROM bookmarks WHERE video_id = v.id AND user_id = ? LIMIT 1),0) AS user_bookmarked
FROM videos v
LEFT JOIN subjects s ON v.subject_id = s.id
WHERE 1=1
";

$params = [];
$types = "";

// bind the two user-specific params first (for subqueries)
$types .= "ii";
$params[] = $user_id;
$params[] = $user_id;

// subject filter
if ($subject_id) {
    $sql .= " AND v.subject_id = ? ";
    $types .= "i";
    $params[] = $subject_id;
}

// search filter
if ($search !== '') {
    $sql .= " AND (v.title LIKE ? OR v.youtube_link LIKE ?) ";
    $types .= "ss";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY v.created_at DESC";

// prepare & execute
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo "<h2>Database error</h2><pre>" . htmlspecialchars($conn->error) . "</pre>";
    exit();
}

// bind params if any
if (count($params) > 0) {
    // mysqli bind_param needs references
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_names[] = &$params[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
}

$stmt->execute();
$res = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Lessons | EXCELLENT SIMPLIFIED</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:#0a0c10; --surface:#111318; --surface2:#181b22; --border:#1f2330;
    --accent:#00c98a; --accent2:#3b82f6; --danger:#ff4757; --text:#e8ecf4;
    --muted:#5a6278; --muted2:#8a93ab;
    --topbar-bg:rgba(10,12,16,0.95);
    --card-hover:#1a1e2a;
    --sent-badge-bg:rgba(0,201,138,0.12); --sent-badge:var(--accent);
    --completed-bg:rgba(0,201,138,0.1); --bookmark-bg:rgba(59,130,246,0.1);
  }
  body.light {
    --bg:#f0f4f9; --surface:#fff; --surface2:#f4f7fc; --border:#dde3ee;
    --accent:#00a872; --accent2:#2563eb; --danger:#e53e3e;
    --text:#0d1526; --muted:#9aa3b8; --muted2:#6b7a99;
    --topbar-bg:rgba(240,244,249,0.96);
    --card-hover:#eaeef8;
    --completed-bg:rgba(0,168,114,0.08); --bookmark-bg:rgba(37,99,235,0.08);
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .25s,color .25s;}
  body{background-image:radial-gradient(ellipse 80% 60% at 10% 0%,rgba(0,229,160,.04) 0%,transparent 60%),radial-gradient(ellipse 60% 50% at 90% 100%,rgba(59,130,246,.05) 0%,transparent 60%);}
  body.light{background-image:radial-gradient(ellipse 80% 60% at 10% 0%,rgba(0,168,114,.05) 0%,transparent 60%),radial-gradient(ellipse 60% 50% at 90% 100%,rgba(37,99,235,.04) 0%,transparent 60%);}

  /* ── TOPBAR ── */
  .topbar{position:sticky;top:0;z-index:40;height:56px;background:var(--topbar-bg);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 24px;gap:12px;transition:background .25s,border-color .25s;}
  .topbar-left{display:flex;align-items:center;gap:12px;}
  .brand-logo{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:#000;flex-shrink:0;}
  .brand-title{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;letter-spacing:.05em;color:var(--text);}
  .brand-sub{font-size:11px;color:var(--muted2);margin-top:1px;}
  .topbar-right{display:flex;align-items:center;gap:8px;}

  /* ── BUTTONS ── */
  .btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;border:1px solid transparent;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;transition:all .15s;text-decoration:none;white-space:nowrap;}
  .btn-primary{background:var(--accent);color:#000;border-color:var(--accent);}
  .btn-primary:hover{background:#00ffb2;border-color:#00ffb2;}
  .btn-ghost{background:transparent;color:var(--muted2);border-color:var(--border);}
  .btn-ghost:hover{color:var(--text);background:var(--surface2);}
  .btn-back{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--muted2);font-size:12px;font-weight:500;text-decoration:none;transition:all .15s;}
  .btn-back:hover{color:var(--text);border-color:var(--muted);}

  /* ── THEME TOGGLE ── */
  .theme-toggle{display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border-radius:20px;border:1px solid var(--border);background:var(--surface2);color:var(--muted2);cursor:pointer;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;transition:all .15s;white-space:nowrap;}
  .theme-toggle:hover{border-color:var(--accent);color:var(--text);}
  .toggle-track{width:28px;height:16px;border-radius:20px;background:var(--border);position:relative;transition:background .2s;flex-shrink:0;}
  body.light .toggle-track{background:var(--accent);}
  .toggle-thumb{position:absolute;top:2px;left:2px;width:12px;height:12px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.3);}
  body.light .toggle-thumb{transform:translateX(12px);}

  /* ── CONTAINER ── */
  .container{max-width:1160px;margin:0 auto;padding:22px 20px;}

  /* ── CONTROLS BAR ── */
  .controls-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:24px;}
  .controls-bar form{display:flex;align-items:center;gap:8px;flex:1;flex-wrap:wrap;}
  .field{background:var(--surface2);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:13px;padding:9px 13px;outline:none;transition:border-color .15s;}
  .field::placeholder{color:var(--muted);}
  .field:focus{border-color:var(--accent);}
  select.field{min-width:160px;cursor:pointer;}
  .search-field{flex:1;min-width:180px;}
  .results-meta{font-family:'Space Mono',monospace;font-size:11px;color:var(--muted2);white-space:nowrap;}

  /* ── SECTION HEADER ── */
  .section-head{display:flex;align-items:center;gap:8px;margin-bottom:16px;}
  .section-title{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted2);}
  .section-title i{color:var(--accent);font-size:10px;}
  .count-badge{background:rgba(0,201,138,.12);color:var(--accent);border-radius:20px;padding:2px 8px;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;}

  /* ── GRID ── */
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;}

  /* ── VIDEO CARD ── */
  .card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:all .18s;display:flex;flex-direction:column;}
  .card:hover{border-color:rgba(0,201,138,.3);box-shadow:0 8px 32px rgba(0,0,0,.2);transform:translateY(-2px);}
  body.light .card:hover{box-shadow:0 8px 28px rgba(0,0,0,.08);}
  .thumb-wrap{position:relative;overflow:hidden;}
  .thumb{width:100%;height:168px;object-fit:cover;display:block;transition:transform .3s;}
  .card:hover .thumb{transform:scale(1.03);}
  .thumb-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.55) 0%,transparent 50%);display:flex;align-items:flex-end;padding:10px 12px;}
  .subject-chip{display:inline-flex;align-items:center;gap:5px;background:rgba(0,0,0,.55);backdrop-filter:blur(8px);color:#fff;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600;border:1px solid rgba(255,255,255,.1);}
  .watch-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;}
  .card:hover .watch-overlay{opacity:1;}
  .play-btn{width:52px;height:52px;border-radius:50%;background:rgba(0,201,138,.9);display:flex;align-items:center;justify-content:center;color:#000;font-size:18px;box-shadow:0 4px 16px rgba(0,0,0,.4);}
  .card-body{padding:14px 16px;display:flex;flex-direction:column;gap:10px;flex:1;}
  .card-title{font-size:14px;font-weight:700;color:var(--text);line-height:1.4;}
  .card-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
  .meta-pill{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:var(--muted2);font-family:'Space Mono',monospace;}
  .meta-pill i{font-size:10px;}
  .card-actions{display:flex;align-items:center;gap:8px;margin-top:auto;}
  .action-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:7px;border:1px solid var(--border);background:transparent;color:var(--muted2);font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;cursor:pointer;transition:all .15s;}
  .action-btn:hover{color:var(--text);background:var(--surface2);}
  .action-btn.bookmarked{background:var(--bookmark-bg);color:var(--accent2);border-color:rgba(59,130,246,.3);}
  .action-btn.completed{background:var(--completed-bg);color:var(--accent);border-color:rgba(0,201,138,.3);}
  .action-btn:disabled{opacity:.55;cursor:not-allowed;}
  .watch-btn{margin-left:auto;background:var(--accent);color:#000;border-color:var(--accent);padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:all .15s;}
  .watch-btn:hover{background:#00ffb2;border-color:#00ffb2;}

  /* ── EMPTY STATE ── */
  .empty{grid-column:1/-1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;gap:14px;text-align:center;}
  .empty-icon{width:56px;height:56px;border-radius:14px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--muted2);}
  .empty-title{font-size:16px;font-weight:700;color:var(--text);}
  .empty-sub{font-size:13px;color:var(--muted2);}

  /* ── FOOTER ── */
  .page-footer{margin-top:40px;padding:18px 0;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
  .footer-brand{font-family:'Space Mono',monospace;font-size:11px;color:var(--muted);letter-spacing:.06em;}

  /* ── RESPONSIVE ── */
  @media(max-width:600px){
    .topbar{padding:0 14px;}
    .container{padding:14px 12px;}
    .grid{grid-template-columns:1fr;}
    .brand-sub{display:none;}
    .controls-bar form{flex-wrap:wrap;}
    .search-field{width:100%;}
  }
</style>
</head>
<body>

<!-- ── TOPBAR ── -->
<nav class="topbar">
  <div class="topbar-left">
    <div class="brand-logo">ES</div>
    <div>
      <div class="brand-title">EXCELLENT SIMPLIFIED</div>
      <div class="brand-sub">Video Lessons</div>
    </div>
  </div>
  <div class="topbar-right">
    <button id="themeToggle" class="theme-toggle">
      <span id="themeIcon">🌙</span>
      <div class="toggle-track"><div class="toggle-thumb"></div></div>
      <span id="themeLabel">Dark</span>
    </button>
    <a href="../dashboard.php" class="btn-back">
      <i class="fa fa-arrow-left" style="font-size:10px"></i> Dashboard
    </a>
  </div>
</nav>

<div class="container">

  <!-- ── CONTROLS ── -->
  <div class="controls-bar">
    <form method="GET">
      <select name="subject_id" class="field" onchange="this.form.submit()">
        <option value="">All subjects</option>
        <?php if($subjectList): while($s=$subjectList->fetch_assoc()): ?>
          <option value="<?php echo (int)$s['id'] ?>" <?php if((int)$s['id']===$subject_id) echo 'selected'?>>
            <?php echo htmlspecialchars($s['name']) ?>
          </option>
        <?php endwhile; endif; ?>
      </select>
      <input class="field search-field" name="search" placeholder="Search lessons…"
             value="<?php echo htmlspecialchars($search_raw) ?>">
      <button class="btn btn-primary" type="submit">
        <i class="fa fa-search" style="font-size:11px"></i> Search
      </button>
      <?php if($search||$subject_id): ?>
        <a href="lessons.php" class="btn btn-ghost">
          <i class="fa fa-times" style="font-size:11px"></i> Clear
        </a>
      <?php endif; ?>
    </form>
  </div>

  <!-- ── SECTION HEADER ── -->
  <?php $rowCount = $res ? $res->num_rows : 0; ?>
  <div class="section-head">
    <span class="section-title"><i class="fa fa-play-circle"></i> Lessons</span>
    <span class="count-badge"><?php echo $rowCount ?> video<?php echo $rowCount!==1?'s':''; ?></span>
  </div>

  <!-- ── GRID ── -->
  <div class="grid">
    <?php if($res && $res->num_rows > 0): ?>
      <?php while($v = $res->fetch_assoc()):
        $youtube_link = $v['youtube_link'] ?? '';
        $vid = '';
        if(preg_match('/(?:youtube\.com\/.*(?:v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/i',$youtube_link,$m)) $vid=$m[1];
        elseif(preg_match('/([A-Za-z0-9_-]{11})$/',$youtube_link,$m)) $vid=$m[1];
        $thumb = $vid
          ? "https://img.youtube.com/vi/{$vid}/hqdefault.jpg"
          : "https://placehold.co/480x360/111318/5a6278?text=No+Thumbnail";
        $user_completed  = (int)$v['user_completed'];
        $user_bookmarked = (int)$v['user_bookmarked'];
      ?>
      <div class="card" id="video-card-<?php echo (int)$v['id'] ?>">
        <!-- Thumbnail -->
        <div class="thumb-wrap">
          <a href="watch_video.php?id=<?php echo (int)$v['id'] ?>">
            <img class="thumb" src="<?php echo htmlspecialchars($thumb) ?>" alt="<?php echo htmlspecialchars($v['title']) ?>" loading="lazy">
            <div class="thumb-overlay">
              <span class="subject-chip">
                <i class="fa fa-book-open" style="font-size:9px"></i>
                <?php echo htmlspecialchars($v['subject'] ?? 'General') ?>
              </span>
            </div>
            <div class="watch-overlay">
              <div class="play-btn"><i class="fa fa-play" style="margin-left:3px"></i></div>
            </div>
          </a>
        </div>

        <!-- Body -->
        <div class="card-body">
          <div class="card-title"><?php echo htmlspecialchars($v['title']) ?></div>
          <div class="card-meta">
            <?php if((int)$v['completed_count']>0): ?>
              <span class="meta-pill"><i class="fa fa-check-circle" style="color:var(--accent)"></i> <?php echo (int)$v['completed_count'] ?> completed</span>
            <?php endif; ?>
            <?php if((int)$v['bookmarks_count']>0): ?>
              <span class="meta-pill"><i class="fa fa-bookmark" style="color:var(--accent2)"></i> <?php echo (int)$v['bookmarks_count'] ?> saved</span>
            <?php endif; ?>
          </div>
          <div class="card-actions">
            <button class="action-btn bookmark-btn <?php echo $user_bookmarked?'bookmarked':'' ?>"
                    data-video="<?php echo (int)$v['id'] ?>">
              <i class="fa fa-bookmark" style="font-size:11px"></i>
              <?php echo $user_bookmarked?'Saved':'Save' ?>
            </button>
            <button class="action-btn complete-btn <?php echo $user_completed?'completed':'' ?>"
                    data-video="<?php echo (int)$v['id'] ?>"
                    <?php echo $user_completed?'disabled':'' ?>>
              <i class="fa fa-check" style="font-size:11px"></i>
              <?php echo $user_completed?'Done':'Mark done' ?>
            </button>
            <a class="watch-btn" href="watch_video.php?id=<?php echo (int)$v['id'] ?>">
              <i class="fa fa-play" style="font-size:10px"></i> Watch
            </a>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty">
        <div class="empty-icon"><i class="fa fa-video-slash"></i></div>
        <div class="empty-title">No lessons found</div>
        <div class="empty-sub">Try a different subject or clear the search filter.</div>
        <a href="lessons.php" class="btn btn-ghost">Clear filters</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- FOOTER -->
  <div class="page-footer">
    <span class="footer-brand">EXCELLENT SIMPLIFIED <span style="color:var(--accent)">◆</span> Lessons</span>
    <span style="font-size:11px;color:var(--muted);font-family:'Space Mono',monospace"><?php echo $rowCount ?> video<?php echo $rowCount!==1?'s':'' ?></span>
  </div>
</div>

<script>
/* ── THEME ── */
(function(){
  if(localStorage.getItem('es_theme')==='light') document.body.classList.add('light');
  syncTheme();
})();
function syncTheme(){
  const isLight = document.body.classList.contains('light');
  document.getElementById('themeIcon').textContent  = isLight ? '☀️' : '🌙';
  document.getElementById('themeLabel').textContent = isLight ? 'Light' : 'Dark';
}
document.getElementById('themeToggle').addEventListener('click',function(){
  const isLight = document.body.classList.toggle('light');
  localStorage.setItem('es_theme', isLight ? 'light' : 'dark');
  syncTheme();
});

/* ── BOOKMARK / COMPLETE ── */
async function postForm(url, data){
  const res = await fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)});
  try{ return await res.json(); } catch(e){ return {success:false,error:'Invalid server response'}; }
}

async function handleBookmarkBtn(btn){
  btn.disabled=true;
  try{
    const resp = await postForm('../api/bookmark.php',{video_id:btn.dataset.video});
    if(resp.success){
      const bm = resp.bookmarked;
      btn.innerHTML = `<i class="fa fa-bookmark" style="font-size:11px"></i> ${bm?'Saved':'Save'}`;
      btn.classList.toggle('bookmarked', bm);
    } else alert(resp.error||'Could not toggle bookmark');
  } catch(e){ alert('Network error'); }
  btn.disabled=false;
}

async function handleCompleteBtn(btn){
  btn.disabled=true;
  try{
    const resp = await postForm('../api/mark_complete.php',{video_id:btn.dataset.video,completed:1});
    if(resp.success){
      btn.innerHTML='<i class="fa fa-check" style="font-size:11px"></i> Done';
      btn.classList.add('completed');
    } else { alert(resp.error||'Could not mark complete'); btn.disabled=false; }
  } catch(e){ alert('Network error'); btn.disabled=false; }
}

document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('.bookmark-btn').forEach(b=> b.addEventListener('click',()=>handleBookmarkBtn(b)));
  document.querySelectorAll('.complete-btn').forEach(b=>{
    if(b.innerText.trim()==='✅ Completed'||b.disabled) b.disabled=true;
    b.addEventListener('click',()=>handleCompleteBtn(b));
  });
});
</script>
</body>
</html>
