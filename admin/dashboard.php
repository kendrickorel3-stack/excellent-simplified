<?php
// admin/dashboard.php
// Improved admin dashboard: responsive, collapsible sidebar (persisted), chat link, and subject assignment for questions

ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('display_errors', '0');
error_reporting(0);
session_start();

require_once __DIR__ . "/../config/db.php";

// ── Admin-only access ────────────────────────────────────────
$adminUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if (!$adminUserId) {
    // Not logged in at all — go to login
    header('Location: ../login.php');
    exit;
}

// Check is_admin flag in DB
$_chk = $conn->prepare("SELECT is_admin, google_name, username, email FROM users WHERE id = ? LIMIT 1");
$_chk->bind_param('i', $adminUserId);
$_chk->execute();
$_adminRow = $_chk->get_result()->fetch_assoc();
$_chk->close();

if (!$_adminRow || !(int)($_adminRow['is_admin'] ?? 0)) {
    // Logged in but not admin — send back to student dashboard
    header('Location: ../dashboard.php');
    exit;
}

$adminName = $_SESSION['google_name'] ?? $_adminRow['google_name'] ?? $_adminRow['username'] ?? 'Admin';

// ─────────────────────────────────────────────────────────────

// read dashboard stats and lists
$students = $conn->query("SELECT COUNT(*) AS cnt FROM users")->fetch_assoc()['cnt'] ?? 0;
$subjectsCount = $conn->query("SELECT COUNT(*) AS cnt FROM subjects")->fetch_assoc()['cnt'] ?? 0;
$videosCount = $conn->query("SELECT COUNT(*) AS cnt FROM videos")->fetch_assoc()['cnt'] ?? 0;
$questionsCount = $conn->query("SELECT COUNT(*) AS cnt FROM questions")->fetch_assoc()['cnt'] ?? 0;

// fetch lists (load subjects into array so we can reuse without re-querying)
$subjectsRes = $conn->query("SELECT id, name FROM subjects ORDER BY name");
$subjects = [];
while($r = $subjectsRes->fetch_assoc()) $subjects[] = $r;

// videos with subject name
$videos = $conn->query("SELECT v.*, s.name AS subject_name FROM videos v LEFT JOIN subjects s ON v.subject_id = s.id ORDER BY v.created_at DESC LIMIT 200");

// questions with subject name
$questions = $conn->query("SELECT q.*, s.name AS subject_name FROM questions q LEFT JOIN subjects s ON q.subject_id = s.id ORDER BY q.created_at DESC LIMIT 200");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>EXCELLENT SIMPLIFIED — Admin</title>
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
      --sidebar-w: 230px;
      --sidebar-collapsed-w: 64px;
      --topbar-bg: rgba(10,12,16,0.92);
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
    }
    body.light {
      background-image:
        radial-gradient(ellipse 80% 60% at 10% 0%, rgba(0,168,114,0.06) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 90% 100%, rgba(37,99,235,0.05) 0%, transparent 60%);
    }
    body.light nav.nav a.active { background: rgba(0,168,114,0.1); }
    body.light .stat::before { opacity: 0.4; }

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
      transition: background 0.2s; flex-shrink: 0;
    }
    body.light .theme-toggle .toggle-track { background: var(--accent); }
    .theme-toggle .toggle-thumb {
      position: absolute; top: 2px; left: 2px;
      width: 13px; height: 13px; border-radius: 50%;
      background: #fff; transition: transform 0.2s ease;
      box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }
    body.light .theme-toggle .toggle-thumb { transform: translateX(13px); }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg); color: var(--text);
      min-height: 100vh;
      transition: background 0.25s ease, color 0.25s ease;
      background-image:
        radial-gradient(ellipse 80% 60% at 10% 0%, rgba(0,229,160,0.04) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 90% 100%, rgba(59,130,246,0.05) 0%, transparent 60%);
    }

    /* ── LAYOUT ── */
    .wrap { display: flex; min-height: 100vh; }

    /* ── SIDEBAR ── */
    aside.sidebar {
      width: var(--sidebar-w);
      background: var(--surface);
      border-right: 1px solid var(--border);
      display: flex; flex-direction: column;
      transition: width .22s ease;
      flex-shrink: 0;
      position: sticky; top: 0; height: 100vh; overflow: hidden;
    }
    .sidebar-inner { display: flex; flex-direction: column; height: 100%; }
    .brand {
      display: flex; align-items: center; gap: 10px;
      padding: 18px 16px 14px;
      border-bottom: 1px solid var(--border);
      white-space: nowrap; overflow: hidden;
    }
    .brand-logo {
      width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Space Mono', monospace; font-size: 12px; font-weight: 700; color: #000;
    }
    .brand-name {
      font-family: 'Space Mono', monospace; font-size: 11px;
      font-weight: 700; letter-spacing: 0.05em; color: var(--text);
      white-space: nowrap;
    }
    nav.nav {
      flex: 1; padding: 12px 8px; display: flex; flex-direction: column; gap: 2px;
      overflow-y: auto; overflow-x: hidden;
    }
    nav.nav a {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 10px; border-radius: 8px;
      color: var(--muted2); text-decoration: none; font-size: 13px; font-weight: 500;
      transition: all .15s; white-space: nowrap; overflow: hidden;
    }
    nav.nav a:hover { background: var(--surface2); color: var(--text); }
    nav.nav a.active { background: rgba(0,229,160,0.1); color: var(--accent); }
    nav.nav a .icon {
      width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
      border-radius: 6px; flex-shrink: 0; font-size: 13px;
    }
    nav.nav a:hover .icon { color: var(--accent); }
    nav.nav a.active .icon { color: var(--accent); }
    .nav-label { white-space: nowrap; overflow: hidden; }
    .nav-divider {
      height: 1px; background: var(--border); margin: 6px 8px;
    }
    .sidebar-footer {
      padding: 12px 8px; border-top: 1px solid var(--border);
    }

    /* Collapsed sidebar */
    body.sidebar-collapsed aside.sidebar { width: var(--sidebar-collapsed-w); }
    body.sidebar-collapsed .brand-name { display: none; }
    body.sidebar-collapsed nav.nav a { justify-content: center; padding: 9px; }
    body.sidebar-collapsed .nav-label { display: none; }
    body.sidebar-collapsed .nav-section-label { display: none; }

    /* ── MAIN ── */
    .main { flex: 1; display: flex; flex-direction: column; min-width: 0; }

    /* ── TOPBAR ── */
    .topbar {
      position: sticky; top: 0; z-index: 30;
      background: var(--topbar-bg); backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 20px; height: 56px; gap: 12px;
      transition: background 0.25s ease, border-color 0.25s ease;
    }
    .topbar-left { display: flex; align-items: center; gap: 12px; }
    .topbar-title { font-weight: 600; font-size: 14px; }
    .topbar-sub { font-size: 11px; color: var(--muted2); margin-top: 1px; }
    .topbar-right { display: flex; align-items: center; gap: 8px; }

    /* ── CONTENT ── */
    .content { padding: 20px; flex: 1; }

    /* ── BUTTONS ── */
    .btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px; border-radius: 7px; border: 1px solid transparent;
      cursor: pointer; font-family: 'DM Sans', sans-serif;
      font-size: 13px; font-weight: 500; transition: all .15s;
      text-decoration: none; white-space: nowrap;
    }
    .btn-primary { background: var(--accent); color: #000; border-color: var(--accent); }
    .btn-primary:hover { background: #00ffb2; border-color: #00ffb2; }
    .btn-ghost { background: transparent; color: var(--muted2); border-color: var(--border); }
    .btn-ghost:hover { color: var(--text); border-color: #2e3548; background: var(--surface2); }
    .btn-dark { background: var(--surface2); color: var(--text); border-color: var(--border); }
    .btn-dark:hover { background: #1e2233; }
    .btn-danger { background: transparent; color: var(--danger); border-color: rgba(255,71,87,0.35); }
    .btn-danger:hover { background: rgba(255,71,87,0.12); border-color: var(--danger); }
    .btn-blue { background: transparent; color: var(--accent2); border-color: rgba(59,130,246,0.35); }
    .btn-blue:hover { background: rgba(59,130,246,0.1); border-color: var(--accent2); }
    /* legacy aliases */
    .btn.ghost { background: var(--surface2); color: var(--text); border: 1px solid var(--border); }
    .danger { background: rgba(255,71,87,0.15) !important; color: var(--danger) !important; border: 1px solid rgba(255,71,87,0.4) !important; }

    /* ── TOGGLE BUTTON ── */
    .toggle {
      background: var(--surface2); border: 1px solid var(--border);
      color: var(--muted2); padding: 7px 10px; border-radius: 8px;
      cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
      font-size: 12px; transition: all .15s;
    }
    .toggle:hover { color: var(--text); border-color: #2e3548; }

    /* ── STATS ── */
    .stats {
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px;
    }
    .stat {
      background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
      padding: 16px 18px; position: relative; overflow: hidden;
    }
    .stat::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
      background: linear-gradient(90deg, var(--accent), var(--accent2));
      opacity: 0.6;
    }
    .stat .num {
      font-family: 'Space Mono', monospace; font-size: 26px; font-weight: 700;
      color: var(--accent); line-height: 1;
    }
    .stat .label { font-size: 12px; color: var(--muted2); margin-top: 5px; letter-spacing: 0.04em; }

    /* ── GRID ── */
    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    /* ── CARDS ── */
    .card {
      background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
      overflow: hidden;
    }
    .card + .card { margin-top: 16px; }
    .card-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 13px 16px; border-bottom: 1px solid var(--border);
    }
    .card-title {
      font-family: 'Space Mono', monospace; font-size: 11px; font-weight: 700;
      letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted2);
      display: flex; align-items: center; gap: 8px;
    }
    .card-title i { color: var(--accent); font-size: 10px; }
    .card-body { padding: 16px; }

    /* ── TABLES ── */
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th {
      padding: 9px 12px; text-align: left;
      font-family: 'Space Mono', monospace; font-size: 10px;
      font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase;
      color: var(--muted); border-bottom: 1px solid var(--border);
    }
    td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background: var(--surface2); }

    /* ── FORMS ── */
    .field, .form-inline input, .form-inline select, .form-inline textarea,
    input[type=text], input[type=url], textarea, select {
      background: var(--surface2) !important; border: 1px solid var(--border) !important;
      border-radius: 8px !important; color: var(--text) !important;
      font-family: 'DM Sans', sans-serif !important; font-size: 13px !important;
      padding: 9px 11px !important; outline: none !important;
      transition: border-color .15s !important;
    }
    input::placeholder, textarea::placeholder { color: var(--muted) !important; }
    input:focus, textarea:focus, select:focus { border-color: var(--accent) !important; }
    select option { background: var(--surface2); color: var(--text); }
    label { font-size: 12px; color: var(--muted2); display: block; margin-bottom: 4px; }

    /* ── HELPERS ── */
    .muted { color: var(--muted2); font-size: 13px; }
    .small { font-size: 12px; color: var(--muted2); }
    .actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .form-inline { display: flex; gap: 8px; align-items: center; }
    .form-inline select { min-width: 150px; }
    .hidden { display: none; }
    .subject-select-inline { min-width: 130px; font-size: 12px !important; padding: 5px 8px !important; }
    .question-row .subject-cell { width: 160px; }
    .section-gap { height: 16px; }

    /* ── MODAL ── */
    .modal-backdrop {
      position: fixed; inset: 0; background: rgba(0,0,0,0.7);
      backdrop-filter: blur(4px);
      display: none; align-items: center; justify-content: center; z-index: 40;
    }
    .modal {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 14px; max-width: 480px; width: 90%; overflow: hidden;
    }
    .modal-head {
      padding: 16px 20px; border-bottom: 1px solid var(--border);
      font-family: 'Space Mono', monospace; font-size: 12px;
      font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text);
    }
    .modal-body-inner { padding: 16px 20px; font-size: 14px; color: var(--muted2); line-height: 1.6; }
    .modal-foot { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; }

    /* ── FOOTER ── */
    .page-footer {
      padding: 16px 20px; border-top: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
    }
    .footer-brand {
      font-family: 'Space Mono', monospace; font-size: 11px;
      color: var(--muted); letter-spacing: 0.06em;
    }

    /* ── MOBILE OVERLAY ── */
    .sidebar-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,0.6); z-index: 19;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 1024px) {
      .stats { grid-template-columns: repeat(2, 1fr); }
      .grid2 { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      aside.sidebar {
        position: fixed; left: 0; top: 0; height: 100vh; z-index: 20;
        transform: translateX(-100%); width: var(--sidebar-w) !important;
        transition: transform .25s ease;
      }
      aside.sidebar.mobile-open { transform: translateX(0); }
      body.sidebar-collapsed aside.sidebar { transform: translateX(-100%); }
      .sidebar-overlay.active { display: block; }
      .collapse-wrap { display: none; }
      .main { width: 100%; }
      .stats { grid-template-columns: repeat(2, 1fr); gap: 10px; }
      .stat .num { font-size: 22px; }
      .content { padding: 14px; }
      .topbar { padding: 0 14px; }
      table { font-size: 12px; }
      th, td { padding: 8px 8px; }
      .actions { flex-direction: column; gap: 4px; }
      .actions .btn { font-size: 11px; padding: 5px 8px; }
    }
    @media (max-width: 480px) {
      .stats { grid-template-columns: 1fr 1fr; gap: 8px; }
      .stat { padding: 12px 14px; }
      .stat .num { font-size: 20px; }
      .topbar-sub { display: none; }
    }
  </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="wrap">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-inner">
      <div class="brand">
        <div class="brand-logo">EA</div>
        <div class="brand-name">EXCELLENT SIMPLIFIED</div>
      </div>
      <nav class="nav" id="navLinks">
        <a href="dashboard.php" class="active"><span class="icon"><i class="fa fa-house"></i></span><span class="nav-label">Dashboard</span></a>
        <a href="../videos/lessons.php"><span class="icon"><i class="fa fa-video"></i></span><span class="nav-label">Lessons</span></a>
        <a href="../questions/get_questions.php"><span class="icon"><i class="fa fa-question-circle"></i></span><span class="nav-label">Brainstorm</span></a>
        <a href="../exams/practice_test.php"><span class="icon"><i class="fa fa-file-pen"></i></span><span class="nav-label">Exams</span></a>
        <div class="nav-divider"></div>
        <a href="live_brainstorm_control.php"><span class="icon"><i class="fa fa-tachometer-alt"></i></span><span class="nav-label">Live Control</span></a>
        <a href="aloc_panel.php" style="color:#f59e0b"><span class="icon" style="background:rgba(245,158,11,.12)"><i class="fa fa-satellite-dish" style="color:#f59e0b"></i></span><span class="nav-label">ALOC Panel</span></a>
        <a href="../chat/index.php"><span class="icon"><i class="fa fa-comments"></i></span><span class="nav-label">Chat</span></a>
        <a href="Leaderboard.php"><span class="icon"><i class="fa fa-ranking-star"></i></span><span class="nav-label">User Monitor</span></a>
        <a href="#" onclick="ytSync();return false;" id="ytNavBtn"><span class="icon"><i class="fa fa-brands fa-youtube" style="color:#ff2e2e"></i></span><span class="nav-label">YouTube Sync</span></a>
        <div class="nav-divider"></div>
        <a href="../auth/logout.php"><span class="icon"><i class="fa fa-sign-out-alt"></i></span><span class="nav-label">Logout</span></a>
      </nav>
      <div class="sidebar-footer collapse-wrap">
        <button id="collapseBtn" class="toggle" style="width:100%;justify-content:center" title="Collapse sidebar">
          <i class="fa fa-angle-left" id="collapseIcon"></i>
          <span class="nav-label" style="font-size:12px">Collapse</span>
        </button>
      </div>
    </div>
  </aside>

  <main class="main">
    <!-- TOPBAR -->
    <div class="topbar">
      <div class="topbar-left">
        <button id="toggleBtn" class="toggle" title="Toggle sidebar"><i class="fa fa-bars"></i></button>
        <div>
          <div class="topbar-title">Admin Panel</div>
          <div class="topbar-sub">👑 <?= htmlspecialchars($adminName) ?> · Class hub control center</div>
        </div>
      </div>
      <div class="topbar-right">
        <button id="themeToggle" class="theme-toggle" title="Toggle light / dark mode">
          <span id="themeIcon">🌙</span>
          <div class="toggle-track"><div class="toggle-thumb"></div></div>
          <span id="themeLabel">Dark</span>
        </button>
        <a class="btn btn-primary" href="export_csv.php"><i class="fa fa-file-csv" style="font-size:11px"></i> Export CSV</a>
      </div>
    </div>

    <div class="content">

    <!-- ALOC Question Panel Card -->
    <div class="card" style="margin-bottom:16px;border-color:rgba(245,158,11,.25)">
      <div class="card-header" style="background:linear-gradient(90deg,rgba(245,158,11,.08),transparent)">
        <span class="card-title">
          <i class="fa fa-satellite-dish" style="color:#f59e0b;font-size:14px"></i>
          ALOC Question Panel
        </span>
        <span style="font-size:10px;background:rgba(245,158,11,.12);color:#f59e0b;border:1px solid rgba(245,158,11,.3);padding:2px 10px;border-radius:20px;font-weight:700;letter-spacing:.05em;text-transform:uppercase">
          Live Past Questions
        </span>
      </div>
      <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:4px">
            Browse &amp; launch ALOC past questions for brainstorming
          </div>
          <div style="font-size:12px;color:var(--muted2);line-height:1.6">
            Fetch live questions from the ALOC database by subject — Mathematics, Physics, Chemistry, Biology, English and more. Preview any question and set it live for students with one click.
          </div>
          <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;align-items:center">
            <span style="font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px">
              <span style="width:7px;height:7px;border-radius:50%;background:#f59e0b;display:inline-block;flex-shrink:0"></span>
              10 subjects · JAMB · WAEC · NECO
            </span>
            <span style="font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px">
              <span style="width:7px;height:7px;border-radius:50%;background:var(--accent);display:inline-block;flex-shrink:0"></span>
              One-click Set Live → Students see it instantly
            </span>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0;min-width:160px">
          <a href="aloc_panel.php" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 22px;border-radius:9px;border:none;cursor:pointer;font-family:inherit;font-size:13px;font-weight:700;transition:all .2s;background:linear-gradient(135deg,#f59e0b,#d97706);color:#000;box-shadow:0 4px 18px rgba(245,158,11,.35);text-decoration:none">
            <i class="fa fa-satellite-dish"></i> Open ALOC Panel
          </a>
          <a href="live_brainstorm_control.php" style="display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 16px;border-radius:9px;font-size:12px;font-weight:600;border:1px solid rgba(255,71,87,.3);color:var(--danger);background:rgba(255,71,87,.08);text-decoration:none;transition:all .15s">
            <i class="fa fa-tower-broadcast" style="font-size:11px"></i> Brainstorm Control
          </a>
        </div>
      </div>
    </div>

    <!-- YouTube Auto-Sync Card -->
    <div class="card" style="margin-bottom:16px;border-color:rgba(255,46,46,0.22)">
      <div class="card-header" style="background:linear-gradient(90deg,rgba(255,46,46,0.08),transparent)">
        <span class="card-title">
          <i class="fa fa-brands fa-youtube" style="color:#ff2e2e;font-size:14px"></i>
          YouTube Auto-Sync
        </span>
        <span style="font-size:10px;background:rgba(255,46,46,0.12);color:#fca5a5;border:1px solid rgba(255,46,46,0.25);padding:2px 10px;border-radius:20px;font-weight:700;letter-spacing:.05em;text-transform:uppercase">
          @ExcellentSimplifiedacademy
        </span>
      </div>
      <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:3px">Pull new videos from your YouTube channel automatically</div>
          <div style="font-size:12px;color:var(--muted2)">Checks your last 20 uploads. New ones are added instantly — duplicates are skipped.</div>
          <div style="font-size:11px;color:var(--muted);margin-top:6px;display:flex;align-items:center;gap:6px">
            <span id="ytStatusDot" style="width:7px;height:7px;border-radius:50%;background:var(--muted);display:inline-block;flex-shrink:0"></span>
            <span id="ytLastSync">Auto-checking on load…</span>
          </div>
        </div>
        <button onclick="ytSync()" id="ytSyncBtn" style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:9px;border:none;cursor:pointer;font-family:inherit;font-size:13px;font-weight:700;transition:all .2s;flex-shrink:0;background:linear-gradient(135deg,#ff2e2e,#b91c1c);color:#fff;box-shadow:0 4px 18px rgba(255,46,46,0.35)">
          <i class="fa fa-rotate" id="ytSyncIcon"></i> Sync Now
        </button>
      </div>
    </div>

    <section class="stats">
      <div class="stat"><div class="num"><?php echo (int)$students ?></div><div class="label">Students</div></div>
      <div class="stat"><div class="num"><?php echo (int)$subjectsCount ?></div><div class="label">Subjects</div></div>
      <div class="stat"><div class="num"><?php echo (int)$videosCount ?></div><div class="label">Videos</div></div>
      <div class="stat"><div class="num"><?php echo (int)$questionsCount ?></div><div class="label">Questions</div></div>
    </section>

    <section class="grid2">
      <div>
        <div class="card" id="publishSection">
          <div class="card-header">
            <span class="card-title"><i class="fa fa-cloud-upload-alt"></i> Publish Lesson (YouTube)</span>
          </div>
          <div class="card-body">
          <form method="POST" action="save_video.php" style="display:flex;flex-direction:column;gap:10px">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <select name="subject_id" required style="flex:1">
                <option value="">-- Select subject --</option>
                <?php foreach($subjects as $s): ?>
                  <option value="<?php echo (int)$s['id'] ?>"><?php echo htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-ghost" onclick="toggleNewSubject()" title="Create new subject"><i class="fa fa-plus" style="font-size:11px"></i> New Subject</button>
            </div>
            <input name="title" placeholder="Video title" style="width:100%" required>
            <input name="youtube_link" placeholder="https://www.youtube.com/watch?v=..." style="width:100%" required>

            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
              <button class="btn btn-primary" type="submit"><i class="fa fa-cloud-arrow-up" style="font-size:11px"></i> Publish</button>
              <button type="button" onclick="scrollToLiveControl()" class="btn btn-blue">Go Live</button>
            </div>
          </form>

          <!-- NEW SUBJECT FORM (hidden by default, supports AJAX create) -->
          <div id="newSubjectBox" style="margin-top:12px;display:none">
            <form id="newSubjectForm" method="POST" action="create_subject.php" class="form-inline" style="flex-direction:column;align-items:stretch">
              <label>New subject name</label>
              <input type="text" id="newSubjectName" name="subject_name" placeholder="e.g. Mathematics" required>
              <div style="display:flex;gap:8px;margin-top:8px">
                <button type="button" class="btn btn-primary" onclick="createSubject()">Create subject</button>
                <button type="button" class="btn btn-ghost" onclick="toggleNewSubject()">Cancel</button>
              </div>
              <div id="newSubjectMsg" class="small" style="margin-top:8px"></div>
            </form>
          </div>
          </div><!-- /card-body -->
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <span class="card-title"><i class="fa fa-brain"></i> Create Brainstorm Question</span>
          </div>
          <div class="card-body">
          <!-- SUBJECT SELECT ADDED HERE -->
          <form method="POST" action="create_question.php" style="display:flex;flex-direction:column;gap:10px">
            <div>
              <label>Question</label>
              <textarea name="question" rows="3" style="width:100%" required></textarea>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <input name="option_a" placeholder="Option A" required style="flex:1">
              <input name="option_b" placeholder="Option B" required style="flex:1">
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <input name="option_c" placeholder="Option C" required style="flex:1">
              <input name="option_d" placeholder="Option D" required style="flex:1">
            </div>

            <div style="display:flex;gap:8px;margin-top:4px;align-items:flex-end;flex-wrap:wrap">
              <div style="flex:1">
                <label>Subject</label>
                <select name="subject_id" class="subject-select-inline" required style="width:100%">
                  <option value="">-- choose subject --</option>
                  <?php foreach($subjects as $s): ?>
                    <option value="<?php echo (int)$s['id'] ?>"><?php echo htmlspecialchars($s['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label>Correct (A/B/C/D)</label>
                <input name="correct_answer" maxlength="1" required style="width:70px">
              </div>
              <div>
                <label>Status</label>
                <select name="status">
                  <option value="inactive">inactive</option>
                  <option value="active">active</option>
                </select>
              </div>
              <div>
                <button class="btn btn-primary" type="submit"><i class="fa fa-plus" style="font-size:11px"></i> Create</button>
              </div>
            </div>
          </form>
          </div><!-- /card-body -->
        </div>

      </div>

      <aside>
        <div class="card">
          <div class="card-header">
            <span class="card-title"><i class="fa fa-video"></i> Videos</span>
          </div>
          <div style="overflow-x:auto">
          <table>
            <thead><tr><th>Title</th><th>Subject</th><th></th></tr></thead>
            <tbody id="videosList">
            <?php while($v = $videos->fetch_assoc()): ?>
              <tr id="video-row-<?php echo (int)$v['id'] ?>">
                <td><?php echo htmlspecialchars($v['title']) ?></td>
                <td class="muted"><?php echo htmlspecialchars($v['subject_name'] ?? '-') ?></td>
                <td style="width:130px">
                  <div class="actions">
                    <a href="edit_video.php?id=<?php echo (int)$v['id'] ?>" class="btn btn-dark" style="font-size:11px;padding:5px 8px"><i class="fa fa-pen"></i></a>
                    <button class="btn btn-danger" style="font-size:11px;padding:5px 8px" onclick="confirmDelete('video',<?php echo (int)$v['id'] ?>, '<?php echo addslashes(htmlspecialchars($v['title'])) ?>')"><i class="fa fa-trash"></i></button>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
          </div><!-- /overflow -->
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <span class="card-title"><i class="fa fa-question-circle"></i> Questions</span>
          </div>
          <div style="overflow-x:auto">
          <table>
            <thead><tr><th>Question</th><th>Subject</th><th>Status</th><th></th></tr></thead>
            <tbody id="questionsList">
            <?php while($qrow = $questions->fetch_assoc()): ?>
              <tr class="question-row" id="question-row-<?php echo (int)$qrow['id'] ?>">
                <td class="small"><?php echo htmlspecialchars($qrow['question']) ?></td>
                <td class="subject-cell">
                  <select data-qid="<?php echo (int)$qrow['id'] ?>" class="subject-select-inline select-question-subject">
                    <option value="">-- none --</option>
                    <?php foreach($subjects as $s): ?>
                      <option value="<?php echo (int)$s['id'] ?>" <?php if((int)$qrow['subject_id'] === (int)$s['id']) echo 'selected' ?>><?php echo htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td class="muted"><?php echo htmlspecialchars($qrow['status']) ?></td>
                <td style="width:100px">
                  <div class="actions">
                    <a href="edit_question.php?id=<?php echo (int)$qrow['id'] ?>" class="btn btn-dark" style="font-size:11px;padding:5px 8px"><i class="fa fa-pen"></i></a>
                    <button class="btn btn-danger" style="font-size:11px;padding:5px 8px" onclick="confirmDelete('question',<?php echo (int)$qrow['id'] ?>, '<?php echo addslashes(htmlspecialchars($qrow['question'])) ?>')"><i class="fa fa-trash"></i></button>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <span class="card-title"><i class="fa fa-book"></i> Subjects</span>
          </div>
          <div style="overflow-x:auto">
          <table>
            <thead><tr><th>Subject</th><th>Videos</th><th></th></tr></thead>
            <tbody id="subjectsList">
              <?php
              $sublist = $conn->query("SELECT s.*, (SELECT COUNT(*) FROM videos v WHERE v.subject_id=s.id) AS vcount FROM subjects s ORDER BY s.name");
              while($r = $sublist->fetch_assoc()):
              ?>
                <tr id="subject-row-<?php echo (int)$r['id'] ?>">
                  <td><?php echo htmlspecialchars($r['name']) ?> <span class="muted">(<?php echo (int)$r['vcount'] ?>)</span></td>
                  <td class="muted"><?php echo (int)$r['vcount'] ?></td>
                  <td style="width:100px">
                    <div class="actions">
                      <a href="edit_subject.php?id=<?php echo (int)$r['id'] ?>" class="btn btn-dark" style="font-size:11px;padding:5px 8px"><i class="fa fa-pen"></i></a>
                      <button class="btn btn-danger" style="font-size:11px;padding:5px 8px" onclick="confirmDelete('subject',<?php echo (int)$r['id'] ?>,'<?php echo addslashes(htmlspecialchars($r['name'])) ?>')"><i class="fa fa-trash"></i></button>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          </div>
        </div>
      </aside>
    </section>

    </div><!-- /content -->
    <div class="page-footer">
      <span class="footer-brand">EXCELLENT SIMPLIFIED <span style="color:var(--accent)">◆</span> Admin Panel</span>
      <span style="font-size:11px;color:var(--muted);font-family:'Space Mono',monospace">Admin</span>
    </div>
  </main>
</div>

<!-- confirmation modal -->
<div id="modalBackdrop" class="modal-backdrop">
  <div class="modal">
    <div class="modal-head" id="modalTitle">Confirm delete</div>
    <div class="modal-body-inner" id="modalBody">Are you sure?</div>
    <div class="modal-foot">
      <button class="btn btn-ghost" id="modalCancel">Cancel</button>
      <button class="btn btn-danger" id="modalConfirm">Delete</button>
    </div>
  </div>
</div>

<script>
  // ── THEME TOGGLE ──
  (function(){
    if(localStorage.getItem('es_theme') === 'light') document.body.classList.add('light');
  })();

  document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('themeToggle');
    if(!btn) return;
    const icon  = document.getElementById('themeIcon');
    const label = document.getElementById('themeLabel');
    function syncUI(){
      const isLight = document.body.classList.contains('light');
      icon.textContent  = isLight ? '☀️' : '🌙';
      label.textContent = isLight ? 'Light' : 'Dark';
    }
    syncUI();
    btn.addEventListener('click', function(){
      const isLight = document.body.classList.toggle('light');
      localStorage.setItem('es_theme', isLight ? 'light' : 'dark');
      syncUI();
    });
  });

  const body = document.body;
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const toggleBtn = document.getElementById('toggleBtn');
  const collapseBtn = document.getElementById('collapseBtn');
  const collapseIcon = document.getElementById('collapseIcon');

  function isMobile(){ return window.innerWidth <= 768; }

  function updateCollapseIcon(){
    if(collapseIcon){
      collapseIcon.className = body.classList.contains('sidebar-collapsed')
        ? 'fa fa-angle-right' : 'fa fa-angle-left';
    }
  }

  // initialize from localStorage (desktop only)
  (function(){
    if(!isMobile()){
      const collapsed = localStorage.getItem('es_admin_sidebar_collapsed') === '1';
      if(collapsed) body.classList.add('sidebar-collapsed');
    }
    updateCollapseIcon();
  })();

  toggleBtn.addEventListener('click', ()=> {
    if(isMobile()){
      // mobile: slide in/out with overlay
      const open = sidebar.classList.toggle('mobile-open');
      overlay.classList.toggle('active', open);
    } else {
      // desktop: collapse to icon rail
      const collapsed = body.classList.toggle('sidebar-collapsed');
      localStorage.setItem('es_admin_sidebar_collapsed', collapsed ? '1' : '0');
      updateCollapseIcon();
    }
  });

  if(collapseBtn){
    collapseBtn.addEventListener('click', ()=>{
      const collapsed = body.classList.toggle('sidebar-collapsed');
      localStorage.setItem('es_admin_sidebar_collapsed', collapsed ? '1' : '0');
      updateCollapseIcon();
    });
  }

  overlay.addEventListener('click', ()=>{
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
  });

  window.addEventListener('resize', ()=>{
    if(!isMobile()){
      sidebar.classList.remove('mobile-open');
      overlay.classList.remove('active');
    }
  });

  // modal logic
  const modalBackdrop = document.getElementById('modalBackdrop');
  const modalTitle = document.getElementById('modalTitle');
  const modalBody = document.getElementById('modalBody');
  let deleteTarget = null;

  function confirmDelete(type, id, label){
    deleteTarget = {type,id};
    modalTitle.innerText = `Delete ${type}`;
    modalBody.innerText = `Permanently delete "${label}"? This action cannot be undone.`;
    modalBackdrop.style.display = 'flex';
  }
  document.getElementById('modalCancel').addEventListener('click', ()=> modalBackdrop.style.display='none');
  document.getElementById('modalConfirm').addEventListener('click', async ()=> {
    if(!deleteTarget) return;
    const {type,id} = deleteTarget;
    let url = `delete_${type}.php`;
    try {
      const resp = await fetch(url, {
        method:'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id})
      });
      const data = await resp.json();
      if(data.success){
        const row = document.getElementById(`${type}-row-${id}`);
        if(row) row.remove();
      } else {
        alert(data.error || 'Delete failed');
      }
    } catch(e) {
      alert('Network error');
    }
    deleteTarget = null;
    modalBackdrop.style.display='none';
  });

  function toggleNewSubject(){
    const box = document.getElementById('newSubjectBox');
    box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
  }

  async function createSubject(){
    const nameEl = document.getElementById('newSubjectName');
    const msg = document.getElementById('newSubjectMsg');
    const name = nameEl.value.trim();
    if(!name){ msg.innerText = 'Type a subject name.'; return; }
    msg.innerText = 'Creating...';
    try{
      const resp = await fetch('create_subject.php', {
        method:'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({subject_name: name})
      });
      try{
        const j = await resp.json();
        if(j.success){
          msg.innerText = 'Created.';
          // add to selects
          const selects = document.querySelectorAll('select[name="subject_id"], .select-question-subject');
          selects.forEach(s => {
            const opt = document.createElement('option');
            opt.value = j.id || '';
            opt.text = j.name || name;
            s.appendChild(opt);
          });
          nameEl.value = '';
          setTimeout(()=>{ msg.innerText=''; toggleNewSubject(); }, 700);
        } else {
          location.reload();
        }
      }catch(e){
        location.reload();
      }
    }catch(e){
      msg.innerText = 'Network error';
      console.error(e);
    }
  }

  function scrollToLiveControl(){ window.location.href='live_brainstorm_control.php'; }

  // wire up inline question subject change (AJAX)
  document.querySelectorAll('.select-question-subject').forEach(select => {
    select.addEventListener('change', async (ev) => {
      const qid = ev.target.dataset.qid;
      const sid = ev.target.value || '';
      // call a small endpoint update_question_subject.php (you need to create it)
      try {
        const r = await fetch('update_question_subject.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({question_id: qid, subject_id: sid})
        });
        const j = await r.json();
        if(!j.success){
          alert('Update failed: ' + (j.error || 'unknown'));
        }
      } catch(e){
        alert('Network error while updating subject');
      }
    });
  });

/* ── YouTube Sync ───────────────────────────────────────────────────── */
</script>

<style>
nav.nav a#ytNavBtn .icon { background: rgba(255,46,46,0.12); }
nav.nav a#ytNavBtn { color: #fca5a5; }
nav.nav a#ytNavBtn:hover { background:rgba(255,46,46,0.1); border-color:rgba(255,46,46,0.25); color:#fff; }
#ytSyncBtn:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(255,46,46,0.5) !important; }
#ytSyncBtn:disabled { opacity:.6; cursor:not-allowed; transform:none !important; }
@keyframes ytSpin { to { transform:rotate(360deg); } }
.yt-spin { animation:ytSpin .8s linear infinite; display:inline-block; }
#ytToast {
  position:fixed; bottom:24px; right:24px; z-index:9999;
  background:var(--surface); border:1px solid rgba(255,255,255,0.1);
  border-radius:14px; padding:14px 18px;
  display:flex; align-items:center; gap:13px;
  min-width:300px; max-width:420px;
  box-shadow:0 20px 60px rgba(0,0,0,0.55);
  transform:translateY(90px) scale(0.95); opacity:0;
  transition:all .4s cubic-bezier(.34,1.56,.64,1);
  pointer-events:none;
}
#ytToast.show  { transform:translateY(0) scale(1); opacity:1; pointer-events:all; }
#ytToast.ok    { border-color:rgba(16,185,129,0.35); }
#ytToast.ok    #ytToastTitle { color:#34d399; }
#ytToast.fail  { border-color:rgba(255,46,46,0.35); }
#ytToast.fail  #ytToastTitle { color:#fca5a5; }
#ytToastIcon  { font-size:24px; flex-shrink:0; }
#ytToastTitle { font-size:13px; font-weight:700; color:var(--text); }
#ytToastSub   { font-size:11px; color:var(--muted2); margin-top:3px; line-height:1.4; }
</style>

<!-- Toast -->
<div id="ytToast">
  <div id="ytToastIcon">📺</div>
  <div>
    <div id="ytToastTitle">Syncing…</div>
    <div id="ytToastSub"></div>
  </div>
</div>

<script>
async function ytSync(silent) {
  const btn  = document.getElementById('ytSyncBtn');
  const icon = document.getElementById('ytSyncIcon');
  const last = document.getElementById('ytLastSync');
  const dot  = document.getElementById('ytStatusDot');

  if (btn)  btn.disabled = true;
  if (icon) icon.className = 'fa fa-rotate yt-spin';
  if (!silent) showToast('📡','Syncing with YouTube…','Checking @ExcellentSimplifiedacademy for new uploads…','');

  try {
    const resp = await fetch('youtube_sync.php', {method:'POST', credentials:'same-origin'});
    const raw  = await resp.text();
    let j;
    try { j = JSON.parse(raw); }
    catch(e) {
      showToast('❌','Server error', raw.replace(/<[^>]*>/g,'').trim().slice(0,120), 'fail');
      resetYtBtn(); return;
    }

    if (j.success) {
      const time = new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
      if (last) last.textContent = 'Last sync: ' + time + ' · ' + j.checked + ' checked';
      if (dot)  dot.style.background = j.added > 0 ? '#10b981' : '#6b7280';

      if (j.added > 0) {
        showToast('🎬', j.message, j.new_titles.slice(0,3).join(' · ') + (j.new_titles.length > 3 ? '…' : ''), 'ok');
        setTimeout(() => location.reload(), 2500);
      } else {
        if (!silent) showToast('✅', j.message, j.checked + ' videos checked — all already in database', 'ok');
        else if (dot) dot.style.background = '#10b981';
      }
    } else {
      if (!silent) showToast('❌','Sync failed', j.error || 'Unknown error', 'fail');
      if (dot) dot.style.background = '#f43f5e';
    }
  } catch(e) {
    if (!silent) showToast('❌','Network error', e.message, 'fail');
    if (dot) dot.style.background = '#f43f5e';
  }
  resetYtBtn();
}

function resetYtBtn() {
  const btn  = document.getElementById('ytSyncBtn');
  const icon = document.getElementById('ytSyncIcon');
  if (btn)  btn.disabled = false;
  if (icon) icon.className = 'fa fa-rotate';
}

function showToast(emoji, title, sub, type) {
  const t = document.getElementById('ytToast');
  if (!t) return;
  document.getElementById('ytToastIcon').textContent  = emoji;
  document.getElementById('ytToastTitle').textContent = title;
  document.getElementById('ytToastSub').textContent   = sub;
  t.className = 'show ' + type;
  clearTimeout(t._t);
  if (type === 'ok' || type === 'fail') t._t = setTimeout(() => t.className = '', 5000);
}

// Auto-sync silently every time the admin panel loads
document.addEventListener('DOMContentLoaded', () => ytSync(true));
</script>
</body>
</html>
