<?php
// login.php — Excellent Simplified
// Handles Firebase token, sets PHP session, redirects server-side

ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('display_errors', '0');
error_reporting(0);
session_start();

// ── If already logged in, go straight to dashboard ──────────
if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    header('Location: dashboard.php');
    exit;
}

// ── Handle POST: Firebase idToken submitted by JS ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $idToken = trim($data['idToken'] ?? '');

    if (!$idToken) {
        echo json_encode(['success' => false, 'error' => 'Missing token']);
        exit;
    }

    // ── Decode Firebase JWT locally ──────────────────────────
    function base64url_decode($input) {
        $rem = strlen($input) % 4;
        if ($rem) $input .= str_repeat('=', 4 - $rem);
        return base64_decode(strtr($input, '-_', '+/'));
    }

    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        echo json_encode(['success' => false, 'error' => 'Invalid token format']);
        exit;
    }

    $payload = json_decode(base64url_decode($parts[1]), true);
    if (!$payload) {
        echo json_encode(['success' => false, 'error' => 'Could not decode token']);
        exit;
    }

    // Check expiry
    if (($payload['exp'] ?? 0) < time()) {
        echo json_encode(['success' => false, 'error' => 'Token expired. Please sign in again.']);
        exit;
    }

    // Check project
    $projectId = 'excellent-simplified';
    if (($payload['aud'] ?? '') !== $projectId || strpos($payload['iss'] ?? '', $projectId) === false) {
        echo json_encode(['success' => false, 'error' => 'Token mismatch']);
        exit;
    }

    // Extract user info
    $uid      = $payload['user_id'] ?? ($payload['sub'] ?? null);
    $email    = $payload['email']   ?? null;
    $fbName   = $payload['name']    ?? null;
    $photoUrl = $payload['picture'] ?? null;
    $name     = $fbName ?: ($email ? explode('@', $email)[0] : 'Student');

    if (!$email && $uid) $email = $uid . '@firebase.user';
    if (!$email) {
        echo json_encode(['success' => false, 'error' => 'No email in token']);
        exit;
    }

    // ── Connect to DB ────────────────────────────────────────
    require_once __DIR__ . '/config/db.php';

    // Look up user by google_id or email
    $user_id = null;
    $hasGoogleId = (bool)($conn->query("SHOW COLUMNS FROM users LIKE 'google_id'")->num_rows ?? 0);

    if ($hasGoogleId && $uid) {
        $s = $conn->prepare("SELECT id FROM users WHERE google_id = ? OR email = ? LIMIT 1");
        $s->bind_param("ss", $uid, $email);
    } else {
        $s = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $s->bind_param("s", $email);
    }
    $s->execute();
    $existing = $s->get_result()->fetch_assoc();
    $s->close();

    $hasLastLogin = (bool)($conn->query("SHOW COLUMNS FROM users LIKE 'last_login'")->num_rows ?? 0);

    if ($existing) {
        // Update existing user
        $user_id = (int)$existing['id'];
        if ($hasGoogleId && $hasLastLogin) {
            $u = $conn->prepare("UPDATE users SET google_id = COALESCE(NULLIF(google_id,''), ?), google_name = COALESCE(NULLIF(?,''), google_name), google_picture = COALESCE(NULLIF(?,''), google_picture), last_login = NOW() WHERE id = ?");
            $u->bind_param("sssi", $uid, $name, $photoUrl, $user_id);
        } elseif ($hasGoogleId) {
            $u = $conn->prepare("UPDATE users SET google_id = COALESCE(NULLIF(google_id,''), ?), google_name = COALESCE(NULLIF(?,''), google_name), google_picture = COALESCE(NULLIF(?,''), google_picture) WHERE id = ?");
            $u->bind_param("sssi", $uid, $name, $photoUrl, $user_id);
        } else {
            $u = $conn->prepare("UPDATE users SET google_name = COALESCE(NULLIF(?,''), google_name), google_picture = COALESCE(NULLIF(?,''), google_picture) WHERE id = ?");
            $u->bind_param("ssi", $name, $photoUrl, $user_id);
        }
        $u->execute();
        $u->close();
    } else {
        // Create new user
        if ($hasGoogleId && $hasLastLogin) {
            $ins = $conn->prepare("INSERT INTO users (username, email, google_id, google_name, google_picture, created_at, last_login) VALUES (?,?,?,?,?,NOW(),NOW())");
            $ins->bind_param("sssss", $name, $email, $uid, $name, $photoUrl);
        } elseif ($hasGoogleId) {
            $ins = $conn->prepare("INSERT INTO users (username, email, google_id, google_name, google_picture, created_at) VALUES (?,?,?,?,?,NOW())");
            $ins->bind_param("sssss", $name, $email, $uid, $name, $photoUrl);
        } else {
            $ins = $conn->prepare("INSERT INTO users (username, email, google_name, google_picture, created_at) VALUES (?,?,?,?,NOW())");
            $ins->bind_param("ssss", $name, $email, $name, $photoUrl);
        }
        if (!$ins->execute()) {
            echo json_encode(['success' => false, 'error' => 'Could not create user: ' . $conn->error]);
            exit;
        }
        $user_id = (int)$ins->insert_id;
        $ins->close();
    }

    // ── Set session ──────────────────────────────────────────
    session_regenerate_id(true); // security: new session ID on login
    $_SESSION['user_id']        = $user_id;
    $_SESSION['google_name']    = $name;
    $_SESSION['google_picture'] = $photoUrl;
    $_SESSION['google_email']   = $email;

    echo json_encode(['success' => true, 'user_id' => $user_id, 'name' => $name]);
    exit;
}
// ── End POST handler ─────────────────────────────────────────
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In — Excellent Simplified | Free JAMB & WAEC Study Platform</title>
<meta name="description" content="Sign in to Excellent Simplified — Nigeria's free JAMB and WAEC study platform. Access video lessons, past questions, AI tutoring, and leaderboards.">
<meta name="robots" content="noindex, follow">
<meta property="og:title" content="Sign In — Excellent Simplified">
<meta property="og:description" content="Sign in to access free JAMB & WAEC study materials, video lessons, and AI tutoring.">
<meta property="og:url" content="https://excellent-simplified-production.up.railway.app/login.php">
<meta property="og:image" content="https://excellent-simplified-production.up.railway.app/assets/og-image.png">
<meta name="theme-color" content="#04020e">
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=Cabinet+Grotesk:wght@400;500;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
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
#bg-canvas { position: fixed; inset: 0; z-index: 0; pointer-events: none; }
body::after {
  content: '';
  position: fixed; inset: 0; z-index: 1; pointer-events: none;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
  opacity: 0.35;
}
.site { position: relative; z-index: 2; width: 100%; max-width: 420px; display: flex; flex-direction: column; align-items: center; }
.logo-wrap { display: flex; flex-direction: column; align-items: center; margin-bottom: 28px; animation: rise 0.7s cubic-bezier(.22,1,.36,1) both; }
.logo-icon {
  width: 52px; height: 52px; border-radius: 16px;
  background: linear-gradient(135deg, #7c3aed, #2563eb, #06b6d4);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 0 32px rgba(124,58,237,0.55); margin-bottom: 14px;
  animation: logo-float 4s ease-in-out infinite;
}
@keyframes logo-float { 0%,100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-4px) rotate(4deg); } }
.logo-name {
  font-family: 'Clash Display', sans-serif; font-size: 22px; font-weight: 700;
  background: linear-gradient(90deg, #a78bfa, #60a5fa, #34d399);
  -webkit-background-clip: text; background-clip: text; color: transparent;
  letter-spacing: -0.02em; line-height: 1;
}
.logo-sub { font-size: 11px; letter-spacing: 0.22em; color: rgba(255,255,255,0.35); text-transform: uppercase; margin-top: 4px; }
.card {
  width: 100%; background: rgba(255,255,255,0.05);
  backdrop-filter: blur(28px) saturate(180%); -webkit-backdrop-filter: blur(28px) saturate(180%);
  border: 1px solid rgba(255,255,255,0.1); border-radius: 26px; padding: 36px 32px 30px;
  box-shadow: 0 32px 80px rgba(0,0,0,0.45); animation: rise 0.7s 0.1s cubic-bezier(.22,1,.36,1) both;
  position: relative; overflow: hidden;
}
.card::before {
  content: ''; position: absolute; top: 0; left: 20%; right: 20%; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(124,58,237,0.6), rgba(96,165,250,0.5), transparent);
}
@keyframes rise { from { opacity: 0; transform: translateY(22px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
.card-head { text-align: center; margin-bottom: 28px; }
.card-head h1 { font-family: 'Clash Display', sans-serif; font-size: 24px; font-weight: 700; color: #fff; letter-spacing: -0.03em; margin-bottom: 7px; }
.card-head p { font-size: 14px; color: rgba(255,255,255,0.45); line-height: 1.5; }
.btn-google {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: 11px;
  padding: 14px 20px; border-radius: 14px; background: #fff; color: #111;
  font-family: 'Cabinet Grotesk', sans-serif; font-size: 15px; font-weight: 700;
  cursor: pointer; border: none; box-shadow: 0 6px 24px rgba(0,0,0,0.25);
  transition: all 0.25s cubic-bezier(.34,1.56,.64,1); position: relative; overflow: hidden;
}
.btn-google:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 12px 36px rgba(0,0,0,0.35); }
.btn-google:active { transform: scale(0.98); }
.btn-google:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.btn-google svg { width: 22px; height: 22px; flex-shrink: 0; }
.divider { display: flex; align-items: center; gap: 12px; margin: 22px 0; color: rgba(255,255,255,0.25); font-size: 12px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.1); }
.email-toggle {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 12px; border-radius: 12px; background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.6);
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 500;
  cursor: pointer; transition: all 0.2s ease;
}
.email-toggle:hover { background: rgba(255,255,255,0.09); color: rgba(255,255,255,0.85); border-color: rgba(255,255,255,0.18); }
.email-toggle svg { width: 16px; height: 16px; }
.toggle-arrow { margin-left: auto; transition: transform 0.25s ease; opacity: 0.5; }
.email-toggle.open .toggle-arrow { transform: rotate(180deg); }
.email-form { overflow: hidden; max-height: 0; transition: max-height 0.35s cubic-bezier(.22,1,.36,1), opacity 0.25s ease; opacity: 0; margin-top: 0; }
.email-form.open { max-height: 300px; opacity: 1; margin-top: 14px; }
.field-wrap { position: relative; margin-bottom: 10px; }
.field-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.3); pointer-events: none; display: flex; }
.field-icon svg { width: 16px; height: 16px; }
.field {
  width: 100%; padding: 12px 14px 12px 40px; border-radius: 11px;
  border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.07);
  color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px;
  outline: none; transition: border-color 0.2s, background 0.2s; -webkit-appearance: none;
}
.field::placeholder { color: rgba(255,255,255,0.3); }
.field:focus { border-color: rgba(124,58,237,0.6); background: rgba(124,58,237,0.08); box-shadow: 0 0 0 3px rgba(124,58,237,0.15); }
.eye-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: rgba(255,255,255,0.3); cursor: pointer; padding: 4px; display: flex; }
.eye-toggle svg { width: 16px; height: 16px; }
.eye-toggle:hover { color: rgba(255,255,255,0.6); }
.btn-email {
  width: 100%; padding: 13px; border-radius: 12px; border: none;
  background: linear-gradient(135deg, #7c3aed, #2563eb); color: #fff;
  font-family: 'Cabinet Grotesk', sans-serif; font-size: 15px; font-weight: 700;
  cursor: pointer; box-shadow: 0 6px 24px rgba(124,58,237,0.4);
  transition: all 0.25s cubic-bezier(.34,1.56,.64,1); margin-top: 4px;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-email:hover { transform: translateY(-2px); box-shadow: 0 12px 36px rgba(124,58,237,0.55); }
.btn-email:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
.forgot-row { text-align: right; margin-bottom: 10px; }
.forgot-row a { font-size: 12px; color: rgba(255,255,255,0.4); text-decoration: none; transition: color 0.2s; }
.forgot-row a:hover { color: rgba(167,139,250,0.85); }
.card-footer { text-align: center; margin-top: 22px; font-size: 13px; color: rgba(255,255,255,0.38); animation: rise 0.7s 0.22s cubic-bezier(.22,1,.36,1) both; }
.card-footer a { color: #a78bfa; text-decoration: none; font-weight: 600; transition: color 0.2s; }
.card-footer a:hover { color: #c4b5fd; }
.error-msg { margin-top: 14px; padding: 10px 14px; border-radius: 10px; background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5; font-size: 13px; line-height: 1.4; display: none; animation: rise 0.3s ease both; }
.error-msg.show { display: block; }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; flex-shrink: 0; display: inline-block; }
</style>
</head>
<body>
<canvas id="bg-canvas"></canvas>
<div class="site">

  <div class="logo-wrap">
    <div class="logo-icon">
      <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/>
      </svg>
    </div>
    <div class="logo-name">Excellent</div>
    <div class="logo-sub">Simplified</div>
  </div>

  <div class="card">
    <div class="card-head">
      <h1>Welcome back 👋</h1>
      <p>Sign in to continue your learning journey</p>
    </div>

    <button class="btn-google" id="googleBtn" onclick="googleSignIn()">
      <svg viewBox="0 0 533.5 544.3" xmlns="http://www.w3.org/2000/svg">
        <path fill="#4285F4" d="M533.5 278.4c0-18.6-1.5-33.1-4.6-47.6H272v90.1h147.6c-3.2 26.3-21.6 65-66.1 91.5l.6 3.9 95.9 74.1 6.7.7c61.1-56 94.9-140.1 94.9-212.7z"/>
        <path fill="#34A853" d="M272 544.3c73.9 0 132.9-24.4 177.2-66l-84.5-65.2c-22.9 15.6-52.1 25.1-92.7 25.1-71 0-131.2-47.9-152.7-112.1l-3.2.3-97.4 75.4-.8 3.1C57.3 483.2 156.3 544.3 272 544.3z"/>
        <path fill="#FBBC05" d="M119.3 322.2c-10.3-30.4-10.3-62.5 0-92.9l-.1-3.1-97.1-75.4-3.3.7C3.9 200.9 0 234.7 0 272c0 37.3 3.9 71.1 18.8 105.6l100.5-55.4z"/>
        <path fill="#EA4335" d="M272 107.7c37.9-.6 75.5 13.2 103.6 37.9l77.6-77.6C389.9 22 333 0 272 0 156.3 0 57.3 61.1 18.8 149.7l100.5 55.4C140.8 137.7 201 107.7 272 107.7z"/>
      </svg>
      Continue with Google
    </button>

    <div class="divider">or use email</div>

    <button class="email-toggle" id="emailToggle" onclick="toggleEmail()">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
      </svg>
      Sign in with Email
      <svg class="toggle-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <path d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
      </svg>
    </button>

    <div class="email-form" id="emailForm">
      <div class="field-wrap">
        <span class="field-icon">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zm0 0c0 1.657 1.007 3 2.25 3S21 13.657 21 12a9 9 0 10-2.636 6.364M16.5 12V8.25"/>
          </svg>
        </span>
        <input class="field" id="email" type="email" placeholder="Email address" autocomplete="email">
      </div>
      <div class="field-wrap">
        <span class="field-icon">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
          </svg>
        </span>
        <input class="field" id="password" type="password" placeholder="Password" autocomplete="current-password" style="padding-right:40px">
        <button class="eye-toggle" type="button" onclick="toggleEye()" tabindex="-1" aria-label="Show password">
          <svg id="eyeIcon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </button>
      </div>
      <div class="forgot-row">
        <a href="#" onclick="forgotPassword(); return false;">Forgot password?</a>
      </div>
      <button class="btn-email" id="signinBtn" onclick="emailSignIn()">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
        </svg>
        Sign In
      </button>
    </div>

    <div class="error-msg" id="error" role="status" aria-live="polite"></div>
  </div>

  <div class="card-footer">
    Don't have an account? <a href="signup.html">Create one free</a>
    &nbsp;·&nbsp;
    <a href="index.php" style="color:rgba(255,255,255,0.3)">← Back home</a>
  </div>

</div>

<script>
/* ── Blob background ── */
(function(){
  const canvas = document.getElementById('bg-canvas');
  const ctx = canvas.getContext('2d');
  const orbs = [
    { x:.12, y:.2,  r:.38, cx:.0012, cy:.0009, color:[124,58,237],  phase:0   },
    { x:.78, y:.15, r:.35, cx:.001,  cy:.0013, color:[37,99,235],   phase:1.2 },
    { x:.55, y:.75, r:.42, cx:.0014, cy:.001,  color:[6,182,212],   phase:2.4 },
    { x:.2,  y:.7,  r:.28, cx:.0009, cy:.0015, color:[16,185,129],  phase:3.6 },
    { x:.85, y:.6,  r:.30, cx:.0015, cy:.0008, color:[245,158,11],  phase:4.8 },
    { x:.45, y:.35, r:.26, cx:.0011, cy:.0012, color:[236,72,153],  phase:1.7 },
  ];
  let W, H, t = 0;
  function resize(){ W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
  window.addEventListener('resize', resize); resize();
  function draw(){
    ctx.clearRect(0,0,W,H);
    ctx.fillStyle='#04020e'; ctx.fillRect(0,0,W,H);
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

function toggleEmail(){
  const form = document.getElementById('emailForm');
  const toggle = document.getElementById('emailToggle');
  const open = form.classList.toggle('open');
  toggle.classList.toggle('open', open);
  if(open) setTimeout(()=>document.getElementById('email').focus(), 80);
}

function toggleEye(){
  const f = document.getElementById('password');
  const ic = document.getElementById('eyeIcon');
  if(f.type==='password'){
    f.type='text';
    ic.innerHTML='<path d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>';
  } else {
    f.type='password';
    ic.innerHTML='<path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>';
  }
}

document.addEventListener('keydown', e=>{
  if(e.key==='Enter' && document.getElementById('emailForm').classList.contains('open')) emailSignIn();
});

function showError(msg){
  const el = document.getElementById('error');
  if(msg){ el.textContent=msg; el.classList.add('show'); }
  else { el.textContent=''; el.classList.remove('show'); }
}
</script>

<!-- Firebase -->
<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/12.10.0/firebase-app.js";
import { getAuth, signInWithEmailAndPassword, GoogleAuthProvider, signInWithPopup, sendPasswordResetEmail } from "https://www.gstatic.com/firebasejs/12.10.0/firebase-auth.js";

const firebaseConfig = {
  apiKey:            "AIzaSyAckwsm4ov-FR84WLCaklmADlxor1JZZsI",
  authDomain:        "excellent-simplified.firebaseapp.com",
  projectId:         "excellent-simplified",
  storageBucket:     "excellent-simplified.firebasestorage.app",
  messagingSenderId: "1031645566404",
  appId:             "1:1031645566404:web:81543ad98c9febca328091"
};

const app  = initializeApp(firebaseConfig);
const auth = getAuth(app);

/* ── POST token to THIS page (login.php), PHP sets session, then redirect ── */
async function syncAndRedirect(user) {
  try {
    const idToken = await user.getIdToken(true);

    const resp = await fetch('login.php', {
      method:      'POST',
      headers:     { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body:        JSON.stringify({ idToken })
    });

    const raw = await resp.text();
    let j;
    try {
      j = JSON.parse(raw);
    } catch(e) {
      const preview = raw.replace(/<[^>]+>/g,'').trim().slice(0, 200);
      showError('Server error: ' + (preview || 'HTTP ' + resp.status));
      return;
    }

    if (!j.success) {
      showError(j.error || 'Sign-in failed. Please try again.');
      return;
    }

    // ✅ Session is now set server-side — redirect to dashboard
    window.location.href = 'dashboard.php';

  } catch(e) {
    showError('Connection error: ' + e.message);
    console.error(e);
  }
}

window.googleSignIn = async function() {
  showError('');
  const btn = document.getElementById('googleBtn');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="border-color:rgba(0,0,0,.2);border-top-color:#111"></div> Signing in…';
  try {
    const result = await signInWithPopup(auth, new GoogleAuthProvider());
    await syncAndRedirect(result.user);
  } catch(err) {
    showError(err.code === 'auth/popup-closed-by-user' ? 'Sign-in cancelled.' : (err.message || 'Google sign-in failed'));
    btn.disabled = false;
    btn.innerHTML = `<svg viewBox="0 0 533.5 544.3" xmlns="http://www.w3.org/2000/svg" style="width:22px;height:22px"><path fill="#4285F4" d="M533.5 278.4c0-18.6-1.5-33.1-4.6-47.6H272v90.1h147.6c-3.2 26.3-21.6 65-66.1 91.5l.6 3.9 95.9 74.1 6.7.7c61.1-56 94.9-140.1 94.9-212.7z"/><path fill="#34A853" d="M272 544.3c73.9 0 132.9-24.4 177.2-66l-84.5-65.2c-22.9 15.6-52.1 25.1-92.7 25.1-71 0-131.2-47.9-152.7-112.1l-3.2.3-97.4 75.4-.8 3.1C57.3 483.2 156.3 544.3 272 544.3z"/><path fill="#FBBC05" d="M119.3 322.2c-10.3-30.4-10.3-62.5 0-92.9l-.1-3.1-97.1-75.4-3.3.7C3.9 200.9 0 234.7 0 272c0 37.3 3.9 71.1 18.8 105.6l100.5-55.4z"/><path fill="#EA4335" d="M272 107.7c37.9-.6 75.5 13.2 103.6 37.9l77.6-77.6C389.9 22 333 0 272 0 156.3 0 57.3 61.1 18.8 149.7l100.5 55.4C140.8 137.7 201 107.7 272 107.7z"/></svg> Continue with Google`;
  }
};

window.emailSignIn = async function() {
  showError('');
  const email    = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  if(!email || !password){ showError('Please enter your email and password'); return; }
  const btn = document.getElementById('signinBtn');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner"></div> Signing in…';
  try {
    const cred = await signInWithEmailAndPassword(auth, email, password);
    await syncAndRedirect(cred.user);
  } catch(err) {
    const friendly = {
      'auth/user-not-found':     'No account found with this email.',
      'auth/wrong-password':     'Wrong password. Try again or reset it.',
      'auth/invalid-email':      'Please enter a valid email address.',
      'auth/too-many-requests':  'Too many attempts. Please wait a moment.',
      'auth/invalid-credential': 'Wrong email or password.',
    };
    showError(friendly[err.code] || err.message || 'Sign-in failed');
    btn.disabled = false;
    btn.innerHTML = '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg> Sign In';
  }
};

window.forgotPassword = async function() {
  const email = document.getElementById('email').value.trim();
  if(!email){ showError('Enter your email first, then click Forgot password'); return; }
  try {
    await sendPasswordResetEmail(auth, email);
    const el = document.getElementById('error');
    el.style.cssText = 'background:rgba(16,185,129,0.12);border-color:rgba(16,185,129,0.3);color:#6ee7b7;display:block';
    el.textContent = '✓ Reset link sent — check your inbox';
  } catch(err) {
    showError(err.message || 'Could not send reset email');
  }
};
</script>
</body>
</html>
