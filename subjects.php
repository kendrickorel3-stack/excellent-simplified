<?php
// subjects.php — Interactive JAMB/WAEC Syllabus Panel
require_once "config/db.php";
session_start();

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$dn = $_SESSION['google_name'] ?? 'Student';
$dp = $_SESSION['google_picture'] ?? null;

// Fetch subjects from DB (for the video count)
$res = $conn->query("SELECT id, name, (SELECT COUNT(*) FROM videos v WHERE v.subject_id = s.id) AS video_count FROM subjects s ORDER BY name");
$db_subjects = [];
while($r = $res->fetch_assoc()) $db_subjects[$r['name']] = $r;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Syllabus — Excellent Simplified</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Sora:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════════
   DESIGN TOKENS
══════════════════════════════════════════════════ */
:root {
  --bg: #060912;
  --s1: #0c1020;
  --s2: #111828;
  --s3: #161e30;
  --s4: #1c2438;
  --border: rgba(255,255,255,.06);
  --border2: rgba(255,255,255,.12);
  --accent: #00c98a;
  --accent-dim: rgba(0,201,138,.12);
  --blue: #4f8ef7;
  --amber: #f59e0b;
  --danger: #f43f5e;
  --text: #eef2ff;
  --sub: #8896b3;
  --dim: #3d4f6e;
}

*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}

/* Ambient background */
body::before {
  content: '';
  position: fixed; inset: 0; pointer-events: none; z-index: 0;
  background:
    radial-gradient(ellipse 70% 40% at 20% 0%, rgba(0,201,138,.06) 0, transparent 60%),
    radial-gradient(ellipse 50% 35% at 80% 100%, rgba(79,142,247,.05) 0, transparent 55%),
    radial-gradient(ellipse 40% 30% at 50% 50%, rgba(0,201,138,.02) 0, transparent 70%);
}

/* ── TOPBAR ── */
.topbar {
  position: sticky; top: 0; z-index: 50;
  height: 58px;
  background: rgba(6,9,18,.94);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; padding: 0 20px; gap: 12px;
}
.tb-logo {
  width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--accent), var(--blue));
  display: flex; align-items: center; justify-content: center;
  font-family: 'Space Mono', monospace; font-size: 10px; font-weight: 700; color: #000;
  box-shadow: 0 3px 14px rgba(0,201,138,.35);
}
.tb-title {
  font-family: 'Sora', sans-serif;
  font-size: 15px; font-weight: 700;
}
.tb-sub { font-size: 11px; color: var(--sub); }
.tb-flex { flex: 1; }
.tb-right { display: flex; gap: 8px; align-items: center; }
.t-btn {
  height: 32px; padding: 0 13px; border-radius: 8px;
  background: var(--s2); border: 1px solid var(--border);
  color: var(--sub); cursor: pointer;
  display: flex; align-items: center; gap: 7px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px; font-weight: 600;
  text-decoration: none; transition: all .15s; flex-shrink: 0;
}
.t-btn:hover { color: var(--text); border-color: var(--border2); }
.user-chip {
  display: flex; align-items: center; gap: 7px;
  padding: 4px 12px; background: var(--s2);
  border: 1px solid var(--border); border-radius: 20px;
  font-size: 12px; font-weight: 600; flex-shrink: 0;
}
.user-chip img { width: 22px; height: 22px; border-radius: 6px; object-fit: cover; }
.user-av {
  width: 22px; height: 22px; border-radius: 6px;
  background: linear-gradient(135deg, var(--accent), var(--blue));
  display: flex; align-items: center; justify-content: center;
  font-family: 'Space Mono', monospace; font-size: 9px; font-weight: 700; color: #000;
}

/* ── HERO ── */
.hero {
  position: relative; z-index: 1;
  padding: 40px 20px 0;
  text-align: center;
}
.hero-eyebrow {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 5px 14px; border-radius: 20px;
  border: 1px solid rgba(0,201,138,.25);
  background: rgba(0,201,138,.07);
  font-family: 'Space Mono', monospace;
  font-size: 10px; font-weight: 700; letter-spacing: .1em;
  text-transform: uppercase; color: var(--accent);
  margin-bottom: 14px;
}
.hero h1 {
  font-family: 'Sora', sans-serif;
  font-size: clamp(24px, 5vw, 42px);
  font-weight: 800; line-height: 1.15; margin-bottom: 10px;
  background: linear-gradient(135deg, #fff 30%, rgba(0,201,138,.8));
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  background-clip: text;
}
.hero p { font-size: 14px; color: var(--sub); max-width: 500px; margin: 0 auto 32px; }

/* ── SUBJECT PICKER ── */
.pick-wrap { position: relative; z-index: 1; max-width: 960px; margin: 0 auto; padding: 0 16px; }
.pick-label {
  font-family: 'Space Mono', monospace; font-size: 10px; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase; color: var(--dim);
  margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
}
.pick-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.subjects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 10px; margin-bottom: 28px;
}
.subj-card {
  position: relative;
  padding: 16px 12px; border-radius: 14px;
  border: 2px solid var(--border);
  background: var(--s1);
  cursor: pointer; transition: all .22s; text-align: center;
  overflow: hidden;
}
.subj-card::before {
  content: ''; position: absolute; inset: 0;
  background: var(--card-color, var(--accent));
  opacity: 0; transition: opacity .22s;
}
.subj-card:hover::before { opacity: .06; }
.subj-card:hover { border-color: var(--card-color, var(--accent)); transform: translateY(-2px); }
.subj-card.active::before { opacity: .1; }
.subj-card.active { border-color: var(--card-color, var(--accent)); }
.sc-emoji { font-size: 28px; margin-bottom: 8px; display: block; }
.sc-name {
  font-size: 12px; font-weight: 700; line-height: 1.3;
  position: relative; z-index: 1;
}
.sc-count {
  font-size: 10px; color: var(--sub); margin-top: 4px;
  font-family: 'Space Mono', monospace; position: relative; z-index: 1;
}
.sc-progress {
  margin-top: 8px; height: 3px; border-radius: 3px;
  background: rgba(255,255,255,.07); overflow: hidden;
  position: relative; z-index: 1;
}
.sc-progress-fill {
  height: 100%; border-radius: 3px;
  background: var(--card-color, var(--accent));
  transition: width .4s ease;
}
.sc-tick {
  position: absolute; top: 8px; right: 8px;
  width: 16px; height: 16px; border-radius: 50%;
  background: var(--card-color, var(--accent));
  display: none; align-items: center; justify-content: center;
  font-size: 8px; color: #000; font-weight: 900; z-index: 2;
}
.subj-card.has-progress .sc-tick { display: flex; }

/* ── SYLLABUS PANEL ── */
.syllabus-panel {
  position: relative; z-index: 1;
  max-width: 960px; margin: 0 auto 60px; padding: 0 16px;
  display: none; animation: panelIn .35s cubic-bezier(.22,1,.36,1) both;
}
.syllabus-panel.show { display: block; }
@keyframes panelIn {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}
.panel-card {
  background: var(--s1); border: 1px solid var(--border);
  border-radius: 18px; overflow: hidden;
}

/* Panel header */
.panel-head {
  padding: 20px 22px 16px;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(135deg, var(--s2), var(--s1));
  display: flex; align-items: flex-start; gap: 14px; flex-wrap: wrap;
}
.ph-icon {
  width: 52px; height: 52px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 26px; flex-shrink: 0;
}
.ph-info { flex: 1; min-width: 0; }
.ph-title {
  font-family: 'Sora', sans-serif; font-size: 20px; font-weight: 800;
  margin-bottom: 4px;
}
.ph-sub { font-size: 12px; color: var(--sub); line-height: 1.5; }
.ph-right { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0; }
.ph-progress-ring { position: relative; width: 52px; height: 52px; flex-shrink: 0; }
.ph-progress-ring svg { position: absolute; inset: 0; transform: rotate(-90deg); }
.ph-progress-ring circle { fill: none; stroke-width: 4; stroke-linecap: round; }
.ring-bg { stroke: var(--s3); }
.ring-fill { stroke: var(--accent); transition: stroke-dashoffset .5s ease; }
.ph-pct {
  position: absolute; inset: 0; display: flex; align-items: center;
  justify-content: center; font-family: 'Space Mono', monospace;
  font-size: 11px; font-weight: 700; color: var(--accent);
}
.ph-actions { display: flex; gap: 7px; flex-wrap: wrap; }
.ph-btn {
  display: flex; align-items: center; gap: 6px;
  padding: 7px 13px; border-radius: 9px;
  border: 1px solid var(--border); background: var(--s3);
  color: var(--sub); font-size: 12px; font-weight: 600;
  cursor: pointer; transition: all .15s;
  font-family: 'Plus Jakarta Sans', sans-serif; text-decoration: none;
}
.ph-btn:hover { color: var(--text); border-color: var(--border2); }
.ph-btn.lessons {
  background: var(--accent-dim);
  border-color: rgba(0,201,138,.3); color: var(--accent);
}
.ph-btn.lessons:hover { background: rgba(0,201,138,.18); }

/* Exam tabs */
.exam-tabs {
  display: flex; gap: 0;
  border-bottom: 1px solid var(--border);
  background: var(--s2);
}
.exam-tab {
  flex: 1; padding: 13px 16px; text-align: center;
  font-size: 13px; font-weight: 700; cursor: pointer;
  border-bottom: 2.5px solid transparent;
  transition: all .18s; color: var(--sub);
  font-family: 'Space Mono', monospace;
}
.exam-tab:hover { color: var(--text); background: var(--s3); }
.exam-tab.active { color: var(--accent); border-bottom-color: var(--accent); background: var(--s1); }

/* Tab bodies */
.tab-body { display: none; }
.tab-body.show { display: block; }

/* Progress bar under tabs */
.tab-stats {
  padding: 10px 18px 0;
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.tab-prog-bar {
  flex: 1; height: 6px; border-radius: 6px;
  background: var(--s3); overflow: hidden; min-width: 80px;
}
.tab-prog-fill {
  height: 100%; border-radius: 6px;
  background: linear-gradient(90deg, var(--accent), var(--blue));
  transition: width .45s ease;
}
.tab-prog-lbl {
  font-family: 'Space Mono', monospace; font-size: 10px;
  font-weight: 700; color: var(--sub); flex-shrink: 0;
}
.tab-mark-all {
  padding: 4px 11px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--s3);
  color: var(--sub); font-size: 11px; font-weight: 600;
  cursor: pointer; transition: all .15s; flex-shrink: 0;
}
.tab-mark-all:hover { color: var(--text); border-color: var(--border2); }

/* Topic sections */
.topic-sections { padding: 14px 18px 20px; }
.topic-section { margin-bottom: 20px; }
.ts-header {
  display: flex; align-items: center; gap: 8px;
  margin-bottom: 10px; cursor: pointer; user-select: none;
}
.ts-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ts-title { font-size: 13px; font-weight: 700; flex: 1; }
.ts-count {
  font-family: 'Space Mono', monospace; font-size: 10px;
  color: var(--dim); flex-shrink: 0;
}
.ts-chevron { color: var(--dim); font-size: 10px; transition: transform .2s; }
.ts-chevron.open { transform: rotate(90deg); }
.ts-items { display: flex; flex-direction: column; gap: 6px; }

/* Individual syllabus item */
.s-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 11px 13px; border-radius: 10px;
  border: 1.5px solid var(--border); background: var(--s2);
  transition: all .18s; position: relative;
}
.s-item:hover { border-color: var(--border2); background: var(--s3); }
.s-item.done {
  border-color: rgba(0,201,138,.25);
  background: rgba(0,201,138,.05);
}
.s-item.done .s-text { text-decoration: line-through; color: var(--sub); }

/* Checkbox */
.s-check {
  width: 20px; height: 20px; border-radius: 6px;
  border: 2px solid var(--dim); background: transparent;
  cursor: pointer; flex-shrink: 0; margin-top: 1px;
  display: flex; align-items: center; justify-content: center;
  transition: all .15s;
}
.s-check:hover { border-color: var(--accent); }
.s-item.done .s-check {
  background: var(--accent); border-color: var(--accent);
}
.s-check i { font-size: 10px; color: #000; display: none; }
.s-item.done .s-check i { display: block; }

.s-content { flex: 1; min-width: 0; }
.s-text { font-size: 13px; font-weight: 500; line-height: 1.5; }
.s-sub { font-size: 11px; color: var(--sub); margin-top: 2px; line-height: 1.4; }

/* AI button on each item */
.s-ai {
  display: flex; align-items: center; gap: 4px;
  padding: 4px 10px; border-radius: 7px;
  border: 1px solid rgba(79,142,247,.3);
  background: rgba(79,142,247,.07);
  color: var(--blue); font-size: 11px; font-weight: 700;
  cursor: pointer; transition: all .15s; flex-shrink: 0;
  font-family: 'Plus Jakarta Sans', sans-serif; white-space: nowrap;
}
.s-ai:hover { background: rgba(79,142,247,.15); border-color: rgba(79,142,247,.5); }

/* Empty state */
.empty-state {
  text-align: center; padding: 50px 20px; color: var(--sub);
}
.empty-state .big { font-size: 44px; margin-bottom: 12px; opacity: .5; }

/* ── AI BOTTOM SHEET ── */
.ai-ov {
  display: none; position: fixed; inset: 0; z-index: 100;
  background: rgba(0,0,0,.75); backdrop-filter: blur(8px);
  align-items: flex-end; justify-content: center;
}
.ai-ov.show { display: flex; }
.ai-sheet {
  width: 100%; max-width: 740px; max-height: 86vh;
  background: var(--s1); border: 1px solid var(--border2);
  border-bottom: none; border-radius: 20px 20px 0 0;
  display: flex; flex-direction: column; overflow: hidden;
  animation: slideup .32s cubic-bezier(.22,1,.36,1) both;
}
@keyframes slideup {
  from { transform: translateY(100%); opacity: 0; }
  to   { transform: translateY(0);    opacity: 1; }
}
.ai-handle { display: flex; justify-content: center; padding: 8px 0 4px; flex-shrink: 0; }
.ai-handle-bar { width: 36px; height: 4px; border-radius: 4px; background: var(--s3); }
.ai-head {
  padding: 10px 18px; border-bottom: 1px solid var(--border);
  display: flex; align-items: flex-start; gap: 10px; flex-shrink: 0;
}
.ai-head-icon {
  width: 36px; height: 36px; border-radius: 10px;
  background: rgba(79,142,247,.1); border: 1px solid rgba(79,142,247,.25);
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; flex-shrink: 0;
}
.ai-head-info { flex: 1; }
.ai-head-name { font-size: 13px; font-weight: 700; margin-bottom: 2px; }
.ai-head-topic {
  font-size: 12px; color: var(--sub); line-height: 1.4;
  display: -webkit-box; -webkit-line-clamp: 2;
  -webkit-box-orient: vertical; overflow: hidden;
}
.ai-x {
  width: 28px; height: 28px; border-radius: 8px;
  background: var(--s2); border: 1px solid var(--border);
  color: var(--sub); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; transition: all .15s; flex-shrink: 0;
}
.ai-x:hover { color: var(--text); }
.ai-body { flex: 1; overflow-y: auto; padding: 14px 18px 20px; }
.ai-body::-webkit-scrollbar { width: 4px; }
.ai-body::-webkit-scrollbar-thumb { background: var(--s3); border-radius: 3px; }
.ai-resp {
  font-size: 14px; color: var(--sub); line-height: 1.82;
  white-space: pre-wrap;
}
.ai-load {
  display: flex; align-items: center; gap: 10px;
  padding: 18px 0; color: var(--dim);
}
.adot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--blue); animation: adotb .9s infinite;
}
.adot:nth-child(2) { animation-delay: .15s; }
.adot:nth-child(3) { animation-delay: .3s; }
@keyframes adotb {
  0%,80%,100% { transform: scale(.7); opacity: .5; }
  40% { transform: scale(1); opacity: 1; }
}
.ai-foot {
  padding: 10px 18px; border-top: 1px solid var(--border);
  display: flex; gap: 8px; flex-shrink: 0;
}
.ai-mark-done {
  display: flex; align-items: center; gap: 6px; padding: 8px 16px;
  border-radius: 9px; border: 1px solid rgba(0,201,138,.3);
  background: rgba(0,201,138,.08); color: var(--accent);
  font-size: 12px; font-weight: 700; cursor: pointer; transition: all .15s;
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.ai-mark-done:hover { background: rgba(0,201,138,.16); }
.ai-mark-done.marked {
  background: var(--accent); color: #000; border-color: var(--accent);
}

/* ── TOAST ── */
.toast {
  position: fixed; bottom: 24px; right: 20px; z-index: 200;
  padding: 10px 16px; border-radius: 10px; font-size: 13px; font-weight: 600;
  display: none; align-items: center; gap: 8px;
  box-shadow: 0 4px 24px rgba(0,0,0,.5);
  animation: toastIn .25s ease both;
}
@keyframes toastIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
.toast.show { display: flex; }
.toast.ok { background: rgba(0,201,138,.15); border: 1px solid rgba(0,201,138,.35); color: var(--accent); }
.toast.info { background: rgba(79,142,247,.12); border: 1px solid rgba(79,142,247,.3); color: var(--blue); }

/* ── RESPONSIVE ── */
@media(max-width: 640px) {
  .topbar { padding: 0 14px; }
  .tb-sub, .user-chip span, .t-btn span { display: none; }
  .t-btn { padding: 0 10px; }
  .hero { padding: 28px 16px 0; }
  .hero h1 { font-size: 24px; }
  .subjects-grid { grid-template-columns: repeat(3, 1fr); gap: 8px; }
  .subj-card { padding: 12px 8px; }
  .sc-emoji { font-size: 24px; margin-bottom: 6px; }
  .sc-name { font-size: 11px; }
  .panel-head { gap: 10px; }
  .ph-title { font-size: 17px; }
  .ph-right { flex-direction: row; align-items: center; }
  .exam-tab { font-size: 11px; padding: 11px 10px; }
  .topic-sections { padding: 12px 14px 20px; }
  .s-item { padding: 10px 11px; }
  .s-text { font-size: 12.5px; }
  .syllabus-panel { padding: 0 12px; }
  .pick-wrap { padding: 0 12px; }
  .ai-sheet { max-height: 90vh; }
}
@media(max-width: 400px) {
  .subjects-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
</head>
<body>

<!-- AI Bottom Sheet -->
<div class="ai-ov" id="aiOv">
  <div class="ai-sheet">
    <div class="ai-handle"><div class="ai-handle-bar"></div></div>
    <div class="ai-head">
      <div class="ai-head-icon">🤖</div>
      <div class="ai-head-info">
        <div class="ai-head-name">AI Tutor Explanation</div>
        <div class="ai-head-topic" id="aiTopic">Loading…</div>
      </div>
      <button class="ai-x" onclick="closeAI()"><i class="fa fa-xmark"></i></button>
    </div>
    <div class="ai-body">
      <div class="ai-load" id="aiLoad">
        <div class="adot"></div><div class="adot"></div><div class="adot"></div>
        <span style="font-size:13px">AI Tutor is preparing your explanation…</span>
      </div>
      <div class="ai-resp" id="aiResp" style="display:none"></div>
    </div>
    <div class="ai-foot">
      <button class="ai-mark-done" id="aiMarkDone" onclick="markDoneFromAI()">
        <i class="fa fa-check"></i> Mark as Read
      </button>
      <span style="flex:1"></span>
      <button class="ai-x" style="width:auto;padding:0 14px;font-size:12px;font-weight:600;gap:6px;display:flex;align-items:center" onclick="closeAI()">
        Close
      </button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<!-- Topbar -->
<nav class="topbar">
  <div class="tb-logo">ES</div>
  <div>
    <div style="font-family:'Sora',sans-serif;font-size:14px;font-weight:700;line-height:1.2">Syllabus</div>
    <div class="tb-sub">JAMB &amp; WAEC</div>
  </div>
  <div class="tb-flex"></div>
  <div class="tb-right">
    <a href="exams/practice_test.php" class="t-btn">
      <i class="fa fa-clipboard-check" style="font-size:11px"></i>
      <span>Practice Test</span>
    </a>
    <a href="formula.php" class="t-btn">
      <i class="fa fa-square-root-variable" style="font-size:11px"></i>
      <span>Formulas</span>
    </a>
    <a href="dashboard.php" class="t-btn">
      <i class="fa fa-house" style="font-size:11px"></i>
    </a>
    <div class="user-chip">
      <?php if($dp):?><img src="<?=htmlspecialchars($dp)?>" alt=""><?php else:?>
      <div class="user-av"><?=htmlspecialchars(mb_strtoupper(mb_substr($dn,0,1)))?></div>
      <?php endif;?>
      <span><?=htmlspecialchars($dn)?></span>
    </div>
  </div>
</nav>

<!-- Hero -->
<div class="hero">
  <div class="hero-eyebrow">
    <i class="fa fa-book-open-reader" style="font-size:9px"></i>
    JAMB &amp; WAEC Syllabus Tracker
  </div>
  <h1>Your Study Roadmap</h1>
  <p>Choose a subject, explore the full syllabus, tick topics as you study, and ask the AI to explain anything.</p>
</div>

<!-- Subject Picker -->
<div class="pick-wrap" style="margin-top:32px">
  <div class="pick-label">Choose a subject</div>
  <div class="subjects-grid" id="subjectsGrid"></div>
</div>

<!-- Syllabus Panel -->
<div class="syllabus-panel" id="syllabusPanel">
  <div class="panel-card">
    <!-- Panel header -->
    <div class="panel-head" id="panelHead"></div>
    <!-- Exam type tabs -->
    <div class="exam-tabs">
      <div class="exam-tab active" data-tab="jamb" onclick="switchTab('jamb')">
        JAMB UTME
      </div>
      <div class="exam-tab" data-tab="waec" onclick="switchTab('waec')">
        WAEC SSCE
      </div>
    </div>
    <!-- JAMB body -->
    <div class="tab-body show" id="tab-jamb">
      <div class="tab-stats" id="stats-jamb"></div>
      <div class="topic-sections" id="topics-jamb"></div>
    </div>
    <!-- WAEC body -->
    <div class="tab-body" id="tab-waec">
      <div class="tab-stats" id="stats-waec"></div>
      <div class="topic-sections" id="topics-waec"></div>
    </div>
  </div>
</div>

<script>
/* ══════════════════════════════════════════════════
   FULL JAMB + WAEC SYLLABUS DATA
══════════════════════════════════════════════════ */
const SYLLABUS = {

  'Mathematics': {
    emoji: '📐', color: '#4f8ef7', sub: 'Pure & Applied Mathematics',
    jamb: [
      { section: 'Number & Numeration', color: '#4f8ef7', items: [
        { t: 'Fractions, Decimals & Approximations', s: 'HCF, LCM, rounding, significant figures, standard form' },
        { t: 'Indices & Logarithms', s: 'Laws of indices, change of base, log equations' },
        { t: 'Surds & Sequences', s: 'Rationalisation, AP and GP, sum to infinity' },
        { t: 'Sets & Number Theory', s: 'Union, intersection, complement, Venn diagrams' },
        { t: 'Matrices & Determinants', s: '2×2 matrix operations, inverse, determinant' },
      ]},
      { section: 'Algebra', color: '#a78bfa', items: [
        { t: 'Polynomials & Factorisation', s: 'Factor theorem, remainder theorem, difference of squares' },
        { t: 'Equations & Inequalities', s: 'Linear, quadratic, simultaneous equations, word problems' },
        { t: 'Variation & Proportion', s: 'Direct, inverse, joint and partial variation' },
        { t: 'Functions & Graphs', s: 'Domain, range, composite functions, inverse functions' },
        { t: 'Binary Operations', s: 'Commutativity, associativity, identity and inverse elements' },
      ]},
      { section: 'Geometry & Trigonometry', color: '#00c98a', items: [
        { t: 'Euclidean Geometry', s: 'Angles, triangles, polygons, circle theorems' },
        { t: 'Coordinate Geometry', s: 'Gradient, midpoint, distance, equation of a line, circles' },
        { t: 'Mensuration', s: 'Area and volume of 2D and 3D shapes, arc length, sector area' },
        { t: 'Trigonometry', s: 'SOH-CAH-TOA, sine/cosine rule, identities, graphs' },
        { t: 'Loci & Construction', s: 'Locus of points, angle bisectors, perpendicular bisectors' },
      ]},
      { section: 'Calculus', color: '#f59e0b', items: [
        { t: 'Differentiation', s: 'Power rule, product rule, chain rule, turning points' },
        { t: 'Integration', s: 'Power rule, definite integrals, area under curve' },
        { t: 'Applications', s: 'Velocity, acceleration, rate of change problems' },
      ]},
      { section: 'Statistics & Probability', color: '#f43f5e', items: [
        { t: 'Data Presentation', s: 'Frequency tables, bar charts, pie charts, histograms, ogive' },
        { t: 'Measures of Location', s: 'Mean, median, mode — grouped and ungrouped data' },
        { t: 'Measures of Dispersion', s: 'Range, variance, standard deviation, percentiles' },
        { t: 'Probability', s: 'Classical probability, addition and multiplication rules, tree diagrams' },
        { t: 'Permutation & Combination', s: 'nPr, nCr, circular arrangements, selection problems' },
      ]},
    ],
    waec: [
      { section: 'Numbers & Numeration', color: '#4f8ef7', items: [
        { t: 'Number Bases', s: 'Binary, octal, hexadecimal conversions and arithmetic' },
        { t: 'Fractions, Ratios & Proportions', s: 'Compound interest, depreciation, profit and loss' },
        { t: 'Logarithms & Indices', s: 'Tables, anti-log, laws of logarithms' },
        { t: 'Surds', s: 'Simplification, rationalisation, conjugate pairs' },
      ]},
      { section: 'Algebra', color: '#a78bfa', items: [
        { t: 'Algebraic Expressions', s: 'Expansion, factorisation, algebraic fractions' },
        { t: 'Linear & Quadratic Equations', s: 'Simultaneous, completing the square, quadratic formula' },
        { t: 'Sequences & Series', s: 'AP, GP, nth term, sum of terms' },
        { t: 'Graphs of Functions', s: 'Linear, quadratic, cubic, reciprocal graphs' },
        { t: 'Linear Programming', s: 'Graphical method, feasible region, objective function' },
      ]},
      { section: 'Geometry & Mensuration', color: '#00c98a', items: [
        { t: 'Plane Geometry', s: 'Congruence, similarity, circle theorems, proofs' },
        { t: 'Solid Geometry', s: 'Surface area and volume of prisms, pyramids, spheres, cones' },
        { t: 'Trigonometry', s: 'Bearings, elevation and depression, angles of any magnitude' },
        { t: 'Coordinate Geometry', s: 'Equations of lines, intersections, parallel and perpendicular' },
        { t: 'Vectors', s: '2D vectors, magnitude, direction, position vectors' },
      ]},
      { section: 'Statistics & Probability', color: '#f59e0b', items: [
        { t: 'Statistics', s: 'Mean, median, mode, quartiles, box plots, cumulative frequency' },
        { t: 'Probability', s: 'Mutually exclusive, independent events, conditional probability' },
        { t: 'Permutation & Combination', s: 'Arrangements and selections with and without repetition' },
      ]},
      { section: 'Calculus (Further)', color: '#f43f5e', items: [
        { t: 'Differentiation', s: 'Stationary points, rates of change, second derivative test' },
        { t: 'Integration', s: 'Area between curves, volumes of revolution' },
      ]},
    ],
  },

  'Physics': {
    emoji: '⚡', color: '#f59e0b', sub: 'Mechanics, Waves, Electricity & Modern Physics',
    jamb: [
      { section: 'Mechanics', color: '#f59e0b', items: [
        { t: 'Scalars & Vectors', s: 'Resolution, resultant, addition of vectors' },
        { t: 'Linear Motion', s: '3 equations of motion, velocity-time graphs, free fall' },
        { t: 'Projectile Motion', s: 'Range, maximum height, time of flight' },
        { t: 'Newton\'s Laws of Motion', s: 'Force, momentum, friction, inertia' },
        { t: 'Work, Energy & Power', s: 'KE, PE, conservation, efficiency, machines' },
        { t: 'Circular Motion', s: 'Angular velocity, centripetal force, period' },
        { t: 'Gravitation', s: "Newton's law, g, satellite motion, escape velocity" },
        { t: 'Simple Harmonic Motion', s: 'Period, pendulum, mass-spring system, Hooke\'s law' },
      ]},
      { section: 'Properties of Matter', color: '#00c98a', items: [
        { t: 'Density & Pressure', s: 'Archimedes\' principle, upthrust, Boyle\'s law, Pascal\'s law' },
        { t: 'Elasticity & Surface Tension', s: 'Young\'s modulus, stress, strain, capillarity' },
        { t: 'Fluid Flow', s: 'Streamlined flow, Bernoulli\'s principle, viscosity' },
      ]},
      { section: 'Heat & Thermodynamics', color: '#f43f5e', items: [
        { t: 'Temperature & Thermometry', s: 'Celsius, Kelvin, fixed points, thermometers' },
        { t: 'Thermal Expansion', s: 'Linear, area, volume expansion coefficients' },
        { t: 'Gas Laws', s: 'Boyle\'s, Charles\', Pressure law, ideal gas equation' },
        { t: 'Heat Capacity & Latent Heat', s: 'Specific heat, changes of state, calorimetry' },
        { t: 'Transfer of Heat', s: 'Conduction, convection, radiation, greenhouse effect' },
      ]},
      { section: 'Waves & Optics', color: '#a78bfa', items: [
        { t: 'Wave Motion', s: 'Transverse, longitudinal, amplitude, period, wavelength, v=fλ' },
        { t: 'Sound Waves', s: 'Speed in solids/liquids/gases, resonance, Doppler effect' },
        { t: 'Light & Reflection', s: 'Laws of reflection, plane and curved mirrors, magnification' },
        { t: 'Refraction & Lenses', s: 'Snell\'s law, critical angle, convex/concave lenses, 1/f=1/u+1/v' },
        { t: 'Wave Phenomena', s: 'Diffraction, interference, polarisation, superposition' },
      ]},
      { section: 'Electricity & Magnetism', color: '#4f8ef7', items: [
        { t: 'Electrostatics', s: "Coulomb's law, electric field, capacitance, dielectrics" },
        { t: 'Current Electricity', s: "Ohm's law, Kirchhoff's laws, power P=IV, resistor networks" },
        { t: 'Electromagnetism', s: 'Magnetic flux, motor, generator, Lenz\'s law, transformers' },
        { t: 'AC Circuits', s: 'RMS, impedance, resonance, power factor' },
      ]},
      { section: 'Modern Physics', color: '#ec4899', items: [
        { t: 'Atomic & Nuclear Physics', s: 'Bohr model, radioactivity, half-life, nuclear reactions' },
        { t: 'Photoelectric Effect', s: 'Photon energy E=hf, work function, threshold frequency' },
        { t: 'Electronics', s: 'p-n junction, diodes, transistors, logic gates' },
      ]},
    ],
    waec: [
      { section: 'Mechanics', color: '#f59e0b', items: [
        { t: 'Linear & Projectile Motion', s: 'Equations of motion, graphs, range and height' },
        { t: 'Forces & Equilibrium', s: 'Moments, couples, centre of gravity, Lami\'s theorem' },
        { t: 'Work, Energy & Machines', s: 'Conservation of energy, mechanical advantage, efficiency' },
        { t: 'Momentum & Collisions', s: 'Conservation of momentum, elastic and inelastic collisions' },
        { t: 'Gravitation & Circular Motion', s: 'Orbital velocity, period, centripetal acceleration' },
      ]},
      { section: 'Properties of Matter & Heat', color: '#f43f5e', items: [
        { t: 'Pressure & Upthrust', s: 'Fluid pressure, Archimedes, floating and sinking' },
        { t: 'Elasticity', s: 'Elastic limit, yield point, Hooke\'s law, stress-strain graph' },
        { t: 'Heat & Thermodynamics', s: 'Gas laws, isothermal, adiabatic, thermodynamic cycles' },
        { t: 'Thermal Properties', s: 'Specific heat, latent heat, calorimetry experiments' },
      ]},
      { section: 'Waves, Optics & Sound', color: '#a78bfa', items: [
        { t: 'Progressive & Stationary Waves', s: 'Nodes, antinodes, standing waves in pipes and strings' },
        { t: 'Sound & Acoustics', s: 'Speed, resonance, frequency, decibels, Doppler' },
        { t: 'Geometrical Optics', s: 'Mirrors, lenses, dispersion, optical instruments' },
        { t: 'Physical Optics', s: 'Young\'s double slit, diffraction grating, thin films' },
      ]},
      { section: 'Electricity & Electronics', color: '#4f8ef7', items: [
        { t: 'Electric Fields & Capacitors', s: 'Field lines, potential, energy stored in capacitors' },
        { t: 'DC Circuits', s: 'EMF, internal resistance, Wheatstone bridge, potentiometer' },
        { t: 'Electromagnetism', s: 'Fleming\'s rules, transformers, eddy currents' },
        { t: 'Semiconductors', s: 'Doping, p-n junction, diode characteristics, rectification' },
      ]},
      { section: 'Atomic & Nuclear Physics', color: '#ec4899', items: [
        { t: 'Nuclear Structure', s: 'Protons, neutrons, isotopes, nuclear binding energy' },
        { t: 'Radioactivity', s: 'Alpha, beta, gamma radiation, half-life calculations, safety' },
        { t: 'Nuclear Reactions', s: 'Fission, fusion, mass defect, energy released (E=mc²)' },
      ]},
    ],
  },

  'Chemistry': {
    emoji: '⚗️', color: '#00c98a', sub: 'Physical, Organic & Inorganic Chemistry',
    jamb: [
      { section: 'Physical Chemistry', color: '#00c98a', items: [
        { t: 'Atomic Structure', s: 'Protons, neutrons, electrons, electron configuration, periodicity' },
        { t: 'Chemical Bonding', s: 'Ionic, covalent, metallic bonding; VSEPR, shapes of molecules' },
        { t: 'Kinetic Theory & Gas Laws', s: 'Boyle\'s, Charles\', Dalton\'s, Graham\'s laws' },
        { t: 'Acids, Bases & Salts', s: 'Bronsted-Lowry theory, pH, indicators, titration calculations' },
        { t: 'Redox Reactions', s: 'Oxidation states, balancing redox, electrochemistry' },
        { t: 'Mole Concept & Stoichiometry', s: 'n=m/M, Avogadro\'s number, percentage yield, purity' },
        { t: 'Equilibrium', s: "Le Chatelier's principle, Kc expression, Haber and Contact processes" },
        { t: 'Energetics', s: 'Exothermic, endothermic, Hess\'s law, bond energies' },
        { t: 'Kinetics', s: 'Rate of reaction, factors affecting rate, activation energy, catalysts' },
        { t: 'Electrochemistry', s: 'Electrolysis, Faraday\'s laws, standard electrode potentials' },
      ]},
      { section: 'Inorganic Chemistry', color: '#4f8ef7', items: [
        { t: 'Periodicity & Periodic Table', s: 'Trends in atomic radius, ionisation energy, electronegativity' },
        { t: 's-Block Elements', s: 'Group I & II: reactions with water, air, and acids' },
        { t: 'p-Block Elements', s: 'Nitrogen, oxygen, halogens, Group IV chemistry' },
        { t: 'Transition Metals', s: 'Variable oxidation states, complex ions, coloured compounds' },
        { t: 'Extraction of Metals', s: 'Iron, aluminium, copper — blast furnace, electrolysis, reduction' },
        { t: 'Water & Pollution', s: 'Hard water, purification, treatment, industrial effluents' },
      ]},
      { section: 'Organic Chemistry', color: '#f59e0b', items: [
        { t: 'Introduction & Nomenclature', s: 'Homologous series, IUPAC naming, isomerism' },
        { t: 'Alkanes', s: 'Properties, combustion, substitution, cracking' },
        { t: 'Alkenes & Alkynes', s: 'Addition reactions, polymerisation, test for unsaturation' },
        { t: 'Benzene & Arenes', s: 'Aromatic stability, electrophilic substitution' },
        { t: 'Halogenoalkanes', s: 'Nucleophilic substitution, elimination reactions' },
        { t: 'Alcohols & Ethers', s: 'Oxidation of alcohols, esterification, dehydration' },
        { t: 'Carbonyl Compounds', s: 'Aldehydes, ketones, carboxylic acids, derivatives' },
        { t: 'Amines & Amino Acids', s: 'Basic properties, proteins, peptide bond' },
        { t: 'Polymers', s: 'Addition and condensation polymerisation, natural polymers' },
      ]},
    ],
    waec: [
      { section: 'Physical Chemistry', color: '#00c98a', items: [
        { t: 'Atomic Theory & Bonding', s: 'Quantum numbers, orbital shapes, bond angles, polarity' },
        { t: 'Energetics & Thermochemistry', s: 'Standard enthalpy of formation, combustion, Hess\'s law' },
        { t: 'Chemical Equilibrium', s: 'Kc, Kp, factors affecting equilibrium, buffer solutions' },
        { t: 'Electrochemistry', s: 'Standard electrode potentials, cell EMF, electrolysis products' },
        { t: 'Mole Calculations', s: 'Concentration, titration, stoichiometry, gas volumes' },
      ]},
      { section: 'Inorganic Chemistry', color: '#4f8ef7', items: [
        { t: 'Periodic Table Trends', s: 'Across periods and down groups — ionisation energy, electron affinity' },
        { t: 'Specific Reactions of Metals', s: 'Na, K, Mg, Ca, Al, Fe, Cu reactions with reagents' },
        { t: 'Non-metals & Their Compounds', s: 'O₂, H₂O, CO₂, SO₂, N₂, HCl, HNO₃, H₂SO₄' },
        { t: 'Industrial Processes', s: 'Haber, Contact, Solvay, Frasch, electrolytic processes' },
      ]},
      { section: 'Organic Chemistry', color: '#f59e0b', items: [
        { t: 'Hydrocarbons', s: 'Reactions and preparations of alkanes, alkenes, alkynes, benzene' },
        { t: 'Functional Group Chemistry', s: 'Halides, alcohols, aldehydes, ketones, acids, esters, amides' },
        { t: 'Isomerism', s: 'Structural, geometric, optical isomerism with examples' },
        { t: 'Polymers & Biomolecules', s: 'Plastics, rubber, carbohydrates, proteins, fats' },
        { t: 'Reaction Mechanisms', s: 'Electrophilic addition, nucleophilic substitution, free radical' },
      ]},
    ],
  },

  'Biology': {
    emoji: '🧬', color: '#a78bfa', sub: 'Cell Biology, Genetics, Ecology & Evolution',
    jamb: [
      { section: 'Cell Biology & Organisation', color: '#a78bfa', items: [
        { t: 'The Cell', s: 'Prokaryotic vs eukaryotic, cell organelles and their functions' },
        { t: 'Cell Division', s: 'Mitosis, meiosis, stages, significance of each type' },
        { t: 'Organisation of Life', s: 'Cell → tissue → organ → system → organism' },
        { t: 'Diffusion, Osmosis & Active Transport', s: 'Concentration gradient, turgor, plasmolysis' },
      ]},
      { section: 'Nutrition & Digestion', color: '#00c98a', items: [
        { t: 'Nutrition in Plants', s: 'Photosynthesis (light & dark reactions), mineral nutrition' },
        { t: 'Nutrition in Animals', s: 'Classes of food, balanced diet, deficiency diseases' },
        { t: 'Digestive System (Man)', s: 'Teeth, alimentary canal, enzymes, absorption, egestion' },
        { t: 'Digestion in Ruminants', s: 'Four-chambered stomach, cellulase, cud chewing' },
      ]},
      { section: 'Transport & Respiration', color: '#f59e0b', items: [
        { t: 'Transport in Plants', s: 'Xylem, phloem, transpiration, translocation' },
        { t: 'Transport in Mammals', s: 'Heart, blood vessels, blood components, ABO blood groups' },
        { t: 'Respiration', s: 'Aerobic and anaerobic, glycolysis, Krebs cycle, ATP production' },
        { t: 'Respiratory System', s: 'Breathing mechanism, gaseous exchange, lung diseases' },
      ]},
      { section: 'Genetics & Evolution', color: '#f43f5e', items: [
        { t: 'Heredity & Variation', s: 'Mendel\'s laws, monohybrid, dihybrid crosses, Punnett square' },
        { t: 'Chromosomes & DNA', s: 'DNA structure, protein synthesis, transcription, translation' },
        { t: 'Mutation & Inheritance Patterns', s: 'Sex-linkage, co-dominance, blood groups, sickle cell' },
        { t: 'Evolution', s: 'Darwin, natural selection, evidence, adaptation, speciation' },
        { t: 'Variation', s: 'Continuous and discontinuous variation, causes, significance' },
      ]},
      { section: 'Ecology & Environment', color: '#4f8ef7', items: [
        { t: 'Ecological Concepts', s: 'Habitat, niche, population, community, ecosystem' },
        { t: 'Energy Flow & Food Webs', s: 'Producers, consumers, food chains, energy pyramids' },
        { t: 'Nutrient Cycles', s: 'Carbon, nitrogen and water cycles' },
        { t: 'Population Ecology', s: 'Growth curves, limiting factors, density-dependent factors' },
        { t: 'Environmental Pollution', s: 'Water, air, land pollution — causes, effects, control' },
        { t: 'Conservation', s: 'Wildlife, game reserves, afforestation, sustainable use' },
      ]},
      { section: 'Reproduction & Development', color: '#ec4899', items: [
        { t: 'Reproduction in Plants', s: 'Flowers, pollination, fertilisation, seed dispersal' },
        { t: 'Reproduction in Animals', s: 'Asexual, sexual, fertilisation, development stages' },
        { t: 'Human Reproductive System', s: 'Male and female anatomy, menstrual cycle, pregnancy' },
        { t: 'Growth & Development', s: 'Metamorphosis, germination conditions, growth curves' },
      ]},
    ],
    waec: [
      { section: 'Cell Biology', color: '#a78bfa', items: [
        { t: 'Cell Structure & Function', s: 'Ultra-structure, comparison of plant and animal cells' },
        { t: 'Cell Physiology', s: 'Active transport, phagocytosis, pinocytosis, endocytosis' },
        { t: 'Cell Division & Growth', s: 'Stages of mitosis and meiosis, significance for organisms' },
      ]},
      { section: 'Nutrition & Feeding', color: '#00c98a', items: [
        { t: 'Photosynthesis', s: 'Light reactions, Calvin cycle, C3 and C4 plants, limiting factors' },
        { t: 'Animal Nutrition', s: 'Heterotrophic nutrition types, adaptations in different animals' },
        { t: 'Alimentary Canal', s: 'Structure and function, role of enzymes at each region' },
      ]},
      { section: 'Transport & Excretion', color: '#f59e0b', items: [
        { t: 'Transport in Plants', s: 'Water potential, osmotic gradient, guard cells, stomata' },
        { t: 'Mammalian Circulatory System', s: 'Cardiac cycle, ECG, blood pressure, clotting' },
        { t: 'Excretion', s: 'Kidney (nephron), liver functions, nitrogenous waste products' },
      ]},
      { section: 'Genetics & Evolution', color: '#f43f5e', items: [
        { t: 'Mendelian Genetics', s: 'Law of segregation, law of independent assortment, ratios' },
        { t: 'Molecular Genetics', s: 'DNA replication, gene expression, genetic code, mutations' },
        { t: 'Evolution & Natural Selection', s: 'Hardy-Weinberg equilibrium, gene pools, selection pressures' },
      ]},
      { section: 'Ecology', color: '#4f8ef7', items: [
        { t: 'Ecosystem Dynamics', s: 'Succession, climax community, carrying capacity' },
        { t: 'Biogeochemical Cycles', s: 'Carbon, nitrogen, phosphorus, sulphur cycles' },
        { t: 'Conservation Biology', s: 'Biodiversity, endangered species, sustainable development' },
      ]},
    ],
  },

  'English Language': {
    emoji: '📖', color: '#f43f5e', sub: 'Reading, Writing, Grammar & Oral English',
    jamb: [
      { section: 'Comprehension & Summary', color: '#f43f5e', items: [
        { t: 'Comprehension Passages', s: 'Identifying main idea, supporting details, inference and tone' },
        { t: 'Summary Writing', s: 'Identifying key points, paraphrasing, concise expression' },
        { t: 'Understanding Passages in Context', s: 'Contextual meaning of words, attitude of the writer' },
      ]},
      { section: 'Grammar & Usage', color: '#4f8ef7', items: [
        { t: 'Parts of Speech', s: 'Noun, pronoun, verb, adjective, adverb, preposition, conjunction, interjection' },
        { t: 'Tense & Aspect', s: 'Simple, continuous, perfect, future tenses — correct usage' },
        { t: 'Sentence Structure', s: 'Simple, compound, complex, compound-complex sentences' },
        { t: 'Concord (Agreement)', s: 'Subject-verb agreement, pronoun-antecedent agreement' },
        { t: 'Active & Passive Voice', s: 'Transformation of active to passive and vice versa' },
        { t: 'Direct & Indirect Speech', s: 'Rules for tense changes, pronoun and adverb changes' },
        { t: 'Question Tags & Short Answers', s: 'Forming correct question tags, response drills' },
        { t: 'Errors & Corrections', s: 'Common errors: double negatives, wrong prepositions, malapropisms' },
      ]},
      { section: 'Vocabulary', color: '#00c98a', items: [
        { t: 'Word Classes & Formation', s: 'Prefixes, suffixes, root words, word families' },
        { t: 'Synonyms & Antonyms', s: 'Identifying nearest meaning, contrast words in context' },
        { t: 'Idioms & Proverbs', s: 'Common Nigerian and international idioms, figurative meaning' },
        { t: 'Lexis in Context', s: 'Choosing the correct word to fill a gap in context' },
        { t: 'Phrasal Verbs', s: 'Common phrasal verbs and their meanings in context' },
      ]},
      { section: 'Oral English', color: '#f59e0b', items: [
        { t: 'Vowels & Consonants', s: 'Pure vowels, diphthongs, consonant clusters, phonetic symbols' },
        { t: 'Stress & Rhythm', s: 'Word stress, sentence stress, weak and strong forms' },
        { t: 'Intonation', s: 'Falling, rising, fall-rise patterns and their communicative functions' },
        { t: 'Rhyme & Alliteration', s: 'Identifying rhyming words, alliterative patterns in literature' },
        { t: 'Figures of Speech', s: 'Simile, metaphor, personification, irony, hyperbole, oxymoron' },
      ]},
    ],
    waec: [
      { section: 'Comprehension', color: '#f43f5e', items: [
        { t: 'Expository Texts', s: 'Factual passages, topic sentences, supporting examples' },
        { t: 'Argumentative Texts', s: 'Identifying claims, counter-arguments, logical fallacies' },
        { t: 'Literary Extracts', s: 'Prose, poetry, drama extracts — character, theme, style' },
        { t: 'Vocabulary in Context', s: 'Inferring meaning from context, denotation and connotation' },
      ]},
      { section: 'Summary Writing', color: '#4f8ef7', items: [
        { t: 'Technique of Summary', s: 'Identifying theme, reduction, writing in own words' },
        { t: 'Connected Prose', s: 'Linking summary into fluent, coherent paragraph' },
        { t: 'Lifting & Paraphrasing', s: 'Difference between acceptable paraphrase and unacceptable copying' },
      ]},
      { section: 'Lexis & Structure', color: '#00c98a', items: [
        { t: 'Sentence Completion', s: 'Filling gaps with correct word class and contextual meaning' },
        { t: 'Comprehension Cloze', s: 'Understanding passage flow, collocations, register' },
        { t: 'Register & Style', s: 'Formal, informal, academic, journalistic, literary registers' },
        { t: 'Spelling & Punctuation', s: 'Common misspellings, comma splices, apostrophes, quotation marks' },
      ]},
      { section: 'Oral Texts', color: '#f59e0b', items: [
        { t: 'Dialogue & Conversation', s: 'Turn-taking, topic management, adjacency pairs' },
        { t: 'Speeches & Lectures', s: 'Organised public speaking, argumentation, audience awareness' },
        { t: 'Phonology', s: 'Phonemes, allophones, minimal pairs, connected speech phenomena' },
      ]},
      { section: 'Essay Writing', color: '#ec4899', items: [
        { t: 'Narrative Essays', s: 'Story structure, characterisation, time sequence, dialogue' },
        { t: 'Argumentative Essays', s: 'Thesis, body paragraphs, counterargument, conclusion' },
        { t: 'Expository Essays', s: 'Explaining processes, defining terms, giving examples' },
        { t: 'Descriptive & Informal Letters', s: 'Format, register, opening and closing formulas' },
        { t: 'Formal Letters & Reports', s: 'Official format, report structure, memo writing' },
      ]},
    ],
  },

  'Economics': {
    emoji: '📊', color: '#ec4899', sub: 'Micro & Macroeconomics, Nigerian Economy',
    jamb: [
      { section: 'Fundamentals', color: '#ec4899', items: [
        { t: 'Concept of Economics', s: 'Scarcity, choice, opportunity cost, scale of preference, PPC' },
        { t: 'Economic Systems', s: 'Capitalism, socialism, mixed economy — features and merits' },
        { t: 'Demand & Supply', s: 'Laws of demand/supply, shifts, price determination, elasticity' },
        { t: 'Theory of the Consumer', s: 'Utility, indifference curves, budget line, consumer equilibrium' },
        { t: 'Theory of the Firm', s: 'Production functions, costs (AC, MC, AVC), revenue, profit' },
        { t: 'Market Structures', s: 'Perfect competition, monopoly, oligopoly, monopolistic competition' },
      ]},
      { section: 'Macroeconomics', color: '#4f8ef7', items: [
        { t: 'National Income', s: 'GDP, GNP, NNP — circular flow, measurement methods' },
        { t: 'Money & Banking', s: 'Functions of money, commercial banks, Central Bank of Nigeria' },
        { t: 'Fiscal & Monetary Policy', s: 'Government expenditure, taxation, money supply controls' },
        { t: 'Inflation & Unemployment', s: 'Types, causes, effects, policies to control' },
        { t: 'International Trade', s: 'Comparative advantage, balance of payments, exchange rates' },
      ]},
      { section: 'Nigerian Economy', color: '#00c98a', items: [
        { t: 'Agriculture in Nigeria', s: 'Food and cash crops, problems, government policies' },
        { t: 'Industry & Petroleum', s: 'Industrialisation, oil sector, import substitution' },
        { t: 'Population & Labour', s: 'Population growth, labour market, unemployment types' },
        { t: 'Economic Development', s: 'Characteristics of LDCs, development planning in Nigeria' },
      ]},
    ],
    waec: [
      { section: 'Microeconomics', color: '#ec4899', items: [
        { t: 'Consumer Theory', s: 'Indifference analysis, revealed preference, consumer surplus' },
        { t: 'Production & Cost Theory', s: 'Returns to scale, isoquants, long-run average cost curves' },
        { t: 'Market Theory', s: 'Price and output determination in different market structures' },
        { t: 'Factor Markets', s: 'Demand and supply of labour, wage determination, rent, profit' },
      ]},
      { section: 'Macroeconomics', color: '#4f8ef7', items: [
        { t: 'National Income Accounting', s: 'Circular flow, multiplier, accelerator principle' },
        { t: 'Monetary Economics', s: 'Creation of credit, monetary transmission, quantitative easing' },
        { t: 'Fiscal Policy', s: 'Budget deficit, public debt, Ricardian equivalence' },
        { t: 'International Economics', s: 'Terms of trade, protectionism, WTO, ECOWAS, IMF' },
      ]},
    ],
  },

  'Government': {
    emoji: '🏛', color: '#06b6d4', sub: 'Political Science, Nigerian Government & International Relations',
    jamb: [
      { section: 'Fundamental Concepts', color: '#06b6d4', items: [
        { t: 'Government & State', s: 'Meaning, elements of a state, sovereignty, legitimacy' },
        { t: 'Constitutions', s: 'Types — written/unwritten, rigid/flexible, federal/unitary' },
        { t: 'Separation of Powers', s: 'Doctrine, checks and balances, Montesquieu' },
        { t: 'Branches of Government', s: 'Legislature, executive, judiciary — functions and structure' },
        { t: 'Democracy & Other Systems', s: 'Representation, parties, universal suffrage, authoritarianism' },
        { t: 'Pressure Groups & Political Parties', s: 'Functions, financing, differences from pressure groups' },
        { t: 'Electoral Systems', s: 'First-past-the-post, proportional representation, INEC' },
      ]},
      { section: 'Nigerian Government', color: '#4f8ef7', items: [
        { t: 'Pre-colonial Political Systems', s: 'Hausa-Fulani, Yoruba, Igbo and other political structures' },
        { t: 'Colonial Rule & Nationalism', s: 'British administration, Richards, MacPherson, nationalist movements' },
        { t: 'Nigeria\'s Constitutions', s: '1922, 1946, 1951, 1954, 1960, 1963, 1979, 1999 constitutions' },
        { t: 'Military Interventions', s: 'Coups 1966, 1983, 1985, 1993, 1999 — causes and effects' },
        { t: 'Federalism in Nigeria', s: 'Revenue sharing, state creation, intergovernmental relations' },
        { t: 'Local Government', s: 'Functions, 1976 reform, challenges, grassroots democracy' },
      ]},
      { section: 'International Relations', color: '#a78bfa', items: [
        { t: 'Foreign Policy', s: 'Nigeria\'s foreign policy objectives, Afrocentrism, non-alignment' },
        { t: 'International Organisations', s: 'UN, AU, ECOWAS, Commonwealth, NAM — functions and organs' },
        { t: 'Diplomacy & International Law', s: 'Treaties, conventions, ICJ, diplomatic immunity' },
      ]},
    ],
    waec: [
      { section: 'Political Concepts', color: '#06b6d4', items: [
        { t: 'Political Power & Authority', s: 'Sources of power, legitimacy, Max Weber\'s authority types' },
        { t: 'Political Culture & Socialisation', s: 'Agents, civic culture, political participation' },
        { t: 'Federalism', s: 'Arguments for and against, fiscal federalism, devolution' },
        { t: 'Human Rights', s: 'Bills of rights, UDHR, enforcement mechanisms, derogation' },
      ]},
      { section: 'West African Politics', color: '#4f8ef7', items: [
        { t: 'Nationalism in West Africa', s: 'Causes, key nationalists, pan-Africanism, independence movement' },
        { t: 'ECOWAS', s: 'Aims, organs, ECOMOG, free movement, economic integration' },
        { t: 'Challenges of Governance', s: 'Corruption, ethnicity, military rule, development challenges' },
      ]},
    ],
  },

  'Geography': {
    emoji: '🌍', color: '#84cc16', sub: 'Physical, Human & Regional Geography',
    jamb: [
      { section: 'Physical Geography', color: '#84cc16', items: [
        { t: 'The Earth\'s Structure', s: 'Crust, mantle, outer and inner core; tectonic plates' },
        { t: 'Rocks & Weathering', s: 'Igneous, sedimentary, metamorphic; physical, chemical, biological weathering' },
        { t: 'Landforms — Fluvial', s: 'River erosion, transportation, deposition; V-valleys, meanders, deltas' },
        { t: 'Landforms — Coastal', s: 'Waves, erosion features (cliffs, stacks), deposition (beaches, spits)' },
        { t: 'Landforms — Arid & Glacial', s: 'Deserts, dunes, inselbergs; glacial erosion and deposition features' },
        { t: 'Atmosphere & Climate', s: 'Composition, layers, temperature and pressure belts, world climates' },
        { t: 'Soils', s: 'Formation, profile, types, soil erosion and conservation' },
        { t: 'Natural Vegetation', s: 'Biomes and their characteristics — equatorial, savanna, desert, temperate' },
      ]},
      { section: 'Human Geography', color: '#4f8ef7', items: [
        { t: 'Population', s: 'Growth, distribution, density, demographic transition model' },
        { t: 'Settlement', s: 'Rural and urban settlements, urbanisation, problems of cities' },
        { t: 'Agriculture', s: 'Types of farming, land use, agricultural problems in Nigeria' },
        { t: 'Industry & Mining', s: 'Location factors, types, major industrial regions, mineral resources' },
        { t: 'Transport & Trade', s: 'Modes, routes, trade blocs, Nigeria\'s trade' },
      ]},
      { section: 'Regional Geography', color: '#f59e0b', items: [
        { t: 'Regional Geography of Nigeria', s: 'Relief, drainage, climate zones, vegetation belts, economic activities' },
        { t: 'West Africa', s: 'Regions, rivers (Niger, Volta), resources, trade patterns' },
        { t: 'Africa', s: 'Physical features, major rivers, climates, economic regions' },
        { t: 'World Geography', s: 'Selected world regions — Asia, Europe, Americas — major features' },
      ]},
    ],
    waec: [
      { section: 'Physical Geography', color: '#84cc16', items: [
        { t: 'Geomorphology', s: 'Plate tectonics, folding, faulting, volcanic landforms' },
        { t: 'Hydrology', s: 'River regimes, drainage patterns, water table, hydrological cycle' },
        { t: 'Climatology', s: 'Synoptic charts, weather forecasting, microclimates, climate change' },
        { t: 'Biogeography & Pedology', s: 'Soil formation factors, soil profiles, vegetation succession' },
      ]},
      { section: 'Human & Economic Geography', color: '#4f8ef7', items: [
        { t: 'Population Geography', s: 'Overpopulation, underpopulation, migration, refugees, population policy' },
        { t: 'Agricultural Geography', s: 'Green revolution, land reform, food security, cash crop economies' },
        { t: 'Industrial Geography', s: 'Industrial inertia, footloose industries, industrial decline and growth' },
        { t: 'Environmental Issues', s: 'Desertification, deforestation, climate change, sustainable development' },
      ]},
    ],
  },

};

/* ══════════════════════════════════════════════════
   STATE & STORAGE
══════════════════════════════════════════════════ */
const STORAGE_KEY = 'es_syllabus_v2';
let progress = {}; // { 'Math_jamb_0_1': true, ... }
let curSubject = null;
let curTab = 'jamb';
let aiCurrentItem = null; // { subj, tab, sectionIdx, itemIdx }

function loadProgress() {
  try { progress = JSON.parse(localStorage.getItem(STORAGE_KEY)||'{}'); } catch(e) { progress = {}; }
}
function saveProgress() {
  try { localStorage.setItem(STORAGE_KEY, JSON.stringify(progress)); } catch(e) {}
}
function itemKey(subj, tab, si, ii) {
  return `${subj}__${tab}__${si}__${ii}`;
}
function isDone(subj, tab, si, ii) {
  return !!progress[itemKey(subj, tab, si, ii)];
}
function setDone(subj, tab, si, ii, val) {
  const k = itemKey(subj, tab, si, ii);
  if (val) progress[k] = true;
  else delete progress[k];
  saveProgress();
}

/* ══════════════════════════════════════════════════
   COMPUTE PROGRESS
══════════════════════════════════════════════════ */
function getSubjProgress(subj) {
  const data = SYLLABUS[subj];
  if(!data) return { done: 0, total: 0, pct: 0 };
  let done = 0, total = 0;
  ['jamb','waec'].forEach(tab => {
    (data[tab]||[]).forEach((sec, si) => {
      sec.items.forEach((_, ii) => {
        total++;
        if(isDone(subj, tab, si, ii)) done++;
      });
    });
  });
  return { done, total, pct: total ? Math.round(done/total*100) : 0 };
}

function getTabProgress(subj, tab) {
  const data = SYLLABUS[subj];
  if(!data) return { done: 0, total: 0, pct: 0 };
  let done = 0, total = 0;
  (data[tab]||[]).forEach((sec, si) => {
    sec.items.forEach((_, ii) => {
      total++;
      if(isDone(subj, tab, si, ii)) done++;
    });
  });
  return { done, total, pct: total ? Math.round(done/total*100) : 0 };
}

/* ══════════════════════════════════════════════════
   BUILD SUBJECT GRID
══════════════════════════════════════════════════ */
function buildSubjectsGrid() {
  const grid = document.getElementById('subjectsGrid');
  grid.innerHTML = '';
  Object.entries(SYLLABUS).forEach(([name, info]) => {
    const { pct, done, total } = getSubjProgress(name);
    const card = document.createElement('div');
    card.className = 'subj-card' + (done > 0 ? ' has-progress' : '') + (curSubject === name ? ' active' : '');
    card.style.setProperty('--card-color', info.color);
    card.dataset.name = name;
    card.innerHTML = `
      <span class="sc-emoji">${info.emoji}</span>
      <div class="sc-name">${name}</div>
      <div class="sc-count">${total} topics</div>
      <div class="sc-progress">
        <div class="sc-progress-fill" style="width:${pct}%"></div>
      </div>
      <div class="sc-tick"><i class="fa fa-check"></i></div>`;
    card.addEventListener('click', () => selectSubject(name));
    grid.appendChild(card);
  });
}

/* ══════════════════════════════════════════════════
   SELECT SUBJECT
══════════════════════════════════════════════════ */
function selectSubject(name) {
  if(!SYLLABUS[name]) return;
  curSubject = name;
  curTab = 'jamb';
  document.querySelectorAll('.subj-card').forEach(c => {
    c.classList.toggle('active', c.dataset.name === name);
  });
  renderPanel();
  const panel = document.getElementById('syllabusPanel');
  panel.classList.add('show');
  setTimeout(() => {
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 80);
}

/* ══════════════════════════════════════════════════
   RENDER PANEL
══════════════════════════════════════════════════ */
function renderPanel() {
  const info = SYLLABUS[curSubject];
  if (!info) return;
  const { pct, done, total } = getSubjProgress(curSubject);

  // Update panel header
  const circumference = 2 * Math.PI * 20; // r=20
  const offset = circumference * (1 - pct/100);
  document.getElementById('panelHead').innerHTML = `
    <div class="ph-icon" style="background:${info.color}18;border:1px solid ${info.color}25">
      ${info.emoji}
    </div>
    <div class="ph-info">
      <div class="ph-title" style="color:${info.color}">${curSubject}</div>
      <div class="ph-sub">${info.sub}</div>
    </div>
    <div class="ph-right">
      <div class="ph-progress-ring">
        <svg viewBox="0 0 50 50">
          <circle class="ring-bg" cx="25" cy="25" r="20"/>
          <circle class="ring-fill" cx="25" cy="25" r="20"
            stroke="${info.color}"
            stroke-dasharray="${circumference}"
            stroke-dashoffset="${offset}"/>
        </svg>
        <div class="ph-pct">${pct}%</div>
      </div>
      <div class="ph-actions">
        <a href="videos/lessons.php?subject_id=" class="ph-btn lessons">
          <i class="fa fa-play" style="font-size:10px"></i> Lessons
        </a>
        <a href="exams/practice_test.php" class="ph-btn">
          <i class="fa fa-clipboard-check" style="font-size:10px"></i> Test
        </a>
      </div>
    </div>`;

  // Update tab active states
  document.querySelectorAll('.exam-tab').forEach(t => {
    t.classList.toggle('active', t.dataset.tab === curTab);
  });
  document.querySelectorAll('.tab-body').forEach(b => {
    b.classList.toggle('show', b.id === 'tab-'+curTab);
  });

  // Render both tabs
  renderTab('jamb');
  renderTab('waec');
}

function renderTab(tab) {
  const info = SYLLABUS[curSubject];
  const { done, total, pct } = getTabProgress(curSubject, tab);

  // Stats bar
  document.getElementById('stats-'+tab).innerHTML = `
    <div class="tab-prog-bar">
      <div class="tab-prog-fill" style="width:${pct}%"></div>
    </div>
    <span class="tab-prog-lbl">${done}/${total} topics read</span>
    <button class="tab-mark-all" onclick="markAllTab('${tab}')">
      Mark all
    </button>`;

  // Topic sections
  const container = document.getElementById('topics-'+tab);
  container.innerHTML = '';
  (info[tab]||[]).forEach((section, si) => {
    const secDiv = document.createElement('div');
    secDiv.className = 'topic-section';

    const doneInSec = section.items.filter((_, ii) => isDone(curSubject, tab, si, ii)).length;
    secDiv.innerHTML = `
      <div class="ts-header" onclick="toggleSection(this)">
        <div class="ts-dot" style="background:${section.color}"></div>
        <span class="ts-title">${section.section}</span>
        <span class="ts-count">${doneInSec}/${section.items.length}</span>
        <i class="fa fa-chevron-right ts-chevron open"></i>
      </div>
      <div class="ts-items" id="sec-${tab}-${si}"></div>`;

    const itemsDiv = secDiv.querySelector('.ts-items');
    section.items.forEach((item, ii) => {
      const done = isDone(curSubject, tab, si, ii);
      const row = document.createElement('div');
      row.className = 's-item' + (done ? ' done' : '');
      row.id = `item-${tab}-${si}-${ii}`;
      row.innerHTML = `
        <div class="s-check" onclick="toggleItem('${tab}',${si},${ii})">
          <i class="fa fa-check"></i>
        </div>
        <div class="s-content">
          <div class="s-text">${esc(item.t)}</div>
          ${item.s ? `<div class="s-sub">${esc(item.s)}</div>` : ''}
        </div>
        <button class="s-ai" onclick="openAI('${tab}',${si},${ii})">
          <i class="fa fa-robot" style="font-size:10px"></i> Explain
        </button>`;
      itemsDiv.appendChild(row);
    });
    container.appendChild(secDiv);
  });
}

/* ══════════════════════════════════════════════════
   TAB SWITCHING
══════════════════════════════════════════════════ */
function switchTab(tab) {
  curTab = tab;
  document.querySelectorAll('.exam-tab').forEach(t => {
    t.classList.toggle('active', t.dataset.tab === tab);
  });
  document.querySelectorAll('.tab-body').forEach(b => {
    b.classList.toggle('show', b.id === 'tab-'+tab);
  });
}

/* ══════════════════════════════════════════════════
   TOGGLE SECTION COLLAPSE
══════════════════════════════════════════════════ */
function toggleSection(header) {
  const chevron = header.querySelector('.ts-chevron');
  const sibling = header.nextElementSibling;
  const isOpen = chevron.classList.contains('open');
  chevron.classList.toggle('open', !isOpen);
  sibling.style.display = isOpen ? 'none' : '';
}

/* ══════════════════════════════════════════════════
   CHECK / UNCHECK ITEM
══════════════════════════════════════════════════ */
function toggleItem(tab, si, ii) {
  const cur = isDone(curSubject, tab, si, ii);
  setDone(curSubject, tab, si, ii, !cur);
  const row = document.getElementById(`item-${tab}-${si}-${ii}`);
  if(row) row.classList.toggle('done', !cur);
  updateSectionCount(tab, si);
  updateTabStats(tab);
  updateSubjectCard();
  updatePanelRing();
}

function markAllTab(tab) {
  const info = SYLLABUS[curSubject];
  (info[tab]||[]).forEach((sec, si) => {
    sec.items.forEach((_, ii) => {
      setDone(curSubject, tab, si, ii, true);
      const row = document.getElementById(`item-${tab}-${si}-${ii}`);
      if(row) row.classList.add('done');
    });
    updateSectionCount(tab, si);
  });
  updateTabStats(tab);
  updateSubjectCard();
  updatePanelRing();
  toast('✅ All topics marked as read!', 'ok');
}

function updateSectionCount(tab, si) {
  const info = SYLLABUS[curSubject];
  const sec = info[tab]?.[si];
  if(!sec) return;
  const doneInSec = sec.items.filter((_, ii) => isDone(curSubject, tab, si, ii)).length;
  const header = document.querySelector(`#sec-${tab}-${si}`)?.previousElementSibling;
  if(header) {
    const cnt = header.querySelector('.ts-count');
    if(cnt) cnt.textContent = `${doneInSec}/${sec.items.length}`;
  }
}

function updateTabStats(tab) {
  const { done, total, pct } = getTabProgress(curSubject, tab);
  const fill = document.querySelector(`#stats-${tab} .tab-prog-fill`);
  const lbl  = document.querySelector(`#stats-${tab} .tab-prog-lbl`);
  if(fill) fill.style.width = pct+'%';
  if(lbl)  lbl.textContent = `${done}/${total} topics read`;
}

function updateSubjectCard() {
  const { pct, done } = getSubjProgress(curSubject);
  const card = document.querySelector(`.subj-card[data-name="${curSubject}"]`);
  if(card) {
    const fill = card.querySelector('.sc-progress-fill');
    const tick = card.querySelector('.sc-tick');
    if(fill) fill.style.width = pct+'%';
    card.classList.toggle('has-progress', done > 0);
  }
}

function updatePanelRing() {
  const { pct } = getSubjProgress(curSubject);
  const info = SYLLABUS[curSubject];
  const circumference = 2 * Math.PI * 20;
  const offset = circumference * (1 - pct/100);
  const ring = document.querySelector('.ring-fill');
  const pctEl = document.querySelector('.ph-pct');
  if(ring) ring.setAttribute('stroke-dashoffset', offset);
  if(pctEl) pctEl.textContent = pct+'%';
}

/* ══════════════════════════════════════════════════
   AI EXPLAIN
══════════════════════════════════════════════════ */
function openAI(tab, si, ii) {
  const info = SYLLABUS[curSubject];
  const item = info[tab]?.[si]?.items?.[ii];
  if(!item) return;
  aiCurrentItem = { subj: curSubject, tab, si, ii };

  document.getElementById('aiTopic').textContent = item.t + (item.s ? ` — ${item.s}` : '');
  document.getElementById('aiLoad').style.display = 'flex';
  document.getElementById('aiResp').style.display = 'none';
  document.getElementById('aiResp').textContent = '';
  document.getElementById('aiOv').classList.add('show');
  document.body.style.overflow = 'hidden';

  // Update mark done button state
  const done = isDone(curSubject, tab, si, ii);
  const markBtn = document.getElementById('aiMarkDone');
  markBtn.className = 'ai-mark-done' + (done ? ' marked' : '');
  markBtn.innerHTML = done
    ? '<i class="fa fa-check-circle"></i> Already Read ✓'
    : '<i class="fa fa-check"></i> Mark as Read';

  // Call Groq
  callAI(curSubject, item.t, item.s || '', tab.toUpperCase());
}

async function callAI(subject, topic, details, examType) {
  const question = `You are an expert ${subject} teacher for Nigerian students preparing for JAMB UTME and WAEC SSCE exams.

Explain this syllabus topic clearly and thoroughly:

Subject: ${subject}
Exam: ${examType}
Topic: ${topic}
${details ? `Key areas: ${details}` : ''}

Your explanation should:
1. WHAT IS IT — Simple, clear definition in 2-3 sentences
2. KEY CONCEPTS — List and explain the 3-5 most important concepts or formulas in this topic
3. WORKED EXAMPLE — One exam-style example with full solution (JAMB or WAEC past question style)
4. COMMON MISTAKES — 2 mistakes students make on this topic and how to avoid them
5. MEMORY TIP — One quick trick to remember the most important part

Use simple language a Nigerian secondary school student will understand. Be thorough but concise.`;

  try {
    const r = await fetch('ai/textbook_ai.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ question })
    });
    const d = await r.json();
    document.getElementById('aiLoad').style.display = 'none';
    document.getElementById('aiResp').style.display = 'block';
    document.getElementById('aiResp').textContent =
      d.answer || ('⚠️ ' + (d.error || 'No response returned. Check ai/textbook_ai.php.'));
  } catch(e) {
    document.getElementById('aiLoad').style.display = 'none';
    document.getElementById('aiResp').style.display = 'block';
    document.getElementById('aiResp').textContent = '⚠️ Network error: ' + e.message;
  }
}

function markDoneFromAI() {
  if(!aiCurrentItem) return;
  const { subj, tab, si, ii } = aiCurrentItem;
  const cur = isDone(subj, tab, si, ii);
  setDone(subj, tab, si, ii, !cur);
  const row = document.getElementById(`item-${tab}-${si}-${ii}`);
  if(row) row.classList.toggle('done', !cur);
  updateSectionCount(tab, si);
  updateTabStats(tab);
  updateSubjectCard();
  updatePanelRing();
  const markBtn = document.getElementById('aiMarkDone');
  markBtn.className = 'ai-mark-done' + (!cur ? ' marked' : '');
  markBtn.innerHTML = !cur
    ? '<i class="fa fa-check-circle"></i> Already Read ✓'
    : '<i class="fa fa-check"></i> Mark as Read';
  toast(!cur ? '✅ Topic marked as read!' : 'Topic unmarked', 'ok');
}

function closeAI() {
  document.getElementById('aiOv').classList.remove('show');
  document.body.style.overflow = '';
}
document.getElementById('aiOv').addEventListener('click', e => {
  if(e.target === document.getElementById('aiOv')) closeAI();
});
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeAI(); });

/* ══════════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════════ */
let toastTimer;
function toast(msg, type = 'ok') {
  const el = document.getElementById('toast');
  el.className = `toast show ${type}`;
  el.innerHTML = `<i class="fa ${type==='ok'?'fa-circle-check':'fa-circle-info'}" style="font-size:13px"></i> ${msg}`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.className = 'toast'; }, 2500);
}

/* ══════════════════════════════════════════════════
   UTILS
══════════════════════════════════════════════════ */
function esc(s) {
  return s ? String(s).replace(/[&<>"']/g, c =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])) : '';
}

/* ══════════════════════════════════════════════════
   BOOT
══════════════════════════════════════════════════ */
loadProgress();
buildSubjectsGrid();
</script>
</body>
</html>
