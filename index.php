<?php
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="google-adsense-account" content="ca-pub-6496385605819496">

<!-- ── Primary SEO ── -->
<title>Excellent Simplified — Free JAMB & WAEC Study Platform for Nigerian Students</title>
<meta name="description" content="Excellent Simplified is Nigeria's smartest free study platform for JAMB UTME and WAEC students. Watch video lessons, practice past questions, use AI tutoring, compete on leaderboards, and ace your exams.">
<meta name="keywords" content="JAMB 2026, WAEC 2026, JAMB past questions, WAEC past questions, free JAMB practice, Nigerian students, UTME preparation, CBT practice, JAMB lessons, WAEC lessons, online school Nigeria, study platform Nigeria, ALOC past questions, JAMB score 300, excellent simplified">
<meta name="author" content="Excellent Simplified Academy">
<meta name="robots" content="index, follow">
<meta name="theme-color" content="#04020e">
<link rel="canonical" href="https://excellent-simplified.ct.ws/">

<!-- ── Open Graph (WhatsApp, Facebook, Telegram) ── -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Excellent Simplified">
<meta property="og:title" content="Excellent Simplified — Free JAMB & WAEC Study Platform">
<meta property="og:description" content="Nigeria's smartest free study platform. Watch lessons, practice JAMB & WAEC past questions, get AI tutoring, and compete with classmates. Join 10,000+ students today!">
<meta property="og:url" content="https://excellent-simplified.ct.ws/">
<meta property="og:image" content="https://excellent-simplified.ct.ws/assets/og-image.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="en_NG">

<!-- ── Twitter / X Card ── -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Excellent Simplified — Free JAMB & WAEC Study Platform">
<meta name="twitter:description" content="Nigeria's smartest free study platform. Watch lessons, practice past questions, get AI tutoring. Join 10,000+ students!">
<meta name="twitter:image" content="https://excellent-simplified.ct.ws/assets/og-image.png">

<!-- ── Structured Data (Google Rich Results) ── -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "EducationalOrganization",
  "name": "Excellent Simplified Academy",
  "url": "https://excellent-simplified.ct.ws",
  "description": "Free online study platform for Nigerian JAMB and WAEC students. Video lessons, past questions, AI tutoring, and leaderboards.",
  "educationalCredentialAwarded": "JAMB UTME, WAEC SSCE",
  "audience": {
    "@type": "EducationalAudience",
    "educationalRole": "student",
    "geographicArea": "Nigeria"
  },
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "NGN",
    "description": "Free access to all study materials, past questions, and AI tutoring"
  },
  "contactPoint": {
    "@type": "ContactPoint",
    "contactType": "customer support",
    "url": "https://wa.me/+2349068394581"
  }
}
</script>

<!-- ── Google AdSense ── -->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6496385605819496"
     crossorigin="anonymous"></script>

<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=Cabinet+Grotesk:wght@400;500;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: #04020e;
  min-height: 100vh;
  overflow-x: hidden;
  color: #fff;
  position: relative;
}
#bg-canvas { position: fixed; inset: 0; z-index: 0; pointer-events: none; }
body::after {
  content: ''; position: fixed; inset: 0; z-index: 1; pointer-events: none;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
  opacity: 0.35;
}
.site { position: relative; z-index: 2; }

/* ─── HEADER ─── */
header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 20px; gap: 12px;
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  background: rgba(255,255,255,0.04);
  border-bottom: 1px solid rgba(255,255,255,0.08);
  position: sticky; top: 0; z-index: 50;
}
.logo { display: flex; align-items: center; gap: 10px; min-width: 0; }
.logo-icon {
  width: 38px; height: 38px; flex-shrink: 0; border-radius: 11px;
  background: linear-gradient(135deg, #7c3aed, #2563eb, #06b6d4);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 0 20px rgba(124,58,237,0.5);
  animation: logo-float 4s ease-in-out infinite;
}
@keyframes logo-float { 0%,100% { transform:translateY(0) rotate(0deg); } 50% { transform:translateY(-3px) rotate(3deg); } }
.logo-text-wrap { display: flex; flex-direction: column; min-width: 0; }
.logo-main {
  font-family: 'Clash Display', sans-serif; font-size: 18px; font-weight: 700;
  background: linear-gradient(90deg, #a78bfa, #60a5fa, #34d399);
  -webkit-background-clip: text; background-clip: text; color: transparent;
  letter-spacing: -0.02em; line-height: 1.1; white-space: nowrap;
}
.logo-sub { font-size: 9px; letter-spacing: 0.25em; color: rgba(255,255,255,0.4); font-weight: 500; text-transform: uppercase; }
nav { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.btn-nav {
  padding: 9px 14px; border-radius: 10px;
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; font-weight: 600;
  cursor: pointer; border: none; transition: all 0.2s cubic-bezier(.34,1.56,.64,1);
  white-space: nowrap; text-decoration: none; display: inline-flex; align-items: center;
  -webkit-tap-highlight-color: transparent;
}
.btn-login { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.85); border: 1px solid rgba(255,255,255,0.12); }
.btn-login:hover { background: rgba(255,255,255,0.14); transform: translateY(-1px); }
.btn-signup { background: linear-gradient(135deg, #7c3aed, #2563eb); color: #fff; box-shadow: 0 4px 16px rgba(124,58,237,0.4); }
.btn-signup:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 8px 24px rgba(124,58,237,0.6); }

/* ─── HERO ─── */
.hero { text-align: center; padding: 60px 20px 50px; position: relative; }
.hero-badge {
  display: inline-flex; align-items: center; gap: 7px; padding: 6px 14px;
  border-radius: 999px; background: rgba(124,58,237,0.15); border: 1px solid rgba(124,58,237,0.35);
  font-size: 11px; font-weight: 600; color: #a78bfa; letter-spacing: 0.04em;
  margin-bottom: 22px; animation: badge-glow 3s ease-in-out infinite; max-width: 100%;
}
@keyframes badge-glow { 0%,100% { box-shadow:0 0 0 0 rgba(124,58,237,0); } 50% { box-shadow:0 0 20px rgba(124,58,237,0.4); } }
.hero-badge-dot { width:6px; height:6px; flex-shrink:0; border-radius:50%; background:#7c3aed; animation: dot-pulse 1.5s ease-in-out infinite; }
@keyframes dot-pulse { 0%,100%{opacity:1;transform:scale(1);}50%{opacity:0.5;transform:scale(0.7);} }
.hero h1 {
  font-family: 'Clash Display', sans-serif; font-size: clamp(30px, 9vw, 72px);
  font-weight: 700; line-height: 1.05; letter-spacing: -0.03em; margin-bottom: 16px;
  animation: hero-rise 0.9s cubic-bezier(.22,1,.36,1) both;
}
@keyframes hero-rise { from{opacity:0;transform:translateY(30px);}to{opacity:1;transform:translateY(0);} }
.hero h1 .line1 { display: block; color: #fff; }
.hero h1 .line2 {
  display: block;
  background: linear-gradient(90deg, #a78bfa 0%, #60a5fa 40%, #34d399 80%);
  -webkit-background-clip: text; background-clip: text; color: transparent;
  background-size: 200% auto; animation: shimmer 4s linear infinite;
}
@keyframes shimmer { from{background-position:0% center;}to{background-position:200% center;} }
.hero p {
  font-size: clamp(14px, 3.5vw, 18px); line-height: 1.7; color: rgba(255,255,255,0.55);
  max-width: 560px; margin: 0 auto 32px; animation: hero-rise 1s 0.15s cubic-bezier(.22,1,.36,1) both; padding: 0 4px;
}
.hero-cta {
  display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;
  animation: hero-rise 1s 0.3s cubic-bezier(.22,1,.36,1) both; padding: 0 4px;
}
.btn-primary {
  display: inline-flex; align-items: center; gap: 8px; padding: 14px 26px; border-radius: 14px;
  background: linear-gradient(135deg, #7c3aed, #2563eb); color: #fff;
  font-family: 'Cabinet Grotesk', sans-serif; font-size: 15px; font-weight: 700;
  cursor: pointer; border: none; box-shadow: 0 8px 28px rgba(124,58,237,0.45);
  transition: all 0.25s cubic-bezier(.34,1.56,.64,1); text-decoration: none;
  -webkit-tap-highlight-color: transparent;
}
.btn-primary:hover { transform: translateY(-3px) scale(1.04); box-shadow: 0 16px 44px rgba(124,58,237,0.6); }
.btn-primary:active { transform: scale(0.97); }
.btn-secondary {
  display: inline-flex; align-items: center; gap: 8px; padding: 14px 26px; border-radius: 14px;
  background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.8);
  font-family: 'Cabinet Grotesk', sans-serif; font-size: 15px; font-weight: 700;
  cursor: pointer; border: 1px solid rgba(255,255,255,0.12); backdrop-filter: blur(12px);
  transition: all 0.2s ease; text-decoration: none; -webkit-tap-highlight-color: transparent;
}
.btn-secondary:hover { background: rgba(255,255,255,0.1); transform: translateY(-2px); }
.btn-secondary:active { transform: scale(0.97); }

/* ─── STATS ─── */
.stats-strip {
  display: grid; grid-template-columns: repeat(4, 1fr);
  max-width: 520px; margin: 44px auto 0;
  animation: hero-rise 1s 0.45s cubic-bezier(.22,1,.36,1) both;
  border: 1px solid rgba(255,255,255,0.07); border-radius: 18px;
  overflow: hidden; background: rgba(255,255,255,0.02);
}
.stat-item { text-align: center; padding: 16px 8px; }
.stat-item + .stat-item { border-left: 1px solid rgba(255,255,255,0.07); }
.stat-num {
  font-family: 'Clash Display', sans-serif; font-size: clamp(18px, 4vw, 26px); font-weight: 700;
  background: linear-gradient(135deg, #a78bfa, #60a5fa);
  -webkit-background-clip: text; background-clip: text; color: transparent; line-height: 1;
}
.stat-lbl { font-size: 10px; color: rgba(255,255,255,0.4); margin-top: 4px; letter-spacing: 0.05em; text-transform: uppercase; }

/* ─── MARQUEE ─── */
.marquee-wrap {
  overflow: hidden; padding: 16px 0; margin: 52px 0 0;
  border-top: 1px solid rgba(255,255,255,0.06); border-bottom: 1px solid rgba(255,255,255,0.06);
  background: rgba(255,255,255,0.02);
}
.marquee-track { display: flex; gap: 40px; animation: marquee 24s linear infinite; white-space: nowrap; width: max-content; }
@keyframes marquee { from{transform:translateX(0);}to{transform:translateX(-50%);} }
.marquee-item { display: flex; align-items: center; gap: 10px; font-family: 'Cabinet Grotesk', sans-serif; font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.28); letter-spacing: 0.05em; text-transform: uppercase; }
.marquee-item .dot { width:5px; height:5px; border-radius:50%; background:rgba(255,255,255,0.2); }

/* ─── FEATURES ─── */
.features { max-width: 1100px; margin: 0 auto; padding: 44px 16px 72px; }
.section-label { text-align: center; margin-bottom: 36px; }
.section-tag {
  display: inline-flex; align-items: center; gap: 7px; padding: 5px 14px; border-radius: 999px;
  background: rgba(96,165,250,0.1); border: 1px solid rgba(96,165,250,0.2);
  font-size: 11px; font-weight: 700; color: #60a5fa; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 12px;
}
.section-title { font-family: 'Clash Display', sans-serif; font-size: clamp(22px, 5vw, 38px); font-weight: 700; color: #fff; letter-spacing: -0.03em; }
.panel-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
.panel {
  background: rgba(255,255,255,0.04); backdrop-filter: blur(24px) saturate(180%);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  border: 1px solid rgba(255,255,255,0.09); border-radius: 20px; padding: 24px 20px 22px;
  position: relative; overflow: hidden; transition: all 0.35s cubic-bezier(.22,1,.36,1);
  animation: card-rise 0.8s cubic-bezier(.22,1,.36,1) both;
  -webkit-tap-highlight-color: transparent;
}
.panel:nth-child(1){animation-delay:0.05s}.panel:nth-child(2){animation-delay:0.1s}.panel:nth-child(3){animation-delay:0.15s}
.panel:nth-child(4){animation-delay:0.2s}.panel:nth-child(5){animation-delay:0.25s}.panel:nth-child(6){animation-delay:0.3s}
@keyframes card-rise { from{opacity:0;transform:translateY(24px) scale(0.97);}to{opacity:1;transform:translateY(0) scale(1);} }
.panel::before { content:''; position:absolute; top:-60px; left:-60px; width:150px; height:150px; border-radius:50%; opacity:0; transition:opacity 0.4s,transform 0.4s; pointer-events:none; filter:blur(40px); }
.panel:nth-child(1)::before{background:#7c3aed}.panel:nth-child(2)::before{background:#2563eb}.panel:nth-child(3)::before{background:#0891b2}
.panel:nth-child(4)::before{background:#059669}.panel:nth-child(5)::before{background:#d97706}.panel:nth-child(6)::before{background:#dc2626}
.panel:hover { transform:translateY(-5px) scale(1.01); border-color:rgba(255,255,255,0.16); background:rgba(255,255,255,0.07); box-shadow:0 20px 56px rgba(0,0,0,0.3); }
.panel:hover::before { opacity:0.18; transform:translate(20px,20px); }
.panel-icon { width:46px; height:46px; border-radius:13px; display:flex; align-items:center; justify-content:center; margin-bottom:14px; position:relative; z-index:1; }
.panel:nth-child(1) .panel-icon{background:rgba(124,58,237,0.2);box-shadow:0 0 0 1px rgba(124,58,237,0.3)}
.panel:nth-child(2) .panel-icon{background:rgba(37,99,235,0.2);box-shadow:0 0 0 1px rgba(37,99,235,0.3)}
.panel:nth-child(3) .panel-icon{background:rgba(8,145,178,0.2);box-shadow:0 0 0 1px rgba(8,145,178,0.3)}
.panel:nth-child(4) .panel-icon{background:rgba(5,150,105,0.2);box-shadow:0 0 0 1px rgba(5,150,105,0.3)}
.panel:nth-child(5) .panel-icon{background:rgba(217,119,6,0.2);box-shadow:0 0 0 1px rgba(217,119,6,0.3)}
.panel:nth-child(6) .panel-icon{background:rgba(220,38,38,0.2);box-shadow:0 0 0 1px rgba(220,38,38,0.3)}
.panel-icon svg{width:22px;height:22px}
.panel:nth-child(1) .panel-icon svg{color:#a78bfa}.panel:nth-child(2) .panel-icon svg{color:#60a5fa}.panel:nth-child(3) .panel-icon svg{color:#22d3ee}
.panel:nth-child(4) .panel-icon svg{color:#34d399}.panel:nth-child(5) .panel-icon svg{color:#fbbf24}.panel:nth-child(6) .panel-icon svg{color:#f87171}
.panel h3 { font-family:'Cabinet Grotesk',sans-serif; font-size:16px; font-weight:800; color:#fff; margin-bottom:8px; letter-spacing:-0.02em; position:relative; z-index:1; }
.panel p  { font-size:13.5px; line-height:1.65; color:rgba(255,255,255,0.48); position:relative; z-index:1; }

/* ─── CTA ─── */
.cta-section { text-align:center; padding:20px 16px 72px; }
.cta-card {
  max-width:620px; margin:0 auto; background:rgba(255,255,255,0.04);
  backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
  border:1px solid rgba(255,255,255,0.08); border-radius:24px; padding:40px 22px;
  position:relative; overflow:hidden;
}
.cta-card::before { content:''; position:absolute; top:-80px; left:50%; transform:translateX(-50%); width:280px; height:180px; background:radial-gradient(ellipse,rgba(124,58,237,0.25) 0%,transparent 70%); pointer-events:none; }
.cta-card h2 { font-family:'Clash Display',sans-serif; font-size:clamp(22px,5vw,34px); font-weight:700; color:#fff; letter-spacing:-0.03em; margin-bottom:12px; }
.cta-card p  { color:rgba(255,255,255,0.5); font-size:15px; line-height:1.65; margin-bottom:28px; }
.cta-btns    { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }

/* ─── FOOTER ─── */
footer { text-align:center; padding:22px 20px 32px; border-top:1px solid rgba(255,255,255,0.06); color:rgba(255,255,255,0.28); font-size:13px; line-height:1.7; }
footer strong { color:rgba(255,255,255,0.5); }

/* ─── WHATSAPP FAB ─── */
.whatsapp-btn {
  position:fixed; bottom:18px; right:18px; width:52px; height:52px; border-radius:50%;
  background:linear-gradient(135deg,#25d366,#128c7e); display:flex; align-items:center; justify-content:center;
  box-shadow:0 6px 24px rgba(37,211,102,0.4); cursor:pointer; z-index:100; border:none;
  transition:all 0.25s cubic-bezier(.34,1.56,.64,1); animation:wa-pop 0.6s 1s cubic-bezier(.34,1.56,.64,1) both;
  -webkit-tap-highlight-color:transparent;
}
@keyframes wa-pop { from{opacity:0;transform:scale(0);}to{opacity:1;transform:scale(1);} }
.whatsapp-btn:hover  { transform:scale(1.1) rotate(-5deg); box-shadow:0 12px 36px rgba(37,211,102,0.6); }
.whatsapp-btn:active { transform:scale(0.95); }
.whatsapp-btn svg    { width:24px; height:24px; fill:white; }

/* ══════════════════════════
   MOBILE  ≤ 480px
══════════════════════════ */
@media (max-width: 480px) {
  header { padding: 11px 14px; }
  .logo-main { font-size: 15px; }
  .logo-sub  { display: none; }
  .logo-icon { width: 34px; height: 34px; }
  .btn-nav   { padding: 8px 12px; font-size: 12px; }

  .hero { padding: 44px 14px 40px; }
  .hero-badge { font-size: 10px; padding: 5px 11px; }
  .hero p { font-size: 14px; }
  .hero-cta { flex-direction: column; align-items: stretch; gap: 10px; }
  .btn-primary, .btn-secondary { justify-content: center; width: 100%; padding: 14px 20px; }

  .stats-strip { grid-template-columns: repeat(2, 1fr); max-width: 100%; margin-top: 28px; }
  .stat-item:nth-child(3) { border-top: 1px solid rgba(255,255,255,0.07); border-left: none; }
  .stat-item:nth-child(4) { border-top: 1px solid rgba(255,255,255,0.07); }

  .features { padding: 32px 12px 56px; }
  .panel-grid { grid-template-columns: 1fr; gap: 11px; }
  .section-title { font-size: 21px; }

  .cta-card { padding: 28px 16px; }
  .cta-btns { flex-direction: column; }
  .cta-btns .btn-primary, .cta-btns .btn-secondary { width: 100%; justify-content: center; }

  .whatsapp-btn { width: 48px; height: 48px; bottom: 14px; right: 14px; }
}

/* ══════════════════════════
   TABLET  481–768px
══════════════════════════ */
@media (min-width: 481px) and (max-width: 768px) {
  .hero { padding: 68px 22px 52px; }
  .panel-grid { grid-template-columns: repeat(2, 1fr); }
  .stats-strip { max-width: 100%; }
}

/* ══════════════════════════
   DESKTOP  769px+
══════════════════════════ */
@media (min-width: 769px) {
  header { padding: 18px 48px; }
  .logo-main { font-size: 20px; }
  .logo-icon { width: 42px; height: 42px; }
  .btn-nav   { padding: 9px 22px; font-size: 14px; }
  .hero { padding: 110px 24px 80px; }
  .panel-grid { grid-template-columns: repeat(3, 1fr); }
}

/* ─── AD PANEL ─── */
#ad-overlay {
  position: fixed; inset: 0; z-index: 200;
  background: rgba(4,2,14,0.75);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
  animation: ad-fade-in 0.4s cubic-bezier(.22,1,.36,1) both;
}
@keyframes ad-fade-in { from{opacity:0;} to{opacity:1;} }
#ad-overlay.hidden { display: none; }

#ad-panel {
  position: relative;
  width: 100%; max-width: 420px;
  background: rgba(255,255,255,0.05);
  backdrop-filter: blur(32px) saturate(200%);
  -webkit-backdrop-filter: blur(32px) saturate(200%);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 24px;
  padding: 28px 22px 22px;
  box-shadow: 0 32px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(124,58,237,0.15);
  animation: ad-rise 0.45s 0.1s cubic-bezier(.22,1,.36,1) both;
  overflow: hidden;
}
@keyframes ad-rise { from{opacity:0;transform:translateY(28px) scale(0.96);} to{opacity:1;transform:translateY(0) scale(1);} }

#ad-panel::before {
  content: ''; position: absolute; top: -60px; left: 50%; transform: translateX(-50%);
  width: 240px; height: 160px;
  background: radial-gradient(ellipse, rgba(124,58,237,0.3) 0%, transparent 70%);
  pointer-events: none;
}

.ad-panel-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 16px; position: relative; z-index: 1;
}
.ad-panel-label {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 10px; font-weight: 700; letter-spacing: 0.12em;
  text-transform: uppercase; color: rgba(255,255,255,0.35);
}
.ad-panel-label-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: linear-gradient(135deg, #7c3aed, #2563eb);
  box-shadow: 0 0 8px rgba(124,58,237,0.6);
}
.ad-close-btn {
  width: 32px; height: 32px; border-radius: 10px; border: none; cursor: pointer;
  background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.55);
  display: flex; align-items: center; justify-content: center;
  transition: all 0.2s ease; -webkit-tap-highlight-color: transparent;
  flex-shrink: 0;
}
.ad-close-btn:hover { background: rgba(255,255,255,0.15); color: #fff; transform: scale(1.08); }
.ad-close-btn:active { transform: scale(0.93); }
.ad-close-btn svg { width: 14px; height: 14px; }

.ad-content-box {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.07);
  border-radius: 16px; overflow: hidden;
  min-height: 120px; display: flex; align-items: center; justify-content: center;
  margin-bottom: 14px; position: relative; z-index: 1;
}

.ad-panel-footer {
  display: flex; align-items: center; justify-content: space-between;
  gap: 10px; position: relative; z-index: 1;
}
.ad-footer-note {
  font-size: 11px; color: rgba(255,255,255,0.25); line-height: 1.4; flex: 1;
}
.ad-goto-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 10px 18px; border-radius: 12px; border: none; cursor: pointer;
  background: linear-gradient(135deg, #7c3aed, #2563eb);
  color: #fff; font-family: 'Cabinet Grotesk', sans-serif;
  font-size: 13px; font-weight: 700;
  box-shadow: 0 6px 20px rgba(124,58,237,0.4);
  transition: all 0.25s cubic-bezier(.34,1.56,.64,1);
  white-space: nowrap; text-decoration: none;
  -webkit-tap-highlight-color: transparent;
}
.ad-goto-btn:hover { transform: translateY(-2px) scale(1.04); box-shadow: 0 10px 28px rgba(124,58,237,0.6); }
.ad-goto-btn:active { transform: scale(0.96); }
.ad-goto-btn svg { width: 13px; height: 13px; }

@media (max-width: 480px) {
  #ad-panel { padding: 22px 16px 18px; border-radius: 20px; }
  .ad-panel-footer { flex-direction: column; align-items: stretch; }
  .ad-goto-btn { justify-content: center; }
}
</style>
</head>
<body>
<canvas id="bg-canvas"></canvas>
<div class="site">

<header>
  <div class="logo">
    <div class="logo-icon">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/>
      </svg>
    </div>
    <div class="logo-text-wrap">
      <span class="logo-main">Excellent</span>
      <span class="logo-sub">Simplified</span>
    </div>
  </div>
  <nav>
    <a class="btn-nav btn-login" href="login.php">Log In</a>
    <a class="btn-nav btn-signup" href="signup.html">Sign Up Free</a>
  </nav>
</header>

<section class="hero">
  <div class="hero-badge">
    <span class="hero-badge-dot"></span>
    Now with AI Tutoring &amp; ALOC Past Questions
  </div>
  <h1>
    <span class="line1">Your Smart Digital</span>
    <span class="line2">Classroom Hub</span>
  </h1>
  <p>Watch lessons, answer live questions, practice JAMB &amp; WAEC exams, and compete on leaderboards — all in one beautifully crafted platform.</p>
  <div class="hero-cta">
    <a class="btn-primary" href="login.php">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
      Start Learning
    </a>
    <a class="btn-secondary" href="signup.php">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
      Create Account
    </a>
  </div>
  <div class="stats-strip">
    <div class="stat-item"><div class="stat-num">10K+</div><div class="stat-lbl">Students</div></div>
    <div class="stat-item"><div class="stat-num">500+</div><div class="stat-lbl">Lessons</div></div>
    <div class="stat-item"><div class="stat-num">7K+</div><div class="stat-lbl">Questions</div></div>
    <div class="stat-item"><div class="stat-num">98%</div><div class="stat-lbl">Satisfaction</div></div>
  </div>
</section>

<div class="marquee-wrap">
  <div class="marquee-track">
    <?php
    $items = ['WAEC Past Questions','JAMB UTME Practice','Live Brainstorm','AI Tutor','Video Lessons','Student Leaderboard','CBT Simulation','Score Tracking','Instant Feedback','Subject Coverage'];
    $repeated = array_merge($items, $items);
    foreach($repeated as $i): ?>
    <div class="marquee-item"><span class="dot"></span><?= htmlspecialchars($i) ?></div>
    <?php endforeach; ?>
  </div>
</div>

<section class="features">
  <div class="section-label">
    <div class="section-tag">
      <svg width="10" height="10" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="10" r="4"/></svg>
      Everything You Need
    </div>
    <h2 class="section-title">Built for Nigerian Students</h2>
  </div>
  <div class="panel-grid">

    <div class="panel">
      <div class="panel-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z"/>
        </svg>
      </div>
      <h3>Central Lesson Hub</h3>
      <p>All video lessons organised by subject in one place. No more searching through WhatsApp or YouTube — everything is right here.</p>
    </div>

    <div class="panel">
      <div class="panel-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
      </div>
      <h3>Student Accounts</h3>
      <p>Personal dashboards track your score, accuracy, streaks, and progress across every subject. Know exactly where you stand.</p>
    </div>

    <div class="panel">
      <div class="panel-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
        </svg>
      </div>
      <h3>Live Brainstorm Mode</h3>
      <p>Your teacher activates a live question and every student answers in real time. First correct answer wins — who's fastest?</p>
    </div>

    <div class="panel">
      <div class="panel-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z"/>
        </svg>
      </div>
      <h3>AI Study Helper</h3>
      <p>Stuck on a concept? Ask the AI Tutor any WAEC or JAMB topic and get a clear, step-by-step textbook explanation instantly.</p>
    </div>

    <div class="panel">
      <div class="panel-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75"/>
        </svg>
      </div>
      <h3>Practice Exams</h3>
      <p>Timed CBT tests using real JAMB UTME and WAEC SSCE past questions. Automatic marking, instant scoring, and full analysis.</p>
    </div>

    <div class="panel">
      <div class="panel-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0"/>
        </svg>
      </div>
      <h3>Leaderboards</h3>
      <p>Compete with your classmates. Real-time rankings show who answers fastest and scores highest — push yourself to the top.</p>
    </div>

  </div>
</section>

<section class="cta-section">
  <div class="cta-card">
    <h2>Ready to Start Learning?</h2>
    <p>Join thousands of Nigerian students already using Excellent Simplified to ace their WAEC and JAMB exams.</p>
    <div class="cta-btns">
      <a class="btn-primary" href="login.php">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
        Login to Learn
      </a>
      <a class="btn-secondary" href="signup.html">Create Free Account</a>
    </div>
  </div>
</section>

<footer>
  <p>© 2026 <strong>Excellent Simplified Academy</strong> — All rights reserved.</p>
  <p style="margin-top:6px;font-size:12px;">Created with ❤️ by <strong>O'net</strong> — a subsidiary of <strong>O'REL</strong></p>
</footer>

</div>

<!-- ─── AD PANEL OVERLAY ─── -->
<div id="ad-overlay">
  <div id="ad-panel">
    <div class="ad-panel-header">
      <div class="ad-panel-label">
        <span class="ad-panel-label-dot"></span>
        Sponsored
      </div>
      <button class="ad-close-btn" id="ad-close-btn" aria-label="Close ad">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <path d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <div class="ad-content-box">
      <!-- Google AdSense Ad Unit -->
      <ins class="adsbygoogle"
           style="display:block;width:100%;min-height:120px;"
           data-ad-client="ca-pub-6496385605819496"
           data-ad-slot="auto"
           data-ad-format="auto"
           data-full-width-responsive="true"></ins>
      <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
    </div>

    <div class="ad-panel-footer">
      <p class="ad-footer-note">Ads help keep Excellent Simplified free for all students.</p>
      <a class="ad-goto-btn" id="ad-goto-btn" href="#" target="_blank" rel="noopener noreferrer">
        Visit
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
        </svg>
      </a>
    </div>
  </div>
</div>

<button class="whatsapp-btn" onclick="window.open('https://wa.me/+2349068394581?text=Hello%2C+I+would+love+to+inquire+about+Excellent+Simplified')" aria-label="Chat on WhatsApp">
  <svg viewBox="0 0 24 24"><path d="M20.5 3.5A11.9 11.9 0 0012 .1C5.5.1.1 5.5.1 12c0 2.1.6 4.1 1.7 5.8L0 24l6.4-1.7A11.8 11.8 0 0012 23.9c6.5 0 11.9-5.4 11.9-11.9 0-3.2-1.2-6.2-3.4-8.5zM12 21.7c-1.8 0-3.5-.5-5-1.3l-.4-.2-3.8 1 1-3.7-.3-.4A9.6 9.6 0 012.3 12c0-5.4 4.4-9.8 9.8-9.8S21.9 6.6 21.9 12 17.5 21.7 12 21.7z"/></svg>
</button>

<script>
(function(){
  const canvas = document.getElementById('bg-canvas');
  const ctx = canvas.getContext('2d');
  const orbs = [
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

/* ─── Ad Panel ─── */
(function(){
  const overlay = document.getElementById('ad-overlay');
  const closeBtn = document.getElementById('ad-close-btn');
  const gotoBtn  = document.getElementById('ad-goto-btn');

  // Close when X is clicked
  closeBtn.addEventListener('click', function(){
    overlay.style.animation = 'ad-fade-in 0.25s reverse both';
    setTimeout(function(){ overlay.classList.add('hidden'); }, 220);
  });

  // Close when clicking the dark backdrop (outside the panel)
  overlay.addEventListener('click', function(e){
    if(e.target === overlay){
      overlay.style.animation = 'ad-fade-in 0.25s reverse both';
      setTimeout(function(){ overlay.classList.add('hidden'); }, 220);
    }
  });

  // "Visit" button — grabs the href from the first ad anchor if available, else opens a blank tab
  gotoBtn.addEventListener('click', function(e){
    const adAnchor = overlay.querySelector('ins a[href]');
    if(adAnchor){
      e.preventDefault();
      window.open(adAnchor.href, '_blank', 'noopener,noreferrer');
    }
    overlay.style.animation = 'ad-fade-in 0.25s reverse both';
    setTimeout(function(){ overlay.classList.add('hidden'); }, 220);
  });
})();

/* Scroll-reveal */
const obs = new IntersectionObserver(entries=>{
  entries.forEach(e=>{ if(e.isIntersecting) e.target.style.animationPlayState='running'; });
},{threshold:0.08});
document.querySelectorAll('.panel').forEach(p=>{ p.style.animationPlayState='paused'; obs.observe(p); });

/* Counters */
function animateCounter(el,target,suffix){
  let cur=0; const step=Math.ceil(target/50);
  const t=setInterval(()=>{ cur=Math.min(cur+step,target); el.textContent=cur.toLocaleString()+suffix; if(cur>=target)clearInterval(t); },28);
}
const sObs = new IntersectionObserver(entries=>{
  entries.forEach(e=>{
    if(e.isIntersecting){
      const nums=document.querySelectorAll('.stat-num');
      [{v:10000,s:'+'},{v:500,s:'+'},{v:7000,s:'+'},{v:98,s:'%'}].forEach((d,i)=>animateCounter(nums[i],d.v,d.s));
      sObs.disconnect();
    }
  });
},{threshold:0.5});
const strip=document.querySelector('.stats-strip');
if(strip) sObs.observe(strip);
</script>
</body>
</html>
