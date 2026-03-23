<?php
// admin/leaderboard.php — Admin User Monitor
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('display_errors', '1');
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../config/db.php';
header('Cache-Control: no-store');

// ── Admin Auth ───────────────────────────────────────────────────────────────
$adminId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if (!$adminId) { header('Location: ../login.php'); exit; }

$_chk = $conn->prepare("SELECT is_admin, google_name, username FROM users WHERE id=? LIMIT 1");
$_chk->bind_param('i', $adminId);
$_chk->execute();
$_adminRow = $_chk->get_result()->fetch_assoc();
$_chk->close();
if (!$_adminRow || !(int)($_adminRow['is_admin'] ?? 0)) {
    header('Location: ../dashboard.php'); exit;
}
$adminName = $_SESSION['google_name'] ?? $_adminRow['google_name'] ?? $_adminRow['username'] ?? 'Admin';

// ── Helpers ──────────────────────────────────────────────────────────────────
function tableExists($conn, $t) {
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? null;
    if (!$db) return false;
    $s = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    if (!$s) return false;
    $s->bind_param("ss", $db, $t); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return (int)($r['c'] ?? 0) > 0;
}
function columnExists($conn, $t, $col) {
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? null;
    if (!$db) return false;
    $s = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    if (!$s) return false;
    $s->bind_param("sss", $db, $t, $col); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return (int)($r['c'] ?? 0) > 0;
}

// ── AJAX: single user detail ─────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'user_detail') {
    header('Content-Type: application/json; charset=utf-8');
    $uid = (int)($_GET['uid'] ?? 0);
    if (!$uid) { echo json_encode(['success'=>false,'error'=>'No user']); exit; }

    // Basic user
    $s = $conn->prepare("SELECT id, username, email, google_name, google_picture, score, is_admin, created_at FROM users WHERE id=? LIMIT 1");
    $s->bind_param('i', $uid); $s->execute();
    $user = $s->get_result()->fetch_assoc(); $s->close();
    if (!$user) { echo json_encode(['success'=>false,'error'=>'Not found']); exit; }

    // Total score
    $totalScore = 0;
    if (tableExists($conn, 'scores')) {
        $col = columnExists($conn, 'scores', 'points') ? 'points' : (columnExists($conn, 'scores', 'score') ? 'score' : null);
        if ($col) {
            $s = $conn->prepare("SELECT COALESCE(SUM($col),0) AS pts FROM scores WHERE user_id=?");
            $s->bind_param('i', $uid); $s->execute();
            $totalScore = (int)($s->get_result()->fetch_assoc()['pts'] ?? 0); $s->close();
        }
    }
    if (!$totalScore) $totalScore = (int)($user['score'] ?? 0);

    // Rank
    $rank = '-';
    if (tableExists($conn, 'scores')) {
        $col = columnExists($conn, 'scores', 'points') ? 'points' : (columnExists($conn, 'scores', 'score') ? 'score' : null);
        if ($col) {
            $res = $conn->query("SELECT user_id, COALESCE(SUM($col),0) AS pts FROM scores GROUP BY user_id ORDER BY pts DESC");
            $pos = 0;
            while ($r = $res->fetch_assoc()) {
                $pos++;
                if ((int)$r['user_id'] === $uid) { $rank = $pos; break; }
            }
        }
    }

    // Videos watched
    $videosWatched = 0; $videoList = [];
    if (tableExists($conn, 'video_progress')) {
        $s = $conn->prepare("SELECT COUNT(DISTINCT video_id) AS cnt FROM video_progress WHERE user_id=?");
        $s->bind_param('i', $uid); $s->execute();
        $videosWatched = (int)($s->get_result()->fetch_assoc()['cnt'] ?? 0); $s->close();

        // Last 10 videos watched
        $hasVid = tableExists($conn, 'videos');
        if ($hasVid) {
            $s = $conn->prepare("
                SELECT v.title, v.id, vp.updated_at
                FROM video_progress vp
                JOIN videos v ON v.id = vp.video_id
                WHERE vp.user_id = ?
                ORDER BY vp.updated_at DESC
                LIMIT 10
            ");
        } else {
            $s = $conn->prepare("SELECT video_id AS id, NULL AS title, updated_at FROM video_progress WHERE user_id=? ORDER BY updated_at DESC LIMIT 10");
        }
        $s->bind_param('i', $uid); $s->execute();
        $res = $s->get_result();
        while ($r = $res->fetch_assoc()) $videoList[] = $r;
        $s->close();
    } elseif (tableExists($conn, 'video_watches')) {
        $s = $conn->prepare("SELECT COUNT(DISTINCT video_id) AS cnt FROM video_watches WHERE user_id=?");
        $s->bind_param('i', $uid); $s->execute();
        $videosWatched = (int)($s->get_result()->fetch_assoc()['cnt'] ?? 0); $s->close();

        if (tableExists($conn, 'videos')) {
            $s = $conn->prepare("
                SELECT v.title, v.id, vw.watched_at AS updated_at
                FROM video_watches vw
                JOIN videos v ON v.id = vw.video_id
                WHERE vw.user_id = ?
                ORDER BY vw.watched_at DESC
                LIMIT 10
            ");
        } else {
            $s = $conn->prepare("SELECT video_id AS id, NULL AS title, watched_at AS updated_at FROM video_watches WHERE user_id=? ORDER BY watched_at DESC LIMIT 10");
        }
        $s->bind_param('i', $uid); $s->execute();
        $res = $s->get_result();
        while ($r = $res->fetch_assoc()) $videoList[] = $r;
        $s->close();
    }

    // Quiz attempts
    $quizAttempts = 0; $quizAccuracy = null;
    if (tableExists($conn, 'quiz_attempts')) {
        $s = $conn->prepare("SELECT COUNT(*) AS cnt, AVG(CASE WHEN is_correct=1 THEN 100 ELSE 0 END) AS acc FROM quiz_attempts WHERE user_id=?");
        $s->bind_param('i', $uid); $s->execute();
        $row = $s->get_result()->fetch_assoc(); $s->close();
        $quizAttempts = (int)($row['cnt'] ?? 0);
        $quizAccuracy = $row['acc'] !== null ? round((float)$row['acc'], 1) : null;
    } elseif (tableExists($conn, 'answers')) {
        $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM answers WHERE user_id=?");
        $s->bind_param('i', $uid); $s->execute();
        $quizAttempts = (int)($s->get_result()->fetch_assoc()['cnt'] ?? 0); $s->close();
    }

    // Score history (last 10)
    $scoreHistory = [];
    if (tableExists($conn, 'scores')) {
        $col = columnExists($conn, 'scores', 'points') ? 'points' : (columnExists($conn, 'scores', 'score') ? 'score' : null);
        if ($col) {
            $extra = columnExists($conn, 'scores', 'subject_id') ? ', subject_id' : '';
            $ts = columnExists($conn, 'scores', 'created_at') ? 'created_at' : (columnExists($conn, 'scores', 'updated_at') ? 'updated_at' : null);
            $orderBy = $ts ? "ORDER BY $ts DESC" : '';
            $s = $conn->prepare("SELECT $col AS pts $extra FROM scores WHERE user_id=? $orderBy LIMIT 10");
            $s->bind_param('i', $uid); $s->execute();
            $res = $s->get_result();
            while ($r = $res->fetch_assoc()) $scoreHistory[] = $r;
            $s->close();
        }
    }

    // Subject breakdown
    $subjectBreakdown = [];
    if (tableExists($conn, 'scores') && tableExists($conn, 'subjects') && columnExists($conn, 'scores', 'subject_id')) {
        $col = columnExists($conn, 'scores', 'points') ? 'points' : 'score';
        $s = $conn->prepare("
            SELECT sub.name, COALESCE(SUM(sc.$col),0) AS pts
            FROM scores sc
            JOIN subjects sub ON sub.id = sc.subject_id
            WHERE sc.user_id = ?
            GROUP BY sc.subject_id
            ORDER BY pts DESC
            LIMIT 8
        ");
        $s->bind_param('i', $uid); $s->execute();
        $res = $s->get_result();
        while ($r = $res->fetch_assoc()) $subjectBreakdown[] = $r;
        $s->close();
    }

    // Chat messages count
    $chatCount = 0;
    if (tableExists($conn, 'chat_messages')) {
        $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM chat_messages WHERE user_id=?");
        $s->bind_param('i', $uid); $s->execute();
        $chatCount = (int)($s->get_result()->fetch_assoc()['cnt'] ?? 0); $s->close();
    }

    // Last seen (chat_participants)
    $lastSeen = null;
    if (tableExists($conn, 'chat_participants')) {
        $s = $conn->prepare("SELECT last_seen FROM chat_participants WHERE user_id=? ORDER BY last_seen DESC LIMIT 1");
        $s->bind_param('i', $uid); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        $lastSeen = $r['last_seen'] ?? null;
    }

    echo json_encode([
        'success' => true,
        'user' => [
            'id'         => $user['id'],
            'name'       => $user['google_name'] ?: ($user['username'] ?: 'Student'),
            'email'      => $user['email'],
            'picture'    => $user['google_picture'],
            'is_admin'   => (int)($user['is_admin'] ?? 0),
            'joined'     => $user['created_at'],
        ],
        'stats' => [
            'score'          => $totalScore,
            'rank'           => $rank,
            'videosWatched'  => $videosWatched,
            'quizAttempts'   => $quizAttempts,
            'quizAccuracy'   => $quizAccuracy,
            'chatMessages'   => $chatCount,
            'lastSeen'       => $lastSeen,
        ],
        'videoList'        => $videoList,
        'scoreHistory'     => $scoreHistory,
        'subjectBreakdown' => $subjectBreakdown,
    ]);
    exit;
}

// ── Main leaderboard data ────────────────────────────────────────────────────
$totalUsers = (int)($conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0);

// Score column
$scoreCol = null; $scoreTable = null;
if (tableExists($conn, 'scores')) {
    if (columnExists($conn, 'scores', 'points'))      { $scoreCol = 'points'; $scoreTable = 'scores'; }
    elseif (columnExists($conn, 'scores', 'score')) { $scoreCol = 'score';  $scoreTable = 'scores'; }
}
$userScoreCol = columnExists($conn, 'users', 'score') ? 'users.score' : '0';

// Video watch count column
$watchTable = tableExists($conn, 'video_progress') ? 'video_progress' : (tableExists($conn, 'video_watches') ? 'video_watches' : null);
$watchTimeCol = $watchTable === 'video_watches' ? 'watched_at' : 'updated_at';

// Build query
if ($scoreTable) {
    $scoreExpr = "COALESCE(SUM(sc.{$scoreCol}),0)";
    $joinScore  = "LEFT JOIN scores sc ON sc.user_id = u.id";
} else {
    $scoreExpr = $userScoreCol;
    $joinScore  = '';
}

if ($watchTable) {
    $watchExpr = "COALESCE(vw_count.cnt,0)";
    $joinWatch = "LEFT JOIN (SELECT user_id, COUNT(DISTINCT video_id) AS cnt FROM {$watchTable} GROUP BY user_id) vw_count ON vw_count.user_id = u.id";
} else {
    $watchExpr = '0';
    $joinWatch = '';
}

$lastSeenJoin = tableExists($conn, 'chat_participants')
    ? "LEFT JOIN (SELECT user_id, MAX(last_seen) AS ls FROM chat_participants GROUP BY user_id) ls_t ON ls_t.user_id = u.id"
    : '';
$lastSeenCol = tableExists($conn, 'chat_participants') ? 'ls_t.ls AS last_seen' : 'NULL AS last_seen';

$sql = "
    SELECT
        u.id,
        u.google_name,
        u.username,
        u.email,
        u.google_picture,
        u.is_admin,
        u.created_at,
        {$scoreExpr} AS total_score,
        {$watchExpr} AS videos_watched,
        {$lastSeenCol}
    FROM users u
    {$joinScore}
    {$joinWatch}
    {$lastSeenJoin}
    GROUP BY u.id
    ORDER BY total_score DESC, videos_watched DESC
    LIMIT 200
";

$result = $conn->query($sql);
$users = [];
$rank = 0;
while ($r = $result->fetch_assoc()) {
    $rank++;
    $r['rank'] = $rank;
    $users[] = $r;
}

// Summary stats
$totalVideos = tableExists($conn, 'videos') ? (int)($conn->query("SELECT COUNT(*) AS c FROM videos")->fetch_assoc()['c'] ?? 0) : 0;
$totalMessages = tableExists($conn, 'chat_messages') ? (int)($conn->query("SELECT COUNT(*) AS c FROM chat_messages")->fetch_assoc()['c'] ?? 0) : 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>User Monitor — Excellent Simplified Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0a0c10;--surface:#111318;--surface2:#181b22;--surface3:#1e2230;
  --border:#1f2330;--accent:#00c98a;--accent2:#3b82f6;--purple:#8b5cf6;
  --danger:#ff4757;--warning:#f59e0b;--gold:#fbbf24;
  --text:#e8ecf4;--muted:#5a6278;--muted2:#8a93ab;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;overflow-x:hidden}
a{color:inherit;text-decoration:none}

/* ── TOPBAR ── */
.topbar{
  position:sticky;top:0;z-index:100;
  background:rgba(10,12,16,0.95);backdrop-filter:blur(16px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 24px;height:58px;gap:16px;
}
.topbar-brand{display:flex;align-items:center;gap:12px;font-family:'Space Mono',monospace;font-size:13px;font-weight:700}
.brand-dot{width:8px;height:8px;border-radius:50%;background:var(--accent);box-shadow:0 0 8px var(--accent)}
.topbar-right{display:flex;align-items:center;gap:10px}
.topbar-admin{font-size:12px;color:var(--muted2)}
.btn-back{display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--muted2);font-size:12px;font-family:'Space Mono',monospace;cursor:pointer;transition:all .15s}
.btn-back:hover{border-color:var(--accent2);color:var(--accent2)}

/* ── LAYOUT ── */
.page{max-width:1380px;margin:0 auto;padding:24px 20px;display:grid;grid-template-columns:1fr 420px;gap:20px;min-height:calc(100vh - 58px)}
@media(max-width:1100px){.page{grid-template-columns:1fr;grid-template-rows:auto 1fr}}

/* ── SUMMARY STRIP ── */
.summary-strip{grid-column:1/-1;display:flex;gap:12px;flex-wrap:wrap}
.sum-card{flex:1;min-width:140px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 20px;position:relative;overflow:hidden;transition:border-color .2s}
.sum-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.sum-card.green::before{background:var(--accent)}
.sum-card.blue::before{background:var(--accent2)}
.sum-card.purple::before{background:var(--purple)}
.sum-card.gold::before{background:var(--gold)}
.sum-card:hover{border-color:rgba(255,255,255,0.12)}
.sum-val{font-family:'Space Mono',monospace;font-size:26px;font-weight:700;line-height:1;margin-bottom:4px}
.sum-lbl{font-size:11px;color:var(--muted2);text-transform:uppercase;letter-spacing:.06em;font-weight:500}

/* ── LEFT PANEL (table) ── */
.left-panel{}
.panel-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:12px;flex-wrap:wrap}
.panel-title{font-family:'Space Mono',monospace;font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px}
.panel-title i{color:var(--accent);font-size:11px}
.search-wrap{display:flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:7px 12px;min-width:220px;transition:border-color .2s}
.search-wrap:focus-within{border-color:var(--accent2)}
.search-wrap i{color:var(--muted);font-size:12px}
.search-wrap input{background:none;border:none;outline:none;color:var(--text);font-size:13px;font-family:'DM Sans',sans-serif;flex:1}
.search-wrap input::placeholder{color:var(--muted)}

/* ── TABLE ── */
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden}
table{width:100%;border-collapse:collapse}
thead tr{background:var(--surface2);border-bottom:1px solid var(--border)}
th{padding:11px 14px;font-size:11px;font-family:'Space Mono',monospace;color:var(--muted2);text-transform:uppercase;letter-spacing:.05em;text-align:left;white-space:nowrap}
th:last-child{text-align:right}
tbody tr{border-bottom:1px solid rgba(31,35,48,0.7);cursor:pointer;transition:background .12s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:var(--surface2)}
tbody tr.active{background:rgba(59,130,246,0.08);border-left:3px solid var(--accent2)}
td{padding:11px 14px;font-size:13px;vertical-align:middle}
td:last-child{text-align:right}

.rank-cell{font-family:'Space Mono',monospace;font-size:12px;color:var(--muted2);width:40px}
.rank-gold{color:var(--gold);font-weight:700}
.rank-silver{color:#94a3b8;font-weight:700}
.rank-bronze{color:#c97b4b;font-weight:700}

.av-row{display:flex;align-items:center;gap:10px}
.av{width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff}
.av img{width:100%;height:100%;object-fit:cover}
.av-info{}
.av-name{font-size:13px;font-weight:600;color:var(--text);line-height:1.2}
.av-email{font-size:11px;color:var(--muted);margin-top:1px}
.badge-admin{font-size:9px;background:rgba(139,92,246,0.2);color:#c4b5fd;border:1px solid rgba(139,92,246,0.3);padding:1px 6px;border-radius:4px;font-family:'Space Mono',monospace;margin-left:6px}

.score-cell{font-family:'Space Mono',monospace;font-size:13px;font-weight:700;color:var(--accent)}
.watch-cell{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--muted2)}
.watch-cell i{color:var(--accent2);font-size:10px}
.seen-cell{font-size:11px;color:var(--muted)}
.no-data{text-align:center;padding:40px;color:var(--muted);font-size:13px}

/* ── RIGHT PANEL (detail) ── */
.detail-panel{
  background:var(--surface);border:1px solid var(--border);border-radius:16px;
  overflow:hidden;position:sticky;top:78px;height:fit-content;
  max-height:calc(100vh - 100px);overflow-y:auto;
  scrollbar-width:thin;scrollbar-color:var(--border) transparent;
}
.detail-panel::-webkit-scrollbar{width:4px}
.detail-panel::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}
.detail-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 30px;text-align:center;color:var(--muted);gap:12px}
.detail-empty i{font-size:32px;opacity:.3}
.detail-empty p{font-size:13px;line-height:1.6}

.detail-header{
  padding:22px 22px 16px;
  background:linear-gradient(135deg,rgba(59,130,246,0.08),rgba(139,92,246,0.05));
  border-bottom:1px solid var(--border);
}
.detail-av-row{display:flex;align-items:center;gap:14px;margin-bottom:14px}
.detail-av{width:54px;height:54px;border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#fff}
.detail-av img{width:100%;height:100%;object-fit:cover}
.detail-name{font-size:16px;font-weight:700;color:var(--text);line-height:1.2;margin-bottom:3px}
.detail-email{font-size:11px;color:var(--muted2)}
.detail-joined{font-size:11px;color:var(--muted);margin-top:3px}
.rank-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.25);
  color:var(--gold);padding:4px 12px;border-radius:20px;
  font-family:'Space Mono',monospace;font-size:12px;font-weight:700;
}

.detail-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1px;background:var(--border)}
.ds{background:var(--surface);padding:14px 12px;text-align:center}
.ds-val{font-family:'Space Mono',monospace;font-size:18px;font-weight:700;color:var(--text);line-height:1;margin-bottom:4px}
.ds-lbl{font-size:10px;color:var(--muted2);text-transform:uppercase;letter-spacing:.05em}

.detail-section{padding:16px 22px;border-bottom:1px solid rgba(31,35,48,0.7)}
.detail-section:last-child{border-bottom:none}
.section-title{font-size:11px;font-family:'Space Mono',monospace;color:var(--muted2);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.section-title i{font-size:10px}

.video-item{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid rgba(31,35,48,0.5)}
.video-item:last-child{border-bottom:none}
.video-icon{width:28px;height:28px;border-radius:8px;background:rgba(59,130,246,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.video-icon i{font-size:11px;color:var(--accent2)}
.video-title{font-size:12px;color:var(--text);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.video-time{font-size:11px;color:var(--muted);flex-shrink:0}

.subject-row{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.subject-row:last-child{margin-bottom:0}
.subject-name{font-size:12px;color:var(--muted2);width:110px;flex-shrink:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.subject-bar-wrap{flex:1;height:6px;background:var(--surface2);border-radius:3px;overflow:hidden}
.subject-bar-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--accent),var(--accent2));transition:width .4s ease}
.subject-score{font-family:'Space Mono',monospace;font-size:11px;color:var(--accent);flex-shrink:0;width:40px;text-align:right}

.activity-item{display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(31,35,48,0.4)}
.activity-item:last-child{border-bottom:none}
.ai-label{font-size:12px;color:var(--muted2);display:flex;align-items:center;gap:7px}
.ai-label i{font-size:10px;color:var(--accent2);width:14px}
.ai-val{font-family:'Space Mono',monospace;font-size:12px;color:var(--text);font-weight:700}
.ai-val.green{color:var(--accent)}
.ai-val.blue{color:var(--accent2)}

/* Loading spinner */
.spinner{display:inline-block;width:20px;height:20px;border:2px solid rgba(255,255,255,0.1);border-top-color:var(--accent2);border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.loading-wrap{display:flex;align-items:center;justify-content:center;padding:40px;gap:12px;color:var(--muted);font-size:13px}

/* Avatar palette */
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="topbar-brand">
    <div class="brand-dot"></div>
    EXCELLENT SIMPLIFIED
    <span style="color:var(--muted);font-weight:400">/ User Monitor</span>
  </div>
  <div class="topbar-right">
    <span class="topbar-admin">👑 <?= htmlspecialchars($adminName) ?></span>
    <a class="btn-back" href="dashboard.php">
      <i class="fa fa-arrow-left"></i> Admin
    </a>
    <a class="btn-back" href="../dashboard.php">
      <i class="fa fa-house"></i> Site
    </a>
  </div>
</div>

<!-- PAGE -->
<div class="page">

  <!-- Summary Strip -->
  <div class="summary-strip">
    <div class="sum-card green">
      <div class="sum-val" style="color:var(--accent)"><?= number_format($totalUsers) ?></div>
      <div class="sum-lbl">Total Users</div>
    </div>
    <div class="sum-card blue">
      <div class="sum-val" style="color:var(--accent2)"><?= number_format($totalVideos) ?></div>
      <div class="sum-lbl">Videos Available</div>
    </div>
    <div class="sum-card purple">
      <div class="sum-val" style="color:var(--purple)"><?= number_format($totalMessages) ?></div>
      <div class="sum-lbl">Chat Messages</div>
    </div>
    <div class="sum-card gold">
      <div class="sum-val" style="color:var(--gold)"><?= count($users) ?></div>
      <div class="sum-lbl">In Leaderboard</div>
    </div>
  </div>

  <!-- LEFT: User Table -->
  <div class="left-panel">
    <div class="panel-header">
      <div class="panel-title">
        <i class="fa fa-users"></i>
        All Users
      </div>
      <div class="search-wrap">
        <i class="fa fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search name or email…">
      </div>
    </div>

    <div class="table-wrap">
      <table id="userTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Student</th>
            <th>Score</th>
            <th><i class="fa fa-play" style="font-size:10px"></i> Videos</th>
            <th>Last Seen</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <?php foreach($users as $u):
            $name  = htmlspecialchars($u['google_name'] ?: ($u['username'] ?: 'Student'));
            $email = htmlspecialchars($u['email'] ?? '');
            $rank  = $u['rank'];
            $rankClass = $rank===1?'rank-gold':($rank===2?'rank-silver':($rank===3?'rank-bronze':''));
            $bg    = avatarBg($u['google_name'] ?: $u['username'] ?: '?');
            $init  = mb_strtoupper(mb_substr($u['google_name'] ?: $u['username'] ?: '?', 0, 1));
            $seen  = $u['last_seen'] ? date('M j, g:ia', strtotime($u['last_seen'])) : '—';
          ?>
          <tr data-uid="<?= $u['id'] ?>" data-name="<?= strtolower($name) ?>" data-email="<?= strtolower($email) ?>">
            <td class="rank-cell <?= $rankClass ?>">
              <?php if($rank<=3): ?>
                <?= $rank===1?'🥇':($rank===2?'🥈':'🥉') ?>
              <?php else: echo $rank; endif; ?>
            </td>
            <td>
              <div class="av-row">
                <div class="av" style="background:<?= $bg ?>">
                  <?php if($u['google_picture']): ?><img src="<?= htmlspecialchars($u['google_picture']) ?>" alt="<?= $init ?>"><?php else: echo $init; endif; ?>
                </div>
                <div class="av-info">
                  <div class="av-name">
                    <?= $name ?>
                    <?php if($u['is_admin']): ?><span class="badge-admin">ADMIN</span><?php endif; ?>
                  </div>
                  <div class="av-email"><?= $email ?></div>
                </div>
              </div>
            </td>
            <td class="score-cell"><?= number_format((int)$u['total_score']) ?></td>
            <td>
              <div class="watch-cell">
                <i class="fa fa-play-circle"></i>
                <?= (int)$u['videos_watched'] ?>
              </div>
            </td>
            <td class="seen-cell"><?= $seen ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$users): ?>
            <tr><td colspan="5" class="no-data">No users found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- RIGHT: Detail Panel -->
  <div class="detail-panel" id="detailPanel">
    <div class="detail-empty">
      <i class="fa fa-user-magnifying-glass"></i>
      <p>Click any student in the table to see their full profile, videos watched, scores, and activity.</p>
    </div>
  </div>

</div><!-- /page -->

<script>
// ── Avatar palette ──────────────────────────────────────────────────────────
const PALETTES = [
  '#6366f1','#8b5cf6','#ec4899','#f59e0b',
  '#10b981','#3b82f6','#06b6d4','#f97316',
  '#84cc16','#ef4444','#a78bfa','#34d399',
];
function avBg(n){ let h=0; for(const c of(n||''))h=(h*31+c.charCodeAt(0))&0xffffff; return PALETTES[Math.abs(h)%PALETTES.length]; }
function avIni(s){ return (s||'?').trim().charAt(0).toUpperCase(); }
function timeAgo(dt){
  if(!dt)return'—';
  const diff=Date.now()-new Date(dt).getTime();
  const m=Math.floor(diff/60000);
  if(m<1)return'Just now';
  if(m<60)return m+'m ago';
  const h=Math.floor(m/60);
  if(h<24)return h+'h ago';
  return Math.floor(h/24)+'d ago';
}
function fmtDate(dt){ if(!dt)return'—'; try{return new Date(dt).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'});}catch{return dt;} }

// ── Search ─────────────────────────────────────────────────────────────────
document.getElementById('searchInput').addEventListener('input', function(){
  const q = this.value.toLowerCase().trim();
  document.querySelectorAll('#tableBody tr[data-uid]').forEach(row=>{
    const match = !q || row.dataset.name.includes(q) || row.dataset.email.includes(q);
    row.style.display = match ? '' : 'none';
  });
});

// ── Row click → load detail ────────────────────────────────────────────────
let activeUid = null;
document.querySelectorAll('#tableBody tr[data-uid]').forEach(row=>{
  row.addEventListener('click', ()=> loadUser(parseInt(row.dataset.uid), row));
});

async function loadUser(uid, rowEl){
  if(activeUid === uid) return;
  activeUid = uid;
  document.querySelectorAll('#tableBody tr').forEach(r=>r.classList.remove('active'));
  rowEl.classList.add('active');

  const panel = document.getElementById('detailPanel');
  panel.innerHTML = '<div class="loading-wrap"><div class="spinner"></div> Loading…</div>';

  try{
    const j = await fetch(`leaderboard.php?action=user_detail&uid=${uid}`).then(r=>r.json());
    if(!j.success){ panel.innerHTML = `<div class="detail-empty"><i class="fa fa-circle-exclamation"></i><p>${j.error||'Failed to load'}</p></div>`; return; }
    renderDetail(j, panel);
  }catch(e){
    panel.innerHTML = '<div class="detail-empty"><i class="fa fa-wifi-slash"></i><p>Network error</p></div>';
  }
}

function renderDetail(data, panel){
  const u = data.user, s = data.stats;
  const bg = avBg(u.name||'?');
  const ini = avIni(u.name||'?');

  let html = `
    <div class="detail-header">
      <div class="detail-av-row">
        <div class="detail-av" style="background:${bg}">
          ${u.picture ? `<img src="${esc(u.picture)}" alt="${esc(ini)}">` : esc(ini)}
        </div>
        <div>
          <div class="detail-name">${esc(u.name)} ${u.is_admin?'<span style="font-size:10px;background:rgba(139,92,246,.2);color:#c4b5fd;border:1px solid rgba(139,92,246,.3);padding:1px 6px;border-radius:4px;font-family:\'Space Mono\',monospace">ADMIN</span>':''}</div>
          <div class="detail-email">${esc(u.email)}</div>
          <div class="detail-joined">Joined ${fmtDate(u.joined)}</div>
        </div>
      </div>
      ${s.rank !== '-' ? `<div class="rank-badge"><i class="fa fa-trophy"></i> Rank #${s.rank}</div>` : ''}
    </div>

    <div class="detail-stats">
      <div class="ds">
        <div class="ds-val" style="color:var(--accent)">${numFmt(s.score)}</div>
        <div class="ds-lbl">Score</div>
      </div>
      <div class="ds">
        <div class="ds-val" style="color:var(--accent2)">${s.videosWatched}</div>
        <div class="ds-lbl">Videos</div>
      </div>
      <div class="ds">
        <div class="ds-val" style="color:var(--purple)">${s.quizAttempts}</div>
        <div class="ds-lbl">Quizzes</div>
      </div>
    </div>

    <div class="detail-section">
      <div class="section-title"><i class="fa fa-chart-bar"></i> Activity Overview</div>
      <div class="activity-item">
        <div class="ai-label"><i class="fa fa-comment-dots"></i> Chat Messages</div>
        <div class="ai-val blue">${s.chatMessages}</div>
      </div>
      ${s.quizAccuracy !== null ? `
      <div class="activity-item">
        <div class="ai-label"><i class="fa fa-bullseye"></i> Quiz Accuracy</div>
        <div class="ai-val green">${s.quizAccuracy}%</div>
      </div>` : ''}
      <div class="activity-item">
        <div class="ai-label"><i class="fa fa-clock"></i> Last Seen</div>
        <div class="ai-val">${timeAgo(s.lastSeen)}</div>
      </div>
    </div>`;

  // Subject breakdown
  if(data.subjectBreakdown && data.subjectBreakdown.length){
    const maxPts = Math.max(...data.subjectBreakdown.map(x=>+x.pts),1);
    html += `<div class="detail-section">
      <div class="section-title"><i class="fa fa-book-open"></i> Subject Scores</div>`;
    for(const sub of data.subjectBreakdown){
      const pct = Math.round((+sub.pts/maxPts)*100);
      html += `<div class="subject-row">
        <div class="subject-name" title="${esc(sub.name)}">${esc(sub.name)}</div>
        <div class="subject-bar-wrap"><div class="subject-bar-fill" style="width:${pct}%"></div></div>
        <div class="subject-score">${numFmt(sub.pts)}</div>
      </div>`;
    }
    html += '</div>';
  }

  // Recent videos
  if(data.videoList && data.videoList.length){
    html += `<div class="detail-section">
      <div class="section-title"><i class="fa fa-play-circle"></i> Recently Watched (${s.videosWatched} total)</div>`;
    for(const v of data.videoList){
      const title = v.title || `Video #${v.id}`;
      const when = v.updated_at ? timeAgo(v.updated_at) : '';
      html += `<div class="video-item">
        <div class="video-icon"><i class="fa fa-play"></i></div>
        <div class="video-title" title="${esc(title)}">${esc(title)}</div>
        <div class="video-time">${when}</div>
      </div>`;
    }
    html += '</div>';
  } else if(s.videosWatched === 0){
    html += `<div class="detail-section">
      <div class="section-title"><i class="fa fa-play-circle"></i> Videos Watched</div>
      <div style="font-size:12px;color:var(--muted);padding:6px 0">No videos watched yet.</div>
    </div>`;
  }

  html += '</div>'; // close detail panel root isn't needed - we're replacing innerHTML
  panel.innerHTML = html;
}

function esc(s){ return s?String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[c])):''; }
function numFmt(n){ return Number(n||0).toLocaleString(); }
</script>
</body>
</html>
<?php
// ── Helper: avatar bg ────────────────────────────────────────────────────────
function avatarBg($name) {
    $palettes = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#06b6d4','#f97316','#84cc16','#ef4444','#a78bfa','#34d399'];
    $h = 0;
    foreach(mb_str_split($name ?: '?') as $c) $h = ($h * 31 + mb_ord($c)) & 0xffffff;
    return $palettes[abs($h) % count($palettes)];
}
