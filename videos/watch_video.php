<?php
// videos/watch_video.php — Premium video watch page
// Place at ~/excellent-academy/videos/watch_video.php

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
session_start();
require_once __DIR__ . '/../config/db.php';

// ── Auth ────────────────────────────────────────────────────────
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$userRow = [];
if ($user_id) {
    $s = $conn->prepare("SELECT username, google_name, google_picture FROM users WHERE id=? LIMIT 1");
    if ($s) { $s->bind_param('i',$user_id); $s->execute(); $userRow=$s->get_result()->fetch_assoc()??[]; $s->close(); }
}
$display_name    = $_SESSION['google_name'] ?? $userRow['google_name'] ?? $userRow['username'] ?? 'Student';
$display_picture = $_SESSION['google_picture'] ?? $userRow['google_picture'] ?? null;

// ── Ensure tables ────────────────────────────────────────────────
@$conn->query("CREATE TABLE IF NOT EXISTS video_reactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  video_id INT NOT NULL,
  user_id INT NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'like',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_react(video_id, user_id, type),
  INDEX(video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

@$conn->query("CREATE TABLE IF NOT EXISTS video_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  video_id INT NOT NULL,
  user_id INT NOT NULL,
  note TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(video_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Reaction API ─────────────────────────────────────────────────
if (!empty($_REQUEST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['action'];

    if ($action === 'toggle_reaction' && $user_id) {
        $vid  = (int)($_POST['video_id'] ?? 0);
        $type = trim($_POST['type'] ?? 'like');
        if (!$vid || !in_array($type, ['like','love','fire','mindblown'], true)) {
            echo json_encode(['success'=>false]); exit;
        }
        // Check exists
        $chk = $conn->prepare("SELECT id FROM video_reactions WHERE video_id=? AND user_id=? AND type=? LIMIT 1");
        $chk->bind_param('iis',$vid,$user_id,$type); $chk->execute();
        $ex = $chk->get_result()->fetch_assoc();
        if ($ex) {
            $conn->prepare("DELETE FROM video_reactions WHERE id=?")->bind_param('i',$ex['id']) || null;
            $d = $conn->prepare("DELETE FROM video_reactions WHERE id=?"); $d->bind_param('i',$ex['id']); $d->execute();
            $done = 'removed';
        } else {
            $ins = $conn->prepare("INSERT IGNORE INTO video_reactions (video_id,user_id,type) VALUES (?,?,?)");
            $ins->bind_param('iis',$vid,$user_id,$type); $ins->execute();
            $done = 'added';
        }
        // Return all counts + my reactions
        $counts = ['like'=>0,'love'=>0,'fire'=>0,'mindblown'=>0];
        $cr = $conn->query("SELECT type, COUNT(*) AS c FROM video_reactions WHERE video_id=$vid GROUP BY type");
        if ($cr) while ($row=$cr->fetch_assoc()) $counts[$row['type']] = (int)$row['c'];
        $mine = [];
        if ($user_id) {
            $mr = $conn->query("SELECT type FROM video_reactions WHERE video_id=$vid AND user_id=$user_id");
            if ($mr) while ($row=$mr->fetch_assoc()) $mine[] = $row['type'];
        }
        echo json_encode(['success'=>true,'action'=>$done,'counts'=>$counts,'mine'=>$mine]); exit;
    }

    if ($action === 'get_reactions') {
        $vid = (int)($_GET['video_id'] ?? 0);
        $counts = ['like'=>0,'love'=>0,'fire'=>0,'mindblown'=>0];
        $cr = $conn->query("SELECT type, COUNT(*) AS c FROM video_reactions WHERE video_id=$vid GROUP BY type");
        if ($cr) while ($row=$cr->fetch_assoc()) $counts[$row['type']] = (int)$row['c'];
        $mine = [];
        if ($user_id) {
            $mr = $conn->query("SELECT type FROM video_reactions WHERE video_id=$vid AND user_id=$user_id");
            if ($mr) while ($row=$mr->fetch_assoc()) $mine[] = $row['type'];
        }
        echo json_encode(['success'=>true,'counts'=>$counts,'mine'=>$mine]); exit;
    }

    if ($action === 'save_note' && $user_id) {
        $vid  = (int)($_POST['video_id'] ?? 0);
        $note = substr(trim($_POST['note'] ?? ''), 0, 2000);
        if ($vid && $note) {
            $ins = $conn->prepare("INSERT INTO video_notes (video_id,user_id,note) VALUES (?,?,?)");
            $ins->bind_param('iis',$vid,$user_id,$note); $ins->execute();
            echo json_encode(['success'=>true,'id'=>$conn->insert_id]); exit;
        }
        echo json_encode(['success'=>false]); exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action']); exit;
}

// ── Fetch video ──────────────────────────────────────────────────
if (!isset($_GET['id'])) { header('Location: lessons.php'); exit; }
$video_id = (int)$_GET['id'];

$q = $conn->prepare("SELECT v.*, s.name AS subject FROM videos v LEFT JOIN subjects s ON s.id=v.subject_id WHERE v.id=? LIMIT 1");
$q->bind_param('i',$video_id); $q->execute();
$video = $q->get_result()->fetch_assoc();
if (!$video) { header('Location: lessons.php'); exit; }

// Extract YouTube ID
preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $video['youtube_link'], $m);
$yt_id = $m[1] ?? '';

// Recommended (same subject first, then others)
$recs = [];
$subj_id = (int)($video['subject_id'] ?? 0);
if ($subj_id) {
    $rs = $conn->prepare("SELECT id,title FROM videos WHERE id!=? AND subject_id=? ORDER BY id DESC LIMIT 6");
    $rs->bind_param('ii',$video_id,$subj_id); $rs->execute();
    $rr = $rs->get_result(); while($r=$rr->fetch_assoc()) $recs[]=$r;
}
if (count($recs) < 8) {
    $need = 8 - count($recs);
    $excludeIds = array_merge([$video_id], array_column($recs,'id'));
    $inList = implode(',', $excludeIds);
    $extra = $conn->query("SELECT id,title FROM videos WHERE id NOT IN ($inList) ORDER BY id DESC LIMIT $need");
    if ($extra) while($r=$extra->fetch_assoc()) $recs[]=$r;
}

// ── Extract YouTube video ID from link for recommended thumbnails
function ytId(string $link): string {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $link, $m);
    return $m[1] ?? '';
}

// Fetch recommended thumbnails
$recData = [];
foreach ($recs as $r) {
    // We need the youtube_link for each rec to get thumbnail
    $rv = $conn->prepare("SELECT youtube_link, title FROM videos WHERE id=? LIMIT 1");
    $rv->bind_param('i',$r['id']); $rv->execute();
    $rd = $rv->get_result()->fetch_assoc();
    $recData[] = [
        'id'    => $r['id'],
        'title' => $r['title'],
        'yt_id' => $rd ? ytId($rd['youtube_link']) : '',
    ];
}

// Initial reaction counts
$initCounts = ['like'=>0,'love'=>0,'fire'=>0,'mindblown'=>0];
$cr = $conn->query("SELECT type, COUNT(*) AS c FROM video_reactions WHERE video_id=$video_id GROUP BY type");
if ($cr) while ($row=$cr->fetch_assoc()) $initCounts[$row['type']] = (int)$row['c'];
$initMine = [];
if ($user_id) {
    $mr = $conn->query("SELECT type FROM video_reactions WHERE video_id=$video_id AND user_id=$user_id");
    if ($mr) while ($row=$mr->fetch_assoc()) $initMine[] = $row['type'];
}

// ── YouTube API key (add yours here for comments) ─────────────────
// Get a free key at https://console.cloud.google.com/
define('YT_API_KEY', 'AIzaSyBfnGXY8WU3ukablTQhLCL7DmNwn29GKA4');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($video['title'])?> — Excellent Simplified</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* ═══ TOKENS ═══ */
:root{
  --bg:#09080f;
  --s1:#0f0e1a;
  --s2:#161525;
  --s3:#1e1c30;
  --s4:#252240;
  --border:rgba(255,255,255,.06);
  --border2:rgba(255,255,255,.12);
  --gold:#f5c842;
  --gold2:#e8a820;
  --blue:#4f9cf9;
  --teal:#2dd4bf;
  --danger:#f87171;
  --text:#eeeaf8;
  --sub:#9490b5;
  --dim:#4e4a6a;
  --ff-head:'Playfair Display',Georgia,serif;
  --ff-body:'DM Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:var(--ff-body);
  background:var(--bg);
  color:var(--text);
  min-height:100vh;
  -webkit-font-smoothing:antialiased;
  /* Grain overlay */
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
}

/* ─ Scrollbar ─ */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:var(--s1)}
::-webkit-scrollbar-thumb{background:var(--s4);border-radius:3px}

/* ─ TOPBAR ─ */
.topbar{
  position:sticky;top:0;z-index:60;
  height:56px;
  background:rgba(9,8,15,.88);
  backdrop-filter:blur(20px);
  -webkit-backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 20px;gap:12px;
}
.tb-logo{
  display:flex;align-items:center;gap:10px;flex:1;text-decoration:none;
}
.tb-logo-mark{
  width:32px;height:32px;border-radius:8px;
  background:linear-gradient(135deg,var(--gold),var(--gold2));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--ff-head);font-size:14px;font-weight:800;color:#000;
  box-shadow:0 0 18px rgba(245,200,66,.35);
}
.tb-logo-text{
  font-family:var(--ff-head);font-size:15px;font-weight:700;color:var(--text);
  letter-spacing:-.01em;
}
.tb-logo-sub{font-size:10px;color:var(--sub);letter-spacing:.06em;text-transform:uppercase;font-family:var(--ff-body)}
.tb-right{display:flex;gap:7px;align-items:center}
.tb-btn{
  width:32px;height:32px;border-radius:8px;background:var(--s3);
  border:1px solid var(--border);color:var(--sub);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:12px;text-decoration:none;transition:all .15s;
}
.tb-btn:hover{color:var(--text);border-color:var(--border2)}
.user-chip{
  display:flex;align-items:center;gap:7px;padding:4px 12px 4px 6px;
  background:var(--s2);border:1px solid var(--border);border-radius:20px;
  font-size:12px;font-weight:500;
}
.user-chip img{width:24px;height:24px;border-radius:6px;object-fit:cover;flex-shrink:0}
.uc-av{
  width:24px;height:24px;border-radius:6px;flex-shrink:0;
  background:linear-gradient(135deg,var(--gold),var(--gold2));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--ff-head);font-size:10px;font-weight:700;color:#000;
}

/* ─ LAYOUT ─ */
.page{max-width:1220px;margin:0 auto;padding:20px 16px 60px}
.grid{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start}
@media(max-width:900px){.grid{grid-template-columns:1fr}}

/* ─ VIDEO PLAYER ─ */
.player-wrap{
  border-radius:14px;overflow:hidden;
  background:#000;
  box-shadow:0 20px 60px rgba(0,0,0,.8),0 0 0 1px var(--border);
  position:relative;
}
.player-wrap iframe{
  width:100%;display:block;
  aspect-ratio:16/9;border:none;
}

/* ─ META ROW ─ */
.meta-row{
  padding:16px 0 12px;
  border-bottom:1px solid var(--border);
  margin-bottom:14px;
}
.meta-subject{
  display:inline-flex;align-items:center;gap:6px;
  padding:4px 12px;border-radius:20px;
  background:rgba(79,156,249,.1);border:1px solid rgba(79,156,249,.25);
  font-size:11px;font-weight:600;color:var(--blue);
  letter-spacing:.04em;text-transform:uppercase;margin-bottom:10px;
}
.meta-title{
  font-family:var(--ff-head);
  font-size:clamp(18px,3vw,24px);
  font-weight:700;
  color:var(--text);
  line-height:1.35;
  margin-bottom:10px;
}
.meta-bottom{
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;
}
.meta-views{font-size:12px;color:var(--sub)}

/* ─ REACTIONS ─ */
.reactions{display:flex;gap:8px;flex-wrap:wrap}
.react-btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:8px 16px;border-radius:22px;
  border:1.5px solid var(--border);background:var(--s2);
  cursor:pointer;font-family:var(--ff-body);font-size:13px;font-weight:600;
  color:var(--sub);transition:all .2s cubic-bezier(.34,1.56,.64,1);
  user-select:none;position:relative;overflow:hidden;
}
.react-btn::before{
  content:'';position:absolute;inset:0;opacity:0;transition:opacity .2s;
}
.react-btn:hover{transform:translateY(-2px) scale(1.05);color:var(--text)}
.react-btn.reacted{color:var(--text)}
.react-btn.r-like.reacted{background:rgba(79,156,249,.12);border-color:rgba(79,156,249,.45);color:var(--blue)}
.react-btn.r-love.reacted{background:rgba(248,113,113,.1);border-color:rgba(248,113,113,.4);color:var(--danger)}
.react-btn.r-fire.reacted{background:rgba(245,200,66,.1);border-color:rgba(245,200,66,.4);color:var(--gold)}
.react-btn.r-mindblown.reacted{background:rgba(45,212,191,.1);border-color:rgba(45,212,191,.4);color:var(--teal)}
.react-emoji{font-size:17px;line-height:1;transition:transform .3s cubic-bezier(.34,1.56,.64,1)}
.react-btn:hover .react-emoji{transform:scale(1.3) rotate(-5deg)}
.react-btn.reacted .react-emoji{transform:scale(1.15)}
.react-count{font-size:12px;min-width:14px;text-align:center}

/* Reaction pop animation */
@keyframes reactPop{0%{transform:scale(1)}40%{transform:scale(1.4)}100%{transform:scale(1)}}
.react-btn.popping .react-emoji{animation:reactPop .35s cubic-bezier(.34,1.56,.64,1)}

/* ─ TABS ─ */
.tabs{display:flex;gap:0;border-bottom:1px solid var(--border);margin-bottom:18px}
.tab-btn{
  padding:10px 18px;font-family:var(--ff-body);font-size:13px;font-weight:600;
  color:var(--sub);background:none;border:none;cursor:pointer;
  border-bottom:2px solid transparent;margin-bottom:-1px;transition:all .15s;
}
.tab-btn:hover{color:var(--text)}
.tab-btn.active{color:var(--gold);border-bottom-color:var(--gold)}
.tab-panel{display:none;animation:fadeTab .2s ease}
.tab-panel.active{display:block}
@keyframes fadeTab{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}

/* ─ YOUTUBE COMMENTS ─ */
.yt-comments-wrap{min-height:200px}
.yt-comment{
  display:flex;align-items:flex-start;gap:11px;
  padding:14px 0;border-bottom:1px solid var(--border);
  animation:fadeTab .2s ease both;
}
.yt-comment:last-child{border-bottom:none}
.yt-av{
  width:36px;height:36px;border-radius:50%;flex-shrink:0;overflow:hidden;
  background:var(--s3);display:flex;align-items:center;justify-content:center;
  font-family:var(--ff-head);font-size:14px;font-weight:700;color:var(--sub);
}
.yt-av img{width:100%;height:100%;object-fit:cover}
.yt-body{flex:1;min-width:0}
.yt-author{font-size:13px;font-weight:600;color:var(--text);margin-bottom:4px;display:flex;align-items:center;gap:8px}
.yt-date{font-size:11px;color:var(--dim)}
.yt-text{font-size:13.5px;color:var(--sub);line-height:1.65}
.yt-likes{font-size:12px;color:var(--dim);margin-top:6px;display:flex;align-items:center;gap:5px}
.yt-load-more{
  width:100%;padding:11px;margin-top:10px;border-radius:10px;
  border:1px solid var(--border);background:var(--s2);
  color:var(--sub);font-family:var(--ff-body);font-size:13px;font-weight:600;
  cursor:pointer;transition:all .15s;
}
.yt-load-more:hover{color:var(--text);border-color:var(--border2)}
.yt-no-key{
  padding:28px;text-align:center;color:var(--dim);
  background:var(--s1);border-radius:12px;border:1px solid var(--border);
}
.yt-no-key a{color:var(--blue);text-decoration:none}
.yt-no-key a:hover{text-decoration:underline}
.comment-spinner{text-align:center;padding:32px;color:var(--sub)}
.comment-spinner i{font-size:22px;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* ─ NOTES SECTION ─ */
.note-form textarea{
  width:100%;background:var(--s2);border:1.5px solid var(--border);border-radius:10px;
  color:var(--text);font-family:var(--ff-body);font-size:14px;padding:11px 13px;
  outline:none;resize:vertical;min-height:80px;transition:border-color .15s;
}
.note-form textarea:focus{border-color:rgba(245,200,66,.4);box-shadow:0 0 0 3px rgba(245,200,66,.06)}
.note-form textarea::placeholder{color:var(--dim)}
.note-submit{
  margin-top:8px;padding:9px 22px;border-radius:9px;border:none;
  background:linear-gradient(135deg,var(--gold),var(--gold2));color:#000;
  font-family:var(--ff-body);font-size:13px;font-weight:700;cursor:pointer;
  transition:all .18s;
}
.note-submit:hover{filter:brightness(1.08);transform:translateY(-1px)}
.note-item{
  padding:11px 13px;background:var(--s2);border-radius:9px;border:1px solid var(--border);
  margin-top:9px;font-size:13px;color:var(--sub);line-height:1.6;
  border-left:3px solid var(--gold);
}
.note-time{font-size:10px;color:var(--dim);margin-top:4px;font-family:var(--ff-body)}
.note-msg{display:none;margin-top:7px;font-size:12px;color:var(--teal)}

/* ─ SIDEBAR ─ */
.sidebar-section{
  background:var(--s1);border:1px solid var(--border);
  border-radius:14px;overflow:hidden;margin-bottom:14px;
}
.ss-head{
  padding:12px 15px;border-bottom:1px solid var(--border);
  font-family:var(--ff-head);font-size:13px;font-weight:700;color:var(--text);
  display:flex;align-items:center;gap:8px;
}
.ss-head .badge{
  font-family:var(--ff-body);font-size:10px;font-weight:700;
  padding:2px 8px;border-radius:20px;
  background:rgba(245,200,66,.12);color:var(--gold);
  letter-spacing:.04em;
}

/* Recommended card */
.rec-card{
  display:flex;align-items:flex-start;gap:10px;
  padding:10px 13px;cursor:pointer;
  border-bottom:1px solid var(--border);
  transition:background .15s;
  text-decoration:none;
}
.rec-card:last-child{border-bottom:none}
.rec-card:hover{background:var(--s2)}
.rec-thumb{
  width:88px;height:52px;border-radius:7px;
  flex-shrink:0;object-fit:cover;background:var(--s3);
  border:1px solid var(--border);
}
.rec-info{flex:1;min-width:0}
.rec-title{
  font-size:12.5px;font-weight:600;color:var(--text);line-height:1.45;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
}
.rec-subject{font-size:11px;color:var(--sub);margin-top:3px}
.rec-card:hover .rec-title{color:var(--gold)}

/* ─ PROGRESS BAR ─ */
.video-progress{
  height:3px;background:var(--s3);margin:0;flex-shrink:0;
}
.vp-fill{
  height:100%;
  background:linear-gradient(90deg,var(--gold),var(--gold2));
  width:0%;transition:width .5s ease;
}

/* ─ FLOATING WATCH BADGE ─ */
.watch-badge{
  position:fixed;bottom:20px;right:20px;z-index:50;
  display:flex;align-items:center;gap:8px;
  padding:9px 16px;border-radius:22px;
  background:rgba(9,8,15,.9);border:1px solid var(--border2);
  backdrop-filter:blur(12px);
  font-size:12px;font-weight:600;color:var(--sub);
  box-shadow:0 8px 24px rgba(0,0,0,.4);
  animation:slideUp .4s 1s both cubic-bezier(.22,1,.36,1);
  transition:transform .2s;
}
.watch-badge:hover{transform:translateY(-2px)}
.watch-badge .wb-dot{width:8px;height:8px;border-radius:50%;background:var(--danger);animation:wpulse 2s infinite}
@keyframes wpulse{0%,100%{box-shadow:0 0 0 0 rgba(248,113,113,.5)}60%{box-shadow:0 0 0 5px rgba(248,113,113,0)}}
@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}

/* ─ LOAD ANIMATIONS ─ */
.video-col{animation:pageIn .5s .05s both cubic-bezier(.22,1,.36,1)}
.sidebar{animation:pageIn .5s .15s both cubic-bezier(.22,1,.36,1)}
@keyframes pageIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}

/* ─ EMPTY STATES ─ */
.no-login{
  padding:24px;text-align:center;color:var(--sub);
  background:var(--s1);border-radius:12px;border:1px solid var(--border);
}
.no-login a{color:var(--gold);text-decoration:none;font-weight:600}
.no-login a:hover{text-decoration:underline}

@media(max-width:600px){
  .page{padding:12px 10px 50px}
  .meta-title{font-size:17px}
  .react-btn{padding:7px 12px;font-size:12px}
  .topbar{padding:0 12px}
}
</style>
</head>
<body>

<!-- ══ TOPBAR ══ -->
<nav class="topbar">
  <a href="../index.php" class="tb-logo">
    <div class="tb-logo-mark">ES</div>
    <div>
      <div class="tb-logo-text">Excellent</div>
      <div class="tb-logo-sub">Simplified</div>
    </div>
  </a>
  <div class="tb-right">
    <?php if($user_id): ?>
    <div class="user-chip">
      <?php if($display_picture):?>
        <img src="<?=htmlspecialchars($display_picture)?>" alt="">
      <?php else:?>
        <div class="uc-av"><?=htmlspecialchars(mb_strtoupper(mb_substr($display_name,0,1)))?></div>
      <?php endif;?>
      <span><?=htmlspecialchars($display_name)?></span>
    </div>
    <?php endif; ?>
    <a href="lessons.php" class="tb-btn" title="All Lessons"><i class="fa fa-grid-2"></i></a>
    <a href="../dashboard.php" class="tb-btn" title="Dashboard"><i class="fa fa-house"></i></a>
  </div>
</nav>

<div class="page">
  <div class="grid">

    <!-- ══ VIDEO COLUMN ══ -->
    <div class="video-col">

      <!-- Player -->
      <div class="player-wrap">
        <div class="video-progress"><div class="vp-fill" id="vpFill"></div></div>
        <iframe
          id="ytPlayer"
          src="https://www.youtube.com/embed/<?=htmlspecialchars($yt_id)?>?enablejsapi=1&rel=0&modestbranding=1&color=white"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen>
        </iframe>
      </div>

      <!-- Meta -->
      <div class="meta-row">
        <?php if($video['subject']): ?>
        <div class="meta-subject"><i class="fa fa-book-open" style="font-size:10px"></i><?=htmlspecialchars($video['subject'])?></div>
        <?php endif; ?>
        <div class="meta-title"><?=htmlspecialchars($video['title'])?></div>
        <div class="meta-bottom">
          <div class="meta-views"><i class="fa fa-play-circle" style="margin-right:5px;opacity:.5"></i>Excellent Simplified · <?=htmlspecialchars($video['subject']??'General')?></div>
          <!-- Reactions -->
          <div class="reactions" id="reactions">
            <?php
            $reactDefs = [
              'like'      => ['👍','Like',    'r-like'],
              'love'      => ['❤️','Love',    'r-love'],
              'fire'      => ['🔥','Fire',    'r-fire'],
              'mindblown' => ['🤯','Wow',     'r-mindblown'],
            ];
            foreach($reactDefs as $type => [$emoji,$label,$cls]):
              $count  = $initCounts[$type];
              $reacted= in_array($type,$initMine,true) ? ' reacted' : '';
            ?>
            <button class="react-btn <?=$cls?><?=$reacted?>" data-type="<?=$type?>" onclick="toggleReact('<?=$type?>')">
              <span class="react-emoji"><?=$emoji?></span>
              <span><?=$label?></span>
              <span class="react-count" id="rc-<?=$type?>"><?=$count?></span>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Tabs: Comments / Notes -->
      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('comments',this)">
          <i class="fa fa-comments" style="margin-right:6px;font-size:11px"></i>YouTube Comments
        </button>
        <?php if($user_id): ?>
        <button class="tab-btn" onclick="switchTab('notes',this)">
          <i class="fa fa-pen-to-square" style="margin-right:6px;font-size:11px"></i>My Notes
        </button>
        <?php endif; ?>
      </div>

      <!-- Tab: Comments -->
      <div class="tab-panel active" id="tab-comments">
        <div class="yt-comments-wrap" id="ytComments">
          <?php if(!YT_API_KEY): ?>
          <div class="yt-no-key">
            <div style="font-size:28px;margin-bottom:10px">💬</div>
            <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:6px">YouTube Comments</div>
            <div style="font-size:13px;line-height:1.65">
              To show real YouTube comments, add your<br>
              <a href="https://console.cloud.google.com/" target="_blank">YouTube Data API v3 key</a>
              in <code style="background:var(--s3);padding:1px 6px;border-radius:4px;font-size:12px">watch_video.php</code>
              (line with <code style="background:var(--s3);padding:1px 6px;border-radius:4px;font-size:12px">YT_API_KEY</code>)
            </div>
            <div style="margin-top:14px">
              <a href="https://www.youtube.com/watch?v=<?=htmlspecialchars($yt_id)?>" target="_blank" style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:rgba(255,0,0,.1);border:1px solid rgba(255,0,0,.3);border-radius:9px;color:#f87171;font-size:13px;font-weight:600;text-decoration:none">
                <i class="fa-brands fa-youtube"></i> View comments on YouTube
              </a>
            </div>
          </div>
          <?php else: ?>
          <div class="comment-spinner"><i class="fa fa-spinner"></i></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Tab: Notes -->
      <?php if($user_id): ?>
      <div class="tab-panel" id="tab-notes">
        <div class="note-form">
          <textarea id="noteInput" placeholder="Write a note for this lesson… timestamps, key points, questions…"></textarea>
          <div style="display:flex;align-items:center;gap:10px">
            <button class="note-submit" onclick="saveNote()">
              <i class="fa fa-save" style="font-size:11px"></i> Save Note
            </button>
            <span class="note-msg" id="noteMsg">✓ Note saved!</span>
          </div>
        </div>
        <div id="notesList">
          <!-- Notes loaded by JS or shown empty -->
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /video-col -->

    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar">

      <!-- Up next / recommended -->
      <div class="sidebar-section">
        <div class="ss-head">
          <i class="fa fa-list-ul" style="color:var(--gold);font-size:12px"></i>
          More Lessons
          <span class="badge"><?=count($recData)?> videos</span>
        </div>
        <?php foreach($recData as $r):
          $thumb = $r['yt_id']
            ? "https://img.youtube.com/vi/{$r['yt_id']}/mqdefault.jpg"
            : '';
        ?>
        <a class="rec-card" href="watch_video.php?id=<?=(int)$r['id']?>">
          <?php if($thumb): ?>
          <img class="rec-thumb" src="<?=htmlspecialchars($thumb)?>" alt="" loading="lazy" onerror="this.style.display='none'">
          <?php else: ?>
          <div class="rec-thumb" style="display:flex;align-items:center;justify-content:center">
            <i class="fa fa-play" style="color:var(--dim);font-size:16px"></i>
          </div>
          <?php endif; ?>
          <div class="rec-info">
            <div class="rec-title"><?=htmlspecialchars($r['title'])?></div>
          </div>
        </a>
        <?php endforeach; ?>
        <?php if(empty($recData)): ?>
        <div style="padding:20px;text-align:center;color:var(--dim);font-size:13px">No more lessons yet</div>
        <?php endif; ?>
      </div>

      <?php if(!$user_id): ?>
      <div class="sidebar-section">
        <div style="padding:18px;text-align:center">
          <div style="font-size:22px;margin-bottom:8px">🎓</div>
          <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:6px">Track Your Progress</div>
          <div style="font-size:12px;color:var(--sub);margin-bottom:14px;line-height:1.6">Log in to save notes, react to lessons, and track your learning.</div>
          <a href="../login.php" style="display:block;padding:10px;border-radius:9px;background:linear-gradient(135deg,var(--gold),var(--gold2));color:#000;font-weight:700;font-size:13px;text-decoration:none;text-align:center">Log In / Sign Up</a>
        </div>
      </div>
      <?php endif; ?>

    </aside>

  </div>
</div>

<!-- Floating "now watching" badge -->
<div class="watch-badge" id="watchBadge" style="display:none">
  <span class="wb-dot"></span>
  <span id="watchBadgeTime">0:00</span>
  watching
</div>

<script>
/* ════════ CONFIG ════════ */
const VIDEO_ID  = <?=(int)$video_id?>;
const YT_ID     = <?=json_encode($yt_id)?>;
const YT_KEY    = <?=json_encode(YT_API_KEY)?>;
const IS_LOGGED = <?=$user_id?'true':'false'?>;
const API       = 'watch_video.php';

/* ════════ REACTIONS ════════ */
async function toggleReact(type) {
  if (!IS_LOGGED) { window.location.href = '../login.php'; return; }
  const btn = document.querySelector(`.react-btn[data-type="${type}"]`);
  btn.classList.add('popping');
  setTimeout(() => btn.classList.remove('popping'), 400);

  const fd = new FormData();
  fd.append('video_id', VIDEO_ID);
  fd.append('type', type);

  try {
    const r = await fetch(`${API}?action=toggle_reaction`, { method:'POST', body:fd });
    const j = await r.json();
    if (!j.success) return;

    // Update all buttons
    document.querySelectorAll('.react-btn').forEach(b => {
      const t  = b.dataset.type;
      const cnt = j.counts[t] ?? 0;
      b.querySelector('.react-count').textContent = cnt;
      b.classList.toggle('reacted', j.mine.includes(t));
    });
  } catch(e) {}
}

/* ════════ TAB SWITCHING ════════ */
function switchTab(name, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  const panel = document.getElementById('tab-'+name);
  if (panel) panel.classList.add('active');
  if (name === 'comments' && YT_KEY && !window._commentsLoaded) {
    loadYTComments();
  }
}

/* ════════ YOUTUBE COMMENTS ════════ */
let ytNextPageToken = null;
window._commentsLoaded = false;

async function loadYTComments(pageToken = null) {
  if (!YT_KEY || !YT_ID) return;
  const container = document.getElementById('ytComments');
  if (!pageToken) {
    container.innerHTML = '<div class="comment-spinner"><i class="fa fa-spinner"></i></div>';
  }

  const url = new URL('https://www.googleapis.com/youtube/v3/commentThreads');
  url.searchParams.set('part',        'snippet');
  url.searchParams.set('videoId',     YT_ID);
  url.searchParams.set('key',         YT_KEY);
  url.searchParams.set('maxResults',  '20');
  url.searchParams.set('order',       'relevance');
  if (pageToken) url.searchParams.set('pageToken', pageToken);

  try {
    const res  = await fetch(url);
    const data = await res.json();

    if (data.error) {
      container.innerHTML = `
        <div class="yt-no-key">
          <div style="font-size:13px;color:var(--danger);margin-bottom:8px">⚠️ YouTube API Error</div>
          <div style="font-size:12px;color:var(--sub)">${esc(data.error.message)}</div>
          <div style="margin-top:10px">
            <a href="https://www.youtube.com/watch?v=${YT_ID}" target="_blank" style="color:var(--blue)">
              View comments on YouTube →
            </a>
          </div>
        </div>`;
      return;
    }

    if (!pageToken) container.innerHTML = '';
    window._commentsLoaded = true;
    ytNextPageToken = data.nextPageToken || null;

    const items = data.items || [];
    if (!items.length) {
      container.innerHTML = '<div style="padding:24px;text-align:center;color:var(--dim);font-size:13px">No comments found for this video.</div>';
      return;
    }

    items.forEach((item, i) => {
      const s   = item.snippet.topLevelComment.snippet;
      const div = document.createElement('div');
      div.className = 'yt-comment';
      div.style.animationDelay = (i * 40) + 'ms';
      const authorInitial = (s.authorDisplayName || '?').trim().charAt(0).toUpperCase();
      div.innerHTML = `
        <div class="yt-av">
          ${s.authorProfileImageUrl
            ? `<img src="${esc(s.authorProfileImageUrl)}" alt="" onerror="this.parentElement.textContent='${authorInitial}'">`
            : authorInitial}
        </div>
        <div class="yt-body">
          <div class="yt-author">
            ${esc(s.authorDisplayName)}
            <span class="yt-date">${formatDate(s.publishedAt)}</span>
          </div>
          <div class="yt-text">${linkify(esc(s.textOriginal))}</div>
          <div class="yt-likes">
            <i class="fa fa-thumbs-up" style="font-size:10px"></i>
            ${s.likeCount > 0 ? s.likeCount.toLocaleString() : ''}
            ${item.snippet.totalReplyCount > 0
              ? `<span style="margin-left:8px"><i class="fa fa-comment" style="font-size:10px"></i> ${item.snippet.totalReplyCount}</span>`
              : ''}
          </div>
        </div>`;
      container.appendChild(div);
    });

    // Load more button
    const oldMore = document.getElementById('ytLoadMore');
    if (oldMore) oldMore.remove();
    if (ytNextPageToken) {
      const moreBtn = document.createElement('button');
      moreBtn.className = 'yt-load-more';
      moreBtn.id = 'ytLoadMore';
      moreBtn.innerHTML = '<i class="fa fa-chevron-down" style="margin-right:7px;font-size:10px"></i>Load more comments';
      moreBtn.onclick = () => loadYTComments(ytNextPageToken);
      container.appendChild(moreBtn);
    }
  } catch(e) {
    container.innerHTML = `
      <div class="yt-no-key">
        <div style="font-size:13px;color:var(--sub)">Could not load comments. Check your API key or network connection.</div>
        <div style="margin-top:10px">
          <a href="https://www.youtube.com/watch?v=${YT_ID}" target="_blank" style="color:var(--blue)">View on YouTube →</a>
        </div>
      </div>`;
  }
}

/* ════════ NOTES ════════ */
async function saveNote() {
  const input = document.getElementById('noteInput');
  const note  = input.value.trim();
  if (!note) return;

  const fd = new FormData();
  fd.append('video_id', VIDEO_ID);
  fd.append('note', note);

  try {
    const r = await fetch(`${API}?action=save_note`, { method:'POST', body:fd });
    const j = await r.json();
    if (j.success) {
      const msg = document.getElementById('noteMsg');
      msg.style.display = 'block';
      setTimeout(() => msg.style.display='none', 2500);
      const list = document.getElementById('notesList');
      const item = document.createElement('div');
      item.className = 'note-item';
      item.innerHTML = `${esc(note)}<div class="note-time">Just now</div>`;
      list.prepend(item);
      input.value = '';
    }
  } catch(e) {}
}

/* ════════ YOUTUBE IFRAME API ════════ */
let ytPlayerObj = null;
let watchInterval = null;
let totalDuration = 0;

window.onYouTubeIframeAPIReady = function() {
  ytPlayerObj = new YT.Player('ytPlayer', {
    events: {
      onReady: (e) => {
        totalDuration = e.target.getDuration();
        document.getElementById('watchBadge').style.display = 'flex';
      },
      onStateChange: (e) => {
        if (e.data === YT.PlayerState.PLAYING) {
          clearInterval(watchInterval);
          watchInterval = setInterval(updateProgress, 1500);
        } else {
          clearInterval(watchInterval);
        }
      }
    }
  });
};

function updateProgress() {
  if (!ytPlayerObj || typeof ytPlayerObj.getCurrentTime !== 'function') return;
  const cur   = ytPlayerObj.getCurrentTime();
  const total = ytPlayerObj.getDuration() || totalDuration || 1;
  const pct   = Math.min(100, (cur / total) * 100);
  document.getElementById('vpFill').style.width = pct + '%';
  // Floating badge time
  const m = Math.floor(cur / 60);
  const s = Math.floor(cur % 60);
  document.getElementById('watchBadgeTime').textContent = `${m}:${String(s).padStart(2,'0')}`;
}

/* Load YouTube IFrame API */
const ytScript = document.createElement('script');
ytScript.src = 'https://www.youtube.com/iframe_api';
document.head.appendChild(ytScript);

/* ════════ AUTO-LOAD COMMENTS ════════ */
if (YT_KEY) {
  setTimeout(() => {
    loadYTComments();
    window._commentsLoaded = true;
  }, 400);
}

/* ════════ UTILS ════════ */
function esc(s) {
  return s ? String(s).replace(/[&<>"']/g, c =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[c])
  ) : '';
}
function formatDate(iso) {
  try {
    const d = new Date(iso);
    const diff = Math.floor((Date.now() - d) / 1000);
    if (diff < 60)    return 'just now';
    if (diff < 3600)  return `${Math.floor(diff/60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
    if (diff < 2592000) return `${Math.floor(diff/86400)}d ago`;
    return d.toLocaleDateString([], {year:'numeric',month:'short',day:'numeric'});
  } catch { return ''; }
}
function linkify(text) {
  return text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener" style="color:var(--blue);word-break:break-all">$1</a>');
}
</script>
<!-- YouTube IFrame API is loaded dynamically above -->
</body>
</html>
