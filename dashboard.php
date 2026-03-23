<?php
// dashboard.php — EXCELLENT SIMPLIFIED
// Place: ~/excellent-academy/dashboard.php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) http_response_code(500);
        echo "<pre style='background:#1e1b4b;color:#f87171;padding:20px;margin:20px;border-radius:8px'>";
        echo "<b>Fatal Error:</b>\n" . htmlspecialchars($e['message']);
        echo "\nFile: " . htmlspecialchars($e['file']) . " line " . $e['line'] . "</pre>";
    }
});

ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once __DIR__ . "/config/db.php";

function tableExists($conn, $table) {
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? null;
    if (!$db) return false;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    if (!$stmt) return false;
    $stmt->bind_param("ss", $db, $table);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($r['c'] ?? 0) > 0;
}
function columnExists($conn, $table, $col) {
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? null;
    if (!$db) return false;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    if (!$stmt) return false;
    $stmt->bind_param("sss", $db, $table, $col);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($r['c'] ?? 0) > 0;
}
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'utf-8'); }

$hasSession = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;

/* ── All data fetching ── */
$user_id = 0; $display_name = ''; $display_email = ''; $display_picture = null;
$is_admin = 0; $score = 0; $rank = '-'; $points = 0.0;
$completed_lessons = 0; $videos_total = 0; $streak = 0;
$questions_answered = 0; $bookmarks = 0; $accuracy = 0;
$continue = []; $recent_activity = []; $leaderboard = [];

if ($hasSession) {
    $user_id = (int)$_SESSION['user_id'];

    $google_name    = $_SESSION['google_name']    ?? null;
    $google_picture = $_SESSION['google_picture'] ?? null;
    $google_email   = $_SESSION['google_email']   ?? null;

    $userRow = [];
    $stmt = $conn->prepare("SELECT id, username, email, google_name, google_picture, score, points FROM users WHERE id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
    }

    $is_admin = 0;
    $_aq = $conn->query("SELECT is_admin FROM users WHERE id=" . intval($user_id) . " LIMIT 1");
    if ($_aq) { $_ar = $_aq->fetch_assoc(); $is_admin = (int)($_ar['is_admin'] ?? 0); }

    $display_name    = $google_name    ?: ($userRow['google_name']    ?? $userRow['username']    ?? 'Student');
    $display_email   = $google_email   ?: ($userRow['email']          ?? '');
    $display_picture = $google_picture ?: ($userRow['google_picture'] ?? null);

    // ── Points: read from users.points (brainstorm awards 0.75 pts per correct answer)
    $points = (float)($userRow['points'] ?? 0);

    // ── Score
    if (tableExists($conn, 'scores')) {
        $useCol = columnExists($conn, 'scores', 'points') ? 'points' : (columnExists($conn, 'scores', 'score') ? 'score' : null);
        if ($useCol) {
            $stmt = $conn->prepare("SELECT COALESCE(SUM($useCol),0) AS pts FROM scores WHERE user_id=?");
            $stmt->bind_param("i", $user_id); $stmt->execute();
            $score = (int)($stmt->get_result()->fetch_assoc()['pts'] ?? 0);
            $stmt->close();
        } else { $score = (int)($userRow['score'] ?? 0); }
    } else { $score = (int)($userRow['score'] ?? 0); }

    // ── Rank
    if (tableExists($conn, 'scores')) {
        $useCol = columnExists($conn, 'scores', 'points') ? 'points' : (columnExists($conn, 'scores', 'score') ? 'score' : null);
        if ($useCol) {
            $res = $conn->query("SELECT user_id, COALESCE(SUM($useCol),0) AS pts FROM scores GROUP BY user_id ORDER BY pts DESC");
            $pos = 1;
            if ($res) { while ($r = $res->fetch_assoc()) { if ((int)$r['user_id'] === $user_id) { $rank = $pos; break; } $pos++; } if ($rank === '-') $rank = $pos; }
        } else {
            $res = $conn->query("SELECT id, score FROM users ORDER BY score DESC");
            $pos = 1;
            if ($res) { while ($r = $res->fetch_assoc()) { if ((int)$r['id'] === $user_id) { $rank = $pos; break; } $pos++; } if ($rank === '-') $rank = $pos; }
        }
    } else {
        $res = $conn->query("SELECT id, score FROM users ORDER BY score DESC");
        $pos = 1;
        if ($res) { while ($r = $res->fetch_assoc()) { if ((int)$r['id'] === $user_id) { $rank = $pos; break; } $pos++; } if ($rank === '-') $rank = $pos; }
    }

    // ── Completed lessons
    if (tableExists($conn, 'video_progress') && columnExists($conn, 'video_progress', 'completed')) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM video_progress WHERE user_id=? AND completed=1");
        if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $completed_lessons = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0); $stmt->close(); }
    }

    // ── Streak
    if (tableExists($conn, 'answers') && columnExists($conn, 'answers', 'created_at')) {
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT DATE(created_at)) AS s FROM answers WHERE user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)");
        if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $streak = (int)($stmt->get_result()->fetch_assoc()['s'] ?? 0); $stmt->close(); }
    }

    // ── Videos total
    if (tableExists($conn, 'videos')) {
        $res = $conn->query("SELECT COUNT(*) AS c FROM videos");
        $videos_total = (int)($res ? ($res->fetch_assoc()['c'] ?? 0) : 0);
    }

    // ── Questions answered + accuracy
    if (tableExists($conn, 'answers') && columnExists($conn, 'answers', 'user_id')) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(is_correct),0) AS corr FROM answers WHERE user_id=?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id); $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $questions_answered = (int)($r['cnt'] ?? 0);
            $accuracy = $questions_answered ? round(100 * ($r['corr'] ?? 0) / $questions_answered) : 0;
            $stmt->close();
        }
    }

    // ── Bookmarks
    if (tableExists($conn, 'bookmarks')) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookmarks WHERE user_id=?");
        if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $bookmarks = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0); $stmt->close(); }
    }

    // ── Continue watching
    if (tableExists($conn, 'video_progress') && tableExists($conn, 'videos')) {
        $stmt = $conn->prepare("SELECT v.id, v.title, vp.watch_percent, vp.last_watched FROM video_progress vp JOIN videos v ON v.id=vp.video_id WHERE vp.user_id=? ORDER BY vp.last_watched DESC LIMIT 6");
        if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) $continue[] = $row; $stmt->close(); }
    }

    // ── Recent activity
    if (tableExists($conn, 'answers')) {
        $stmt = $conn->prepare("SELECT a.selected_answer AS answer, a.is_correct, a.created_at, q.question FROM answers a LEFT JOIN questions q ON q.id=a.question_id WHERE a.user_id=? ORDER BY a.created_at DESC LIMIT 6");
        if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) $recent_activity[] = $row; $stmt->close(); }
    }

    // ── Leaderboard (top 5, show display_name / google_name / username)
    $leaderboard = [];
    if (tableExists($conn, 'scores')) {
        $useCol = columnExists($conn, 'scores', 'points') ? 'points' : (columnExists($conn, 'scores', 'score') ? 'score' : null);
        if ($useCol) {
            $res = $conn->query("SELECT COALESCE(u.google_name, u.username, 'Student') AS username, COALESCE(SUM(s.$useCol),0) AS pts FROM users u LEFT JOIN scores s ON s.user_id=u.id GROUP BY u.id ORDER BY pts DESC LIMIT 5");
            if ($res) while ($r = $res->fetch_assoc()) $leaderboard[] = $r;
        }
    }
    if (empty($leaderboard) && columnExists($conn, 'users', 'score')) {
        $res = $conn->query("SELECT COALESCE(google_name, username, 'Student') AS username, score AS pts FROM users ORDER BY score DESC LIMIT 5");
        if ($res) while ($r = $res->fetch_assoc()) $leaderboard[] = $r;
    }
    // Also show points-based leaderboard from users.points if above gave nothing
    if (empty($leaderboard) && columnExists($conn, 'users', 'points')) {
        $res = $conn->query("SELECT COALESCE(google_name, username, 'Student') AS username, points AS pts FROM users ORDER BY points DESC LIMIT 5");
        if ($res) while ($r = $res->fetch_assoc()) $leaderboard[] = $r;
    }
}

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// First letter for avatar fallback
$av_letter = mb_strtoupper(mb_substr($display_name, 0, 1)) ?: 'S';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — Excellent Simplified</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════
   DESIGN TOKENS
══════════════════════════════════════════════ */
:root {
  /* shared accents */
  --teal:   #00c98a;
  --blue:   #3b82f6;
  --purple: #a78bfa;
  --amber:  #f59e0b;
  --danger: #ff4757;
  --pink:   #f472b6;

  /* dark mode (default) */
  --bg:       #04020e;
  --bg2:      #0d1017;
  --surface:  #131720;
  --surface2: #1a1f2e;
  --border:   rgba(255,255,255,.07);
  --border2:  rgba(255,255,255,.14);
  --text:     #e8ecf4;
  --sub:      #8a93ab;
  --dim:      #4a5268;
  --glass-bg: rgba(255,255,255,.04);
  --glass-border: rgba(255,255,255,.09);
}

/* ── LIGHT MODE OVERRIDE ── */
body.light {
  --bg:       #eef2ff;
  --bg2:      #e8eeff;
  --surface:  rgba(255,255,255,0.75);
  --surface2: rgba(255,255,255,0.55);
  --border:   rgba(99,102,241,.12);
  --border2:  rgba(99,102,241,.25);
  --text:     #1e1b4b;
  --sub:      #6366f1;
  --dim:      #a5b4fc;
  --glass-bg: rgba(255,255,255,0.65);
  --glass-border: rgba(255,255,255,0.9);
}

/* ══════════════════════════════════════════════
   RESET & BASE
══════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
  font-family: 'Outfit', system-ui, sans-serif;
  background: var(--bg);
  color: var(--text);
  -webkit-font-smoothing: antialiased;
  transition: background .3s, color .3s;
  overflow-x: hidden;
}
body.light { background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 40%, #ede9fe 100%); }

a { color: inherit; text-decoration: none; }

/* ══════════════════════════════════════════════
   HEADER — Animated, Unique
══════════════════════════════════════════════ */
header {
  position: sticky; top: 0; z-index: 60;
  height: 64px;
  background: var(--bg2);
  border-bottom: 1px solid var(--border2);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 20px; gap: 12px;
  overflow: hidden;
  transition: background .3s, border-color .3s;
}

/* Animated shimmer stripe across header */
header::before {
  content: '';
  position: absolute; top: 0; left: -100%; width: 60%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.06), transparent);
  animation: hdrShimmer 4s ease-in-out infinite;
  pointer-events: none;
}
@keyframes hdrShimmer { 0%,100%{left:-60%} 50%{left:110%} }

body.light header {
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #2563eb 100%);
  border-bottom-color: rgba(255,255,255,.15);
}

/* Floating particle orbs behind header text */
.hdr-orbs {
  position: absolute; inset: 0; pointer-events: none; overflow: hidden;
}
.hdr-orb {
  position: absolute; border-radius: 50%;
  filter: blur(24px); animation: orbFloat 6s ease-in-out infinite;
}
.hdr-orb:nth-child(1) { width:80px;height:80px; background:rgba(0,201,138,.25); top:-20px; left:5%; animation-delay:0s; }
.hdr-orb:nth-child(2) { width:60px;height:60px; background:rgba(59,130,246,.2); top:-10px; left:45%; animation-delay:2s; }
.hdr-orb:nth-child(3) { width:70px;height:70px; background:rgba(167,114,250,.2); top:-15px; right:10%; animation-delay:4s; }
@keyframes orbFloat { 0%,100%{transform:translateY(0) scale(1)} 50%{transform:translateY(10px) scale(1.1)} }

.hdr-brand {
  display: flex; align-items: center; gap: 12px; position: relative; z-index: 1;
}
.hdr-crest {
  width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--teal), var(--blue));
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
  box-shadow: 0 0 16px rgba(0,201,138,.4);
  animation: crestPulse 3s ease-in-out infinite;
}
@keyframes crestPulse { 0%,100%{box-shadow:0 0 16px rgba(0,201,138,.4)} 50%{box-shadow:0 0 28px rgba(0,201,138,.7)} }

.hdr-text { display: flex; flex-direction: column; }
.hdr-school {
  font-family: 'Space Mono', monospace;
  font-size: 13px; font-weight: 700; letter-spacing: .06em;
  background: linear-gradient(90deg, var(--teal), var(--blue), var(--purple));
  -webkit-background-clip: text; background-clip: text; color: transparent;
  background-size: 200%; animation: gradShift 4s linear infinite;
  line-height: 1.1;
}
body.light .hdr-school { background: linear-gradient(90deg, #fff, #c7d2fe, #a5f3fc); -webkit-background-clip:text; background-clip:text; color:transparent; background-size:200%; animation:gradShift 4s linear infinite; }
@keyframes gradShift { 0%{background-position:0%} 100%{background-position:200%} }
.hdr-tagline { font-size: 10px; color: var(--sub); letter-spacing: .05em; margin-top: 1px; }
body.light .hdr-tagline { color: rgba(255,255,255,.7); }

.hdr-right {
  display: flex; align-items: center; gap: 8px; position: relative; z-index: 1;
}

/* User chip in header */
.user-chip {
  display: flex; align-items: center; gap: 7px;
  padding: 5px 12px; border-radius: 20px;
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  backdrop-filter: blur(8px);
  font-size: 12px; font-weight: 600;
  transition: all .15s;
}
body.light .user-chip { background:rgba(255,255,255,.25); border-color:rgba(255,255,255,.5); color:#fff; }
.user-chip img { width:22px;height:22px;border-radius:6px;object-fit:cover; }
.user-chip .av {
  width:22px;height:22px;border-radius:6px;
  background:linear-gradient(135deg,var(--teal),var(--blue));
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:#000;
}

.hdr-btn {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 12px; border-radius: 8px;
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  backdrop-filter: blur(8px);
  color: var(--sub); font-size: 12px; font-weight: 600;
  cursor: pointer; text-decoration: none; white-space: nowrap;
  transition: all .15s; font-family: 'Outfit', sans-serif;
}
.hdr-btn:hover { color: var(--text); border-color: var(--border2); }
body.light .hdr-btn { background:rgba(255,255,255,.2); border-color:rgba(255,255,255,.4); color:#fff; }
body.light .hdr-btn:hover { background:rgba(255,255,255,.35); }

/* Theme toggle pill */
.theme-pill {
  display: flex; align-items: center; gap: 5px;
  padding: 5px 11px; border-radius: 20px;
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  backdrop-filter: blur(8px);
  cursor: pointer; font-size: 11px; font-weight: 600;
  color: var(--sub); transition: all .15s;
  font-family: 'Outfit', sans-serif;
}
.theme-pill:hover { color: var(--text); }
body.light .theme-pill { background:rgba(255,255,255,.2); border-color:rgba(255,255,255,.4); color:rgba(255,255,255,.9); }
.theme-track { width:24px;height:13px;border-radius:20px;background:var(--dim);position:relative;transition:background .2s;flex-shrink:0; }
.theme-thumb { position:absolute;top:2px;left:2px;width:9px;height:9px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.3); }
body.light .theme-track { background:rgba(255,255,255,.5); }
body.light .theme-thumb { transform:translateX(11px); }

/* ══════════════════════════════════════════════
   WRAP & LAYOUT
══════════════════════════════════════════════ */
.wrap { max-width: 1140px; margin: 0 auto; padding: 20px 16px 60px; }

/* ── PROFILE HERO ── */
.profile-hero {
  display: flex; align-items: center; gap: 16px;
  padding: 18px 20px;
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
  border-radius: 16px;
  backdrop-filter: blur(16px);
  margin-bottom: 18px;
  animation: fadeUp .5s ease both;
  position: relative; overflow: hidden;
}
.profile-hero::before {
  content: ''; position: absolute; top: -40%; right: -10%;
  width: 200px; height: 200px; border-radius: 50%;
  background: radial-gradient(circle, rgba(0,201,138,.08), transparent 70%);
  pointer-events: none;
}
body.light .profile-hero { background:rgba(255,255,255,.6); border-color:rgba(255,255,255,.9); box-shadow:0 4px 24px rgba(99,102,241,.1); }

.profile-av {
  width: 60px; height: 60px; border-radius: 14px; flex-shrink: 0;
  overflow: hidden; border: 2px solid var(--border2);
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, var(--teal), var(--blue));
  font-family: 'Space Mono', monospace; font-size: 22px; font-weight: 700; color: #000;
}
.profile-av img { width: 100%; height: 100%; object-fit: cover; }
.profile-info { flex: 1; min-width: 0; }
.profile-greeting { font-size: 11px; color: var(--sub); text-transform: uppercase; letter-spacing: .06em; }
.profile-name { font-size: 20px; font-weight: 800; line-height: 1.2; }
.profile-email { font-size: 12px; color: var(--sub); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Admin badge */
.admin-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 14px; border-radius: 8px;
  background: linear-gradient(135deg, #7c3aed, #dc2626);
  color: #fff; font-weight: 700; font-size: 12px;
  text-decoration: none; flex-shrink: 0;
  box-shadow: 0 4px 14px rgba(124,58,237,.4);
  transition: all .2s;
}
.admin-badge:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(124,58,237,.5); }

/* ══════════════════════════════════════════════
   STAT STRIP — horizontal scroll track
══════════════════════════════════════════════ */
.stat-strip-wrap {
  position: relative;
  margin-bottom: 20px;
  animation: fadeUp .5s .1s ease both;
}
/* fade edge hints on mobile */
.stat-strip-wrap::after {
  content: '';
  position: absolute; top: 0; right: 0;
  width: 40px; height: 100%;
  background: linear-gradient(to right, transparent, var(--bg));
  pointer-events: none; border-radius: 0 13px 13px 0;
  transition: background .3s;
}
body.light .stat-strip-wrap::after { background: linear-gradient(to right, transparent, #eef2ff); }

.stat-strip {
  display: flex;
  flex-direction: row;
  gap: 10px;
  overflow-x: auto;
  overflow-y: visible;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 6px;
  /* hide scrollbar but keep scrolling */
  scrollbar-width: none;
}
.stat-strip::-webkit-scrollbar { display: none; }

.stat-card {
  flex: 0 0 120px;          /* fixed width, never shrink */
  scroll-snap-align: start;
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
  border-radius: 13px;
  padding: 14px 12px 12px;
  backdrop-filter: blur(12px);
  text-align: center;
  transition: all .2s cubic-bezier(.34,1.56,.64,1);
  cursor: default; position: relative; overflow: hidden;
}
.stat-card::after {
  content: ''; position: absolute; inset: 0; border-radius: 13px;
  background: rgba(255,255,255,.0); transition: background .2s;
}
.stat-card:hover { transform: translateY(-3px); border-color: var(--border2); }
.stat-card:hover::after { background: rgba(255,255,255,.03); }
/* Click glass effect */
.stat-card:active {
  transform: scale(.96);
  background: rgba(255,255,255,.12);
  border-color: rgba(255,255,255,.3);
  backdrop-filter: blur(24px) saturate(200%);
  box-shadow: 0 0 0 1px rgba(255,255,255,.2) inset;
}
body.light .stat-card { background:rgba(255,255,255,.65); border-color:rgba(255,255,255,.9); box-shadow:0 2px 12px rgba(99,102,241,.08); }
body.light .stat-card:active { background:rgba(255,255,255,.95); box-shadow:0 4px 20px rgba(99,102,241,.2); }

.stat-num {
  font-family: 'Space Mono', monospace;
  font-size: 22px; font-weight: 700; line-height: 1;
  margin-bottom: 5px;
}
.stat-lbl { font-size: 10px; color: var(--sub); text-transform: uppercase; letter-spacing: .06em; font-weight: 600; }
.stat-card.s-rank    .stat-num { color: var(--amber); }
.stat-card.s-score   .stat-num { color: var(--blue); }
.stat-card.s-points  .stat-num { color: var(--teal); }
.stat-card.s-lessons .stat-num { color: var(--purple); }
.stat-card.s-streak  .stat-num { color: #f97316; }
.stat-card.s-answers .stat-num { color: var(--pink); }
.stat-card.s-accuracy .stat-num { color: var(--teal); }

/* ══════════════════════════════════════════════
   FEATURE GRID — All Cards
══════════════════════════════════════════════ */
.section-label {
  font-family: 'Space Mono', monospace;
  font-size: 10px; font-weight: 700; letter-spacing: .1em;
  text-transform: uppercase; color: var(--sub);
  margin-bottom: 10px;
  display: flex; align-items: center; gap: 8px;
}
.section-label::after { content:''; flex:1; height:1px; background:var(--border); }

.feature-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 11px; margin-bottom: 22px;
}
@media (min-width: 600px)  { .feature-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 900px)  { .feature-grid { grid-template-columns: repeat(4, 1fr); } }

.fcard {
  display: flex; flex-direction: column; justify-content: space-between;
  text-decoration: none; color: inherit;
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
  border-radius: 14px;
  padding: 16px 14px 13px;
  backdrop-filter: blur(12px);
  min-height: 90px;
  position: relative; overflow: hidden;
  transition: all .22s cubic-bezier(.34,1.56,.64,1);
  /* stagger set by inline style */
  animation: fadeUp .45s ease both;
}
.fcard::before {
  content: ''; position: absolute; inset: 0;
  border-radius: 14px;
  background: rgba(255,255,255,0);
  transition: background .15s;
}
/* hover lift */
.fcard:hover {
  transform: translateY(-4px) scale(1.02);
  border-color: var(--border2);
  box-shadow: 0 12px 32px rgba(0,0,0,.3);
}
.fcard:hover::before { background: rgba(255,255,255,.03); }

/* CLICK glassmorphism explosion */
.fcard:active {
  transform: scale(.95);
  background: rgba(255,255,255,.18) !important;
  backdrop-filter: blur(28px) saturate(220%) !important;
  border-color: rgba(255,255,255,.45) !important;
  box-shadow: 0 0 0 2px rgba(255,255,255,.25) inset, 0 8px 30px rgba(255,255,255,.1) !important;
}
body.light .fcard:active {
  background: rgba(255,255,255,.92) !important;
  box-shadow: 0 0 0 2px rgba(99,102,241,.3) inset, 0 8px 30px rgba(99,102,241,.15) !important;
}

body.light .fcard {
  background: rgba(255,255,255,.65);
  border-color: rgba(255,255,255,.9);
  box-shadow: 0 2px 14px rgba(99,102,241,.07);
  color: var(--text);
}
body.light .fcard:hover {
  box-shadow: 0 10px 30px rgba(99,102,241,.18);
  border-color: rgba(99,102,241,.35);
}

.fcard-top { display: flex; align-items: center; gap: 11px; }
.fcard-icon {
  width: 42px; height: 42px; border-radius: 11px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 22px;
  background: rgba(255,255,255,.06); border: 1px solid var(--border);
  transition: transform .2s;
}
.fcard:hover .fcard-icon { transform: rotate(-6deg) scale(1.1); }
.fcard-title { font-size: 14px; font-weight: 800; line-height: 1.2; }
.fcard-desc { font-size: 11px; color: var(--sub); margin-top: 8px; line-height: 1.4; }
body.light .fcard-desc { color: rgba(99,102,241,.7); }

/* Special card accents */
.fcard-ai .fcard-icon { background: linear-gradient(135deg, rgba(59,130,246,.2), rgba(167,114,250,.2)); border-color: rgba(59,130,246,.2); }
.fcard-formula .fcard-icon { background: linear-gradient(135deg, rgba(0,201,138,.15), rgba(59,130,246,.15)); border-color: rgba(0,201,138,.2); }
.fcard-practice .fcard-icon { background: linear-gradient(135deg, rgba(245,158,11,.15), rgba(239,68,68,.15)); border-color: rgba(245,158,11,.2); }
.fcard-chat .fcard-icon { background: linear-gradient(135deg, rgba(244,114,182,.15), rgba(167,114,250,.15)); border-color: rgba(244,114,182,.2); }
.fcard-brain .fcard-icon { background: linear-gradient(135deg, rgba(167,114,250,.15), rgba(59,130,246,.15)); border-color: rgba(167,114,250,.2); }
.fcard-leader .fcard-icon { background: linear-gradient(135deg, rgba(245,158,11,.15), rgba(249,115,22,.15)); border-color: rgba(245,158,11,.2); }
.fcard-lessons .fcard-icon { background: linear-gradient(135deg, rgba(239,68,68,.15), rgba(245,158,11,.15)); border-color: rgba(239,68,68,.2); }

/* ── NEW BADGE on formula card ── */
.new-badge {
  position: absolute; top: 8px; right: 8px;
  background: linear-gradient(135deg, var(--teal), var(--blue));
  color: #000; font-family: 'Space Mono', monospace;
  font-size: 8px; font-weight: 700; letter-spacing: .08em;
  padding: 2px 6px; border-radius: 20px;
  animation: badgePop 1.5s ease-in-out infinite;
}
@keyframes badgePop { 0%,100%{transform:scale(1)} 50%{transform:scale(1.08)} }

/* ══════════════════════════════════════════════
   ADMIN STRIP
══════════════════════════════════════════════ */
.admin-strip {
  display: flex; align-items: center; justify-content: center;
  gap: 10px; padding: 14px 20px; border-radius: 14px; margin-bottom: 18px;
  background: linear-gradient(135deg, rgba(124,58,237,.15), rgba(220,38,38,.12));
  border: 1px solid rgba(124,58,237,.3);
  text-decoration: none; color: var(--text);
  font-weight: 700; font-size: 14px;
  transition: all .2s;
}
.admin-strip:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(124,58,237,.3); }

/* ══════════════════════════════════════════════
   BOTTOM LAYOUT (Continue + Activity + Sidebar)
══════════════════════════════════════════════ */
.bottom-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}
@media (min-width: 860px) { .bottom-grid { grid-template-columns: 1fr 320px; } }

.glass-card {
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
  border-radius: 14px;
  padding: 16px 16px;
  backdrop-filter: blur(12px);
  transition: all .2s;
}
/* Glass card click effect */
.glass-card:active {
  background: rgba(255,255,255,.1);
  backdrop-filter: blur(24px);
  border-color: rgba(255,255,255,.25);
}
body.light .glass-card { background:rgba(255,255,255,.65); border-color:rgba(255,255,255,.9); box-shadow:0 2px 14px rgba(99,102,241,.07); }

.card-title {
  font-family: 'Space Mono', monospace;
  font-size: 11px; font-weight: 700; letter-spacing: .08em;
  text-transform: uppercase; color: var(--sub); margin-bottom: 12px;
}

/* Continue watching items */
.cw-item {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  padding: 10px 12px; border-radius: 10px; margin-bottom: 7px;
  background: rgba(255,255,255,.03); border: 1px solid var(--border);
  transition: all .15s;
}
.cw-item:hover { background: rgba(255,255,255,.06); border-color: var(--border2); }
body.light .cw-item { background:rgba(255,255,255,.5); border-color:rgba(99,102,241,.12); }
body.light .cw-item:hover { background:rgba(255,255,255,.8); }
.cw-title { font-size: 13px; font-weight: 700; }
.cw-meta { font-size: 11px; color: var(--sub); margin-top: 2px; }
.cw-btn {
  padding: 6px 12px; border-radius: 8px; border: none;
  background: linear-gradient(135deg, var(--teal), var(--blue));
  color: #000; font-weight: 700; font-size: 12px; cursor: pointer;
  text-decoration: none; white-space: nowrap; flex-shrink: 0;
  transition: all .15s;
}
.cw-btn:hover { filter: brightness(1.1); transform: scale(1.04); }

/* Activity items */
.act-item {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;
  padding: 10px 12px; border-radius: 10px; margin-bottom: 7px;
  background: rgba(255,255,255,.03); border: 1px solid var(--border);
  transition: all .15s;
}
body.light .act-item { background:rgba(255,255,255,.5); border-color:rgba(99,102,241,.12); }
.act-q { font-size: 12px; font-weight: 600; color: var(--text); line-height: 1.4; }
.act-time { font-size: 10px; color: var(--sub); margin-top: 2px; }
.act-badge { padding: 3px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.act-correct { background: rgba(0,201,138,.12); color: var(--teal); border: 1px solid rgba(0,201,138,.2); }
.act-wrong   { background: rgba(255,71,87,.1);   color: var(--danger); border: 1px solid rgba(255,71,87,.2); }

/* Leaderboard */
.lb-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 9px 10px; border-radius: 9px; margin-bottom: 6px;
  background: rgba(255,255,255,.03); border: 1px solid var(--border);
  font-size: 13px; transition: all .15s;
}
.lb-item:hover { background: rgba(255,255,255,.06); }
body.light .lb-item { background:rgba(255,255,255,.5); }
.lb-rank { font-family:'Space Mono',monospace; font-size:11px; font-weight:700; color:var(--dim); width:22px; flex-shrink:0; }
.lb-rank.gold   { color:#f59e0b; }
.lb-rank.silver { color:#94a3b8; }
.lb-rank.bronze { color:#cd7c47; }
.lb-name { flex:1; min-width:0; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.lb-pts { font-family:'Space Mono',monospace; font-size:11px; font-weight:700; color:var(--teal); }

/* Quick links */
.qlink {
  display: flex; align-items: center; gap: 10px; padding: 9px 10px;
  border-radius: 9px; font-size: 13px; font-weight: 600;
  transition: all .15s; background: rgba(255,255,255,.03);
  border: 1px solid var(--border); margin-bottom: 6px;
  text-decoration: none; color: var(--text);
}
.qlink:hover { background: rgba(255,255,255,.07); border-color: var(--border2); transform: translateX(3px); }
body.light .qlink { background:rgba(255,255,255,.5); }
.qlink i { width: 16px; text-align: center; color: var(--sub); }
.qlink.danger { color: var(--danger); }
.qlink.danger i { color: var(--danger); }

/* ══════════════════════════════════════════════
   ANIMATIONS
══════════════════════════════════════════════ */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes countUp {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Scrollbar */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 5px; }

/* Prevent body horizontal overflow on mobile */
html, body { overflow-x: hidden; max-width: 100%; }
.sync-wrap { min-height: 60vh; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 16px; }
.spinner { width: 44px; height: 44px; border-radius: 50%; border: 4px solid var(--border); border-top-color: var(--teal); animation: spin 0.9s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Footer */
footer {
  text-align: center; font-size: 12px; color: var(--dim);
  padding: 20px; font-family: 'Space Mono', monospace; letter-spacing: .04em;
}

/* ══════════════════════════════════════════════
   RESPONSIVE — Mobile first
══════════════════════════════════════════════ */

/* ── Tablet and up: stat cards can grow a bit ── */
@media (min-width: 600px) {
  .stat-card { flex: 0 0 140px; }
  .feature-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (min-width: 900px) {
  .stat-card { flex: 0 0 150px; }
  .feature-grid { grid-template-columns: repeat(4, 1fr); }
}

/* ── Small phones (≤480px) ── */
@media (max-width: 480px) {
  /* header: compress */
  header { height: auto; min-height: 56px; flex-wrap: wrap; padding: 10px 12px; gap: 8px; }
  .hdr-brand { gap: 8px; }
  .hdr-crest { width: 32px; height: 32px; font-size: 15px; }
  .hdr-school { font-size: 11px; }
  .hdr-tagline { display: none; }
  .hdr-right { gap: 6px; }
  /* hide user chip name text, keep avatar */
  .user-chip .name-txt { display: none; }
  /* hide logout label, keep icon */
  .hdr-btn .btn-lbl { display: none; }
  .hdr-btn { padding: 6px 8px; }

  /* wrap */
  .wrap { padding: 12px 10px 50px; }

  /* profile hero */
  .profile-hero { padding: 14px 14px; gap: 12px; }
  .profile-av { width: 48px; height: 48px; font-size: 18px; }
  .profile-name { font-size: 16px; }
  .admin-badge span { display: none; }
  .admin-badge { padding: 6px 10px; }

  /* stat scroll: narrower cards */
  .stat-card { flex: 0 0 100px; padding: 12px 10px 10px; }
  .stat-num { font-size: 18px; }
  .stat-lbl { font-size: 9px; }

  /* feature grid: always 2 cols on small phones */
  .feature-grid { grid-template-columns: repeat(2, 1fr); gap: 9px; }
  .fcard { padding: 13px 11px 11px; min-height: 80px; }
  .fcard-icon { width: 36px; height: 36px; font-size: 18px; }
  .fcard-title { font-size: 13px; }
  .fcard-desc { display: none; }        /* hide desc on tiny screens to save space */

  /* bottom grid single col */
  .bottom-grid { grid-template-columns: 1fr; }

  /* glass cards */
  .glass-card { padding: 13px 12px; }
  .cw-item, .act-item, .lb-item { padding: 9px 10px; }
  .cw-title, .act-q { font-size: 12px; }
}

/* ── Medium phones (481-640px) ── */
@media (min-width: 481px) and (max-width: 640px) {
  .hdr-tagline { display: none; }
  .user-chip .name-txt { display: none; }
  .wrap { padding: 14px 12px 50px; }
  .stat-card { flex: 0 0 110px; }
  .feature-grid { grid-template-columns: repeat(2, 1fr); }
  .fcard-desc { font-size: 10px; }
  .bottom-grid { grid-template-columns: 1fr; }
}

/* ── Large phones / small tablets (641-860px) ── */
@media (min-width: 641px) and (max-width: 860px) {
  .bottom-grid { grid-template-columns: 1fr; }
  .feature-grid { grid-template-columns: repeat(3, 1fr); }
}

/* ── Landscape phones (short viewports) ── */
@media (max-height: 500px) and (max-width: 900px) {
  header { height: 50px; }
  .wrap { padding: 8px 12px 40px; }
  .profile-hero { padding: 10px 14px; }
  .profile-av { width: 40px; height: 40px; }
}
</style>
</head>
<body class="dark">

<header>
  <div class="hdr-orbs">
    <div class="hdr-orb"></div>
    <div class="hdr-orb"></div>
    <div class="hdr-orb"></div>
  </div>
  <div class="hdr-brand">
    <div class="hdr-crest">🎓</div>
    <div class="hdr-text">
      <span class="hdr-school">EXCELLENT SIMPLIFIED</span>
      <span class="hdr-tagline">Your Smart Study Hub</span>
    </div>
  </div>
  <div class="hdr-right">
    <?php if ($hasSession && $is_admin): ?>
    <a href="admin/dashboard.php" class="hdr-btn" style="background:linear-gradient(135deg,rgba(124,58,237,.3),rgba(220,38,38,.3));border-color:rgba(124,58,237,.5);color:#c4b5fd">
      <i class="fa fa-crown" style="font-size:10px"></i> Admin
    </a>
    <?php endif; ?>
    <?php if ($hasSession): ?>
    <div class="user-chip">
      <?php if ($display_picture): ?><img src="<?=esc($display_picture)?>" alt=""><?php else: ?><div class="av"><?=esc($av_letter)?></div><?php endif; ?>
      <span class="name-txt"><?=esc($display_name)?></span>
    </div>
    <a href="auth/logout.php" class="hdr-btn"><i class="fa fa-right-from-bracket" style="font-size:10px"></i><span class="btn-lbl"> Logout</span></a>
    <?php else: ?>
    <a href="login.php" class="hdr-btn"><i class="fa fa-sign-in-alt" style="font-size:10px"></i><span class="btn-lbl"> Login</span></a>
    <?php endif; ?>
    <button class="theme-pill" id="themeBtn" onclick="toggleTheme()">
      <span id="themeIcon">🌙</span>
      <div class="theme-track"><div class="theme-thumb"></div></div>
      <span id="themeLabel">Dark</span>
    </button>
  </div>
</header>

<div class="wrap">

<?php if (!$hasSession): ?>
<!-- ── NOT LOGGED IN: silent Firebase re-sync ── -->
<div class="sync-wrap">
  <div class="spinner"></div>
  <p style="color:var(--sub);font-size:13px">Checking your session…</p>
</div>
<script type="module">
  import { initializeApp } from "https://www.gstatic.com/firebasejs/12.10.0/firebase-app.js";
  import { getAuth, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/12.10.0/firebase-auth.js";
  const app = initializeApp({
    apiKey:"AIzaSyAckwsm4ov-FR84WLCaklmADlxor1JZZsI",
    authDomain:"excellent-simplified.firebaseapp.com",
    projectId:"excellent-simplified",
    storageBucket:"excellent-simplified.firebasestorage.app",
    messagingSenderId:"1031645566404",
    appId:"1:1031645566404:web:81543ad98c9febca328091"
  });
  const auth = getAuth(app);
  let tried = false;
  onAuthStateChanged(auth, async (user) => {
    if (user && !tried) {
      tried = true;
      try {
        const idToken = await user.getIdToken(true);
        const res = await fetch('login.php', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify({ idToken }) });
        const j = await res.json().catch(()=>null);
        if (j?.success) window.location.reload();
        else window.location.href = 'login.php';
      } catch { window.location.href = 'login.php'; }
    } else if (!user) { window.location.href = 'login.php'; }
  });
  setTimeout(() => { if (!tried) window.location.href = 'login.php'; }, 5000);
</script>

<?php else: ?>
<!-- ── DASHBOARD ── -->

  <!-- Profile hero -->
  <div class="profile-hero">
    <div class="profile-av">
      <?php if ($display_picture): ?><img src="<?=esc($display_picture)?>" alt=""><?php else: ?><?=esc($av_letter)?><?php endif; ?>
    </div>
    <div class="profile-info">
      <div class="profile-greeting"><?=esc($greeting)?></div>
      <div class="profile-name"><?=esc($display_name)?></div>
      <div class="profile-email"><?=esc($display_email)?></div>
    </div>
    <?php if ($is_admin): ?>
    <a href="admin/dashboard.php" class="admin-badge" style="flex-shrink:0">
      <i class="fa fa-crown" style="font-size:11px"></i> Admin Panel
    </a>
    <?php endif; ?>
  </div>

  <!-- Stats strip (horizontal scroll) -->
  <div class="section-label">Your Stats</div>
  <div class="stat-strip-wrap">
  <div class="stat-strip">
    <div class="stat-card s-rank" style="animation-delay:.05s">
      <div class="stat-num" data-count="<?=is_numeric($rank)?$rank:0?>"><?=esc($rank)?></div>
      <div class="stat-lbl">🏅 Rank</div>
    </div>
    <div class="stat-card s-score" style="animation-delay:.08s">
      <div class="stat-num" data-count="<?=(int)$score?>" id="scoreNum">0</div>
      <div class="stat-lbl">⭐ Score</div>
    </div>
    <div class="stat-card s-points" style="animation-delay:.11s">
      <div class="stat-num" id="pointsNum"><?=number_format($points, 2)?></div>
      <div class="stat-lbl">💎 Points</div>
    </div>
    <div class="stat-card s-lessons" style="animation-delay:.14s">
      <div class="stat-num"><?=esc($completed_lessons)?>/<?=esc($videos_total)?></div>
      <div class="stat-lbl">🎥 Lessons</div>
    </div>
    <div class="stat-card s-streak" style="animation-delay:.17s">
      <div class="stat-num" data-count="<?=(int)$streak?>" id="streakNum">0</div>
      <div class="stat-lbl">🔥 Streak</div>
    </div>
    <div class="stat-card s-answers" style="animation-delay:.20s">
      <div class="stat-num" data-count="<?=(int)$questions_answered?>" id="answersNum">0</div>
      <div class="stat-lbl">📝 Answers</div>
    </div>
    <div class="stat-card s-accuracy" style="animation-delay:.23s">
      <div class="stat-num" data-count="<?=(int)$accuracy?>" id="accuracyNum">0</div>
      <div class="stat-lbl">🎯 Accuracy %</div>
    </div>
  </div><!-- /stat-strip -->
  </div><!-- /stat-strip-wrap -->

  <!-- Feature grid -->
  <div class="section-label">Quick Access</div>
  <div class="feature-grid" style="margin-bottom:22px">

    <a class="fcard fcard-lessons" href="videos/lessons.php" style="animation-delay:.06s">
      <div class="fcard-top">
        <div class="fcard-icon">🎥</div>
        <div class="fcard-title">Lessons</div>
      </div>
      <div class="fcard-desc">All YouTube lessons by subject</div>
    </a>

    <a class="fcard fcard-brain" href="questions/live_brainstorm.php" style="animation-delay:.09s">
      <div class="fcard-top">
        <div class="fcard-icon">🧠</div>
        <div class="fcard-title">Brainstorm</div>
      </div>
      <div class="fcard-desc">Live real-time quizzes</div>
    </a>

    <a class="fcard fcard-practice" href="exams/practice_test.php" style="animation-delay:.12s">
      <div class="fcard-top">
        <div class="fcard-icon">📝</div>
        <div class="fcard-title">Practice Test</div>
      </div>
      <div class="fcard-desc">JAMB & WAEC past questions</div>
    </a>

    <a class="fcard fcard-ai" href="ai/ai_helper.php" style="animation-delay:.15s">
      <div class="fcard-top">
        <div class="fcard-icon">🤖</div>
        <div class="fcard-title">AI Tutor</div>
      </div>
      <div class="fcard-desc">Ask any exam question</div>
    </a>

    <a class="fcard fcard-formula" href="formula.php" style="animation-delay:.18s">
      <span class="new-badge">NEW</span>
      <div class="fcard-top">
        <div class="fcard-icon">📐</div>
        <div class="fcard-title">Formula Panel</div>
      </div>
      <div class="fcard-desc">JAMB formulas + AI explanations</div>
    </a>

    <a class="fcard fcard-chat" href="chat/index.php" style="animation-delay:.21s">
      <div class="fcard-top">
        <div class="fcard-icon">💬</div>
        <div class="fcard-title">Class Chat</div>
      </div>
      <div class="fcard-desc">Discuss with classmates</div>
    </a>

    <a class="fcard fcard-leader" href="leaderboard.php" style="animation-delay:.24s">
      <div class="fcard-top">
        <div class="fcard-icon">🏆</div>
        <div class="fcard-title">Leaderboard</div>
      </div>
      <div class="fcard-desc">Top students this week</div>
    </a>

    <a class="fcard" href="subjects.php" style="animation-delay:.27s">
      <div class="fcard-top">
        <div class="fcard-icon" style="background:rgba(255,255,255,.06)">📚</div>
        <div class="fcard-title">Subjects</div>
      </div>
      <div class="fcard-desc">Browse by topic</div>
    </a>

  </div>

  <!-- Bottom grid: activity + sidebar -->
  <div class="bottom-grid">

    <!-- Left column -->
    <div style="display:flex;flex-direction:column;gap:14px">

      <!-- Continue watching -->
      <div class="glass-card">
        <div class="card-title">▶ Continue Watching</div>
        <?php if (empty($continue)): ?>
          <div style="color:var(--sub);font-size:13px;padding:8px 0">
            No progress yet — <a href="videos/lessons.php" style="color:var(--teal)">start a lesson</a>
          </div>
        <?php else: foreach($continue as $c): ?>
          <div class="cw-item">
            <div style="flex:1;min-width:0">
              <div class="cw-title"><?=esc($c['title'])?></div>
              <div class="cw-meta">Progress: <?=(int)($c['watch_percent']??0)?>% &nbsp;·&nbsp; <?=esc($c['last_watched']??'')?></div>
            </div>
            <a class="cw-btn" href="videos/watch_video.php?id=<?=(int)$c['id']?>">▶ Resume</a>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Recent activity -->
      <div class="glass-card">
        <div class="card-title">⚡ Recent Activity</div>
        <?php if (empty($recent_activity)): ?>
          <div style="color:var(--sub);font-size:13px;padding:8px 0">No answers yet — try <a href="exams/practice_test.php" style="color:var(--teal)">Practice Tests</a></div>
        <?php else: foreach($recent_activity as $act): ?>
          <div class="act-item">
            <div style="flex:1;min-width:0">
              <div class="act-q"><?=esc(mb_substr($act['question']??'Question',0,60))?><?=strlen($act['question']??'')>60?'…':''?></div>
              <div class="act-time"><?=esc($act['created_at']??'')?></div>
            </div>
            <span class="act-badge <?=$act['is_correct']?'act-correct':'act-wrong'?>">
              <?=$act['is_correct']?'✓ Correct':'✗ Wrong'?>
            </span>
          </div>
        <?php endforeach; endif; ?>
      </div>

    </div>

    <!-- Right sidebar -->
    <div style="display:flex;flex-direction:column;gap:14px">

      <!-- Leaderboard -->
      <div class="glass-card">
        <div class="card-title">🏆 Top Students</div>
        <?php if (empty($leaderboard)): ?>
          <div style="color:var(--sub);font-size:13px;padding:8px 0">No leaderboard data yet</div>
        <?php else: $i=1; foreach($leaderboard as $lb): ?>
          <div class="lb-item">
            <span class="lb-rank <?=$i===1?'gold':($i===2?'silver':($i===3?'bronze':''))?>">#<?=$i?></span>
            <span class="lb-name"><?=esc($lb['username']??'Student')?></span>
            <span class="lb-pts"><?=number_format((float)($lb['pts']??0),0)?> pts</span>
          </div>
        <?php $i++; endforeach; endif; ?>
      </div>

      <!-- Quick links -->
      <div class="glass-card">
        <div class="card-title">🔗 Quick Links</div>
        <a href="videos/lessons.php"              class="qlink"><i class="fa fa-play-circle"></i> All Lessons</a>
        <a href="formula.php"                     class="qlink"><i class="fa fa-square-root-variable"></i> Formula Panel</a>
        <a href="questions/live_brainstorm.php"   class="qlink"><i class="fa fa-bolt"></i> Live Brainstorm</a>
        <a href="exams/practice_test.php"         class="qlink"><i class="fa fa-clipboard-check"></i> Practice Tests</a>
        <a href="ai/ai_helper.php"                class="qlink"><i class="fa fa-robot"></i> AI Tutor</a>
        <a href="leaderboard.php"                 class="qlink"><i class="fa fa-trophy"></i> Leaderboard</a>
        <a href="auth/logout.php"                 class="qlink danger"><i class="fa fa-right-from-bracket"></i> Logout</a>
      </div>

    </div>
  </div><!-- /bottom-grid -->

<?php endif; ?>
</div><!-- /wrap -->

<footer>EXCELLENT SIMPLIFIED &nbsp;·&nbsp; <?=date('Y')?></footer>

<script>
/* ══════════════════════════════════════════════
   THEME TOGGLE
══════════════════════════════════════════════ */
function toggleTheme(){
  const isDark = document.body.classList.contains('dark');
  applyTheme(isDark ? 'light' : 'dark');
}
function applyTheme(t){
  document.body.classList.remove('dark','light');
  document.body.classList.add(t);
  const lbl  = document.getElementById('themeLabel');
  const icon = document.getElementById('themeIcon');
  if(t==='dark'){ icon.textContent='🌙'; lbl.textContent='Dark'; }
  else          { icon.textContent='☀️'; lbl.textContent='Light'; }
  try{ localStorage.setItem('es_theme', t); }catch(e){}
}
(function initTheme(){
  let saved = null;
  try{ saved = localStorage.getItem('es_theme'); }catch(e){}
  if(saved){ applyTheme(saved); return; }
  const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
  applyTheme(prefersDark ? 'dark' : 'light');
})();

/* ══════════════════════════════════════════════
   ANIMATED STAT COUNTERS
   Counts up from 0 to the real value on load
══════════════════════════════════════════════ */
function animateCount(el, target, decimals){
  if(!el || isNaN(target) || target <= 0) return;
  const duration  = 800;
  const startTime = performance.now();
  function step(now){
    const progress = Math.min((now - startTime) / duration, 1);
    const ease     = 1 - Math.pow(1 - progress, 3); // cubic ease-out
    const cur      = target * ease;
    el.textContent = decimals ? cur.toFixed(decimals) : Math.round(cur).toLocaleString();
    if(progress < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

// Trigger counters when the stat strip enters the viewport
const statObserver = new IntersectionObserver(entries => {
  entries.forEach(en => {
    if(!en.isIntersecting) return;
    // Counters by data-count attributes
    document.querySelectorAll('.stat-num[data-count]').forEach(el => {
      const v = parseFloat(el.dataset.count);
      animateCount(el, v, 0);
    });
    // Points (float)
    const pts = document.getElementById('pointsNum');
    if(pts){
      const v = parseFloat(pts.textContent.replace(/,/g,'')) || 0;
      animateCount(pts, v, 2);
    }
    statObserver.disconnect();
  });
}, { threshold: 0.3 });

const strip = document.querySelector('.stat-strip-wrap');
if(strip) statObserver.observe(strip);

/* ══════════════════════════════════════════════
   CLICK RIPPLE EFFECT on feature cards
══════════════════════════════════════════════ */
document.querySelectorAll('.fcard').forEach(card => {
  card.addEventListener('click', function(e){
    // create ripple
    const ripple = document.createElement('span');
    const rect   = card.getBoundingClientRect();
    const size   = Math.max(rect.width, rect.height) * 1.5;
    ripple.style.cssText = `
      position:absolute; border-radius:50%; pointer-events:none;
      width:${size}px; height:${size}px;
      left:${e.clientX - rect.left - size/2}px;
      top:${e.clientY - rect.top  - size/2}px;
      background:rgba(255,255,255,.12);
      transform:scale(0); animation:ripple .5s ease-out forwards;
    `;
    card.appendChild(ripple);
    ripple.addEventListener('animationend', () => ripple.remove());
  });
});

// Inject ripple keyframe
const rippleStyle = document.createElement('style');
rippleStyle.textContent = '@keyframes ripple{to{transform:scale(1);opacity:0}}';
document.head.appendChild(rippleStyle);
</script>
</body>
</html>
