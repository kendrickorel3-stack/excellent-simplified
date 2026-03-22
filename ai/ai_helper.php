<?php
// ai/ai_helper.php
// AI Textbook Helper — personalised with session user data
// Place at ~/excellent-academy/ai/ai_helper.php

session_start();
require_once __DIR__ . "/../config/db.php";

// ── Auth & user data ────────────────────────────────────
$user_id        = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$google_name    = $_SESSION['google_name']    ?? null;
$google_picture = $_SESSION['google_picture'] ?? null;

$userRow = [];
if ($user_id) {
    $s = $conn->prepare("SELECT username, email, google_name, google_picture, score FROM users WHERE id = ? LIMIT 1");
    if ($s) { $s->bind_param('i', $user_id); $s->execute(); $userRow = $s->get_result()->fetch_assoc() ?: []; $s->close(); }
}
$display_name    = $google_name ?: ($userRow['google_name'] ?? $userRow['username'] ?? 'Student');
$display_picture = $google_picture ?: ($userRow['google_picture'] ?? null);
$display_email   = $_SESSION['google_email'] ?? $userRow['email'] ?? '';
$user_score      = (int)($userRow['score'] ?? 0);

// ── Stats ────────────────────────────────────────────────
$questions_answered = 0;
$streak_days        = 0;
$accuracy           = 0;

if ($user_id) {
    // Questions answered + accuracy
    $r = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(is_correct),0) AS corr FROM answers WHERE user_id = $user_id");
    if ($r) {
        $row = $r->fetch_assoc();
        $questions_answered = (int)($row['c'] ?? 0);
        $accuracy = $questions_answered ? round(100 * $row['corr'] / $questions_answered) : 0;
    }
    // Streak
    $ss = $conn->prepare("SELECT COUNT(DISTINCT DATE(created_at)) AS s FROM answers WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    if ($ss) { $ss->bind_param('i', $user_id); $ss->execute(); $streak_days = (int)($ss->get_result()->fetch_assoc()['s'] ?? 0); $ss->close(); }
}

// Greeting based on time
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'utf-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>AI Tutor — EXCELLENT SIMPLIFIED</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ── TOKENS ── */
:root{
  --bg:#0a0c10;--surface:#111318;--surface2:#181b22;--surface3:#1e2230;
  --border:#1f2330;--accent:#00c98a;--accent2:#3b82f6;--purple:#a78bfa;
  --amber:#f59e0b;--danger:#ff4757;
  --text:#e8ecf4;--muted:#5a6278;--muted2:#8a93ab;
  --topbar-bg:rgba(10,12,16,.96);
  --user-bubble:#3b82f6;--ai-bubble:#181b22;
  --ai-border:#1f2330;
}
body.light{
  --bg:#f0f4f9;--surface:#fff;--surface2:#f4f7fc;--surface3:#eaeef8;
  --border:#dde3ee;--accent:#00a872;--accent2:#2563eb;--purple:#7c3aed;
  --amber:#d97706;--danger:#e53e3e;
  --text:#0d1526;--muted:#9aa3b8;--muted2:#6b7a99;
  --topbar-bg:rgba(240,244,249,.97);
  --user-bubble:#2563eb;--ai-bubble:#fff;
  --ai-border:#dde3ee;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;transition:background .25s,color .25s;-webkit-font-smoothing:antialiased;}

/* ── TOPBAR ── */
.topbar{
  position:sticky;top:0;z-index:50;height:56px;flex-shrink:0;
  background:var(--topbar-bg);backdrop-filter:blur(16px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 18px;gap:10px;transition:background .25s;
}
.topbar-brand{display:flex;align-items:center;gap:10px;}
.brand-logo{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;flex-shrink:0;}
.brand-name{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;letter-spacing:.05em;color:var(--text);}
.brand-sub{font-size:11px;color:var(--muted2);margin-top:1px;}
.topbar-right{display:flex;align-items:center;gap:8px;}
.user-chip{display:flex;align-items:center;gap:7px;padding:5px 11px;border-radius:20px;background:var(--surface2);border:1px solid var(--border);font-size:12px;font-weight:600;color:var(--text);cursor:default;}
.user-chip img{width:22px;height:22px;border-radius:6px;object-fit:cover;}
.user-chip .av{width:22px;height:22px;border-radius:6px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:#000;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--muted2);font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;cursor:pointer;text-decoration:none;white-space:nowrap;transition:all .15s;}
.btn:hover{color:var(--text);border-color:var(--muted);}
.btn-ghost{background:transparent;}
.theme-toggle{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:20px;border:1px solid var(--border);background:var(--surface2);color:var(--muted2);cursor:pointer;font-size:11px;font-family:'DM Sans',sans-serif;transition:all .15s;}
.t-track{width:26px;height:15px;border-radius:20px;background:var(--border);position:relative;transition:background .2s;flex-shrink:0;}
body.light .t-track{background:var(--accent);}
.t-thumb{position:absolute;top:2px;left:2px;width:11px;height:11px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.3);}
body.light .t-thumb{transform:translateX(11px);}

/* ── APP BODY ── */
.app-body{display:flex;flex:1;min-height:0;overflow:hidden;}

/* ── LEFT PANEL: User profile + stats ── */
.side-panel{
  width:260px;flex-shrink:0;
  background:var(--surface);border-right:1px solid var(--border);
  display:flex;flex-direction:column;overflow-y:auto;overflow-x:hidden;
  transition:transform .25s;
}
.side-panel::-webkit-scrollbar{width:3px;}
.side-panel::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px;}

/* Profile card */
.profile-card{
  background:linear-gradient(135deg,var(--accent2),var(--accent));
  padding:22px 18px 18px;position:relative;overflow:hidden;
}
.profile-card::before{
  content:'';position:absolute;top:-30px;right:-30px;
  width:120px;height:120px;border-radius:50%;
  background:rgba(255,255,255,.07);
}
.profile-card::after{
  content:'';position:absolute;bottom:-20px;left:-20px;
  width:80px;height:80px;border-radius:50%;
  background:rgba(255,255,255,.05);
}
.profile-avatar{
  width:52px;height:52px;border-radius:14px;overflow:hidden;
  border:2px solid rgba(255,255,255,.3);margin-bottom:12px;
  background:rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:18px;font-weight:700;color:#fff;
  position:relative;z-index:1;
}
.profile-avatar img{width:100%;height:100%;object-fit:cover;}
.profile-greeting{font-size:11px;color:rgba(255,255,255,.75);margin-bottom:3px;position:relative;z-index:1;}
.profile-name{font-size:16px;font-weight:800;color:#fff;line-height:1.2;position:relative;z-index:1;}
.profile-email{font-size:11px;color:rgba(255,255,255,.65);margin-top:3px;position:relative;z-index:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* Stats grid */
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:14px 14px 0;}
.stat-tile{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:11px 12px;}
.stat-tile .n{font-family:'Space Mono',monospace;font-size:20px;font-weight:700;line-height:1;}
.stat-tile .l{font-size:11px;color:var(--muted2);margin-top:4px;}
.stat-tile.score .n{color:var(--accent);}
.stat-tile.answered .n{color:var(--accent2);}
.stat-tile.streak .n{color:var(--amber);}
.stat-tile.accuracy .n{color:var(--purple);}

/* Suggested topics */
.side-section{padding:14px 14px 6px;}
.side-section-title{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted2);margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.side-section-title i{color:var(--accent);font-size:9px;}
.topic-chip{display:flex;align-items:center;gap:7px;padding:8px 11px;border-radius:9px;border:1px solid var(--border);background:var(--surface2);font-size:12px;color:var(--muted2);cursor:pointer;transition:all .15s;margin-bottom:6px;user-select:none;}
.topic-chip:hover{border-color:var(--accent2);color:var(--text);background:rgba(59,130,246,.07);}
.topic-chip .tc-icon{font-size:14px;flex-shrink:0;}
.topic-chip .tc-label{flex:1;line-height:1.3;}
.tc-arrow{font-size:10px;color:var(--muted);flex-shrink:0;}
.side-gap{flex:1;}
/* Exam badges at bottom of panel */
.exam-badges{padding:12px 14px 18px;display:flex;gap:6px;flex-wrap:wrap;}
.exam-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:20px;font-size:10px;font-weight:700;font-family:'Space Mono',monospace;}
.exam-badge.waec{background:rgba(0,201,138,.12);color:var(--accent);}
.exam-badge.jamb{background:rgba(59,130,246,.12);color:var(--accent2);}
.exam-badge.neco{background:rgba(167,139,250,.12);color:var(--purple);}

/* ── CHAT COLUMN ── */
.chat-col{flex:1;display:flex;flex-direction:column;min-width:0;}

/* Chat header row */
.chat-header{
  padding:12px 18px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  background:var(--surface);flex-shrink:0;gap:10px;
}
.chat-header-left{display:flex;align-items:center;gap:10px;}
.ai-avatar{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--purple),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.chat-title{font-size:14px;font-weight:700;color:var(--text);}
.chat-sub{font-size:11px;color:var(--muted2);margin-top:1px;}
.ai-status{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--accent);font-family:'Space Mono',monospace;}
.ai-dot{width:6px;height:6px;border-radius:50%;background:var(--accent);animation:pulse 1.8s infinite;}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(0,201,138,.5)}60%{box-shadow:0 0 0 4px rgba(0,201,138,0)}}

/* Chat messages area */
.chat-wrap{flex:1;overflow-y:auto;padding:18px;display:flex;flex-direction:column;align-items:center;-webkit-overflow-scrolling:touch;}
.chat-wrap::-webkit-scrollbar{width:4px;}
.chat-wrap::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}
.inner{width:100%;max-width:780px;display:flex;flex-direction:column;}

/* Welcome card */
.welcome-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:14px;padding:20px;margin-bottom:14px;
  background-image:radial-gradient(ellipse 80% 60% at 10% 0%,rgba(0,201,138,.04) 0%,transparent 60%);
}
.welcome-card h3{font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px;}
.welcome-card p{font-size:13px;color:var(--muted2);line-height:1.65;}
.quick-prompts{display:flex;gap:7px;flex-wrap:wrap;margin-top:12px;}
.qp{padding:6px 12px;border-radius:20px;border:1px solid var(--border);background:var(--surface2);font-size:12px;color:var(--muted2);cursor:pointer;transition:all .15s;white-space:nowrap;}
.qp:hover{border-color:var(--accent2);color:var(--text);}

/* Message row */
.row{display:flex;flex-direction:column;gap:4px;margin-bottom:10px;}
.row.align-start{align-items:flex-start;}
.row.align-end{align-items:flex-end;}
.msg-wrap{max-width:80%;display:flex;flex-direction:column;}
.row.align-start .msg-wrap{align-items:flex-start;}
.row.align-end .msg-wrap{align-items:flex-end;}
.msg-label{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;letter-spacing:.04em;margin-bottom:4px;padding:0 4px;}
.row.align-start .msg-label{color:var(--accent);}
.row.align-end   .msg-label{color:var(--accent2);}

/* Bubbles */
.msg{
  padding:12px 16px;border-radius:16px;word-break:break-word;
  line-height:1.65;font-size:14px;position:relative;
}
.msg.ai{
  background:var(--ai-bubble);color:var(--text);
  border:1px solid var(--ai-border);border-bottom-left-radius:4px;
}
.msg.user{
  background:var(--user-bubble);color:#fff;
  border-bottom-right-radius:4px;
}
.msg-meta{font-size:10px;color:var(--muted2);padding:0 4px;font-family:'Space Mono',monospace;}
.bubble-actions{position:absolute;top:8px;right:8px;display:flex;gap:5px;}
.action-btn{background:var(--surface3);border:1px solid var(--border);padding:4px 8px;border-radius:6px;cursor:pointer;color:var(--muted2);font-size:11px;transition:all .15s;}
.action-btn:hover{color:var(--text);border-color:var(--muted);}
.bubble-img{display:block;margin-top:8px;max-width:280px;border-radius:8px;border:1px solid var(--border);}
.empty-note{color:var(--muted2);text-align:center;padding:24px;font-size:13px;}

/* Loading dots */
.loading{display:inline-flex;align-items:center;gap:7px;color:var(--muted2);font-size:13px;}
.dot{display:inline-block;width:7px;height:7px;border-radius:50%;animation:blink 1.2s infinite;}
.dot:nth-child(1){background:var(--accent);}
.dot:nth-child(2){background:var(--accent2);animation-delay:.18s;}
.dot:nth-child(3){background:var(--purple);animation-delay:.36s;}
@keyframes blink{0%,100%{opacity:.25;transform:translateY(0)}50%{opacity:1;transform:translateY(-4px)}}

/* ── INPUT BAR ── */
.input-bar{
  flex-shrink:0;padding:12px 16px;
  background:var(--surface);border-top:1px solid var(--border);
  display:flex;align-items:flex-end;gap:8px;
}
.input-wrap{
  flex:1;display:flex;align-items:flex-end;gap:7px;
  background:var(--surface2);border:1.5px solid var(--border);
  border-radius:14px;padding:8px 12px;transition:border-color .15s;
}
.input-wrap:focus-within{border-color:var(--accent);}
.attach-preview{
  width:44px;height:44px;border-radius:8px;overflow:hidden;flex-shrink:0;
  background:var(--surface3);border:1px solid var(--border);cursor:pointer;
  display:none;
}
.attach-preview img{width:100%;height:100%;object-fit:cover;}
#questionInput{
  flex:1;background:none;border:none;outline:none;
  color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;
  resize:none;min-height:36px;max-height:120px;line-height:1.5;
  align-self:flex-end;
}
#questionInput::placeholder{color:var(--muted2);}
.icon-btn{
  background:none;border:none;color:var(--muted2);
  font-size:16px;cursor:pointer;padding:4px 5px;
  border-radius:7px;transition:color .15s;flex-shrink:0;align-self:flex-end;
}
.icon-btn:hover{color:var(--accent2);}
.send-btn{
  width:44px;height:44px;border-radius:12px;flex-shrink:0;
  background:linear-gradient(135deg,var(--accent2),var(--accent));
  border:none;color:#fff;font-size:15px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:all .15s;box-shadow:0 4px 12px rgba(59,130,246,.3);
}
.send-btn:hover{transform:scale(1.06);box-shadow:0 6px 18px rgba(59,130,246,.45);}
.send-btn:active{transform:scale(.96);}

/* ── MOBILE SIDEBAR TOGGLE ── */
.sb-toggle{display:none;width:36px;height:36px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;color:var(--muted2);cursor:pointer;font-size:13px;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0;}
.sb-toggle:hover{color:var(--accent);}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:39;}

/* ── RESPONSIVE ── */
@media(max-width:768px){
  .side-panel{position:fixed;left:0;top:0;bottom:0;z-index:40;transform:translateX(-100%);transition:transform .25s ease;width:280px;}
  .side-panel.open{transform:translateX(0);}
  .sb-overlay.active{display:block;}
  .sb-toggle{display:inline-flex;}
  .brand-sub{display:none;}
  .input-bar{padding:10px 12px;}
}
@media(max-width:480px){
  .chat-wrap{padding:12px;}
  .msg-wrap{max-width:90%;}
  .topbar{padding:0 12px;}
}
</style>
</head>
<body>

<div class="sb-overlay" id="sbOverlay"></div>

<!-- ── TOPBAR ── -->
<nav class="topbar">
  <div class="topbar-brand">
    <button class="sb-toggle" id="sbToggle"><i class="fa fa-robot"></i></button>
    <div class="brand-logo">ES</div>
    <div>
      <div class="brand-name">AI TUTOR</div>
      <div class="brand-sub">Textbook Assistant</div>
    </div>
  </div>
  <div class="topbar-right">
    <div class="user-chip">
      <?php if ($display_picture): ?>
        <img src="<?= esc($display_picture) ?>" alt="">
      <?php else: ?>
        <div class="av"><?= esc(mb_strtoupper(mb_substr($display_name,0,1))) ?></div>
      <?php endif; ?>
      <span><?= esc($display_name) ?></span>
    </div>
    <button id="themeToggle" class="theme-toggle">
      <span id="tIcon">🌙</span>
      <div class="t-track"><div class="t-thumb"></div></div>
      <span id="tLabel">Dark</span>
    </button>
    <a href="../dashboard.php" class="btn"><i class="fa fa-house" style="font-size:10px"></i></a>
  </div>
</nav>

<div class="app-body">

  <!-- ── LEFT PANEL ── -->
  <aside class="side-panel" id="sidePanel">

    <!-- Profile card -->
    <div class="profile-card">
      <div class="profile-avatar">
        <?php if ($display_picture): ?>
          <img src="<?= esc($display_picture) ?>" alt="">
        <?php else: ?>
          <?= esc(mb_strtoupper(mb_substr($display_name,0,1))) ?>
        <?php endif; ?>
      </div>
      <div class="profile-greeting"><?= esc($greeting) ?>, 👋</div>
      <div class="profile-name"><?= esc($display_name) ?></div>
      <?php if ($display_email): ?>
        <div class="profile-email"><?= esc($display_email) ?></div>
      <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-tile score">
        <div class="n"><?= number_format($user_score) ?></div>
        <div class="l">Score</div>
      </div>
      <div class="stat-tile answered">
        <div class="n"><?= $questions_answered ?></div>
        <div class="l">Answered</div>
      </div>
      <div class="stat-tile streak">
        <div class="n"><?= $streak_days ?>🔥</div>
        <div class="l">Day Streak</div>
      </div>
      <div class="stat-tile accuracy">
        <div class="n"><?= $accuracy ?>%</div>
        <div class="l">Accuracy</div>
      </div>
    </div>

    <!-- Suggested topics -->
    <div class="side-section">
      <div class="side-section-title"><i class="fa fa-lightbulb"></i> Suggested Topics</div>
      <?php
      $topics = [
        ['📐', 'Mathematics', 'Algebra, calculus, statistics'],
        ['🧬', 'Biology', 'Cells, genetics, ecology'],
        ['⚗️', 'Chemistry', 'Bonding, reactions, organic'],
        ['⚡', 'Physics', 'Mechanics, waves, electricity'],
        ['📖', 'English', 'Comprehension, grammar, essays'],
        ['🏛️', 'Government', 'Democracy, constitution, organs'],
        ['📈', 'Economics', 'Demand, supply, market systems'],
        ['🔬', 'Further Maths', 'Calculus, complex numbers'],
      ];
      foreach ($topics as [$icon, $label, $sub]):
      ?>
      <div class="topic-chip" onclick="insertTopic('<?= esc($label) ?>')">
        <span class="tc-icon"><?= $icon ?></span>
        <div class="tc-label">
          <div style="font-weight:600;color:var(--text)"><?= esc($label) ?></div>
          <div style="font-size:10px;color:var(--muted2)"><?= esc($sub) ?></div>
        </div>
        <span class="tc-arrow"><i class="fa fa-chevron-right" style="font-size:9px"></i></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="side-gap"></div>

    <div class="exam-badges">
      <span class="exam-badge waec">● WAEC</span>
      <span class="exam-badge jamb">● JAMB UTME</span>
      <span class="exam-badge neco">● NECO</span>
    </div>

  </aside>

  <!-- ── CHAT COLUMN ── -->
  <div class="chat-col">

    <!-- Chat header -->
    <div class="chat-header">
      <div class="chat-header-left">
        <div class="ai-avatar">🤖</div>
        <div>
          <div class="chat-title">AI Textbook Tutor</div>
          <div class="chat-sub">WAEC · JAMB · NECO · Post-UTME</div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <div class="ai-status">
          <span class="ai-dot"></span> Ready
        </div>
        <button class="btn btn-ghost" id="clearBtn" title="Clear chat">
          <i class="fa fa-trash" style="font-size:11px"></i> Clear
        </button>
      </div>
    </div>

    <!-- Messages -->
    <div id="chat" class="chat-wrap" aria-live="polite">
      <div class="inner" id="innerCol">

        <!-- Personalised welcome -->
        <div class="welcome-card" id="welcomeCard">
          <h3>👋 Welcome back, <?= esc($display_name) ?>!</h3>
          <p>I'm your AI Textbook Tutor. Ask me any WAEC, JAMB, or NECO topic — I'll give you a clear, textbook-style explanation.<br>You've answered <strong style="color:var(--accent2)"><?= $questions_answered ?></strong> questions so far with a <strong style="color:var(--accent)"><?= $accuracy ?>% accuracy</strong>. Keep it up! 🚀</p>
          <div class="quick-prompts" id="quickPrompts">
            <span class="qp" onclick="usePrompt(this)">Explain photosynthesis</span>
            <span class="qp" onclick="usePrompt(this)">What is osmosis?</span>
            <span class="qp" onclick="usePrompt(this)">Newton's laws of motion</span>
            <span class="qp" onclick="usePrompt(this)">Factorisation methods</span>
            <span class="qp" onclick="usePrompt(this)">Types of market structures</span>
          </div>
        </div>

        <div id="empty" class="empty-note">Type a question below or tap a topic to begin. 📚</div>
      </div>
    </div>

    <!-- Input bar -->
    <div class="input-bar">
      <div class="input-wrap">
        <div class="attach-preview" id="attachPreview" title="Attached image (click to remove)"></div>
        <textarea id="questionInput" rows="1"
          placeholder="Ask a WAEC / JAMB question… (e.g. Explain the water cycle)"></textarea>
        <button class="icon-btn" id="attachBtn" title="Attach image">
          <i class="fa fa-image" style="font-size:14px"></i>
        </button>
        <input id="fileInput" type="file" accept="image/*" style="display:none">
      </div>
      <button class="send-btn" id="sendBtn" aria-label="Ask">
        <i class="fa fa-paper-plane"></i>
      </button>
    </div>

  </div><!-- /chat-col -->
</div><!-- /app-body -->

<script>
/* ── THEME ── */
(function(){ if(localStorage.getItem('es_theme')==='light') document.body.classList.add('light'); syncT(); })();
function syncT(){ const l=document.body.classList.contains('light'); document.getElementById('tIcon').textContent=l?'☀️':'🌙'; document.getElementById('tLabel').textContent=l?'Light':'Dark'; }
document.getElementById('themeToggle').addEventListener('click',()=>{ const l=document.body.classList.toggle('light'); localStorage.setItem('es_theme',l?'light':'dark'); syncT(); });

/* ── SIDEBAR MOBILE ── */
document.getElementById('sbToggle').addEventListener('click',()=>{
  document.getElementById('sidePanel').classList.add('open');
  document.getElementById('sbOverlay').classList.add('active');
});
document.getElementById('sbOverlay').addEventListener('click',()=>{
  document.getElementById('sidePanel').classList.remove('open');
  document.getElementById('sbOverlay').classList.remove('active');
});

/* ── CONFIG ── */
const ENDPOINTS = ['./textbook_ai.php','/ai/textbook_ai.php','/textbook_ai.php'];
let resolvedEndpoint = null;
const STORAGE_KEY = 'es_ai_convo_v2';
const MY_NAME = <?= json_encode($display_name) ?>;
let convo = [];
let attachedFile = null;

/* ── DOM REFS ── */
const chatWrap  = document.getElementById('chat');
const innerCol  = document.getElementById('innerCol');
const input     = document.getElementById('questionInput');
const sendBtn   = document.getElementById('sendBtn');
const clearBtn  = document.getElementById('clearBtn');
const attachBtn = document.getElementById('attachBtn');
const fileInput = document.getElementById('fileInput');
const attachPreview = document.getElementById('attachPreview');

/* ── HELPERS ── */
function timeNow(){ const d=new Date(); return d.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}); }
function esc(s){ if(typeof s!=='string') return s; return s.replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[m])); }
function scrollBottom(){ try{ chatWrap.scrollTop=chatWrap.scrollHeight; }catch(e){} }

/* ── TOPIC CLICK ── */
function insertTopic(label){
  input.value = `Explain ${label} for WAEC`;
  input.focus();
  document.getElementById('sidePanel').classList.remove('open');
  document.getElementById('sbOverlay').classList.remove('active');
}
function usePrompt(el){ input.value=el.textContent; input.focus(); document.getElementById('welcomeCard')?.remove(); document.getElementById('empty')?.remove(); }

/* ── RENDER MESSAGE ── */
function renderMessage(role, text, ts, imgDataUrl){
  document.getElementById('empty')?.remove();
  document.getElementById('welcomeCard')?.remove();

  const row = document.createElement('div');
  row.className = 'row ' + (role==='user'?'align-end':'align-start');

  const wrap = document.createElement('div');
  wrap.className = 'msg-wrap';

  const label = document.createElement('div');
  label.className = 'msg-label';
  label.textContent = role==='user' ? MY_NAME : '🤖 AI Tutor';

  const bubble = document.createElement('div');
  bubble.className = 'msg ' + role;
  bubble.innerHTML = `<div>${esc(text).replace(/\n/g,'<br>')}</div>`;

  if(role==='ai'){
    const actions = document.createElement('div');
    actions.className = 'bubble-actions';
    const copyBtn = document.createElement('button');
    copyBtn.className='action-btn'; copyBtn.title='Copy'; copyBtn.innerHTML='<i class="fa fa-copy" style="font-size:10px"></i>';
    copyBtn.addEventListener('click',()=>{
      navigator.clipboard?.writeText(text).then(()=>{ copyBtn.innerHTML='<i class="fa fa-check" style="font-size:10px;color:var(--accent)"></i>'; setTimeout(()=>{ copyBtn.innerHTML='<i class="fa fa-copy" style="font-size:10px"></i>'; },1200); }).catch(()=>alert('Copy failed'));
    });
    actions.appendChild(copyBtn); bubble.appendChild(actions);
  }

  if(imgDataUrl){ const img=document.createElement('img'); img.className='bubble-img'; img.src=imgDataUrl; bubble.appendChild(img); }

  const meta = document.createElement('div');
  meta.className='msg-meta'; meta.textContent=ts||timeNow();

  wrap.appendChild(label); wrap.appendChild(bubble); wrap.appendChild(meta);
  row.appendChild(wrap); innerCol.appendChild(row);
  setTimeout(scrollBottom,60);
  return bubble;
}

/* ── LOADING ── */
function createLoading(){
  removeLoading();
  const row=document.createElement('div'); row.className='row align-start';
  const wrap=document.createElement('div'); wrap.className='msg-wrap';
  const bubble=document.createElement('div'); bubble.className='msg ai'; bubble.id='hfLoading';
  bubble.innerHTML=`<div class="loading"><span class="dot"></span><span class="dot"></span><span class="dot"></span><span style="margin-left:4px;font-size:13px">AI is thinking…</span></div>`;
  wrap.appendChild(bubble); row.appendChild(wrap); innerCol.appendChild(row);
  scrollBottom();
}
function removeLoading(){ const el=document.getElementById('hfLoading'); if(el&&el.parentNode) el.parentNode.parentNode?.removeChild(el.parentNode); }

/* ── FIND ENDPOINT ── */
async function findEndpoint(){
  if(resolvedEndpoint) return resolvedEndpoint;
  for(const ep of ENDPOINTS){
    try{
      const t=await fetch(ep,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({question:'ping'})});
      if(t){ resolvedEndpoint=ep; return ep; }
    }catch(e){}
  }
  resolvedEndpoint=ENDPOINTS[0]; return resolvedEndpoint;
}

/* ── SEND ── */
async function askQuestion(){
  const q=input.value.trim();
  if(!q&&!attachedFile) return;
  const ts=timeNow();
  renderMessage('user',q||'[image]',ts,attachedFile?.preview||null);
  convo.push({role:'user',text:q||'[image]',ts,img:attachedFile?.preview||null});
  saveConvo();
  input.value=''; input.style.height='auto'; clearAttachmentUI();
  createLoading();
  const ep=await findEndpoint();
  try{
    let opts;
    if(attachedFile){
      const fd=new FormData(); fd.append('question',q); fd.append('image',attachedFile.file,attachedFile.file.name);
      opts={method:'POST',body:fd};
    } else {
      opts={method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({question:q})};
    }
    const res=await fetch(ep,opts);
    const txt=await res.text();
    removeLoading();
    let data=null;
    try{ data=JSON.parse(txt); }catch(e){ showError('Server returned non-JSON response. Check backend.'); return; }
    if(data.error){ showError(data.error); return; }
    let answer=data.answer||data.generated_text||(Array.isArray(data)&&data[0]?.generated_text)||data?.outputs?.[0]?.generated_text||null;
    if(typeof data==='string') answer=data;
    if(!answer){ showError('No answer returned from AI backend.'); return; }
    const ats=timeNow();
    renderMessage('ai',answer,ats,data.debug_img||null);
    convo.push({role:'ai',text:answer,ts:ats,img:data.debug_img||null});
    saveConvo();
  }catch(err){ removeLoading(); showError(err.message||String(err)); }
}
function showError(err){ renderMessage('ai',`⚠️ ${err}`,timeNow()); }

/* ── ATTACHMENT ── */
attachBtn.addEventListener('click',()=>fileInput.click());
fileInput.addEventListener('change',e=>{
  const f=e.target.files?.[0]; if(!f) return;
  if(!f.type.startsWith('image/')){ alert('Please select an image'); return; }
  const reader=new FileReader();
  reader.onload=ev=>{
    const url=ev.target.result;
    attachPreview.innerHTML=''; const img=document.createElement('img'); img.src=url; attachPreview.appendChild(img);
    attachPreview.style.display='block';
    attachedFile={file:f,preview:url};
    attachPreview.onclick=()=>{ if(confirm('Remove image?')) clearAttachmentUI(); };
  };
  reader.readAsDataURL(f);
});
function clearAttachmentUI(){ attachedFile=null; fileInput.value=''; attachPreview.style.display='none'; attachPreview.innerHTML=''; attachPreview.onclick=null; }

/* ── PERSIST ── */
function saveConvo(){ try{ localStorage.setItem(STORAGE_KEY,JSON.stringify(convo)); }catch(e){} }
function loadConvo(){
  const raw=localStorage.getItem(STORAGE_KEY); if(!raw) return;
  try{ const a=JSON.parse(raw); if(Array.isArray(a)) convo=a; }catch(e){}
}
function renderStored(){
  if(!convo.length) return;
  document.getElementById('empty')?.remove();
  document.getElementById('welcomeCard')?.remove();
  for(const m of convo){ if(m.role==='user') renderMessage('user',m.text,m.ts,m.img); else renderMessage('ai',m.text,m.ts,m.img); }
  scrollBottom();
}

/* ── CLEAR ── */
clearBtn.addEventListener('click',()=>{
  if(!confirm('Clear the chat history?')) return;
  convo=[]; localStorage.removeItem(STORAGE_KEY); innerCol.innerHTML='';
  const wc=document.createElement('div'); wc.className='welcome-card'; wc.id='welcomeCard';
  wc.innerHTML=`<h3>👋 What do you want to learn today?</h3><p>Ask any WAEC, JAMB, or NECO topic and get a clear textbook-style explanation.</p>`;
  innerCol.appendChild(wc);
  const en=document.createElement('div'); en.id='empty'; en.className='empty-note'; en.textContent='Type a question below or tap a topic to begin. 📚';
  innerCol.appendChild(en);
  scrollBottom();
});

/* ── AUTO-RESIZE TEXTAREA ── */
input.addEventListener('input',()=>{ input.style.height='auto'; input.style.height=Math.min(input.scrollHeight,120)+'px'; });

/* ── KEYBOARD ── */
input.addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); askQuestion(); } });
sendBtn.addEventListener('click',askQuestion);

/* ── BOOT ── */
loadConvo(); renderStored(); setTimeout(()=>input.focus(),300);
</script>
</body>
</html>
