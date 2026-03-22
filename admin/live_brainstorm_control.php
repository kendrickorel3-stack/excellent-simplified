<?php
// admin/live_brainstorm_control.php
// Admin control for live brainstorm / chat / announcements
// Place: ~/excellent-academy/admin/live_brainstorm_control.php

session_start();
require_once __DIR__ . "/../config/db.php";
header('Cache-Control: no-store');

// ── Auth: must be logged in AND is_admin ─────────────────────
$CURRENT_USER_ID   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$CURRENT_USER_NAME = $_SESSION['google_name'] ?? $_SESSION['username'] ?? 'Admin';

if (!$CURRENT_USER_ID) {
    if (isset($_REQUEST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>'Not authenticated']);
        exit;
    }
    header('Location: ../login.php');
    exit;
}

// Check is_admin flag
$isAdmin = false;
$chk = $conn->prepare("SELECT is_admin FROM users WHERE id=? LIMIT 1");
if ($chk) {
    $chk->bind_param('i', $CURRENT_USER_ID);
    $chk->execute();
    $tmp = $chk->get_result()->fetch_assoc();
    $isAdmin = (bool)($tmp['is_admin'] ?? 0);
    $chk->close();
}

if (!$isAdmin) {
    if (isset($_REQUEST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>'Access denied']);
        exit;
    }
    header('Location: ../dashboard.php');
    exit;
}

// Ensure chat/announcement schema exists (safe)
$conn->query("
CREATE TABLE IF NOT EXISTS chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room VARCHAR(100) NOT NULL DEFAULT 'global',
  user_id INT DEFAULT 0,
  display_name VARCHAR(255) DEFAULT NULL,
  message TEXT NOT NULL,
  mentions TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(room),
  INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$conn->query("
CREATE TABLE IF NOT EXISTS chat_participants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT 0,
  display_name VARCHAR(255) DEFAULT NULL,
  last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$conn->query("
CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  created_by INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$conn->query("
CREATE TABLE IF NOT EXISTS announcement_reads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  announcement_id INT NOT NULL,
  user_id INT DEFAULT 0,
  seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (announcement_id, user_id),
  FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Simple API routing (AJAX)
$action = $_REQUEST['action'] ?? '';

if ($action === 'create_announcement') {
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body'] ?? '');
    if ($title === '' || $body === '') json_response(['success'=>false,'error'=>'Missing title or body']);
    $stmt = $conn->prepare("INSERT INTO announcements (title, body, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $title, $body, $CURRENT_USER_ID);
    $ok = $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();
    json_response(['success'=>$ok, 'id'=>$id]);
}

if ($action === 'list_announcements') {
    $res = $conn->query("SELECT id, title, body, created_by, created_at FROM announcements ORDER BY created_at DESC LIMIT 100");
    $arr = [];
    while ($r = $res->fetch_assoc()) $arr[] = $r;
    json_response(['success'=>true, 'announcements'=>$arr]);
}

if ($action === 'announcement_reads') {
    $aid = intval($_GET['announcement_id'] ?? 0);
    if (!$aid) json_response(['success'=>false,'error'=>'missing id']);
    $stmt = $conn->prepare("
      SELECT ar.user_id, ar.seen_at, p.display_name
      FROM announcement_reads ar
      LEFT JOIN chat_participants p ON p.user_id = ar.user_id
      WHERE ar.announcement_id = ?
      ORDER BY ar.seen_at DESC
    ");
    $stmt->bind_param('i', $aid);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    json_response(['success'=>true,'reads'=>$rows]);
}

if ($action === 'post_admin_message') {
    $msg = trim($_POST['message'] ?? '');
    if ($msg === '') json_response(['success'=>false,'error'=>'empty message']);
    $disp = $CURRENT_USER_NAME;
    $stmt = $conn->prepare("INSERT INTO chat_messages (room, user_id, display_name, message) VALUES ('global', ?, ?, ?)");
    $stmt->bind_param('iss', $CURRENT_USER_ID, $disp, $msg);
    $ok = $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();
    json_response(['success'=>$ok, 'id'=>$id]);
}

if ($action === 'fetch_messages') {
    $after = intval($_GET['after'] ?? 0);
    $limit = min(200, intval($_GET['limit'] ?? 200));
    if ($after) {
        $stmt = $conn->prepare("SELECT id, user_id, display_name, message, created_at FROM chat_messages WHERE id > ? ORDER BY id ASC LIMIT ?");
        $stmt->bind_param('ii', $after, $limit);
    } else {
        $stmt = $conn->prepare("SELECT id, user_id, display_name, message, created_at FROM chat_messages ORDER BY id DESC LIMIT ?");
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    if (!$after) $rows = array_reverse($rows);
    json_response(['success'=>true,'messages'=>$rows]);
}

if ($action === 'participants') {
    $limit = min(500, intval($_GET['limit'] ?? 200));
    $res = $conn->query("
        SELECT user_id, display_name, MAX(last_seen) AS last_seen
        FROM chat_participants
        WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        GROUP BY user_id
        ORDER BY last_seen DESC
        LIMIT " . intval($limit));
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    json_response(['success'=>true,'participants'=>$out,'total'=>count($out)]);
}

if ($action === 'kick_participant') {
    $uid = intval($_POST['user_id'] ?? 0);
    if (!$uid) json_response(['success'=>false,'error'=>'missing user_id']);
    $stmt = $conn->prepare("DELETE FROM chat_participants WHERE user_id = ?");
    $stmt->bind_param('i', $uid);
    $ok = $stmt->execute();
    $stmt->close();
    json_response(['success'=>$ok]);
}

if ($action === 'clear_messages') {
    $stmt = $conn->prepare("TRUNCATE TABLE chat_messages");
    $ok = $stmt->execute();
    json_response(['success'=>$ok]);
}

function json_response($data){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- PAGE: admin UI ----------
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Live Brainstorm — Admin Control</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0a0c10;
      --surface: #111318;
      --surface2: #181b22;
      --border: #1f2330;
      --accent: #00c98a;
      --accent2: #3b82f6;
      --danger: #ff4757;
      --warning: #f59e0b;
      --text: #e8ecf4;
      --muted: #5a6278;
      --muted2: #8a93ab;
      --topbar-bg: rgba(10,12,16,0.92);
      --shadow: 0 2px 12px rgba(0,0,0,0.25);
    }

    /* ── LIGHT MODE ── */
    body.light {
      --bg: #f0f4f9;
      --surface: #ffffff;
      --surface2: #f4f7fc;
      --border: #dde3ee;
      --accent: #00a872;
      --accent2: #2563eb;
      --danger: #e53e3e;
      --warning: #d97706;
      --text: #0d1526;
      --muted: #9aa3b8;
      --muted2: #6b7a99;
      --topbar-bg: rgba(240,244,249,0.94);
      --shadow: 0 2px 12px rgba(0,0,0,0.08);
    }
    body.light {
      background-image:
        radial-gradient(ellipse 80% 60% at 10% 0%, rgba(0,168,114,0.06) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 90% 100%, rgba(37,99,235,0.05) 0%, transparent 60%);
    }
    body.light .topbar { background: var(--topbar-bg); }
    body.light .pulse-dot { background: var(--accent); box-shadow: 0 0 0 0 rgba(0,168,114,0.5); }
    body.light .msg-name { color: var(--accent); }
    body.light .badge-green { background: rgba(0,168,114,0.12); color: var(--accent); }
    body.light .card-title i { color: var(--accent); }
    body.light .status-bar { background: rgba(0,168,114,0.05); }

    /* ── THEME TOGGLE BUTTON ── */
    .theme-toggle {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 6px 12px; border-radius: 20px;
      border: 1px solid var(--border);
      background: var(--surface2); color: var(--muted2);
      cursor: pointer; font-family: 'DM Sans', sans-serif;
      font-size: 12px; font-weight: 500;
      transition: all 0.2s ease; white-space: nowrap;
    }
    .theme-toggle:hover { border-color: var(--accent); color: var(--text); }
    .theme-toggle .toggle-track {
      width: 30px; height: 17px; border-radius: 20px;
      background: var(--border); position: relative;
      transition: background 0.2s;
      flex-shrink: 0;
    }
    body.light .theme-toggle .toggle-track { background: var(--accent); }
    .theme-toggle .toggle-thumb {
      position: absolute; top: 2px; left: 2px;
      width: 13px; height: 13px; border-radius: 50%;
      background: #fff; transition: transform 0.2s ease;
      box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }
    body.light .theme-toggle .toggle-thumb { transform: translateX(13px); }
    .toggle-icon { font-size: 12px; }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      transition: background 0.25s ease, color 0.25s ease;
      background-image:
        radial-gradient(ellipse 80% 60% at 10% 0%, rgba(0,229,160,0.04) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 90% 100%, rgba(59,130,246,0.05) 0%, transparent 60%);
    }

    /* ── TOPBAR ── */
    .topbar {
      position: sticky; top: 0; z-index: 50;
      background: var(--topbar-bg);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 28px; height: 58px;
      transition: background 0.25s ease, border-color 0.25s ease;
    }
    .topbar-brand {
      display: flex; align-items: center; gap: 12px;
    }
    .topbar-logo {
      width: 32px; height: 32px; border-radius: 8px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Space Mono', monospace; font-size: 13px; font-weight: 700;
      color: #000;
      flex-shrink: 0;
    }
    .topbar-title {
      font-family: 'Space Mono', monospace;
      font-size: 13px; font-weight: 700; letter-spacing: 0.04em;
      color: var(--text);
    }
    .topbar-sub {
      font-size: 11px; color: var(--muted); margin-top: 1px; letter-spacing: 0.03em;
    }
    .topbar-actions { display: flex; gap: 8px; align-items: center; }

    /* ── BUTTONS ── */
    .btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px; border-radius: 7px; border: 1px solid transparent;
      cursor: pointer; font-family: 'DM Sans', sans-serif;
      font-size: 13px; font-weight: 500; transition: all 0.15s ease;
      text-decoration: none; white-space: nowrap;
    }
    .btn-primary {
      background: var(--accent); color: #000; border-color: var(--accent);
    }
    .btn-primary:hover { background: #00ffb2; border-color: #00ffb2; }
    .btn-ghost {
      background: transparent; color: var(--muted2); border-color: var(--border);
    }
    .btn-ghost:hover { color: var(--text); border-color: #2e3548; background: var(--surface2); }
    .btn-dark {
      background: var(--surface2); color: var(--text); border-color: var(--border);
    }
    .btn-dark:hover { background: #1e2233; }
    .btn-danger {
      background: transparent; color: var(--danger); border-color: rgba(255,71,87,0.35);
    }
    .btn-danger:hover { background: rgba(255,71,87,0.12); border-color: var(--danger); }
    .btn-blue {
      background: transparent; color: var(--accent2); border-color: rgba(59,130,246,0.35);
    }
    .btn-blue:hover { background: rgba(59,130,246,0.1); border-color: var(--accent2); }

    /* ── LAYOUT ── */
    .wrap { max-width: 1180px; margin: 0 auto; padding: 24px 20px; }
    .grid { display: grid; grid-template-columns: 1fr 380px; gap: 16px; }
    @media(max-width:900px){ .grid{ grid-template-columns: 1fr; } }

    /* ── CARDS ── */
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
    }
    .card + .card { margin-top: 16px; }
    .card-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 18px;
      border-bottom: 1px solid var(--border);
    }
    .card-title {
      font-family: 'Space Mono', monospace;
      font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
      text-transform: uppercase; color: var(--muted2);
      display: flex; align-items: center; gap: 8px;
    }
    .card-title i { color: var(--accent); font-size: 10px; }
    .card-body { padding: 18px; }

    /* ── BADGE / PILL ── */
    .badge {
      display: inline-flex; align-items: center;
      padding: 2px 8px; border-radius: 20px;
      font-size: 10px; font-weight: 700; letter-spacing: 0.05em;
      font-family: 'Space Mono', monospace;
    }
    .badge-green { background: rgba(0,229,160,0.12); color: var(--accent); }
    .badge-blue  { background: rgba(59,130,246,0.12); color: var(--accent2); }

    /* ── LIVE PULSE ── */
    .pulse-dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: var(--accent); display: inline-block;
      box-shadow: 0 0 0 0 rgba(0,229,160,0.6);
      animation: pulse-ring 1.8s infinite;
    }
    @keyframes pulse-ring {
      0%   { box-shadow: 0 0 0 0 rgba(0,229,160,0.5); }
      70%  { box-shadow: 0 0 0 6px rgba(0,229,160,0); }
      100% { box-shadow: 0 0 0 0 rgba(0,229,160,0); }
    }

    /* ── FORM FIELDS ── */
    .field {
      width: 100%; padding: 10px 13px;
      background: var(--surface2); border: 1px solid var(--border);
      border-radius: 8px; color: var(--text);
      font-family: 'DM Sans', sans-serif; font-size: 14px;
      transition: border-color 0.15s;
      outline: none; resize: none;
    }
    .field::placeholder { color: var(--muted); }
    .field:focus { border-color: var(--accent); }
    .field + .field { margin-top: 10px; }

    /* ── LISTS ── */
    .list { max-height: 420px; overflow-y: auto; }
    .list::-webkit-scrollbar { width: 4px; }
    .list::-webkit-scrollbar-track { background: transparent; }
    .list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

    .list-item {
      padding: 12px 18px;
      border-bottom: 1px solid var(--border);
      transition: background 0.1s;
    }
    .list-item:last-child { border-bottom: none; }
    .list-item:hover { background: var(--surface2); }

    .msg-name {
      font-size: 12px; font-weight: 600; color: var(--accent);
      font-family: 'Space Mono', monospace; margin-bottom: 3px;
    }
    .msg-name.admin { color: var(--accent2); }
    .msg-text { font-size: 13px; color: var(--text); line-height: 1.5; }
    .msg-time { font-size: 11px; color: var(--muted); margin-top: 4px; font-family: 'Space Mono', monospace; }

    .ann-title { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 3px; }
    .ann-body  { font-size: 12px; color: var(--muted2); line-height: 1.5; }
    .ann-meta  { font-size: 11px; color: var(--muted); margin-top: 6px; font-family: 'Space Mono', monospace; }

    .participant-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 10px 18px; border-bottom: 1px solid var(--border);
      transition: background 0.1s;
    }
    .participant-row:last-child { border-bottom: none; }
    .participant-row:hover { background: var(--surface2); }
    .participant-avatar {
      width: 30px; height: 30px; border-radius: 8px;
      background: linear-gradient(135deg, rgba(0,229,160,0.2), rgba(59,130,246,0.2));
      display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 700; color: var(--accent);
      font-family: 'Space Mono', monospace; flex-shrink: 0;
    }
    .participant-name { font-size: 13px; font-weight: 500; color: var(--text); }
    .participant-time { font-size: 11px; color: var(--muted); font-family: 'Space Mono', monospace; }

    /* ── STATUS BAR ── */
    .status-bar {
      display: flex; align-items: center; gap: 8px;
      padding: 8px 18px;
      background: rgba(0,229,160,0.04);
      border-bottom: 1px solid var(--border);
      font-size: 11px; color: var(--muted2);
      font-family: 'Space Mono', monospace;
    }

    /* ── SEND ROW ── */
    .send-row {
      display: flex; gap: 8px; padding: 12px 18px;
      border-top: 1px solid var(--border);
      background: var(--surface2);
    }
    .send-row .field { margin: 0; }

    /* ── STATUS MSG ── */
    .status-msg {
      font-size: 12px; color: var(--muted2);
      padding: 6px 18px 0;
      font-family: 'Space Mono', monospace;
    }
    .status-msg.ok  { color: var(--accent); }
    .status-msg.err { color: var(--danger); }

    /* ── DIVIDER ── */
    .form-row { display: flex; gap: 8px; margin-top: 10px; }

    /* ── FOOTER ── */
    .footer {
      margin-top: 32px; padding: 18px 0;
      border-top: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
    }
    .footer-brand {
      font-family: 'Space Mono', monospace; font-size: 11px;
      color: var(--muted); letter-spacing: 0.08em;
    }
    .footer-dot { color: var(--accent); margin: 0 6px; }

    /* ── MODAL ── */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
      align-items: center; justify-content: center; z-index: 90;
    }
    .modal-box {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 14px; width: 90%; max-width: 640px;
      overflow: hidden;
    }
    .modal-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 20px; border-bottom: 1px solid var(--border);
    }
    .modal-title {
      font-family: 'Space Mono', monospace; font-size: 12px;
      font-weight: 700; letter-spacing: 0.06em; color: var(--text);
      text-transform: uppercase;
    }
    .modal-body { padding: 16px 20px; max-height: 420px; overflow-y: auto; }
    .modal-footer {
      padding: 14px 20px; border-top: 1px solid var(--border);
      display: flex; justify-content: flex-end;
    }
    .reads-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 10px 0; border-bottom: 1px solid var(--border);
    }
    .reads-row:last-child { border-bottom: none; }
    .reads-name { font-size: 13px; font-weight: 500; color: var(--text); }
    .reads-time { font-size: 11px; color: var(--muted); font-family: 'Space Mono', monospace; }

    /* ── SECTION SPACING ── */
    .section-gap { height: 16px; }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      .grid { grid-template-columns: 1fr; }
      aside { order: -1; }
    }
    @media (max-width: 600px) {
      .topbar { padding: 0 14px; height: auto; min-height: 56px; flex-wrap: wrap; gap: 8px; padding-top: 10px; padding-bottom: 10px; }
      .topbar-brand { flex: 1; min-width: 0; }
      .topbar-actions { width: 100%; justify-content: flex-end; flex-wrap: wrap; }
      .wrap { padding: 12px 10px; }
      .card-header { padding: 11px 14px; flex-wrap: wrap; gap: 6px; }
      .card-body { padding: 13px; }
      .send-row { padding: 10px 13px; }
      .list-item { padding: 10px 13px; }
      .participant-row { padding: 9px 13px; }
      .btn { font-size: 12px; padding: 6px 11px; }
      .modal-box { width: 96%; }
      .modal-header, .modal-body, .modal-footer { padding: 14px 15px; }
    }
    @media (max-width: 400px) {
      .topbar-sub { display: none; }
      .stat .num { font-size: 18px; }
    }
  </style>
</head>
<body>

  <!-- TOPBAR -->
  <nav class="topbar">
    <div class="topbar-brand">
      <div class="topbar-logo">EA</div>
      <div>
        <div class="topbar-title">LIVE BRAINSTORM <span style="color:var(--muted);">/</span> ADMIN</div>
        <div class="topbar-sub">Send announcements · manage participants · monitor chat</div>
      </div>
    </div>
    <div class="topbar-actions">
      <span class="pulse-dot"></span>
      <span style="font-size:12px;color:var(--muted2);font-family:'Space Mono',monospace;">LIVE</span>
      <button id="themeToggle" class="theme-toggle" title="Toggle light / dark mode">
        <span class="toggle-icon" id="themeIcon">🌙</span>
        <div class="toggle-track"><div class="toggle-thumb"></div></div>
        <span id="themeLabel">Dark</span>
      </button>
      <a href="../dashboard.php" class="btn btn-dark"><i class="fa fa-arrow-left" style="font-size:11px"></i> Dashboard</a>
      <button id="clearMessagesBtn" class="btn btn-danger"><i class="fa fa-trash" style="font-size:11px"></i> Clear Chat</button>
    </div>
  </nav>

  <div class="wrap">
    <div class="grid">

      <!-- LEFT COLUMN -->
      <div>

        <!-- POST ANNOUNCEMENT -->
        <div class="card">
          <div class="card-header">
            <span class="card-title"><i class="fa fa-bullhorn"></i> Post Announcement</span>
            <span class="badge badge-green">Broadcasts to all users</span>
          </div>
          <div class="card-body">
            <form id="announceForm">
              <input id="announceTitle" class="field" placeholder="Announcement title…" required>
              <textarea id="announceBody" class="field" placeholder="Announcement body…" rows="4" required></textarea>
              <div class="form-row">
                <button class="btn btn-primary" type="submit"><i class="fa fa-paper-plane" style="font-size:11px"></i> Create Announcement</button>
                <button id="announceAndMessage" type="button" class="btn btn-blue"><i class="fa fa-bolt" style="font-size:11px"></i> Announce &amp; Send</button>
              </div>
            </form>
            <div id="announceMsg" class="status-msg"></div>
          </div>
        </div>

        <div class="section-gap"></div>

        <!-- ANNOUNCEMENTS LIST -->
        <div class="card">
          <div class="card-header">
            <span class="card-title"><i class="fa fa-list"></i> Announcements</span>
          </div>
          <div id="annList" class="list"></div>
        </div>

        <div class="section-gap"></div>

        <!-- LIVE MESSAGES -->
        <div class="card">
          <div class="card-header">
            <span class="card-title"><i class="fa fa-comments"></i> Live Chat</span>
            <span class="pulse-dot"></span>
          </div>
          <div id="messagesList" class="list" style="max-height:380px"></div>
          <div class="send-row">
            <input id="adminMessageInput" class="field" placeholder="Write as admin…">
            <button id="sendAdminMsg" class="btn btn-primary"><i class="fa fa-paper-plane" style="font-size:11px"></i></button>
          </div>
        </div>

      </div><!-- /LEFT -->

      <!-- RIGHT SIDEBAR -->
      <aside>

        <!-- PARTICIPANTS -->
        <div class="card">
          <div class="card-header">
            <span class="card-title"><i class="fa fa-users"></i> Active Users</span>
            <span class="badge badge-green"><span id="participantCount">…</span> online</span>
          </div>
          <div class="status-bar">
            <span class="pulse-dot" style="width:6px;height:6px"></span>
            <span>Active in last 10 minutes</span>
          </div>
          <div id="participantsList" class="list" style="max-height:340px"></div>
        </div>

        <div class="section-gap"></div>

        <!-- ACTIONS -->
        <div class="card">
          <div class="card-header">
            <span class="card-title"><i class="fa fa-sliders-h"></i> Quick Actions</span>
          </div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
            <button id="refreshBtn" class="btn btn-ghost" style="justify-content:center">
              <i class="fa fa-sync-alt" style="font-size:11px"></i> Refresh All
            </button>
            <button id="viewReadsBtn" class="btn btn-blue" style="justify-content:center">
              <i class="fa fa-eye" style="font-size:11px"></i> View Announcement Reads
            </button>
          </div>
        </div>

      </aside><!-- /SIDEBAR -->
    </div><!-- /GRID -->

    <!-- FOOTER -->
    <div class="footer">
      <span class="footer-brand">EXCELLENT SIMPLIFIED<span class="footer-dot">◆</span>Live Brainstorm Control</span>
      <span style="font-size:11px;color:var(--muted);font-family:'Space Mono',monospace;">Admin Panel</span>
    </div>
  </div>

  <!-- READS MODAL -->
  <div id="readsModal" class="modal-overlay">
    <div class="modal-box">
      <div class="modal-header">
        <span class="modal-title" id="readsTitle">Reads</span>
        <button onclick="document.getElementById('readsModal').style.display='none'" class="btn btn-ghost" style="padding:4px 10px;font-size:12px">✕ Close</button>
      </div>
      <div class="modal-body" id="readsBody"></div>
      <div class="modal-footer">
        <button onclick="document.getElementById('readsModal').style.display='none'" class="btn btn-ghost">Close</button>
      </div>
    </div>
  </div>

<script>
// ── THEME TOGGLE ──
(function(){
  const saved = localStorage.getItem('es_theme');
  if(saved === 'light') document.body.classList.add('light');
})();

document.getElementById('themeToggle').addEventListener('click', function(){
  const isLight = document.body.classList.toggle('light');
  localStorage.setItem('es_theme', isLight ? 'light' : 'dark');
  document.getElementById('themeIcon').textContent = isLight ? '☀️' : '🌙';
  document.getElementById('themeLabel').textContent = isLight ? 'Light' : 'Dark';
});

// Set correct icon on load
(function(){
  const isLight = document.body.classList.contains('light');
  document.getElementById('themeIcon').textContent = isLight ? '☀️' : '🌙';
  document.getElementById('themeLabel').textContent = isLight ? 'Light' : 'Dark';
})();

const API = 'live_brainstorm_control.php';
let lastMessageId = 0;

async function createAnnouncement(title, body, postAsMessage=false){
  const form = new FormData();
  form.append('title', title);
  form.append('body', body);
  const res = await fetch(API + '?action=create_announcement', { method: 'POST', body: form });
  const j = await res.json();
  if (!j.success) throw new Error(j.error || 'Failed');
  if (postAsMessage) {
    // post admin message with announcement body
    await postAdminMessage(`Announcement: ${title}\n\n${body}`);
  }
  return j.id;
}

async function listAnnouncements(){
  const res = await fetch(API + '?action=list_announcements');
  const j = await res.json();
  const wrap = document.getElementById('annList');
  wrap.innerHTML = '';
  if (!j.success) return;
  for (const a of j.announcements){
    const div = document.createElement('div');
    div.className = 'list-item';
    div.innerHTML = `<div class="ann-title">${escapeHtml(a.title)}</div>
      <div class="ann-body">${escapeHtml(a.body)}</div>
      <div class="ann-meta">Posted: ${a.created_at} &nbsp;·&nbsp; by user #${a.created_by}</div>
      <div style="margin-top:8px"><button class="btn btn-blue" style="font-size:12px;padding:5px 10px" onclick="viewReads(${a.id}, '${escapeJs(a.title)}')"><i class="fa fa-eye" style="font-size:10px"></i> View reads</button></div>`;
    wrap.appendChild(div);
  }
}

async function viewReads(aid, title){
  const res = await fetch(API + '?action=announcement_reads&announcement_id=' + aid);
  const j = await res.json();
  const body = document.getElementById('readsBody');
  document.getElementById('readsTitle').innerText = 'Reads for: ' + title;
  body.innerHTML = '';
  if (!j.success) { body.innerText = 'Failed to load'; document.getElementById('readsModal').style.display='flex'; return; }
  if (j.reads.length === 0) body.innerHTML = '<div class="small">No acknowledgements yet.</div>';
  for (const r of j.reads){
    const el = document.createElement('div');
    el.className = 'reads-row';
    el.innerHTML = `<span class="reads-name">${escapeHtml(r.display_name || 'Anon')}</span><span class="reads-time">${r.seen_at}</span>`;
    body.appendChild(el);
  }
  document.getElementById('readsModal').style.display='flex';
}

async function postAdminMessage(msg){
  if (!msg || !msg.trim()) return;
  const form = new FormData(); form.append('message', msg);
  const res = await fetch(API + '?action=post_admin_message', { method: 'POST', body: form });
  const j = await res.json();
  if (!j.success) alert('Failed to post message: ' + (j.error || 'unknown'));
  return j;
}

async function fetchMessages(){
  try {
    const res = await fetch(API + '?action=fetch_messages&after=' + lastMessageId + '&limit=400');
    const j = await res.json();
    if (!j.success) return;
    const wrap = document.getElementById('messagesList');
    for (const m of j.messages){
      const div = document.createElement('div');
      div.className = 'list-item';
      div.innerHTML = `<div class="msg-name">${escapeHtml(m.display_name || 'Anon')}</div>
        <div class="msg-text">${escapeHtml(m.message)}</div>
        <div class="msg-time">${m.created_at}</div>`;
      wrap.appendChild(div);
      lastMessageId = Math.max(lastMessageId, Number(m.id));
    }
    // limit to last 500 nodes
    while (wrap.children.length > 800) wrap.removeChild(wrap.firstChild);
    wrap.scrollTop = wrap.scrollHeight;
  } catch(e){ console.error(e); }
}

async function fetchParticipants(){
  try {
    const res = await fetch(API + '?action=participants&limit=200');
    const j = await res.json();
    const wrap = document.getElementById('participantsList');
    wrap.innerHTML = '';
    if (!j.success) return;

    // Update count in header
    const countEl = document.getElementById('participantCount');
    if (countEl) countEl.textContent = j.total || j.participants.length;

    if (!j.participants.length) {
      wrap.innerHTML = '<div style="padding:16px 18px;text-align:center;color:var(--muted);font-size:12px;font-family:\'Space Mono\',monospace">No active users in last 10 min</div>';
      return;
    }

    for (const p of j.participants){
      const div = document.createElement('div');
      div.className = 'participant-row';
      const initials = (p.display_name || 'G').charAt(0).toUpperCase();
      const isMe = parseInt(p.user_id) === <?= (int)$CURRENT_USER_ID ?>;
      div.innerHTML = `<div style="display:flex;align-items:center;gap:10px">
          <div class="participant-avatar">${initials}</div>
          <div>
            <div class="participant-name">${escapeHtml(p.display_name || ('User-' + p.user_id))}${isMe?' <span style="color:var(--accent);font-size:10px">(you)</span>':''}</div>
            <div class="participant-time">Last seen: ${p.last_seen}</div>
          </div>
        </div>
        ${!isMe?`<button class="btn btn-danger" style="font-size:11px;padding:5px 10px" onclick="kick(${p.user_id})"><i class="fa fa-ban" style="font-size:10px"></i> Kick</button>`:''}`;
      wrap.appendChild(div);
    }
  } catch(e){ console.error(e); }
}

async function kick(userId){
  if (!confirm('Remove this participant from the active list?')) return;
  const form = new FormData(); form.append('user_id', userId);
  const res = await fetch(API + '?action=kick_participant', { method: 'POST', body: form });
  const j = await res.json();
  if (j.success) { fetchParticipants(); }
  else alert(j.error || 'Failed to remove');
}

async function clearMessages(){
  if (!confirm('Clear all chat messages? This cannot be undone.')) return;
  const res = await fetch(API + '?action=clear_messages');
  const j = await res.json();
  if (j.success) {
    document.getElementById('messagesList').innerHTML = '';
    lastMessageId = 0;
  } else alert('Clear failed');
}

function escapeHtml(s){ if(!s) return ''; return String(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[m])); }
function escapeJs(s){ return (s || '').replace(/'/g,"\\'").replace(/"/g,'\"'); }

// wire UI
document.getElementById('announceForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const t = document.getElementById('announceTitle').value.trim();
  const b = document.getElementById('announceBody').value.trim();
  if (!t || !b) { document.getElementById('announceMsg').className='status-msg err'; document.getElementById('announceMsg').innerText = '✗ Provide title and body'; return; }
  document.getElementById('announceMsg').className='status-msg'; document.getElementById('announceMsg').innerText = 'Posting...';
  try {
    await createAnnouncement(t, b, false);
    document.getElementById('announceMsg').className = 'status-msg ok';
    document.getElementById('announceMsg').innerText = '✓ Posted successfully.';
    document.getElementById('announceTitle').value=''; document.getElementById('announceBody').value='';
    await listAnnouncements();
  } catch(err) {
    document.getElementById('announceMsg').className='status-msg err';
    document.getElementById('announceMsg').innerText = '✗ Error: ' + err.message;
  }
});

document.getElementById('announceAndMessage').addEventListener('click', async ()=>{
  const t = document.getElementById('announceTitle').value.trim();
  const b = document.getElementById('announceBody').value.trim();
  if (!t || !b) { document.getElementById('announceMsg').className='status-msg err'; document.getElementById('announceMsg').innerText = '✗ Provide title and body'; return; }
  document.getElementById('announceMsg').className='status-msg'; document.getElementById('announceMsg').innerText = 'Posting & sending...';
  try {
    await createAnnouncement(t, b, true);
    document.getElementById('announceMsg').className='status-msg ok'; document.getElementById('announceMsg').innerText = '✓ Done.';
    document.getElementById('announceTitle').value=''; document.getElementById('announceBody').value='';
    await listAnnouncements();
    await fetchMessages();
  } catch(err) {
    document.getElementById('announceMsg').className='status-msg err'; document.getElementById('announceMsg').innerText = '✗ Error: ' + err.message;
  }
});

document.getElementById('sendAdminMsg').addEventListener('click', async ()=>{
  const m = document.getElementById('adminMessageInput').value.trim();
  if (!m) return;
  await postAdminMessage(m);
  document.getElementById('adminMessageInput').value = '';
  await fetchMessages();
});

document.getElementById('refreshBtn').addEventListener('click', async ()=>{
  await listAnnouncements();
  await fetchParticipants();
  await fetchMessages();
});

document.getElementById('viewReadsBtn').addEventListener('click', async ()=>{
  await listAnnouncements();
  alert('Click "View reads" on any announcement to see acknowledgements.');
});

document.getElementById('clearMessagesBtn').addEventListener('click', clearMessages);

// boot
(async function boot(){
  await listAnnouncements();
  await fetchParticipants();
  await fetchMessages();
  setInterval(async ()=>{ await fetchParticipants(); await fetchMessages(); }, 2200);
})();
</script>
</body>
</html>
