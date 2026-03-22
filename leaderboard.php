<?php
// leaderboard.php
// Improved Leaderboard: weekly ranking, subject ranking, streaks, score history, weak topics, accuracy %
// Place: ~/excellent-academy/leaderboard.php

session_start();
require_once __DIR__ . "/config/db.php";

// --- helpers (defensive) ---
function tableExists($conn, $table) {
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? null;
    if (!$db) return false;
    $sql = "SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("ss", $db, $table);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($r['c'] ?? 0) > 0;
}
function columnExists($conn, $table, $column) {
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? null;
    if (!$db) return false;
    $sql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("sss", $db, $table, $column);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($r['c'] ?? 0) > 0;
}
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'utf-8'); }

// user
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

// constants
$POINTS_PER_QUESTION = defined('POINTS_PER_QUESTION') ? POINTS_PER_QUESTION : 0.75;

// --- compute user summary (accuracy, streak) ---
$userStats = [
    'questions_answered' => 0,
    'correct' => 0,
    'accuracy' => 0.0,
    'streak_days' => 0
];

if ($user_id && tableExists($conn,'answers')) {
    $r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(is_correct),0) AS corr FROM answers WHERE user_id = $user_id");
    if ($r) {
        $row = $r->fetch_assoc();
        $userStats['questions_answered'] = (int)($row['c'] ?? 0);
        $userStats['correct'] = (int)($row['corr'] ?? 0);
        $userStats['accuracy'] = $userStats['questions_answered'] ? round(100 * $userStats['correct'] / $userStats['questions_answered'], 1) : 0.0;
    }
    // streak: distinct days in last 7 days
    $ss = $conn->prepare("SELECT COUNT(DISTINCT DATE(created_at)) AS s FROM answers WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    if ($ss) {
        $ss->bind_param('i', $user_id);
        $ss->execute();
        $userStats['streak_days'] = (int)($ss->get_result()->fetch_assoc()['s'] ?? 0);
        $ss->close();
    }
}

// --- Weekly ranking (top 20) ---
// Prefer to use scores table if present and has a date column
$weeklyRanking = [];
if (tableExists($conn,'scores') && columnExists($conn,'scores','created_at')) {
    // detect whether scores.points exists or scores.score
    $scoreCol = columnExists($conn,'scores','points') ? 'points' : (columnExists($conn,'scores','score') ? 'score' : null);
    if ($scoreCol) {
        $sql = "SELECT u.id AS user_id, u.username, COALESCE(SUM(s.$scoreCol),0) AS pts
                FROM users u
                JOIN scores s ON s.user_id = u.id
                WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY u.id
                ORDER BY pts DESC
                LIMIT 20";
        $res = $conn->query($sql);
        if ($res) while ($r = $res->fetch_assoc()) $weeklyRanking[] = $r;
    }
}
// Fallback: compute weekly ranking from answers (correct count)
if (empty($weeklyRanking) && tableExists($conn,'answers') && tableExists($conn,'questions')) {
    $sql = "SELECT u.id AS user_id, u.username, COALESCE(SUM(a.is_correct),0) AS correct_count
            FROM users u
            JOIN answers a ON a.user_id = u.id
            WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY u.id
            ORDER BY correct_count DESC
            LIMIT 20";
    $res = $conn->query($sql);
    if ($res) while ($r = $res->fetch_assoc()) $weeklyRanking[] = $r;
}

// --- Subject ranking (global accuracy by subject over last 7 days) ---
$subjectRanking = [];
if (tableExists($conn,'answers') && tableExists($conn,'questions') && tableExists($conn,'subjects')) {
    $sql = "SELECT s.id AS subject_id, s.name AS subject_name,
            COUNT(*) AS attempts,
            COALESCE(SUM(a.is_correct),0) AS corrects,
            (COALESCE(SUM(a.is_correct),0) / COUNT(*))*100 AS accuracy
            FROM answers a
            JOIN questions q ON q.id = a.question_id
            JOIN subjects s ON s.id = q.subject_id
            WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY s.id
            HAVING attempts >= 5
            ORDER BY accuracy DESC
            LIMIT 20";
    $res = $conn->query($sql);
    if ($res) while ($r = $res->fetch_assoc()) $subjectRanking[] = $r;
}

// --- Score history for current user (last 30 entries) ---
$scoreHistory = [];
if ($user_id) {
    if (tableExists($conn,'scores') && (columnExists($conn,'scores','points') || columnExists($conn,'scores','score')) && columnExists($conn,'scores','created_at')) {
        $col = columnExists($conn,'scores','points') ? 'points' : 'score';
        $sql = $conn->prepare("SELECT $col AS pts, created_at FROM scores WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
        if ($sql) {
            $sql->bind_param('i',$user_id);
            $sql->execute();
            $res = $sql->get_result();
            while ($r = $res->fetch_assoc()) $scoreHistory[] = $r;
            $sql->close();
        }
    } elseif (tableExists($conn,'answers') && tableExists($conn,'questions')) {
        // derive daily points from answers (last 30 days)
        $sql = $conn->prepare("
            SELECT DATE(a.created_at) AS day, COUNT(*) AS attempts, SUM(a.is_correct) AS corrects
            FROM answers a
            WHERE a.user_id = ? AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(a.created_at)
            ORDER BY day DESC
            LIMIT 30
        ");
        if ($sql) {
            $sql->bind_param('i',$user_id);
            $sql->execute();
            $res = $sql->get_result();
            while ($r = $res->fetch_assoc()) {
                $pts = (int)$r['corrects'] * $POINTS_PER_QUESTION;
                $scoreHistory[] = ['pts'=>$pts, 'created_at'=>$r['day']];
            }
            $sql->close();
        }
    }
}

// --- Weak topics for user (lowest accuracy subjects, require min attempts) ---
$weakTopics = [];
if ($user_id && tableExists($conn,'answers') && tableExists($conn,'questions') && tableExists($conn,'subjects')) {
    $minAttempts = 3;
    $sql = $conn->prepare("
        SELECT s.id AS subject_id, s.name AS subject_name,
               COUNT(*) AS attempts,
               SUM(a.is_correct) AS corrects,
               (SUM(a.is_correct)/COUNT(*))*100 AS accuracy
        FROM answers a
        JOIN questions q ON q.id = a.question_id
        JOIN subjects s ON s.id = q.subject_id
        WHERE a.user_id = ?
        GROUP BY s.id
        HAVING attempts >= ?
        ORDER BY accuracy ASC
        LIMIT 6
    ");
    if ($sql) {
        $sql->bind_param('ii', $user_id, $minAttempts);
        $sql->execute();
        $res = $sql->get_result();
        while ($r = $res->fetch_assoc()) $weakTopics[] = $r;
        $sql->close();
    }
}

// --- Global overall leaderboard (top 50) (best-effort: prefer scores table, fallback to total corrects) ---
$globalLeaderboard = [];
if (tableExists($conn,'scores') && (columnExists($conn,'scores','points') || columnExists($conn,'scores','score'))) {
    $col = columnExists($conn,'scores','points') ? 'points' : 'score';
    $sql = "SELECT u.id AS user_id, u.username, COALESCE(SUM(s.$col),0) AS pts
            FROM users u
            LEFT JOIN scores s ON s.user_id = u.id
            GROUP BY u.id
            ORDER BY pts DESC
            LIMIT 50";
    $res = $conn->query($sql);
    if ($res) while ($r = $res->fetch_assoc()) $globalLeaderboard[] = $r;
} elseif (tableExists($conn,'answers')) {
    $sql = "SELECT u.id AS user_id, u.username, COALESCE(SUM(a.is_correct),0) AS corrects
            FROM users u
            LEFT JOIN answers a ON a.user_id = u.id
            GROUP BY u.id
            ORDER BY corrects DESC
            LIMIT 50";
    $res = $conn->query($sql);
    if ($res) while ($r = $res->fetch_assoc()) $globalLeaderboard[] = $r;
}

// --- render page ---
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Leaderboard — Excellent Simplified</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@600;700&family=Cabinet+Grotesk:wght@500;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{-webkit-text-size-adjust:100%}
body{
  font-family:'Plus Jakarta Sans',sans-serif;
  background:#04020e;
  color:#fff;
  min-height:100vh;
  overflow-x:hidden;
}

/* ── Background blobs ── */
#bg-canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
body::after{content:'';position:fixed;inset:0;z-index:1;pointer-events:none;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
  opacity:.35}

.site{position:relative;z-index:2;max-width:1100px;margin:0 auto;padding:20px 16px 40px}

/* ── Header ── */
.page-header{
  display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:12px;
  margin-bottom:28px;
}
.back-btn{
  display:inline-flex;align-items:center;gap:8px;
  padding:9px 16px;border-radius:10px;
  background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);
  color:rgba(255,255,255,0.7);text-decoration:none;font-size:13px;font-weight:600;
  transition:all .2s;
}
.back-btn:hover{background:rgba(255,255,255,0.12);color:#fff}
.page-title{font-family:'Clash Display',sans-serif;font-size:clamp(22px,5vw,34px);font-weight:700;letter-spacing:-0.03em}
.page-title span{background:linear-gradient(90deg,#ffd700,#ff6b35);-webkit-background-clip:text;background-clip:text;color:transparent}
.page-sub{font-size:13px;color:rgba(255,255,255,0.45);margin-top:4px;font-weight:500}

/* ── My stats bar ── */
.my-stats{
  display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px;
}
.my-stat{
  display:flex;align-items:center;gap:8px;
  padding:10px 16px;border-radius:12px;
  background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);
  font-size:13px;font-weight:600;
}
.my-stat .si{font-size:18px}
.my-stat .sv{font-size:16px;font-weight:800;color:#ffd700}
.my-stat .sl{color:rgba(255,255,255,0.45);font-size:12px}

/* ── Tabs ── */
.tabs{display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap}
.tab{
  padding:9px 18px;border-radius:10px;border:1.5px solid rgba(255,255,255,0.1);
  background:rgba(255,255,255,0.04);color:rgba(255,255,255,0.5);
  font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;
  display:flex;align-items:center;gap:7px;
}
.tab:hover{background:rgba(255,255,255,0.08);color:#fff}
.tab.active{background:linear-gradient(135deg,#7c3aed,#2563eb);border-color:transparent;color:#fff;box-shadow:0 4px 16px rgba(124,58,237,0.4)}
.tab .ti{font-size:15px}

/* ── Grid ── */
.grid{display:grid;grid-template-columns:1fr 360px;gap:16px;align-items:start}
@media(max-width:860px){.grid{grid-template-columns:1fr}}

/* ── Cards ── */
.card{
  background:rgba(255,255,255,0.04);
  backdrop-filter:blur(20px) saturate(180%);
  -webkit-backdrop-filter:blur(20px) saturate(180%);
  border:1px solid rgba(255,255,255,0.08);
  border-radius:18px;overflow:hidden;
  margin-bottom:16px;
}
.card-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.07);
}
.card-title{font-family:'Cabinet Grotesk',sans-serif;font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px}
.card-title .ct{font-size:18px}
.card-badge{
  font-size:11px;font-weight:800;padding:3px 10px;border-radius:20px;
  background:rgba(255,215,0,0.15);color:#ffd700;letter-spacing:.04em;
}
.card-body{padding:16px 18px}
.empty-state{padding:24px;text-align:center;color:rgba(255,255,255,0.3);font-size:13px;font-weight:600}
.empty-state .ee{font-size:36px;margin-bottom:8px;display:block}

/* ── Leaderboard rows ── */
.lb-row{
  display:flex;align-items:center;gap:12px;
  padding:12px 18px;
  border-bottom:1px solid rgba(255,255,255,0.05);
  transition:background .15s;position:relative;
}
.lb-row:last-child{border-bottom:none}
.lb-row:hover{background:rgba(255,255,255,0.03)}
.lb-row.me{background:rgba(124,58,237,0.1);border-left:3px solid #7c3aed}

/* Rank number */
.rank{
  width:32px;text-align:center;font-family:'Cabinet Grotesk',sans-serif;
  font-size:14px;font-weight:800;color:rgba(255,255,255,0.4);flex-shrink:0;
}
.rank.r1{color:#ffd700;font-size:18px}
.rank.r2{color:#c0c0c0;font-size:17px}
.rank.r3{color:#cd7f32;font-size:16px}

/* Avatar */
.av{
  width:38px;height:38px;border-radius:50%;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-size:14px;font-weight:800;color:#fff;
  box-shadow:0 3px 10px rgba(0,0,0,0.25);
}

/* Name */
.lb-name{flex:1;min-width:0}
.lb-username{font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lb-sub{font-size:11px;color:rgba(255,255,255,0.35);margin-top:1px;font-weight:500}

/* Score */
.lb-score{text-align:right;flex-shrink:0}
.lb-pts{font-family:'Cabinet Grotesk',sans-serif;font-size:15px;font-weight:800;color:#ffd700}
.lb-label{font-size:10px;color:rgba(255,255,255,0.3);font-weight:600;margin-top:1px}

/* Top 3 crown */
.lb-row.top1{background:linear-gradient(90deg,rgba(255,215,0,0.08),transparent)}
.lb-row.top2{background:linear-gradient(90deg,rgba(192,192,192,0.06),transparent)}
.lb-row.top3{background:linear-gradient(90deg,rgba(205,127,50,0.06),transparent)}

/* ── Subject accuracy bars ── */
.subj-row{padding:12px 18px;border-bottom:1px solid rgba(255,255,255,0.05)}
.subj-row:last-child{border-bottom:none}
.subj-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.subj-name{font-size:13px;font-weight:700}
.subj-pct{font-size:13px;font-weight:800;color:#34d399}
.bar-bg{height:6px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden}
.bar-fill{height:100%;border-radius:3px;transition:width .6s ease;background:linear-gradient(90deg,#34d399,#06b6d4)}
.subj-meta{font-size:11px;color:rgba(255,255,255,0.3);margin-top:4px;font-weight:500}

/* ── Weak topics (red bars) ── */
.weak .bar-fill{background:linear-gradient(90deg,#f87171,#f59e0b)}
.weak .subj-pct{color:#f87171}

/* ── History rows ── */
.hist-row{
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 18px;border-bottom:1px solid rgba(255,255,255,0.05);
}
.hist-row:last-child{border-bottom:none}
.hist-date{font-size:12px;color:rgba(255,255,255,0.4);font-weight:600}
.hist-pts{font-size:14px;font-weight:800;color:#a78bfa}

/* ── Accuracy card ── */
.acc-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:16px 18px}
.acc-item{
  padding:14px;border-radius:12px;
  background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);
  text-align:center;
}
.acc-val{font-family:'Cabinet Grotesk',sans-serif;font-size:22px;font-weight:800;margin-bottom:4px}
.acc-lbl{font-size:11px;color:rgba(255,255,255,0.4);font-weight:600;text-transform:uppercase;letter-spacing:.05em}

/* ── Tab panels ── */
.tab-panel{display:none}
.tab-panel.active{display:block}

/* ── Mobile ── */
@media(max-width:560px){
  .page-header{flex-direction:column;align-items:flex-start}
  .my-stats{gap:8px}
  .my-stat{padding:8px 12px}
  .tabs{gap:4px}
  .tab{padding:8px 12px;font-size:12px}
  .lb-row{padding:10px 14px;gap:10px}
  .av{width:34px;height:34px;font-size:12px}
  .acc-grid{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>
<canvas id="bg-canvas"></canvas>
<div class="site">

  <!-- Header -->
  <div class="page-header">
    <a class="back-btn" href="dashboard.php"><i class="fa fa-arrow-left"></i> Dashboard</a>
    <div>
      <div class="page-title">🏆 <span>Leaderboard</span></div>
      <div class="page-sub">Weekly · All-time · Subject accuracy · Your stats</div>
    </div>
  </div>

  <!-- My stats strip -->
  <div class="my-stats">
    <?php if($user_id): ?>
    <div class="my-stat">
      <span class="si">🎯</span>
      <div><div class="sv"><?= esc($userStats['accuracy']) ?>%</div><div class="sl">Accuracy</div></div>
    </div>
    <div class="my-stat">
      <span class="si">🔥</span>
      <div><div class="sv"><?= (int)$userStats['streak_days'] ?></div><div class="sl">Day Streak</div></div>
    </div>
    <div class="my-stat">
      <span class="si">✅</span>
      <div><div class="sv"><?= (int)$userStats['correct'] ?></div><div class="sl">Correct</div></div>
    </div>
    <div class="my-stat">
      <span class="si">📝</span>
      <div><div class="sv"><?= (int)$userStats['questions_answered'] ?></div><div class="sl">Answered</div></div>
    </div>
    <?php else: ?>
    <div class="my-stat"><span class="si">👋</span><div><div class="sv" style="color:rgba(255,255,255,0.5)">Sign in</div><div class="sl">to see your stats</div></div></div>
    <?php endif; ?>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active" onclick="showTab('weekly')" id="tab-weekly"><span class="ti">📅</span> This Week</button>
    <button class="tab" onclick="showTab('global')" id="tab-global"><span class="ti">🌍</span> All Time</button>
    <button class="tab" onclick="showTab('subjects')" id="tab-subjects"><span class="ti">📚</span> Subjects</button>
    <?php if($user_id): ?>
    <button class="tab" onclick="showTab('mine')" id="tab-mine"><span class="ti">👤</span> My Stats</button>
    <?php endif; ?>
  </div>

  <!-- ── WEEKLY TAB ── -->
  <div class="tab-panel active" id="panel-weekly">
    <div class="card">
      <div class="card-head">
        <div class="card-title"><span class="ct">📅</span> Weekly Top Players</div>
        <div class="card-badge">Last 7 days</div>
      </div>
      <?php if(empty($weeklyRanking)): ?>
        <div class="empty-state"><span class="ee">😴</span>No activity this week yet — start answering questions!</div>
      <?php else: ?>
        <?php $i=1; foreach($weeklyRanking as $r):
          $name = $r['username'] ?? ('User #'.$r['user_id']);
          $pts  = isset($r['pts']) ? number_format((float)$r['pts'],2) : ((int)($r['correct_count']??0)).' correct';
          $label= isset($r['pts']) ? 'pts' : '';
          $isMe = $user_id && ($r['user_id']==$user_id);
          $topCls= $i==1?'top1':($i==2?'top2':($i==3?'top3':''));
          $rCls  = $i==1?'r1':($i==2?'r2':($i==3?'r3':''));
          $bgStyle="background:".avBg($name).";";
        ?>
        <div class="lb-row <?= $topCls ?> <?= $isMe?'me':'' ?>">
          <div class="rank <?= $rCls ?>"><?= $i==1?'🥇':($i==2?'🥈':($i==3?'🥉':$i)) ?></div>
          <div class="av" style="<?= $bgStyle ?>"><?= strtoupper(mb_substr($name,0,1)) ?></div>
          <div class="lb-name">
            <div class="lb-username"><?= esc($name) ?><?= $isMe?' <span style="font-size:10px;color:#a78bfa;font-weight:800"> YOU</span>':'' ?></div>
          </div>
          <div class="lb-score"><div class="lb-pts"><?= esc($pts) ?></div><div class="lb-label"><?= $label ?></div></div>
        </div>
        <?php $i++; endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── GLOBAL TAB ── -->
  <div class="tab-panel" id="panel-global">
    <div class="card">
      <div class="card-head">
        <div class="card-title"><span class="ct">🌍</span> All-Time Leaderboard</div>
        <div class="card-badge">Top <?= count($globalLeaderboard) ?></div>
      </div>
      <?php if(empty($globalLeaderboard)): ?>
        <div class="empty-state"><span class="ee">🏁</span>No scores yet — be the first on the board!</div>
      <?php else: ?>
        <?php $i=1; foreach($globalLeaderboard as $r):
          $name = $r['username'] ?? ('User #'.$r['user_id']);
          $pts  = isset($r['pts']) ? number_format((float)$r['pts'],2) : ((int)($r['corrects']??0)).' correct';
          $label= isset($r['pts']) ? 'pts' : '';
          $isMe = $user_id && ($r['user_id']==$user_id);
          $topCls= $i==1?'top1':($i==2?'top2':($i==3?'top3':''));
          $rCls  = $i==1?'r1':($i==2?'r2':($i==3?'r3':''));
          $bgStyle="background:".avBg($name).";";
        ?>
        <div class="lb-row <?= $topCls ?> <?= $isMe?'me':'' ?>">
          <div class="rank <?= $rCls ?>"><?= $i==1?'🥇':($i==2?'🥈':($i==3?'🥉':$i)) ?></div>
          <div class="av" style="<?= $bgStyle ?>"><?= strtoupper(mb_substr($name,0,1)) ?></div>
          <div class="lb-name">
            <div class="lb-username"><?= esc($name) ?><?= $isMe?' <span style="font-size:10px;color:#a78bfa;font-weight:800"> YOU</span>':'' ?></div>
          </div>
          <div class="lb-score"><div class="lb-pts"><?= esc($pts) ?></div><div class="lb-label"><?= $label ?></div></div>
        </div>
        <?php $i++; endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── SUBJECTS TAB ── -->
  <div class="tab-panel" id="panel-subjects">
    <div class="card">
      <div class="card-head">
        <div class="card-title"><span class="ct">📚</span> Subject Accuracy</div>
        <div class="card-badge">Last 7 days</div>
      </div>
      <?php if(empty($subjectRanking)): ?>
        <div class="empty-state"><span class="ee">📖</span>Not enough attempts yet — answer at least 5 questions per subject.</div>
      <?php else: ?>
        <?php foreach($subjectRanking as $s):
          $pct = round((float)$s['accuracy'],1);
          $width = min(100, $pct);
        ?>
        <div class="subj-row">
          <div class="subj-top">
            <div class="subj-name"><?= esc($s['subject_name']) ?></div>
            <div class="subj-pct"><?= $pct ?>%</div>
          </div>
          <div class="bar-bg"><div class="bar-fill" style="width:<?= $width ?>%"></div></div>
          <div class="subj-meta"><?= (int)$s['attempts'] ?> attempts</div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── MY STATS TAB ── -->
  <?php if($user_id): ?>
  <div class="tab-panel" id="panel-mine">
    <div class="grid">
      <div>
        <!-- Accuracy overview -->
        <div class="card">
          <div class="card-head">
            <div class="card-title"><span class="ct">📊</span> Your Performance</div>
          </div>
          <div class="acc-grid">
            <div class="acc-item">
              <div class="acc-val" style="color:#ffd700"><?= esc($userStats['accuracy']) ?>%</div>
              <div class="acc-lbl">Accuracy</div>
            </div>
            <div class="acc-item">
              <div class="acc-val" style="color:#34d399"><?= (int)$userStats['correct'] ?></div>
              <div class="acc-lbl">Correct</div>
            </div>
            <div class="acc-item">
              <div class="acc-val" style="color:#60a5fa"><?= (int)$userStats['questions_answered'] ?></div>
              <div class="acc-lbl">Answered</div>
            </div>
            <div class="acc-item">
              <div class="acc-val" style="color:#f87171"><?= (int)$userStats['streak_days'] ?></div>
              <div class="acc-lbl">Day Streak</div>
            </div>
          </div>
          <div style="padding:0 18px 16px">
            <a href="exams/practice_test.php?mode=review" style="display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:10px;background:linear-gradient(135deg,#7c3aed,#2563eb);color:#fff;text-decoration:none;font-size:13px;font-weight:700;box-shadow:0 4px 12px rgba(124,58,237,0.4)">
              <i class="fa fa-rotate-left" style="font-size:12px"></i> Review Wrong Questions
            </a>
          </div>
        </div>

        <!-- Weak topics -->
        <div class="card weak">
          <div class="card-head">
            <div class="card-title"><span class="ct">⚠️</span> Weak Topics</div>
            <div class="card-badge" style="background:rgba(248,113,113,0.15);color:#f87171">Needs work</div>
          </div>
          <?php if(empty($weakTopics)): ?>
            <div class="empty-state"><span class="ee">🎉</span>No weak spots detected yet — keep answering!</div>
          <?php else: ?>
            <?php foreach($weakTopics as $w):
              $pct = round((float)$w['accuracy'],1);
              $width = min(100, $pct);
            ?>
            <div class="subj-row">
              <div class="subj-top">
                <div class="subj-name"><?= esc($w['subject_name']) ?></div>
                <div class="subj-pct"><?= $pct ?>%</div>
              </div>
              <div class="bar-bg"><div class="bar-fill" style="width:<?= $width ?>%"></div></div>
              <div class="subj-meta"><?= (int)$w['attempts'] ?> attempts</div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Score history -->
      <div>
        <div class="card">
          <div class="card-head">
            <div class="card-title"><span class="ct">📈</span> Score History</div>
            <div class="card-badge">Last 30</div>
          </div>
          <?php if(empty($scoreHistory)): ?>
            <div class="empty-state"><span class="ee">📉</span>No history yet — start practising!</div>
          <?php else: ?>
            <?php foreach($scoreHistory as $row):
              $date = substr($row['created_at'] ?? $row['day'] ?? '', 0, 10);
              $pts  = number_format((float)($row['pts'] ?? 0), 2);
            ?>
            <div class="hist-row">
              <div class="hist-date"><?= esc($date) ?></div>
              <div class="hist-pts">+<?= esc($pts) ?> pts</div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /site -->

<script>
/* ── Tab switching ── */
function showTab(name) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('panel-' + name)?.classList.add('active');
  document.getElementById('tab-' + name)?.classList.add('active');
}

/* ── Avatar background helper ── */
<?php
function avBg($name) {
    $palettes = [
        'linear-gradient(135deg,#667eea,#764ba2)',
        'linear-gradient(135deg,#f5576c,#f093fb)',
        'linear-gradient(135deg,#4facfe,#00f2fe)',
        'linear-gradient(135deg,#43e97b,#38f9d7)',
        'linear-gradient(135deg,#fa709a,#fee140)',
        'linear-gradient(135deg,#a18cd1,#fbc2eb)',
        'linear-gradient(135deg,#fda085,#f6d365)',
        'linear-gradient(135deg,#89f7fe,#66a6ff)',
        'linear-gradient(135deg,#fd7043,#ff8a65)',
        'linear-gradient(135deg,#26c6da,#00acc1)',
    ];
    $h = 0;
    foreach (str_split($name) as $c) $h = ($h * 31 + ord($c)) & 0xffffff;
    return $palettes[abs($h) % count($palettes)];
}
?>

/* ── Blob background ── */
(function(){
  const canvas=document.getElementById('bg-canvas'),ctx=canvas.getContext('2d');
  const orbs=[
    {x:.15,y:.2, r:.38,cx:.0012,cy:.0009,color:[124,58,237], phase:0  },
    {x:.75,y:.15,r:.35,cx:.001, cy:.0013,color:[37,99,235],  phase:1.2},
    {x:.55,y:.7, r:.42,cx:.0014,cy:.001, color:[6,182,212],  phase:2.4},
    {x:.2, y:.75,r:.32,cx:.0009,cy:.0015,color:[16,185,129], phase:3.6},
    {x:.85,y:.6, r:.30,cx:.0015,cy:.0008,color:[245,158,11], phase:4.8},
    {x:.45,y:.35,r:.28,cx:.0011,cy:.0012,color:[236,72,153], phase:1.7},
  ];
  let W,H,t=0;
  function resize(){W=canvas.width=window.innerWidth;H=canvas.height=window.innerHeight;}
  window.addEventListener('resize',resize);resize();
  function draw(){
    ctx.clearRect(0,0,W,H);ctx.fillStyle='#04020e';ctx.fillRect(0,0,W,H);
    orbs.forEach(o=>{
      const px=(o.x+Math.sin(t*o.cx*100+o.phase)*0.18)*W;
      const py=(o.y+Math.cos(t*o.cy*100+o.phase*1.3)*0.16)*H;
      const radius=o.r*Math.max(W,H)*0.65;
      const g=ctx.createRadialGradient(px,py,0,px,py,radius);
      const [r,gb,b]=o.color;
      g.addColorStop(0,`rgba(${r},${gb},${b},0.18)`);
      g.addColorStop(0.4,`rgba(${r},${gb},${b},0.07)`);
      g.addColorStop(1,`rgba(${r},${gb},${b},0)`);
      ctx.fillStyle=g;ctx.fillRect(0,0,W,H);
    });
    t++;requestAnimationFrame(draw);
  }
  draw();
})();
</script>
</body>
</html>
