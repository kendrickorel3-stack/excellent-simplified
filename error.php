<?php
// error.php — Excellent Simplified
// Usage: error.php?code=404  OR  error.php?code=500&msg=Custom+message
// Also works as Apache/Nginx custom error page:
//   ErrorDocument 404 /error.php
//   ErrorDocument 500 /error.php

$code = (int)($_GET['code'] ?? ($_SERVER['REDIRECT_STATUS'] ?? 0));
$customMsg = trim($_GET['msg'] ?? '');

// ── Error definitions ────────────────────────────────────────
$errors = [
    400 => [
        'emoji'   => '🤔',
        'title'   => 'Bad Request',
        'message' => 'Something was off with that request. Check the URL or try again.',
        'color'   => '#f97316',
        'glow'    => 'rgba(249,115,22,0.4)',
    ],
    401 => [
        'emoji'   => '🔐',
        'title'   => 'Login Required',
        'message' => 'You need to be logged in to access that page.',
        'color'   => '#a78bfa',
        'glow'    => 'rgba(167,139,250,0.4)',
        'action'  => ['label' => 'Go to Login', 'href' => 'login.php'],
    ],
    403 => [
        'emoji'   => '🚫',
        'title'   => 'Access Denied',
        'message' => "You don't have permission to view this page. If you think this is a mistake, please contact support.",
        'color'   => '#ef4444',
        'glow'    => 'rgba(239,68,68,0.4)',
    ],
    404 => [
        'emoji'   => '🔭',
        'title'   => 'Page Not Found',
        'message' => "That page doesn't exist or may have been moved. Double-check the URL, or head back home.",
        'color'   => '#60a5fa',
        'glow'    => 'rgba(96,165,250,0.4)',
    ],
    408 => [
        'emoji'   => '⏱️',
        'title'   => 'Request Timeout',
        'message' => 'The server took too long to respond. This might be a temporary issue — please try again.',
        'color'   => '#fbbf24',
        'glow'    => 'rgba(251,191,36,0.4)',
    ],
    429 => [
        'emoji'   => '🐢',
        'title'   => 'Too Many Requests',
        'message' => "You're doing that too fast! Please slow down and try again in a moment.",
        'color'   => '#f97316',
        'glow'    => 'rgba(249,115,22,0.4)',
    ],
    500 => [
        'emoji'   => '⚙️',
        'title'   => 'Server Error',
        'message' => "Something went wrong on our end. We're working on it — please try again shortly.",
        'color'   => '#f87171',
        'glow'    => 'rgba(248,113,113,0.4)',
    ],
    502 => [
        'emoji'   => '🌩️',
        'title'   => 'Bad Gateway',
        'message' => "The server received an invalid response. This is usually temporary — try refreshing the page.",
        'color'   => '#f87171',
        'glow'    => 'rgba(248,113,113,0.4)',
    ],
    503 => [
        'emoji'   => '🛠️',
        'title'   => 'Under Maintenance',
        'message' => "Excellent Simplified is temporarily down for maintenance. We'll be back very soon — thank you for your patience!",
        'color'   => '#34d399',
        'glow'    => 'rgba(52,211,153,0.4)',
    ],
];

// Fallback for unknown codes
$default = [
    'emoji'   => '😕',
    'title'   => 'Something Went Wrong',
    'message' => "An unexpected error occurred. Please go back and try again.",
    'color'   => '#a78bfa',
    'glow'    => 'rgba(167,139,250,0.4)',
];

$err     = $errors[$code] ?? $default;
$display = $code > 0 ? $code : '';
if ($customMsg) $err['message'] = htmlspecialchars($customMsg);

// Set HTTP status
if ($code >= 400) http_response_code($code);

$pageTitle = $display ? "Error $display — Excellent Simplified" : "Error — Excellent Simplified";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= $pageTitle ?></title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=Cabinet+Grotesk:wght@400;500;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; -webkit-text-size-adjust: 100%; }

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: #04020e;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  overflow-x: hidden;
  color: #fff;
  position: relative;
  padding: 24px 16px;
}

/* ── Blob canvas ── */
#bg-canvas { position: fixed; inset: 0; z-index: 0; pointer-events: none; }

/* ── Grain overlay ── */
body::after {
  content: ''; position: fixed; inset: 0; z-index: 1; pointer-events: none;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
  opacity: 0.35;
}

/* ── Site layer ── */
.site {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 480px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
}

/* ── Logo ── */
.logo-wrap {
  display: flex; flex-direction: column; align-items: center;
  margin-bottom: 30px;
  animation: rise 0.7s cubic-bezier(.22,1,.36,1) both;
}
.logo-icon {
  width: 46px; height: 46px; border-radius: 14px;
  background: linear-gradient(135deg, #7c3aed, #2563eb, #06b6d4);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 0 28px rgba(124,58,237,0.5); margin-bottom: 12px;
  animation: logo-float 4s ease-in-out infinite;
}
@keyframes logo-float { 0%,100%{transform:translateY(0) rotate(0deg);}50%{transform:translateY(-3px) rotate(3deg);} }
.logo-name {
  font-family: 'Clash Display', sans-serif; font-size: 20px; font-weight: 700;
  background: linear-gradient(90deg, #a78bfa, #60a5fa, #34d399);
  -webkit-background-clip: text; background-clip: text; color: transparent;
  letter-spacing: -0.02em; line-height: 1;
}
.logo-sub { font-size: 10px; letter-spacing: 0.22em; color: rgba(255,255,255,0.35); text-transform: uppercase; margin-top: 4px; }

/* ── Card ── */
.card {
  width: 100%;
  background: rgba(255,255,255,0.05);
  backdrop-filter: blur(28px) saturate(180%);
  -webkit-backdrop-filter: blur(28px) saturate(180%);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 26px;
  padding: 40px 32px 36px;
  box-shadow: 0 32px 80px rgba(0,0,0,0.45);
  animation: rise 0.7s 0.1s cubic-bezier(.22,1,.36,1) both;
  position: relative;
  overflow: hidden;
  text-align: center;
}

/* Dynamic top glow strip using PHP color */
.card::before {
  content: '';
  position: absolute; top: 0; left: 15%; right: 15%; height: 1px;
  background: linear-gradient(90deg, transparent, <?= $err['color'] ?>99, transparent);
}

/* Glow orb behind card */
.card::after {
  content: '';
  position: absolute; top: -80px; left: 50%; transform: translateX(-50%);
  width: 260px; height: 160px;
  background: radial-gradient(ellipse, <?= $err['glow'] ?> 0%, transparent 70%);
  pointer-events: none;
  animation: orb-pulse 3s ease-in-out infinite;
}
@keyframes orb-pulse { 0%,100%{opacity:0.6;}50%{opacity:1;} }

@keyframes rise { from{opacity:0;transform:translateY(24px) scale(0.97);}to{opacity:1;transform:translateY(0) scale(1);} }

/* ── Error emoji ── */
.error-emoji {
  font-size: 58px;
  line-height: 1;
  margin-bottom: 18px;
  display: block;
  animation: emoji-bounce 0.6s 0.3s cubic-bezier(.34,1.56,.64,1) both;
  filter: drop-shadow(0 4px 16px <?= $err['glow'] ?>);
}
@keyframes emoji-bounce { from{opacity:0;transform:scale(0.4) translateY(12px);}to{opacity:1;transform:scale(1) translateY(0);} }

/* ── Error code badge ── */
.error-code {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 14px;
  border-radius: 999px;
  background: <?= $err['color'] ?>22;
  border: 1px solid <?= $err['color'] ?>55;
  font-family: 'Cabinet Grotesk', sans-serif;
  font-size: 12px;
  font-weight: 800;
  color: <?= $err['color'] ?>;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin-bottom: 16px;
  animation: rise 0.6s 0.25s cubic-bezier(.22,1,.36,1) both;
}
.error-code-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: <?= $err['color'] ?>;
  animation: dot-pulse 1.5s ease-in-out infinite;
}
@keyframes dot-pulse { 0%,100%{opacity:1;transform:scale(1);}50%{opacity:0.4;transform:scale(0.6);} }

/* ── Error title ── */
.error-title {
  font-family: 'Clash Display', sans-serif;
  font-size: clamp(22px, 5vw, 30px);
  font-weight: 700;
  color: #fff;
  letter-spacing: -0.03em;
  margin-bottom: 12px;
  animation: rise 0.6s 0.3s cubic-bezier(.22,1,.36,1) both;
}

/* ── Error message ── */
.error-message {
  font-size: 15px;
  line-height: 1.7;
  color: rgba(255,255,255,0.5);
  max-width: 360px;
  margin: 0 auto 30px;
  animation: rise 0.6s 0.38s cubic-bezier(.22,1,.36,1) both;
}

/* ── Divider ── */
.divider {
  height: 1px;
  background: rgba(255,255,255,0.07);
  margin: 0 0 26px;
  animation: rise 0.6s 0.42s cubic-bezier(.22,1,.36,1) both;
}

/* ── Action buttons ── */
.actions {
  display: flex;
  gap: 10px;
  justify-content: center;
  flex-wrap: wrap;
  animation: rise 0.6s 0.46s cubic-bezier(.22,1,.36,1) both;
}

.btn-back {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 13px 24px; border-radius: 13px;
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.8);
  font-family: 'Cabinet Grotesk', sans-serif;
  font-size: 14px; font-weight: 700;
  cursor: pointer; text-decoration: none;
  transition: all 0.22s cubic-bezier(.34,1.56,.64,1);
  -webkit-tap-highlight-color: transparent;
}
.btn-back:hover { background: rgba(255,255,255,0.12); transform: translateY(-2px); border-color: rgba(255,255,255,0.2); }
.btn-back:active { transform: scale(0.97); }
.btn-back svg { width: 16px; height: 16px; flex-shrink: 0; }

.btn-home {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 13px 24px; border-radius: 13px;
  background: linear-gradient(135deg, #7c3aed, #2563eb);
  color: #fff;
  font-family: 'Cabinet Grotesk', sans-serif;
  font-size: 14px; font-weight: 700;
  cursor: pointer; text-decoration: none;
  box-shadow: 0 6px 22px rgba(124,58,237,0.4);
  transition: all 0.22s cubic-bezier(.34,1.56,.64,1);
  -webkit-tap-highlight-color: transparent;
  border: none;
}
.btn-home:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 10px 32px rgba(124,58,237,0.6); }
.btn-home:active { transform: scale(0.97); }
.btn-home svg { width: 16px; height: 16px; flex-shrink: 0; }

.btn-action {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 13px 24px; border-radius: 13px;
  color: #fff;
  font-family: 'Cabinet Grotesk', sans-serif;
  font-size: 14px; font-weight: 700;
  cursor: pointer; text-decoration: none;
  transition: all 0.22s cubic-bezier(.34,1.56,.64,1);
  -webkit-tap-highlight-color: transparent;
  border: none;
  background: <?= $err['color'] ?>33;
  border: 1px solid <?= $err['color'] ?>55;
  color: <?= $err['color'] ?>;
}
.btn-action:hover { background: <?= $err['color'] ?>44; transform: translateY(-2px); }
.btn-action:active { transform: scale(0.97); }

/* ── Footer note ── */
.card-footer {
  text-align: center; margin-top: 22px; font-size: 12px;
  color: rgba(255,255,255,0.25);
  animation: rise 0.6s 0.55s cubic-bezier(.22,1,.36,1) both;
}
.card-footer a { color: rgba(167,139,250,0.6); text-decoration: none; transition: color 0.2s; }
.card-footer a:hover { color: #a78bfa; }

/* ── Mobile ── */
@media (max-width: 480px) {
  body { padding: 20px 14px; }
  .card { padding: 30px 18px 28px; }
  .error-emoji { font-size: 48px; }
  .actions { flex-direction: column; }
  .btn-back, .btn-home, .btn-action { width: 100%; justify-content: center; }
  .logo-wrap { margin-bottom: 22px; }
}
</style>
</head>
<body>
<canvas id="bg-canvas"></canvas>

<div class="site">

  <!-- Logo -->
  <div class="logo-wrap">
    <div class="logo-icon">
      <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/>
      </svg>
    </div>
    <div class="logo-name">Excellent</div>
    <div class="logo-sub">Simplified</div>
  </div>

  <!-- Error Card -->
  <div class="card">

    <span class="error-emoji"><?= $err['emoji'] ?></span>

    <?php if ($display): ?>
    <div class="error-code">
      <span class="error-code-dot"></span>
      Error <?= $display ?>
    </div>
    <?php endif; ?>

    <h1 class="error-title"><?= htmlspecialchars($err['title']) ?></h1>

    <p class="error-message"><?= $err['message'] ?></p>

    <div class="divider"></div>

    <div class="actions">

      <!-- Go Back button -->
      <button class="btn-back" onclick="goBack()">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
        </svg>
        Go Back
      </button>

      <!-- Special action button (e.g. Go to Login for 401) -->
      <?php if (!empty($err['action'])): ?>
      <a class="btn-action" href="<?= htmlspecialchars($err['action']['href']) ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
        </svg>
        <?= htmlspecialchars($err['action']['label']) ?>
      </a>
      <?php endif; ?>

      <!-- Go Home button -->
      <a class="btn-home" href="index.php">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
        </svg>
        Go Home
      </a>

    </div>

  </div>

  <div class="card-footer">
    Need help? <a href="https://wa.me/+2349068394581?text=Hello%2C+I+need+help+with+Excellent+Simplified">Contact Support</a>
    &nbsp;·&nbsp;
    <a href="index.php">← Back to home</a>
  </div>

</div>

<script>
/* ── Smart Go Back ── */
function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    window.location.href = 'index.php';
  }
}

/* ── Blob background ── */
(function(){
  const canvas = document.getElementById('bg-canvas');
  const ctx    = canvas.getContext('2d');
  const orbs   = [
    {x:.15,y:.2, r:.38,cx:.0012,cy:.0009,color:[124,58,237], phase:0  },
    {x:.75,y:.15,r:.35,cx:.001, cy:.0013,color:[37,99,235],  phase:1.2},
    {x:.55,y:.7, r:.42,cx:.0014,cy:.001, color:[6,182,212],  phase:2.4},
    {x:.2, y:.75,r:.32,cx:.0009,cy:.0015,color:[16,185,129], phase:3.6},
    {x:.85,y:.6, r:.30,cx:.0015,cy:.0008,color:[245,158,11], phase:4.8},
    {x:.45,y:.35,r:.28,cx:.0011,cy:.0012,color:[236,72,153], phase:1.7},
  ];
  let W,H,t=0;
  function resize(){ W=canvas.width=window.innerWidth; H=canvas.height=window.innerHeight; }
  window.addEventListener('resize',resize); resize();
  function draw(){
    ctx.clearRect(0,0,W,H); ctx.fillStyle='#04020e'; ctx.fillRect(0,0,W,H);
    orbs.forEach(o=>{
      const px=(o.x+Math.sin(t*o.cx*100+o.phase)*0.18)*W;
      const py=(o.y+Math.cos(t*o.cy*100+o.phase*1.3)*0.16)*H;
      const radius=o.r*Math.max(W,H)*0.65;
      const g=ctx.createRadialGradient(px,py,0,px,py,radius);
      const [r,gb,b]=o.color;
      g.addColorStop(0,`rgba(${r},${gb},${b},0.18)`);
      g.addColorStop(0.4,`rgba(${r},${gb},${b},0.07)`);
      g.addColorStop(1,`rgba(${r},${gb},${b},0)`);
      ctx.fillStyle=g; ctx.fillRect(0,0,W,H);
    });
    t++; requestAnimationFrame(draw);
  }
  draw();
})();
</script>
</body>
</html>
