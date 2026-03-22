<?php
// ~/excellent-academy/chat/index.php
// Student chat room — real-time user-to-user messaging

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
session_start();
require_once __DIR__ . "/../config/db.php";
header('Cache-Control: no-store');

// ---------- helpers ----------
function json_out($data){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'utf-8'); }

function tableExists($conn, $table){
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? null;
    if (!$db) return false;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    if (!$stmt) return false;
    $stmt->bind_param("ss",$db,$table); $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return (int)($r['c']??0) > 0;
}
function columnExists($conn, $table, $column){
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? null;
    if (!$db) return false;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    if (!$stmt) return false;
    $stmt->bind_param("sss",$db,$table,$column); $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return (int)($r['c']??0) > 0;
}

// ---------- auth: same pattern as dashboard.php ----------
$hasSession = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;

$USER_ID         = 0;
$display_name    = '';
$display_email   = '';
$display_picture = null;
$is_admin        = 0;

if ($hasSession) {
    $USER_ID = (int)$_SESSION['user_id'];

    $google_name    = $_SESSION['google_name']    ?? null;
    $google_picture = $_SESSION['google_picture'] ?? null;
    $google_email   = $_SESSION['google_email']   ?? null;

    // Pull fresh user row from DB (same as dashboard.php)
    $userRow = [];
    $stmt = $conn->prepare("SELECT id, username, email, google_name, google_picture FROM users WHERE id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $USER_ID);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
    }

    if (columnExists($conn, 'users', 'is_admin')) {
        $s = $conn->prepare("SELECT is_admin FROM users WHERE id=? LIMIT 1");
        if ($s) { $s->bind_param("i",$USER_ID); $s->execute(); $tmp=$s->get_result()->fetch_assoc(); $is_admin=(int)($tmp['is_admin']??0); $s->close(); }
    }

    // Name priority: session google_name > DB google_name > DB username > email prefix
    $display_name    = $google_name
                    ?: ($userRow['google_name']
                    ?: ($userRow['username']
                    ?: ($google_email ? explode('@', $google_email)[0]
                    : (isset($userRow['email']) ? explode('@', $userRow['email'])[0] : 'Student'))));
    $display_email   = $google_email   ?: ($userRow['email']         ?? '');
    $display_picture = $google_picture ?: ($userRow['google_picture'] ?? null);

    // Auto-register as chat participant (upsert) so they appear in the online list
    // without needing to send a message first
    $conn->query("INSERT INTO chat_participants (user_id, display_name, last_seen)
        VALUES ($USER_ID, '" . $conn->real_escape_string($display_name) . "', NOW())
        ON DUPLICATE KEY UPDATE display_name='" . $conn->real_escape_string($display_name) . "', last_seen=NOW()");
}

// Guest name fallback (for users who are NOT logged in)
if (!$hasSession && !empty($_POST['guest_name'])) {
    $display_name = trim(substr(strip_tags($_POST['guest_name']), 0, 40));
    $_SESSION['chat_guest_name'] = $display_name;
}
if (!$hasSession && empty($display_name) && isset($_SESSION['chat_guest_name'])) {
    $display_name = $_SESSION['chat_guest_name'];
}

$USER_NAME = $display_name;

// ── STRICT AUTH: Only allow logged-in users ──────────
// Remove all guest access — redirect immediately if no session
if (!$hasSession) {
    if (isset($_REQUEST['action'])) {
        json_out(['success' => false, 'error' => 'Not authenticated']);
    }
    header('Location: ../login.php');
    exit;
}

// Auto-register as participant on page load

// ---------- ensure DB schema (safe) ----------
$conn->query("CREATE TABLE IF NOT EXISTS chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room VARCHAR(100) NOT NULL DEFAULT 'global',
  user_id INT DEFAULT 0,
  display_name VARCHAR(255) DEFAULT NULL,
  message TEXT NOT NULL,
  mentions TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(room), INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS chat_participants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT 0,
  display_name VARCHAR(255) DEFAULT NULL,
  last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  created_by INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS announcement_reads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  announcement_id INT NOT NULL,
  user_id INT DEFAULT 0,
  seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (announcement_id, user_id),
  FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// New: reactions per message
$conn->query("CREATE TABLE IF NOT EXISTS chat_reactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  user_id INT DEFAULT 0,
  display_name VARCHAR(255),
  emoji VARCHAR(10) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_react(message_id,user_id,emoji),
  INDEX(message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// New: typing status
$conn->query("CREATE TABLE IF NOT EXISTS chat_typing (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room VARCHAR(100) DEFAULT 'global',
  user_id INT DEFAULT 0,
  display_name VARCHAR(255),
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_typing(room,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ---------- simple API ----------
$action = $_REQUEST['action'] ?? '';
if ($action === 'send_message') {
    $room = $_POST['room'] ?? 'global';
    $message = trim($_POST['message'] ?? '');
    $mentions = $_POST['mentions'] ?? ''; // optional JSON string or comma-separated
    if ($message === '') json_out(['success'=>false,'error'=>'Empty message']);
    $mentionsArr = [];
    if ($mentions) {
        // accept JSON array or comma-separated
        $m = @json_decode($mentions, true);
        if (is_array($m)) $mentionsArr = array_values($m);
        else $mentionsArr = array_values(array_filter(array_map('trim', explode(',', $mentions))));
    }

    // insert participant (or update last_seen)
    if ($USER_ID) {
        $u = $conn->prepare("SELECT id FROM chat_participants WHERE user_id=? LIMIT 1");
        $u->bind_param('i', $USER_ID);
        $u->execute();
        $res = $u->get_result();
        if ($row = $res->fetch_assoc()) {
            $pid = (int)$row['id'];
            $upd = $conn->prepare("UPDATE chat_participants SET display_name=?, last_seen=NOW() WHERE id=?");
            $upd->bind_param('si', $USER_NAME, $pid);
            $upd->execute();
            $upd->close();
        } else {
            $ins = $conn->prepare("INSERT INTO chat_participants (user_id, display_name, last_seen) VALUES (?, ?, NOW())");
            $ins->bind_param('is', $USER_ID, $USER_NAME);
            $ins->execute();
            $ins->close();
        }
        $u->close();
    } else {
        // guest: update or insert by display_name (best-effort)
        $g = $conn->prepare("SELECT id FROM chat_participants WHERE display_name=? LIMIT 1");
        $g->bind_param('s', $USER_NAME);
        $g->execute();
        $res = $g->get_result();
        if ($row = $res->fetch_assoc()) {
            $upd = $conn->prepare("UPDATE chat_participants SET last_seen=NOW() WHERE id=?");
            $upd->bind_param('i', $row['id']);
            $upd->execute(); $upd->close();
        } else {
            $ins = $conn->prepare("INSERT INTO chat_participants (user_id, display_name, last_seen) VALUES (0, ?, NOW())");
            $ins->bind_param('s', $USER_NAME);
            $ins->execute(); $ins->close();
        }
        $g->close();
    }

    $mentionsJson = json_encode(array_values($mentionsArr), JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare("INSERT INTO chat_messages (room, user_id, display_name, message, mentions) VALUES (?, ?, ?, ?, ?)");
    $uid = $USER_ID;
    $stmt->bind_param('sisss', $room, $uid, $USER_NAME, $message, $mentionsJson);
    $stmt->execute();
    $msgId = $conn->insert_id;
    $stmt->close();

    // If message mentions AI (case-insensitive '@ai' or 'ai' in mentions), call AI and insert AI reply
    $mentionedAI = false;
    foreach ($mentionsArr as $m) {
        if (strtolower(trim($m)) === 'ai' || strtolower(trim($m)) === '@ai') { $mentionedAI = true; break; }
    }

    if ($mentionedAI) {
        // Call local AI endpoint /ai/textbook_ai.php with question -> receive answer
        // We'll call via internal POST to relative path (best-effort)
        $aiAnswer = null;
        $aiEndpoint = dirname(__DIR__) . '/ai/textbook_ai.php';
        // If file exists, try to POST to it using CURL to same host (or require it and call function)
        // Simpler: do a server-side POST request to the local endpoint via curl (relative to site root)
        $baseUrl = (isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')) : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $aiUrl = $baseUrl . '/ai/textbook_ai.php';
        $payload = json_encode(['question' => $message], JSON_UNESCAPED_UNICODE);
        // make POST request with curl, but be robust if curl disabled
        $aiResp = null;
        if (function_exists('curl_version')) {
            $ch = curl_init($aiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            $res = curl_exec($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            if ($res !== false && $res !== null) {
                $jr = @json_decode($res, true);
                if (is_array($jr) && !empty($jr['answer'])) $aiAnswer = $jr['answer'];
                else if (is_string($res)) $aiAnswer = $res;
            }
        } else {
            // fallback: try file_get_contents
            $opts = ['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload, 'timeout' => 8]];
            $ctx = stream_context_create($opts);
            $res = @file_get_contents($aiUrl, false, $ctx);
            if ($res !== false) {
                $jr = @json_decode($res, true);
                if (is_array($jr) && !empty($jr['answer'])) $aiAnswer = $jr['answer'];
                else $aiAnswer = $res;
            }
        }

        if ($aiAnswer) {
            $aiName = "AI Tutor";
            $ins = $conn->prepare("INSERT INTO chat_messages (room, user_id, display_name, message, mentions) VALUES (?, 0, ?, ?, ?)");
            $mentionsJson2 = json_encode([], JSON_UNESCAPED_UNICODE);
            $ins->bind_param('ssss', $room, $aiName, $aiAnswer, $mentionsJson2);
            $ins->execute();
            $ins->close();
        }
    }

    json_out(['success'=>true,'message_id'=>$msgId]);
}

if ($action === 'fetch_messages') {
    $room = $_GET['room'] ?? 'global';
    $after = isset($_GET['after']) ? $_GET['after'] : 0; // message id
    $limit = isset($_GET['limit']) ? min(200, (int)$_GET['limit']) : 100;
    if ($after) {
        $stmt = $conn->prepare("SELECT id, user_id, display_name, message, mentions, created_at FROM chat_messages WHERE room=? AND id > ? ORDER BY id ASC LIMIT ?");
        $stmt->bind_param('sii', $room, $after, $limit);
    } else {
        $stmt = $conn->prepare("SELECT id, user_id, display_name, message, mentions, created_at FROM chat_messages WHERE room=? ORDER BY id DESC LIMIT ?");
        $stmt->bind_param('si', $room, $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    // when we fetched DESC we should reverse to show oldest first
    if (!$after) $rows = array_reverse($rows);
    json_out(['success'=>true,'messages'=>$rows]);
}

if ($action === 'participants') {
    // Deduplicate by user_id — only return the most recently seen entry per user
    $limit = isset($_GET['limit']) ? min(200, (int)$_GET['limit']) : 50;
    $res = $conn->query("
        SELECT user_id, display_name, MAX(last_seen) AS last_seen
        FROM chat_participants
        WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        GROUP BY user_id
        ORDER BY last_seen DESC
        LIMIT " . intval($limit));
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    json_out(['success'=>true,'participants'=>$out]);
}

if ($action === 'check_announcements') {
    // returns latest announcement not seen by this user (if any)
    // If user is not signed in, treat user_id = 0 but still mark reads referencing 0
    $user = $USER_ID;
    // get latest announcement
    $res = $conn->query("SELECT id, title, body, created_at FROM announcements ORDER BY created_at DESC LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) json_out(['success'=>true,'announcement'=>null]);
    $aid = (int)$row['id'];
    // check if read
    $stmt = $conn->prepare("SELECT 1 FROM announcement_reads WHERE announcement_id=? AND user_id=? LIMIT 1");
    $uidForRead = $user ? $user : 0;
    $stmt->bind_param('ii', $aid, $uidForRead);
    $stmt->execute();
    $rr = $stmt->get_result();
    $seen = (bool)$rr->fetch_assoc();
    $stmt->close();
    if ($seen) json_out(['success'=>true,'announcement'=>null]); // already seen
    json_out(['success'=>true,'announcement'=>$row]);
}

if ($action === 'mark_announcement_seen') {
    $aid = isset($_POST['announcement_id']) ? intval($_POST['announcement_id']) : 0;
    if (!$aid) json_out(['success'=>false,'error'=>'no id']);
    $uidForRead = $USER_ID ? $USER_ID : 0;
    $ins = $conn->prepare("INSERT IGNORE INTO announcement_reads (announcement_id, user_id, seen_at) VALUES (?, ?, NOW())");
    $ins->bind_param('ii', $aid, $uidForRead);
    $ins->execute();
    $ins->close();
    json_out(['success'=>true]);
}

if ($action === 'react') {
    $msgId = (int)($_POST['message_id'] ?? 0);
    $emoji = trim($_POST['emoji'] ?? '');
    if (!$msgId || !$emoji) json_out(['success'=>false,'error'=>'missing params']);
    $uid  = $USER_ID;
    $name = $USER_NAME;
    $chk  = $conn->prepare("SELECT id FROM chat_reactions WHERE message_id=? AND user_id=? AND emoji=? LIMIT 1");
    $chk->bind_param('iis',$msgId,$uid,$emoji); $chk->execute();
    $existing = $chk->get_result()->fetch_assoc(); $chk->close();
    if ($existing) {
        $del = $conn->prepare("DELETE FROM chat_reactions WHERE id=?"); $del->bind_param('i',$existing['id']); $del->execute(); $del->close(); $done='removed';
    } else {
        $ins = $conn->prepare("INSERT IGNORE INTO chat_reactions(message_id,user_id,display_name,emoji)VALUES(?,?,?,?)"); $ins->bind_param('iiss',$msgId,$uid,$name,$emoji); $ins->execute(); $ins->close(); $done='added';
    }
    $rq = $conn->query("SELECT emoji,COUNT(*) AS cnt FROM chat_reactions WHERE message_id=$msgId GROUP BY emoji");
    $reactions=[]; while($r=$rq->fetch_assoc()) $reactions[$r['emoji']]=(int)$r['cnt'];
    json_out(['success'=>true,'action'=>$done,'reactions'=>$reactions]);
}

if ($action === 'set_typing') {
    $room   = addslashes($_POST['room'] ?? 'global');
    $typing = (int)($_POST['typing'] ?? 0);
    if ($typing) {
        $conn->query("INSERT INTO chat_typing(room,user_id,display_name,updated_at)VALUES('$room',$USER_ID,'".addslashes($USER_NAME)."',NOW()) ON DUPLICATE KEY UPDATE display_name='".addslashes($USER_NAME)."',updated_at=NOW()");
    } else {
        $conn->query("DELETE FROM chat_typing WHERE user_id=$USER_ID AND room='$room'");
    }
    json_out(['success'=>true]);
}

if ($action === 'get_typing') {
    $room = addslashes($_GET['room'] ?? 'global');
    $uid  = intval($USER_ID);
    $res  = $conn->query("SELECT display_name FROM chat_typing WHERE room='$room' AND user_id!=$uid AND updated_at > DATE_SUB(NOW(),INTERVAL 4 SECOND)");
    $names=[]; while($r=$res->fetch_assoc()) $names[]=$r['display_name'];
    json_out(['success'=>true,'typing'=>$names]);
}

// Updated fetch_messages — includes reactions
if ($action === 'fetch_messages_v2') {
    $room  = $_GET['room']  ?? 'global';
    $after = (int)($_GET['after'] ?? 0);
    $limit = min(200,(int)($_GET['limit']??80));
    if ($after) { $stmt=$conn->prepare("SELECT id,user_id,display_name,message,mentions,created_at FROM chat_messages WHERE room=? AND id>? ORDER BY id ASC LIMIT ?"); $stmt->bind_param('sii',$room,$after,$limit); }
    else { $stmt=$conn->prepare("SELECT id,user_id,display_name,message,mentions,created_at FROM chat_messages WHERE room=? ORDER BY id DESC LIMIT ?"); $stmt->bind_param('si',$room,$limit); }
    $stmt->execute(); $res=$stmt->get_result(); $rows=[];
    while($r=$res->fetch_assoc()){
        $rid=(int)$r['id'];
        $rq=$conn->query("SELECT emoji,COUNT(*) AS cnt FROM chat_reactions WHERE message_id=$rid GROUP BY emoji");
        $reactions=[]; while($rrow=$rq->fetch_assoc()) $reactions[$rrow['emoji']]=(int)$rrow['cnt'];
        $myReacts=[];
        if($USER_ID){ $mrq=$conn->query("SELECT emoji FROM chat_reactions WHERE message_id=$rid AND user_id=$USER_ID"); while($mr=$mrq->fetch_assoc()) $myReacts[]=$mr['emoji']; }
        $r['reactions']=$reactions; $r['my_reactions']=$myReacts;
        $rows[]=$r;
    }
    $stmt->close();
    if(!$after) $rows=array_reverse($rows);
    json_out(['success'=>true,'messages'=>$rows]);
}

// nothing matched => render UI
// nothing matched => render UI
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,viewport-fit=cover">
<title>Class Chat · Excellent Simplified</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Fredoka+One&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%;overflow:hidden}
body{
  font-family:'Nunito',sans-serif;
  height:100%;overflow:hidden;
  background:#f0f4ff;
  -webkit-font-smoothing:antialiased;
}

/* ── TOKENS ── */
:root{
  --bg:#f0f4ff;
  --panel-bg:#ffffff;
  --surface:#ffffff;
  --surface2:#f5f7ff;
  --surface3:#e8ecff;
  --border:#e2e8ff;
  --text:#1a1a2e;
  --muted:#8892b0;
  --sent-bg:linear-gradient(135deg,#667eea,#764ba2);
  --recv-bg:#ffffff;
  --ai-bg:linear-gradient(135deg,#f093fb,#f5576c);
  --tch-bg:linear-gradient(135deg,#4facfe,#00f2fe);
  --accent:#667eea;
  --accent2:#f5576c;
  --green:#06d6a0;
}

/* ── APP SHELL ── */
.app{display:flex;width:100%;height:100vh;height:100dvh;overflow:hidden;}

/* ── SIDEBAR ── */
.panel{
  width:300px;flex-shrink:0;
  background:var(--panel-bg);
  border-right:2px solid var(--border);
  display:flex;flex-direction:column;
  height:100%;overflow:hidden;
  position:relative;
}
/* rainbow top border */
.panel::before{
  content:'';position:absolute;top:0;left:0;right:0;height:4px;
  background:linear-gradient(90deg,#667eea,#f5576c,#ffd93d,#06d6a0,#667eea);
  background-size:200% auto;animation:rainbow 3s linear infinite;
}
@keyframes rainbow{0%{background-position:0%}100%{background-position:200%}}

.panel-top{padding:16px 14px 10px;flex-shrink:0;margin-top:4px}
.panel-brand{
  display:flex;align-items:center;gap:10px;margin-bottom:14px;
}
.panel-logo{
  width:38px;height:38px;border-radius:12px;flex-shrink:0;
  background:linear-gradient(135deg,#667eea,#764ba2);
  display:flex;align-items:center;justify-content:center;
  font-size:18px;box-shadow:0 4px 12px rgba(102,126,234,0.4);
}
.panel-title{font-family:'Fredoka One',cursive;font-size:18px;color:var(--text)}
.panel-title span{background:linear-gradient(135deg,#667eea,#f5576c);-webkit-background-clip:text;background-clip:text;color:transparent}

.panel-user-row{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;border-radius:14px;
  background:linear-gradient(135deg,rgba(102,126,234,0.08),rgba(245,87,108,0.05));
  border:1px solid rgba(102,126,234,0.15);
  margin-bottom:12px;
}
.panel-av{
  width:38px;height:38px;border-radius:50%;overflow:hidden;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-size:15px;font-weight:800;color:#fff;
  box-shadow:0 3px 10px rgba(0,0,0,0.15);
}
.panel-av img{width:100%;height:100%;object-fit:cover}
.panel-uinfo{flex:1;min-width:0}
.panel-username{font-size:14px;font-weight:800;color:var(--text);line-height:1.2}
.panel-role{font-size:11px;color:var(--muted);font-weight:600}
.panel-search{
  background:var(--surface2);border-radius:12px;
  display:flex;align-items:center;gap:8px;
  padding:9px 12px;border:1.5px solid var(--border);
  transition:border-color .2s;
}
.panel-search:focus-within{border-color:var(--accent)}
.panel-search i{color:var(--muted);font-size:13px}
.panel-search input{flex:1;background:none;border:none;outline:none;color:var(--text);font-size:13px;font-family:inherit;font-weight:600}
.panel-search input::placeholder{color:var(--muted)}

.panel-section-label{
  font-size:11px;font-weight:800;color:var(--muted);
  letter-spacing:.08em;text-transform:uppercase;
  padding:10px 14px 6px;
}
.part-count{
  display:inline-flex;align-items:center;justify-content:center;
  width:20px;height:20px;border-radius:20px;
  background:var(--accent);color:#fff;font-size:10px;font-weight:800;
  margin-left:6px;
}

.part-list{flex:1;overflow-y:auto;padding:0 6px 6px}
.part-list::-webkit-scrollbar{width:3px}
.part-list::-webkit-scrollbar-thumb{background:#dde;border-radius:2px}

.part-row{
  display:flex;align-items:center;gap:10px;
  padding:8px 10px;border-radius:12px;margin-bottom:2px;
  cursor:pointer;transition:all .15s;
}
.part-row:hover{background:var(--surface2);transform:translateX(2px)}
.part-row.me-row{background:linear-gradient(135deg,rgba(102,126,234,0.08),rgba(245,87,108,0.05));border:1px solid rgba(102,126,234,0.15)}
.part-av{
  width:40px;height:40px;border-radius:50%;flex-shrink:0;
  overflow:hidden;position:relative;
  display:flex;align-items:center;justify-content:center;
  font-size:15px;font-weight:800;color:#fff;
  box-shadow:0 3px 8px rgba(0,0,0,0.12);
}
.part-av img{width:100%;height:100%;object-fit:cover}
.part-dot{
  position:absolute;bottom:1px;right:1px;
  width:11px;height:11px;border-radius:50%;
  background:var(--green);border:2px solid #fff;
}
.part-info{flex:1;min-width:0}
.part-name{font-size:13px;font-weight:800;color:var(--text);line-height:1.2}
.part-sub{font-size:11px;color:var(--muted);font-weight:600;margin-top:1px}
.me-badge{
  font-size:10px;font-weight:800;color:#fff;
  background:var(--accent);padding:1px 7px;border-radius:20px;
}

.panel-footer{border-top:2px solid var(--border);padding:8px 8px;flex-shrink:0}
.pf-link{
  display:flex;align-items:center;gap:10px;
  padding:9px 12px;border-radius:10px;
  text-decoration:none;color:var(--muted);
  font-size:13px;font-weight:700;
  transition:all .15s;cursor:pointer;
}
.pf-link:hover{background:var(--surface2);color:var(--text);transform:translateX(2px)}
.pf-link i{width:16px;text-align:center;font-size:14px}
.pf-link.danger{color:#f5576c}
.pf-link.danger:hover{background:#fff0f2;color:#f5576c}

.panel-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:39;backdrop-filter:blur(2px)}
.panel-overlay.show{display:block}

/* ── CHAT WINDOW ── */
.chat-win{
  flex:1;display:flex;flex-direction:column;
  min-width:0;height:100vh;height:100dvh;overflow:hidden;
  background:var(--bg);
}

/* Header */
.chat-header{
  flex-shrink:0;
  background:var(--surface);
  border-bottom:2px solid var(--border);
  display:flex;align-items:center;
  padding:0 14px;gap:12px;
  height:62px;z-index:10;
  position:relative;
}
.chat-header::after{
  content:'';position:absolute;bottom:-2px;left:0;right:0;height:2px;
  background:linear-gradient(90deg,#667eea,#f5576c,#ffd93d,#06d6a0);
}
.ch-back{
  width:36px;height:36px;border-radius:50%;
  background:var(--surface2);border:1.5px solid var(--border);
  color:var(--muted);cursor:pointer;display:none;
  align-items:center;justify-content:center;font-size:16px;
  transition:all .15s;
}
.ch-back:hover{background:var(--surface3);color:var(--text)}
.ch-av{
  width:42px;height:42px;border-radius:14px;flex-shrink:0;
  background:linear-gradient(135deg,#667eea,#764ba2);
  display:flex;align-items:center;justify-content:center;
  font-size:18px;color:#fff;
  box-shadow:0 4px 12px rgba(102,126,234,0.35);
}
.ch-info{flex:1;min-width:0}
.ch-name{font-family:'Fredoka One',cursive;font-size:17px;color:var(--text);line-height:1.2}
.ch-status{font-size:12px;color:var(--muted);font-weight:700}
.ch-actions{display:flex;gap:6px}
.ch-btn{
  width:36px;height:36px;border-radius:10px;
  background:var(--surface2);border:1.5px solid var(--border);
  color:var(--muted);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:15px;transition:all .15s;
}
.ch-btn:hover{background:var(--surface3);color:var(--accent);border-color:var(--accent)}

/* Messages */
.msgs{
  flex:1;min-height:0;overflow-y:auto;overflow-x:hidden;
  padding:16px 16px 8px;
  background:linear-gradient(180deg,#f0f4ff 0%,#f5f0ff 100%);
}
.msgs::-webkit-scrollbar{width:4px}
.msgs::-webkit-scrollbar-thumb{background:#dde;border-radius:2px}

/* Fun background pattern */
.msgs::before{
  content:'';position:fixed;inset:62px 0 0 0;
  pointer-events:none;z-index:0;
  background-image:
    radial-gradient(circle at 20% 30%,rgba(102,126,234,0.04) 0%,transparent 50%),
    radial-gradient(circle at 80% 70%,rgba(245,87,108,0.04) 0%,transparent 50%);
}

/* Date chip */
.date-chip{
  text-align:center;margin:12px 0 8px;
  font-size:11px;font-weight:800;color:#fff;
  display:flex;justify-content:center;
}
.date-chip span{
  background:linear-gradient(135deg,#667eea,#764ba2);
  padding:4px 14px;border-radius:20px;
  box-shadow:0 2px 8px rgba(102,126,234,0.3);
}

/* Broadcast */
.bcast-pill{display:flex;justify-content:center;margin:6px 0}
.bcast-pill span{
  background:linear-gradient(135deg,#ffd93d,#ff6b6b);
  color:#fff;font-size:12.5px;font-weight:700;
  padding:6px 16px;border-radius:20px;
  text-align:center;max-width:80%;
  box-shadow:0 2px 8px rgba(255,107,107,0.3);
}

/* Message group */
.msg-group{
  display:flex;gap:8px;margin-bottom:6px;
  align-items:flex-end;position:relative;z-index:1;
  animation:msgin .2s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes msgin{from{opacity:0;transform:translateY(10px) scale(0.95)}to{opacity:1;transform:none}}
.msg-group.sent{flex-direction:row-reverse}
.group-av{
  width:32px;height:32px;border-radius:50%;flex-shrink:0;
  align-self:flex-end;overflow:hidden;
  display:flex;align-items:center;justify-content:center;
  font-size:12px;font-weight:800;color:#fff;
  margin-bottom:2px;box-shadow:0 3px 8px rgba(0,0,0,0.15);
}
.group-av img{width:100%;height:100%;object-fit:cover}
.group-av.ghost{opacity:0;pointer-events:none}

.bubbles-col{
  display:flex;flex-direction:column;gap:3px;
  max-width:68%;align-items:flex-start;position:relative;
}
.msg-group.sent .bubbles-col{align-items:flex-end}

.sender-name{
  font-size:11px;font-weight:800;
  margin-bottom:3px;padding:0 6px;
  letter-spacing:0.02em;
}

/* Bubbles */
.bubble{
  padding:10px 14px;
  font-size:14.5px;line-height:1.5;
  word-break:break-word;position:relative;
  max-width:100%;cursor:pointer;font-weight:600;
}
.bubble.sent{
  background:var(--sent-bg);color:#fff;
  border-radius:18px 18px 4px 18px;
  box-shadow:0 4px 15px rgba(102,126,234,0.35);
}
.bubble.recv{
  background:var(--recv-bg);color:var(--text);
  border-radius:18px 18px 18px 4px;
  box-shadow:0 2px 10px rgba(0,0,0,0.08);
  border:1.5px solid var(--border);
}
.bubble.ai-bub{
  background:var(--ai-bg);color:#fff;
  border-radius:18px 18px 18px 4px;
  box-shadow:0 4px 15px rgba(245,87,108,0.35);
}
.bubble.tch-bub{
  background:var(--tch-bg);color:#fff;
  border-radius:18px 18px 18px 4px;
  box-shadow:0 4px 15px rgba(79,172,254,0.35);
}
.bubble.mid{border-radius:18px!important}

.btime{font-size:11px;color:var(--muted);padding:0 6px;margin-top:2px;display:block;font-weight:700}
.msg-group.sent .btime{text-align:right}

/* Reactions */
.r-bar{display:flex;flex-wrap:wrap;gap:4px;padding:3px 4px}
.r-pill{
  display:inline-flex;align-items:center;gap:3px;
  padding:3px 8px;border-radius:20px;
  background:#fff;border:1.5px solid var(--border);
  font-size:13px;cursor:pointer;user-select:none;
  transition:all .15s;font-weight:700;
  box-shadow:0 1px 4px rgba(0,0,0,0.06);
}
.r-pill:hover{transform:scale(1.12);box-shadow:0 3px 10px rgba(0,0,0,0.12)}
.r-pill.mine{border-color:var(--accent);background:rgba(102,126,234,0.08);color:var(--accent)}
.r-cnt{font-size:11px;color:var(--muted);font-weight:800}

/* Emoji popup */
.emoji-pop{
  position:absolute;bottom:calc(100% + 6px);
  background:#fff;border:1.5px solid var(--border);
  border-radius:24px;padding:8px 10px;
  display:none;gap:4px;
  box-shadow:0 8px 30px rgba(0,0,0,0.15);
  z-index:40;white-space:nowrap;
}
.msg-group.sent .emoji-pop{right:0}
.msg-group:not(.sent) .emoji-pop{left:0}
.emoji-pop.show{display:flex}
.ep-e{font-size:22px;cursor:pointer;padding:3px 5px;border-radius:10px;transition:all .12s;line-height:1}
.ep-e:hover{background:var(--surface2);transform:scale(1.25)}

.react-hover{position:absolute;bottom:6px;opacity:0;pointer-events:none;transition:opacity .15s;z-index:5}
.msg-group.sent .react-hover{left:-36px}
.msg-group:not(.sent) .react-hover{right:-36px}
.bubbles-col:hover .react-hover{opacity:1;pointer-events:all}
.react-hover button{
  width:28px;height:28px;border-radius:50%;
  background:#fff;border:1.5px solid var(--border);
  cursor:pointer;font-size:15px;
  display:flex;align-items:center;justify-content:center;
  transition:all .12s;box-shadow:0 2px 8px rgba(0,0,0,0.1);
}
.react-hover button:hover{transform:scale(1.15);box-shadow:0 4px 12px rgba(0,0,0,0.15)}

/* Typing */
.typing-bub{
  display:none;align-items:center;gap:5px;
  background:#fff;border:1.5px solid var(--border);
  border-radius:18px 18px 18px 4px;
  padding:10px 16px;width:fit-content;margin-bottom:6px;
  box-shadow:0 2px 10px rgba(0,0,0,0.08);
}
.typing-bub.show{display:flex}
.ty{width:7px;height:7px;border-radius:50%;background:linear-gradient(135deg,#667eea,#f5576c);animation:tdot 1.2s infinite}
.ty:nth-child(2){animation-delay:.18s}
.ty:nth-child(3){animation-delay:.36s}
@keyframes tdot{0%,80%,100%{transform:scale(.6);opacity:.3}40%{transform:scale(1);opacity:1}}

/* Scroll FAB */
.scroll-fab{
  position:absolute;bottom:85px;right:16px;z-index:15;
  width:40px;height:40px;border-radius:50%;
  background:linear-gradient(135deg,#667eea,#764ba2);
  border:none;color:#fff;cursor:pointer;
  display:none;align-items:center;justify-content:center;
  font-size:16px;box-shadow:0 4px 16px rgba(102,126,234,0.5);
  transition:all .15s;
}
.scroll-fab.show{display:flex}
.scroll-fab:hover{transform:scale(1.1)}

/* COMPOSER */
.composer{
  flex-shrink:0;
  background:var(--surface);
  border-top:2px solid var(--border);
  padding:10px 12px;
  padding-bottom:max(10px,env(safe-area-inset-bottom,0px));
  display:flex;align-items:flex-end;gap:8px;
  position:relative;z-index:10;
}
.ie-picker{
  position:absolute;bottom:calc(100% + 8px);left:12px;
  background:#fff;border:1.5px solid var(--border);
  border-radius:18px;padding:12px;
  display:none;flex-wrap:wrap;gap:6px;max-width:310px;
  box-shadow:0 8px 30px rgba(0,0,0,0.12);z-index:50;
}
.ie-picker.show{display:flex}
.ie-e{font-size:24px;cursor:pointer;padding:4px;border-radius:10px;transition:all .1s;line-height:1}
.ie-e:hover{background:var(--surface2);transform:scale(1.2)}

.mention-drop{
  position:absolute;bottom:calc(100% + 6px);left:12px;right:12px;
  background:#fff;border:1.5px solid var(--border);
  border-radius:16px;overflow:hidden;
  display:none;max-height:200px;overflow-y:auto;
  box-shadow:0 8px 30px rgba(0,0,0,0.12);z-index:50;
}
.mention-drop.show{display:block}
.md-item{display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;transition:background .1s}
.md-item:hover{background:var(--surface2)}
.md-av{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#fff;flex-shrink:0}
.md-name{font-size:14px;color:var(--text);font-weight:700}

.emoji-trigger{
  width:38px;height:38px;border-radius:12px;
  background:var(--surface2);border:1.5px solid var(--border);
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  font-size:20px;flex-shrink:0;transition:all .15s;
}
.emoji-trigger:hover{background:var(--surface3);transform:scale(1.08)}

.input-pill{
  flex:1;background:var(--surface2);border-radius:20px;
  display:flex;align-items:flex-end;
  padding:9px 14px;gap:8px;min-height:42px;
  border:1.5px solid var(--border);
  transition:border-color .2s;
}
.input-pill:focus-within{border-color:var(--accent)}
#msgInput{
  flex:1;background:none;border:none;outline:none;
  color:var(--text);font-family:inherit;font-size:15px;font-weight:600;
  resize:none;min-height:24px;max-height:120px;
  line-height:1.45;align-self:center;
}
#msgInput::placeholder{color:var(--muted)}

.send-btn{
  width:42px;height:42px;border-radius:14px;
  background:linear-gradient(135deg,#667eea,#764ba2);
  border:none;color:#fff;cursor:pointer;font-size:17px;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;transition:all .2s cubic-bezier(.34,1.56,.64,1);
  box-shadow:0 4px 15px rgba(102,126,234,0.45);
}
.send-btn:hover{transform:scale(1.1) rotate(-5deg);box-shadow:0 6px 20px rgba(102,126,234,0.6)}
.send-btn:active{transform:scale(.93)}

/* Announcement */
.ann-ov{position:fixed;inset:0;z-index:80;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.ann-ov.show{display:flex}
.ann-card{width:100%;max-width:360px;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.2);animation:pop .3s cubic-bezier(.34,1.56,.64,1) both}
@keyframes pop{from{transform:scale(.85);opacity:0}to{transform:scale(1);opacity:1}}
.ann-stripe{height:4px;background:linear-gradient(90deg,#667eea,#f5576c,#ffd93d)}
.ann-head{padding:18px 20px 8px;display:flex;align-items:flex-start;gap:12px}
.ann-icon{font-size:24px;flex-shrink:0;margin-top:2px}
.ann-lbl{font-size:11px;font-weight:800;color:var(--accent);letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
.ann-title{font-size:16px;font-weight:800;color:var(--text)}
.ann-body{padding:0 20px 16px;font-size:14px;color:var(--muted);line-height:1.65;font-weight:600}
.ann-foot{padding:12px 20px;border-top:1.5px solid var(--border);display:flex;justify-content:flex-end}
.ann-ok{padding:10px 24px;border-radius:12px;border:none;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;transition:all .2s}
.ann-ok:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(102,126,234,0.4)}

/* Empty state */
.empty-state{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:40px 20px;text-align:center;opacity:0.7;
}
.empty-state .es-emoji{font-size:48px;margin-bottom:12px;animation:bounce 2s ease-in-out infinite}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.empty-state p{font-size:15px;font-weight:700;color:var(--muted)}
.empty-state small{font-size:13px;color:var(--muted);margin-top:4px;font-weight:600}

/* Responsive */
@media(max-width:768px){
  .panel{position:fixed;left:0;top:0;height:100%;z-index:40;width:85%;max-width:300px;transform:translateX(-100%);transition:transform .3s cubic-bezier(.34,1.56,.64,1);box-shadow:6px 0 30px rgba(0,0,0,0.2)}
  .panel.open{transform:translateX(0)}
  .ch-back{display:flex}
  .msgs{padding:12px 10px 6px}
  .bubbles-col{max-width:78%}
}
@media(max-width:480px){.bubbles-col{max-width:86%}.bubble{font-size:14px;padding:9px 12px}}
</style>
</head>
<body>
<div class="panel-overlay" id="panelOverlay"></div>
<div class="app">

  <!-- SIDEBAR -->
  <aside class="panel" id="panel">
    <div class="panel-top">
      <div class="panel-brand">
        <div class="panel-logo">🎓</div>
        <div class="panel-title">Class <span>Chat</span></div>
      </div>
      <div class="panel-user-row">
        <div class="panel-av" id="myAv">
<?php if($display_picture):?><img src="<?=esc($display_picture)?>" alt=""><?php else: echo esc(mb_strtoupper(mb_substr($display_name,0,1))); endif?>
        </div>
        <div class="panel-uinfo">
          <div class="panel-username"><?=esc($display_name)?></div>
          <div class="panel-role"><?=$is_admin?'👑 Admin':'📚 Student'?></div>
        </div>
      </div>
      <div class="panel-search">
        <i class="fa fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search members…">
      </div>
    </div>

    <div class="panel-section-label">
      🟢 Online Now <span class="part-count" id="partCount">0</span>
    </div>
    <div class="part-list" id="partList"></div>

    <div class="panel-footer">
      <a href="../dashboard.php" class="pf-link"><i class="fa fa-house"></i> Dashboard</a>
      <a href="../exams/practice_test.php" class="pf-link"><i class="fa fa-file-pen"></i> Practice Tests</a>
      <?php if($is_admin):?><a href="../admin/live_brainstorm_control.php" class="pf-link"><i class="fa fa-tower-broadcast"></i> Admin Panel</a><?php endif?>
      <a href="../auth/logout.php" class="pf-link danger"><i class="fa fa-right-from-bracket"></i> Sign Out</a>
    </div>
  </aside>

  <!-- CHAT WINDOW -->
  <div class="chat-win">

    <div class="chat-header">
      <button class="ch-back" id="chBack"><i class="fa fa-arrow-left"></i></button>
      <div class="ch-av">🎒</div>
      <div class="ch-info">
        <div class="ch-name">Excellent Class Chat</div>
        <div class="ch-status" id="chStatus">Loading…</div>
      </div>
      <div class="ch-actions">
        <button class="ch-btn" id="panelToggleBtn" title="Members"><i class="fa fa-users"></i></button>
      </div>
    </div>

    <div class="msgs" id="msgs">
      <div id="messages">
        <div class="empty-state" id="emptyState">
          <div class="es-emoji">💬</div>
          <p>No messages yet!</p>
          <small>Be the first to say something 👇</small>
        </div>
      </div>
      <div class="typing-bub" id="typingBub">
        <div class="ty"></div><div class="ty"></div><div class="ty"></div>
      </div>
    </div>

    <button class="scroll-fab" id="scrollFab" onclick="scrollBot()">
      <i class="fa fa-chevron-down"></i>
    </button>

    <div class="composer">
      <div class="mention-drop" id="mentionDrop"></div>
      <div class="ie-picker" id="iePicker">
<?php foreach(['😊','😂','❤️','🔥','👍','💯','😍','🤔','😅','🙌','✅','📚','🎯','💪','🧠','😭','🫡','👀','🥳','🎉','🏆','⚡','🌟','😤','🫶'] as $e):?><span class="ie-e" onclick="insertEmoji('<?=$e?>')"><?=$e?></span><?php endforeach?>
      </div>
      <button class="emoji-trigger" id="emojiBtn">😊</button>
      <div class="input-pill">
        <textarea id="msgInput" rows="1" placeholder="Say something fun…"></textarea>
      </div>
      <button class="send-btn" id="sendBtn">
        <i class="fa fa-paper-plane" style="transform:rotate(45deg) translate(1px,-1px)"></i>
      </button>
    </div>

  </div>
</div>

<!-- Announcement overlay -->
<div class="ann-ov" id="annOv">
  <div class="ann-card">
    <div class="ann-stripe"></div>
    <div class="ann-head"><div class="ann-icon">📢</div><div><div class="ann-lbl">Announcement</div><div class="ann-title" id="annTitle">—</div></div></div>
    <div class="ann-body" id="annBody"></div>
    <div class="ann-foot"><button class="ann-ok" id="annOk">Got it! 👍</button></div>
  </div>
</div>

<script>
const API='index.php', ROOM='global';
const REACTS=['❤️','😂','😮','😢','👍','🔥'];
const ME={
  id:   <?=json_encode((int)$USER_ID)?>,
  name: <?=json_encode($USER_NAME)?>,
  pic:  <?=json_encode($display_picture)?>
};
let lastId=0, participants=[], typingTimer=null;

function esc(s){return s?String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[c])):'';}
function tStr(dt){try{return new Date(dt).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});}catch{return '';}}
function scrollBot(){document.getElementById('msgs').scrollTo({top:9999999,behavior:'smooth'});}

/* Colorful avatar backgrounds */
const PALETTES=[
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
function avBg(n){let h=0;for(const c of(n||''))h=(h*31+c.charCodeAt(0))&0xffffff;return PALETTES[Math.abs(h)%PALETTES.length];}
function avIni(s){return(s||'?').trim().charAt(0).toUpperCase();}

/* Set my avatar color */
const myAvEl=document.getElementById('myAv');
if(myAvEl&&!ME.pic) myAvEl.style.background=avBg(ME.name||'?');

/* Panel toggle */
const panel=document.getElementById('panel'),panelOv=document.getElementById('panelOverlay');
function openPanel(){panel.classList.add('open');panelOv.classList.add('show');}
function closePanel(){panel.classList.remove('open');panelOv.classList.remove('show');}
panelOv.addEventListener('click',closePanel);
document.getElementById('panelToggleBtn').addEventListener('click',()=>panel.classList.contains('open')?closePanel():openPanel());
document.getElementById('chBack').addEventListener('click',openPanel);
function checkMobile(){document.getElementById('chBack').style.display=window.innerWidth<=768?'flex':'none';}
checkMobile(); window.addEventListener('resize',checkMobile);

/* Load participants */
async function loadParticipants(){
  try{
    const j=await fetch(API+'?action=participants').then(r=>r.json());
    if(!j.success)return;
    participants=j.participants||[];
    const list=document.getElementById('partList'); list.innerHTML='';
    const count=participants.length;
    document.getElementById('partCount').textContent=count;
    document.getElementById('chStatus').textContent=count+' member'+(count!==1?'s':'')+' online';

    if(!participants.length){
      list.innerHTML='<div style="text-align:center;padding:20px;color:var(--muted);font-size:13px;font-weight:700">No one online yet 😴</div>';
      return;
    }

    participants.forEach(p=>{
      const bg=avBg(p.display_name||'?');
      const isMe=+p.user_id===+ME.id;
      const d=document.createElement('div');
      d.className='part-row'+(isMe?' me-row':'');
      const avContent=`<div class="part-av" style="background:${bg}">${esc(avIni(p.display_name||'?'))}<span class="part-dot"></span></div>`;
      d.innerHTML=avContent+`
        <div class="part-info">
          <div class="part-name">${esc(p.display_name||'Anon')}${isMe?' <span class="me-badge">You</span>':''}</div>
          <div class="part-sub">⏱ ${tStr(p.last_seen)}</div>
        </div>`;
      d.addEventListener('click',()=>{addMention(p.display_name);closePanel();});
      list.appendChild(d);
    });
  }catch(e){}
}

/* Search */
document.getElementById('searchInput').addEventListener('input',function(){
  const q=this.value.toLowerCase();
  document.querySelectorAll('.part-row').forEach(el=>{
    el.style.display=(el.querySelector('.part-name')?.textContent?.toLowerCase()||'').includes(q)?'':'none';
  });
});

/* Render message */
let prevKey=null;
function renderMsg(m,container){
  /* Hide empty state */
  const es=document.getElementById('emptyState');
  if(es) es.style.display='none';

  const isSent=(+m.user_id&&+m.user_id===+ME.id)||(!ME.id&&m.display_name===ME.name);
  const nl=(m.display_name||'').toLowerCase();
  const isAI=nl.includes('ai tutor'),isTch=nl.includes('admin')||nl.includes('teacher')||nl.includes('onet');
  const isBcast=String(m.message||'').startsWith('📢')||String(m.message||'').startsWith('🔔');

  if(isBcast){
    const b=document.createElement('div');b.className='bcast-pill';
    b.innerHTML=`<span>${esc(m.message)}</span>`;
    container.appendChild(b);prevKey=null;return;
  }

  const sKey=String(m.user_id||m.display_name);
  const stacked=prevKey===sKey; prevKey=sKey;
  const bg=avBg(m.display_name||'?');
  const reactions=m.reactions||{},myR=m.my_reactions||[];
  const reHtml=Object.entries(reactions).filter(([,c])=>c>0).map(([e,c])=>`<span class="r-pill${myR.includes(e)?' mine':''}" onclick="reactTo(${m.id},'${e}')">${e}<span class="r-cnt">${c}</span></span>`).join('');
  const epHtml=REACTS.map(e=>`<span class="ep-e" onclick="reactTo(${m.id},'${e}')">${e}</span>`).join('');
  const bCls=isSent?'sent':isAI?'ai-bub':isTch?'tch-bub':'recv';

  const g=document.createElement('div');
  g.className=`msg-group${isSent?' sent':''}`;g.id='mg-'+m.id;

  let avHtml='';
  if(isSent&&ME.pic) avHtml=`<img src="${esc(ME.pic)}" alt="">`;
  else avHtml=esc(avIni(m.display_name||'?'));

  const avStyle=isSent&&ME.pic?'':'background:'+bg;

  g.innerHTML=`
    <div class="group-av${stacked?' ghost':''}" style="${avStyle}">${avHtml}</div>
    <div class="bubbles-col">
      ${!isSent&&!stacked?`<div class="sender-name" style="color:${avBg(m.display_name||'?').match(/#[a-f0-9]{6}/i)?.[0]||'var(--accent)'}">${esc(m.display_name||'Anon')}</div>`:''}
      <div style="position:relative">
        <div class="react-hover"><button onclick="toggleEP(${m.id})">😊</button></div>
        <div class="emoji-pop" id="ep-${m.id}">${epHtml}</div>
        <div class="bubble ${bCls}${stacked?' mid':''}" id="bbl-${m.id}">${esc(m.message)}</div>
      </div>
      ${reHtml?`<div class="r-bar" id="rb-${m.id}">${reHtml}</div>`:''}
      <span class="btime">${tStr(m.created_at)}</span>
    </div>`;

  /* long-press to react */
  const bbl=g.querySelector('.bubble');
  let lt;
  bbl.addEventListener('touchstart',()=>{lt=setTimeout(()=>toggleEP(m.id),500);},{passive:true});
  bbl.addEventListener('touchend',()=>clearTimeout(lt),{passive:true});
  bbl.addEventListener('touchmove',()=>clearTimeout(lt),{passive:true});
  bbl.addEventListener('dblclick',()=>toggleEP(m.id));
  container.appendChild(g);
}

/* Fetch messages */
async function fetchMessages(){
  try{
    const j=await fetch(`${API}?action=fetch_messages_v2&room=${ROOM}&after=${lastId}&limit=80`).then(r=>r.json());
    if(!j.success)return;
    const msgs=j.messages||[]; if(!msgs.length)return;
    const wrap=document.getElementById('msgs');
    const atBot=wrap.scrollHeight-wrap.scrollTop-wrap.clientHeight<120;
    const container=document.getElementById('messages');
    msgs.forEach(m=>renderMsg(m,container));
    lastId=Math.max(lastId,...msgs.map(m=>+m.id));
    if(atBot)wrap.scrollTop=wrap.scrollHeight;
    else document.getElementById('scrollFab').classList.add('show');
  }catch(e){}
}

/* Reactions */
function toggleEP(id){
  document.querySelectorAll('.emoji-pop').forEach(p=>{if(p.id!=='ep-'+id)p.classList.remove('show');});
  document.getElementById('ep-'+id)?.classList.toggle('show');
}
async function reactTo(msgId,emoji){
  document.getElementById('ep-'+msgId)?.classList.remove('show');
  try{
    const fd=new FormData();fd.append('message_id',msgId);fd.append('emoji',emoji);
    const j=await fetch(API+'?action=react',{method:'POST',body:fd}).then(r=>r.json());
    if(!j.success)return;
    const html=Object.entries(j.reactions||{}).filter(([,c])=>c>0).map(([e,c])=>`<span class="r-pill" onclick="reactTo(${msgId},'${e}')">${e}<span class="r-cnt">${c}</span></span>`).join('');
    let rb=document.getElementById('rb-'+msgId);
    if(rb){rb.innerHTML=html;if(!html)rb.remove();}
    else if(html){rb=document.createElement('div');rb.className='r-bar';rb.id='rb-'+msgId;rb.innerHTML=html;document.getElementById('bbl-'+msgId)?.insertAdjacentElement('afterend',rb);}
  }catch(e){}
}
document.addEventListener('click',e=>{
  if(!e.target.closest('.emoji-pop')&&!e.target.closest('.react-hover')&&!e.target.closest('.bubble'))
    document.querySelectorAll('.emoji-pop').forEach(p=>p.classList.remove('show'));
  if(!e.target.closest('.ie-picker')&&!e.target.closest('#emojiBtn'))
    document.getElementById('iePicker').classList.remove('show');
});

/* Send */
const msgInput=document.getElementById('msgInput');
async function sendMessage(){
  const txt=msgInput.value.trim(); if(!txt)return;
  const mentions=[...new Set([...String(txt).match(/@([A-Za-z0-9_\-\. ]{1,50})/g)||[]].map(m=>m.slice(1).trim()))];
  if(/@ai\b/i.test(txt))mentions.push('AI');
  const fd=new FormData();fd.append('room',ROOM);fd.append('message',txt);fd.append('mentions',JSON.stringify(mentions));
  msgInput.value='';msgInput.style.height='auto';
  setTyping(false);clearTimeout(typingTimer);
  document.getElementById('mentionDrop').classList.remove('show');
  try{const j=await fetch(API+'?action=send_message',{method:'POST',body:fd}).then(r=>r.json());if(j.success)fetchMessages();}catch(e){}
}
document.getElementById('sendBtn').addEventListener('click',sendMessage);
msgInput.addEventListener('keydown',e=>{
  if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();
    const dd=document.getElementById('mentionDrop');
    if(dd.classList.contains('show')){const f=dd.querySelector('.md-item');if(f){f.click();return;}}
    sendMessage();
  }
});
msgInput.addEventListener('input',()=>{
  msgInput.style.height='auto';msgInput.style.height=Math.min(msgInput.scrollHeight,120)+'px';
  handleMention();
  setTyping(true);clearTimeout(typingTimer);typingTimer=setTimeout(()=>setTyping(false),3000);
});

/* @mention */
function handleMention(){
  const val=msgInput.value,cur=msgInput.selectionStart,before=val.slice(0,cur);
  const match=before.match(/@([^@\s]*)$/);
  const dd=document.getElementById('mentionDrop');
  if(!match){dd.classList.remove('show');return;}
  const q=match[1].toLowerCase(); dd.innerHTML='';
  const opts=[{display_name:'AI Tutor',ai:true},...participants].filter(p=>(p.display_name||'').toLowerCase().startsWith(q)&&p.display_name!==ME.name).slice(0,6);
  if(!opts.length){dd.classList.remove('show');return;}
  opts.forEach(p=>{
    const bg=avBg(p.display_name||'?');
    const item=document.createElement('div');item.className='md-item';
    item.innerHTML=`<div class="md-av" style="background:${bg}">${p.ai?'🤖':esc(avIni(p.display_name||'?'))}</div><div class="md-name">@${esc(p.display_name||'Anon')}</div>`;
    item.addEventListener('click',()=>{msgInput.value=val.slice(0,cur-match[0].length)+'@'+p.display_name+' '+val.slice(cur);dd.classList.remove('show');msgInput.focus();});
    dd.appendChild(item);
  });
  dd.classList.add('show');
}
function addMention(n){msgInput.value+='@'+n+' ';msgInput.focus();}

/* Emoji picker */
document.getElementById('emojiBtn').addEventListener('click',()=>document.getElementById('iePicker').classList.toggle('show'));
function insertEmoji(e){msgInput.value+=e;msgInput.focus();document.getElementById('iePicker').classList.remove('show');}

/* Typing indicators */
async function setTyping(on){try{const fd=new FormData();fd.append('room',ROOM);fd.append('typing',on?'1':'0');await fetch(API+'?action=set_typing',{method:'POST',body:fd});}catch(e){}}
async function pollTyping(){
  try{
    const j=await fetch(`${API}?action=get_typing&room=${ROOM}`).then(r=>r.json());
    const tb=document.getElementById('typingBub');
    if(!j.typing?.length){tb.classList.remove('show');}
    else{
      tb.classList.add('show');
      const w=document.getElementById('msgs');
      if(w.scrollHeight-w.scrollTop-w.clientHeight<80)w.scrollTop=w.scrollHeight;
    }
  }catch(e){}
}

/* Scroll FAB */
document.getElementById('msgs').addEventListener('scroll',function(){
  document.getElementById('scrollFab').classList.toggle('show',this.scrollHeight-this.scrollTop-this.clientHeight>100);
});

/* Announcements */
async function checkAnn(){
  try{
    const j=await fetch(API+'?action=check_announcements').then(r=>r.json());
    if(!j.announcement)return;
    const a=j.announcement;
    document.getElementById('annTitle').textContent=a.title;
    document.getElementById('annBody').textContent=a.body;
    document.getElementById('annOv').classList.add('show');
    document.getElementById('annOk').onclick=async()=>{
      document.getElementById('annOv').classList.remove('show');
      const fd=new FormData();fd.append('announcement_id',a.id);
      await fetch(API+'?action=mark_announcement_seen',{method:'POST',body:fd});
    };
  }catch(e){}
}

/* Boot */
fetchMessages(); loadParticipants(); checkAnn();
document.getElementById('msgs').scrollTop=9999999;
setInterval(fetchMessages,1500);
setInterval(loadParticipants,8000);
setInterval(pollTyping,1500);
setInterval(checkAnn,30000);
document.addEventListener('visibilitychange',()=>{if(!document.hidden){fetchMessages();loadParticipants();}});
</script>
</body>
</html>
