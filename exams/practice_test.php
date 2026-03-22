<?php
// exams/practice_test.php — Enhanced CBT with themes, JAMB mode, AI explain
error_reporting(E_ERROR|E_PARSE); ini_set('display_errors','0');
session_start();
require_once __DIR__.'/../config/db.php';

$user_id=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:0;
if(!$user_id){header('Location: ../login.html?redirect=exams/practice_test.php');exit;}

$s=$conn->prepare("SELECT username,email,google_name,google_picture FROM users WHERE id=? LIMIT 1");
$userRow=[];
if($s){$s->bind_param('i',$user_id);$s->execute();$userRow=$s->get_result()->fetch_assoc()??[];$s->close();}
$dn=$_SESSION['google_name']??$userRow['google_name']??$userRow['username']??'Student';
$dp=$_SESSION['google_picture']??$userRow['google_picture']??null;

define('ALOC_TOKEN','QB-b67089074cbb68438091');
define('ALOC_BASE','https://questions.aloc.com.ng/api/v2');

$ALOC_SUBJECTS=[
  'Mathematics'     =>'mathematics',
  'Physics'         =>'physics',
  'Chemistry'       =>'chemistry',
  'Biology'         =>'biology',
  'English Language'=>'english',
];

function aloc_fetch_one(string $slug,string $exam_type):?array{
  $url=ALOC_BASE.'/q?subject='.urlencode($slug).'&type='.urlencode($exam_type);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,
    CURLOPT_HTTPHEADER=>['AccessToken: '.ALOC_TOKEN,'Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_FOLLOWLOCATION=>true]);
  $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  if(!$raw||$code!==200)return null;
  $data=json_decode($raw,true);
  if(empty($data['data']))return null;
  $d=$data['data'];
  return['id'=>$d['id']??uniqid('q_'),'question'=>$d['question']??'',
    'option_a'=>$d['option']['a']??$d['a']??'','option_b'=>$d['option']['b']??$d['b']??'',
    'option_c'=>$d['option']['c']??$d['c']??'','option_d'=>$d['option']['d']??$d['d']??'',
    'correct_answer'=>strtoupper(trim($d['answer']??'')),'subject'=>$d['subject']??$slug,
    'year'=>$d['year']??'','source'=>'aloc'];
}

if(isset($_GET['action'])&&$_GET['action']==='fetch_questions'){
  header('Content-Type: application/json; charset=utf-8');
  $sname=trim($_GET['subject']??'');
  $etype=trim($_GET['exam_type']??'utme');
  $cnt=min(50,max(5,(int)($_GET['count']??30)));
  if(!$sname||!isset($ALOC_SUBJECTS[$sname])){
    echo json_encode(['success'=>false,'error'=>'Unknown subject: '.htmlspecialchars($sname)]);exit;
  }
  $slug=$ALOC_SUBJECTS[$sname];
  $qs=[];$seen=[];$att=0;$max=$cnt*5;
  while(count($qs)<$cnt&&$att<$max){
    $att++;$q=aloc_fetch_one($slug,$etype);
    if(!$q){usleep(100000);continue;}
    if(in_array($q['id'],$seen,true))continue;
    $seen[]=$q['id'];$qs[]=$q;usleep(80000);
  }
  if(empty($qs)){echo json_encode(['success'=>false,'error'=>"No questions for {$sname} ({$etype})."]);exit;}
  echo json_encode(['success'=>true,'questions'=>$qs,'count'=>count($qs)]);exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='save_score'){
  header('Content-Type: application/json');
  $sc=(int)($_POST['score']??0);$tot=(int)($_POST['total']??0);
  if($user_id&&$tot>0){
    @$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS score INT DEFAULT 0");
    @$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS points DECIMAL(10,2) DEFAULT 0");
    $pts=round($sc/$tot*10,2);
    $u=$conn->prepare("UPDATE users SET score=score+?,points=points+? WHERE id=?");
    if($u){$u->bind_param('idi',$sc,$pts,$user_id);$u->execute();$u->close();}
  }
  echo json_encode(['success'=>true]);exit;
}

$subjJson=json_encode(array_keys($ALOC_SUBJECTS));
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Practice Test — Excellent Simplified</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Playfair+Display:wght@700;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════
   DARK THEME  — Deep Crimson Red
═══════════════════════════════════════ */
:root{
  --bg:#0c0405;--s1:#140709;--s2:#1c0c0f;--s3:#251013;--s4:#2e1417;
  --border:rgba(210,50,50,.15);--border2:rgba(230,80,80,.3);
  --accent:#e53e3e;--accent2:#fc8181;--accentd:#c53030;
  --blue:#3b82f6;--amber:#f59e0b;--green:#22c55e;
  --text:#fef2f2;--sub:#d4a0a0;--dim:#7c5050;
  --btn-grad:linear-gradient(135deg,#e53e3e,#c53030);
  --btn-glow:rgba(229,62,62,.4);
  --ring-stroke:#e53e3e;
  --ok-bg:rgba(34,197,94,.09);--ok-bd:rgba(34,197,94,.4);--ok-txt:#4ade80;
  --bad-bg:rgba(239,68,68,.09);--bad-bd:rgba(239,68,68,.4);--bad-txt:#f87171;
  --skip-bg:rgba(245,158,11,.09);--skip-bd:rgba(245,158,11,.35);--skip-txt:#fbbf24;
  --topbar:rgba(12,4,5,.95);
  --sel-bg:rgba(229,62,62,.1);--sel-bd:rgba(229,62,62,.4);
}
/* ═══════════════════════════════════════
   LIGHT THEME — Warm Wood + Ocean Blue
═══════════════════════════════════════ */
body.light{
  --bg:#f2e8d9;--s1:#fdf7f0;--s2:#fff9f3;--s3:#ede0cc;--s4:#e2ceb4;
  --border:rgba(110,55,10,.13);--border2:rgba(110,55,10,.28);
  --accent:#2563eb;--accent2:#3b82f6;--accentd:#1d4ed8;
  --blue:#2563eb;--amber:#b45309;--green:#15803d;
  --text:#231005;--sub:#7a4f28;--dim:#bb9070;
  --btn-grad:linear-gradient(135deg,#2563eb,#1d4ed8);
  --btn-glow:rgba(37,99,235,.35);
  --ring-stroke:#2563eb;
  --ok-bg:rgba(21,128,61,.07);--ok-bd:rgba(21,128,61,.35);--ok-txt:#15803d;
  --bad-bg:rgba(185,28,28,.07);--bad-bd:rgba(185,28,28,.3);--bad-txt:#b91c1c;
  --skip-bg:rgba(180,83,9,.07);--skip-bd:rgba(180,83,9,.3);--skip-txt:#b45309;
  --topbar:rgba(242,232,217,.96);
  --sel-bg:rgba(37,99,235,.09);--sel-bd:rgba(37,99,235,.4);
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;
  transition:background .3s,color .3s}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;opacity:.7;
  background:
    radial-gradient(ellipse 60% 40% at 5% 5%,rgba(229,62,62,.07) 0,transparent 60%),
    radial-gradient(ellipse 50% 40% at 95% 95%,rgba(229,62,62,.05) 0,transparent 55%)}
body.light::before{
  background:
    radial-gradient(ellipse 60% 40% at 5% 5%,rgba(37,99,235,.07) 0,transparent 60%),
    radial-gradient(ellipse 50% 40% at 95% 95%,rgba(139,69,19,.06) 0,transparent 55%)}

/* ─ TOPBAR ─ */
.topbar{position:sticky;top:0;z-index:50;height:58px;background:var(--topbar);
  backdrop-filter:blur(20px);border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 18px;gap:10px;transition:background .3s}
.tb-brand{display:flex;align-items:center;gap:9px;flex:1}
.tb-logo{width:32px;height:32px;border-radius:9px;background:var(--btn-grad);
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:#fff;
  flex-shrink:0;box-shadow:0 3px 12px var(--btn-glow)}
.tb-name{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;letter-spacing:.06em}
.tb-right{display:flex;gap:7px;align-items:center;flex-wrap:wrap}
.t-btn{height:32px;padding:0 12px;border-radius:8px;background:var(--s2);
  border:1px solid var(--border);color:var(--sub);cursor:pointer;
  display:flex;align-items:center;gap:6px;font-family:'Plus Jakarta Sans',sans-serif;
  font-size:12px;font-weight:600;text-decoration:none;transition:all .15s;flex-shrink:0}
.t-btn:hover{color:var(--text);border-color:var(--border2)}
.t-btn.ai{border-color:rgba(59,130,246,.35);background:rgba(59,130,246,.08);color:var(--blue)}
.t-btn.ai:hover{background:rgba(59,130,246,.16)}
.user-chip{display:flex;align-items:center;gap:7px;padding:4px 11px;
  background:var(--s2);border:1px solid var(--border);border-radius:20px;
  font-size:12px;font-weight:600;flex-shrink:0}
.user-chip img{width:22px;height:22px;border-radius:6px;object-fit:cover}
.user-av{width:22px;height:22px;border-radius:6px;background:var(--btn-grad);
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:#fff}
.theme-btn{display:flex;align-items:center;gap:6px;padding:5px 10px;
  border-radius:20px;background:var(--s2);border:1px solid var(--border);
  cursor:pointer;font-size:11px;color:var(--sub);transition:all .15s;flex-shrink:0}
.theme-btn:hover{color:var(--text);border-color:var(--border2)}
.tt-track{width:26px;height:14px;border-radius:20px;background:var(--border);
  position:relative;flex-shrink:0;transition:background .2s}
body.light .tt-track{background:var(--accent)}
.tt-thumb{position:absolute;top:2px;left:2px;width:10px;height:10px;border-radius:50%;
  background:#fff;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}
body.light .tt-thumb{transform:translateX(12px)}

/* ─ PAGE ─ */
.wrap{max-width:830px;margin:0 auto;padding:24px 16px 60px;position:relative;z-index:1}
.page-hero{text-align:center;margin-bottom:26px;padding-top:6px}
.page-hero h1{font-family:'Playfair Display',serif;font-size:28px;font-weight:900;
  margin-bottom:5px;line-height:1.2}
.page-hero p{font-size:13px;color:var(--sub)}

/* ─ MODE CARDS ─ */
.mode-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px}
.mode-card{padding:20px 18px;border-radius:14px;border:2px solid var(--border);
  background:var(--s1);cursor:pointer;transition:all .2s;text-align:left;
  position:relative;overflow:hidden}
.mode-card:hover{background:var(--s2);border-color:var(--border2)}
.mode-card.active{border-color:var(--sel-bd);background:var(--sel-bg)}
.mc-icon{font-size:26px;margin-bottom:10px}
.mc-title{font-size:15px;font-weight:800;margin-bottom:4px}
.mc-sub{font-size:12px;color:var(--sub);line-height:1.5}
.mc-pill{position:absolute;top:12px;right:12px;padding:3px 9px;border-radius:20px;
  font-size:10px;font-weight:700;font-family:'Space Mono',monospace;
  background:var(--sel-bg);border:1px solid var(--sel-bd);color:var(--accent)}
.mode-panel{display:none}
.mode-panel.show{display:block}

/* ─ SETUP CARD ─ */
.setup-card{background:var(--s1);border:1px solid var(--border);border-radius:16px;overflow:hidden}
.setup-head{padding:18px 20px 14px;border-bottom:1px solid var(--border)}
.setup-title{font-size:15px;font-weight:800;margin-bottom:3px}
.setup-sub{font-size:12px;color:var(--sub)}
.setup-body{padding:18px 20px}
.form-row{margin-bottom:18px}
.form-lbl{display:block;font-size:10px;font-weight:700;letter-spacing:.1em;
  text-transform:uppercase;color:var(--dim);margin-bottom:8px;font-family:'Space Mono',monospace}

.subj-grid{display:flex;flex-wrap:wrap;gap:8px}
.subj-chip{display:flex;align-items:center;gap:8px;padding:9px 15px;border-radius:10px;
  border:2px solid var(--border);background:var(--s2);cursor:pointer;
  font-size:13px;font-weight:600;color:var(--sub);transition:all .15s;user-select:none}
.subj-chip:hover{border-color:var(--border2);color:var(--text)}
.subj-chip.selected{background:var(--sel-bg);border-color:var(--sel-bd);color:var(--accent)}
body.light .subj-chip.selected{color:var(--blue)}
.chip-icon{font-size:17px;flex-shrink:0}
.chip-chk{width:16px;height:16px;border-radius:50%;background:var(--accent);
  display:none;align-items:center;justify-content:center;font-size:8px;color:#fff;flex-shrink:0}
.subj-chip.selected .chip-chk{display:flex}
.subj-note{font-size:11px;color:var(--dim);margin-top:7px;font-style:italic}

.opt-btns{display:flex;gap:8px;flex-wrap:wrap}
.ob{padding:7px 15px;border-radius:8px;border:1.5px solid var(--border);
  background:var(--s2);cursor:pointer;font-size:13px;font-weight:600;
  color:var(--sub);transition:all .15s}
.ob:hover{border-color:var(--border2);color:var(--text)}
.ob.on{background:var(--sel-bg);border-color:var(--sel-bd);color:var(--accent)}
body.light .ob.on{color:var(--blue)}

/* ─ TIME INPUT ─ */
.time-wrap{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.time-field{display:flex;align-items:center;background:var(--s2);
  border:1.5px solid var(--border);border-radius:9px;overflow:hidden;
  transition:border-color .15s}
.time-field:focus-within{border-color:var(--sel-bd)}
.time-field input[type=number]{width:68px;padding:9px 10px;background:none;border:none;
  outline:none;color:var(--text);font-family:'Space Mono',monospace;
  font-size:17px;font-weight:700;text-align:center;-moz-appearance:textfield}
.time-field input[type=number]::-webkit-inner-spin-button,
.time-field input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none}
.time-unit{padding:9px 12px 9px 0;color:var(--sub);font-size:12px;font-weight:600;
  font-family:'Space Mono',monospace}
.time-presets{display:flex;gap:6px;flex-wrap:wrap}
.tp{padding:5px 11px;border-radius:7px;border:1px solid var(--border);
  background:var(--s2);cursor:pointer;font-size:12px;font-weight:600;
  color:var(--sub);transition:all .15s}
.tp:hover{border-color:var(--border2);color:var(--text)}
.tp.on{background:var(--sel-bg);border-color:var(--sel-bd);color:var(--accent)}
body.light .tp.on{color:var(--blue)}
.time-hint{font-size:11px;color:var(--dim);margin-top:5px;font-style:italic}

/* ─ START BUTTON ─ */
.start-btn{width:100%;padding:14px;border-radius:12px;border:none;
  background:var(--btn-grad);color:#fff;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;
  cursor:pointer;transition:all .2s;display:flex;align-items:center;
  justify-content:center;gap:9px;box-shadow:0 4px 18px var(--btn-glow);margin-top:20px}
.start-btn:hover:not(:disabled){transform:translateY(-2px);
  box-shadow:0 8px 26px var(--btn-glow)}
.start-btn:disabled{opacity:.45;cursor:not-allowed;transform:none}
.err-msg{display:none;margin-top:12px;padding:10px 14px;border-radius:8px;
  background:var(--bad-bg);border:1px solid var(--bad-bd);color:var(--bad-txt);font-size:13px}

/* ═══════════════════════════════════════
   TEST SCREEN
═══════════════════════════════════════ */
.test-screen{display:none}
.test-hdr{background:var(--s1);border:1px solid var(--border);border-radius:14px;
  padding:13px 15px;margin-bottom:14px;display:flex;align-items:center;
  gap:12px;flex-wrap:wrap}
.test-meta{flex:1;min-width:160px}
.test-title{font-size:13px;font-weight:700;margin-bottom:5px;white-space:nowrap;
  overflow:hidden;text-overflow:ellipsis}
.test-pb{height:5px;border-radius:5px;background:var(--s3);overflow:hidden}
.test-pb-fill{height:100%;background:var(--btn-grad);transition:width .35s}
.test-pb-lbl{font-size:10px;color:var(--dim);margin-top:4px;font-family:'Space Mono',monospace}
.timer-box{text-align:center;flex-shrink:0}
.timer-num{font-family:'Space Mono',monospace;font-size:24px;font-weight:700;
  color:var(--accent);line-height:1;transition:color .3s}
.timer-lbl{font-size:9px;color:var(--dim);text-transform:uppercase;letter-spacing:.08em;margin-top:2px}
.timer-num.warn{color:var(--amber)!important}
.timer-num.danger{color:var(--bad-txt)!important;animation:tp 1s infinite}
@keyframes tp{0%,100%{opacity:1}50%{opacity:.35}}
.hdr-btns{display:flex;gap:7px;flex-shrink:0}
.hdr-btn{display:flex;align-items:center;gap:5px;padding:7px 13px;border-radius:9px;
  border:1px solid var(--border);background:var(--s2);color:var(--sub);
  font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:600;
  cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap}
.hdr-btn:hover{color:var(--text);border-color:var(--border2)}
.hdr-btn.ai{border-color:rgba(59,130,246,.35);background:rgba(59,130,246,.08);color:var(--blue)}
.hdr-btn.submit{border-color:var(--bad-bd);background:var(--bad-bg);color:var(--bad-txt)}

/* ─ QUESTION CARD ─ */
.q-card{background:var(--s1);border:1px solid var(--border);border-radius:14px;
  overflow:hidden;margin-bottom:14px}
.q-card-head{padding:11px 16px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.q-num{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;
  color:var(--accent);background:var(--sel-bg);border:1px solid var(--sel-bd);
  padding:3px 10px;border-radius:20px}
.q-chips{display:flex;gap:6px;flex-wrap:wrap}
.q-chip{font-size:10px;padding:2px 8px;border-radius:20px;
  background:var(--s3);color:var(--dim);font-family:'Space Mono',monospace}
.q-body{padding:18px}
.q-text{font-size:16px;font-weight:600;line-height:1.7;margin-bottom:18px}
.opts{display:flex;flex-direction:column;gap:9px}
.opt{display:flex;align-items:center;gap:11px;padding:13px 15px;border-radius:11px;
  border:2px solid var(--border);background:var(--s2);cursor:pointer;
  text-align:left;width:100%;color:var(--text);
  font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:500;transition:all .18s}
.opt:hover:not(:disabled){border-color:var(--border2);background:var(--s3);transform:translateX(3px)}
.opt:disabled{cursor:default;transform:none}
.opt-l{width:34px;height:34px;border-radius:9px;flex-shrink:0;background:var(--s3);
  border:1px solid var(--border);display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:12px;font-weight:700;
  color:var(--sub);transition:all .18s}
.opt-t{flex:1;line-height:1.5}
.opt-m{margin-left:auto;font-size:15px;opacity:0;transition:opacity .2s;flex-shrink:0}
.opt.correct{border-color:var(--ok-bd);background:var(--ok-bg)}
.opt.correct .opt-l{background:var(--green);color:#fff;border-color:var(--green)}
.opt.correct .opt-m{opacity:1}
.opt.wrong{border-color:var(--bad-bd);background:var(--bad-bg)}
.opt.wrong .opt-l{background:var(--bad-txt);color:#fff;border-color:var(--bad-txt)}
.opt.wrong .opt-m{opacity:1}
.ans-fb{display:none;margin-top:12px;padding:10px 14px;border-radius:9px;
  font-size:13px;font-weight:600;line-height:1.5}
.ans-fb.ok{background:var(--ok-bg);border:1px solid var(--ok-bd);color:var(--ok-txt)}
.ans-fb.bad{background:var(--bad-bg);border:1px solid var(--bad-bd);color:var(--bad-txt)}
.q-nav{display:flex;align-items:center;justify-content:space-between;gap:10px;
  padding-top:14px;border-top:1px solid var(--border);margin-top:14px;flex-wrap:wrap}
.nav-btn{display:flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;
  border:1px solid var(--border);background:var(--s2);color:var(--sub);
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:600;
  cursor:pointer;transition:all .15s}
.nav-btn:hover:not(:disabled){color:var(--text);border-color:var(--border2)}
.nav-btn:disabled{opacity:.35;cursor:not-allowed}
.nav-btn.prim{background:var(--btn-grad);color:#fff;border-color:transparent;
  box-shadow:0 4px 12px var(--btn-glow)}
.nav-btn.prim:hover{filter:brightness(1.07)}
.skip-btn{font-size:12px;color:var(--dim);background:none;border:none;cursor:pointer;
  padding:4px 8px;text-decoration:underline;transition:color .15s}
.skip-btn:hover{color:var(--sub)}

/* ─ GRID ─ */
.grid-card{background:var(--s1);border:1px solid var(--border);border-radius:14px;padding:14px 16px}
.grid-lbl{font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  color:var(--dim);margin-bottom:10px;font-family:'Space Mono',monospace}
.q-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(36px,1fr));gap:6px}
.qdot{width:36px;height:36px;border-radius:8px;border:1.5px solid var(--border);
  background:var(--s2);cursor:pointer;font-family:'Space Mono',monospace;
  font-size:10px;font-weight:700;color:var(--dim);
  display:flex;align-items:center;justify-content:center;transition:all .12s}
.qdot:hover{border-color:var(--border2);color:var(--text)}
.qdot.cur{background:var(--accent);border-color:var(--accent);color:#fff}
.qdot.ans-c{background:var(--ok-bg);border-color:var(--ok-bd);color:var(--ok-txt)}
.qdot.ans-w{background:var(--bad-bg);border-color:var(--bad-bd);color:var(--bad-txt)}
.qdot.ans-s{background:var(--skip-bg);border-color:var(--skip-bd);color:var(--skip-txt)}

/* ═══════════════════════════════════════
   RESULTS SCREEN
═══════════════════════════════════════ */
.results-screen{display:none}
.results-card{background:var(--s1);border:1px solid var(--border);border-radius:16px;overflow:hidden}
.results-hero{padding:28px 20px;text-align:center;background:var(--s2);
  border-bottom:1px solid var(--border);position:relative;overflow:hidden}
.results-hero::before{content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse 80% 55% at 50% -10%,rgba(229,62,62,.09) 0,transparent 60%)}
body.light .results-hero::before{
  background:radial-gradient(ellipse 80% 55% at 50% -10%,rgba(37,99,235,.07) 0,transparent 60%)}
.score-ring{width:130px;height:130px;margin:0 auto 14px;
  display:flex;align-items:center;justify-content:center;position:relative}
.score-ring svg{position:absolute;inset:0;width:100%;height:100%;transform:rotate(-90deg)}
.score-ring circle{fill:none;stroke-width:9;stroke-linecap:round}
.ring-bg{stroke:var(--s3)}
.ring-fill{stroke:var(--ring-stroke);transition:stroke-dashoffset .9s cubic-bezier(.22,1,.36,1)}
.score-pct{font-family:'Playfair Display',serif;font-size:30px;font-weight:900;line-height:1}
.score-sub-lbl{font-size:11px;color:var(--dim);margin-top:2px}
.res-grade{font-size:19px;font-weight:800;margin-bottom:5px}
.res-msg{font-size:13px;color:var(--sub)}
.res-stats{display:flex;border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
.rs{flex:1;padding:14px 8px;text-align:center;border-right:1px solid var(--border)}
.rs:last-child{border-right:none}
.rs-n{font-family:'Space Mono',monospace;font-size:20px;font-weight:700;line-height:1}
.rs-l{font-size:10px;color:var(--dim);margin-top:3px;text-transform:uppercase;letter-spacing:.05em}
.rs.c .rs-n{color:var(--ok-txt)}
.rs.w .rs-n{color:var(--bad-txt)}
.rs.s .rs-n{color:var(--skip-txt)}
.rs.t .rs-n{color:var(--blue)}
.res-actions{padding:15px 18px;display:flex;gap:9px;flex-wrap:wrap}
.ra{flex:1;min-width:120px;padding:11px;border-radius:10px;
  border:1px solid var(--border);background:var(--s2);color:var(--sub);
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:600;
  cursor:pointer;transition:all .15s;display:flex;align-items:center;
  justify-content:center;gap:7px;text-decoration:none}
.ra:hover{color:var(--text);border-color:var(--border2)}
.ra.prim{background:var(--btn-grad);color:#fff;border-color:transparent;
  box-shadow:0 4px 14px var(--btn-glow)}

/* ─ REVIEW ─ */
.review-sec{padding:14px 16px 20px}
.review-sec-title{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
  letter-spacing:.1em;text-transform:uppercase;color:var(--dim);margin-bottom:12px}
.ri{padding:14px;background:var(--s2);border-radius:12px;
  border:1px solid var(--border);margin-bottom:10px}
.ri.ok{border-color:var(--ok-bd)}
.ri.bad{border-color:var(--bad-bd)}
.ri-top{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:7px}
.ri-meta{display:flex;gap:7px;align-items:center}
.ri-num{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:var(--dim)}
.ri-subj{font-size:10px;padding:2px 7px;border-radius:20px;
  background:var(--s3);color:var(--dim);font-family:'Space Mono',monospace}
.ri-year{font-size:10px;color:var(--dim);font-family:'Space Mono',monospace}
.ri-q{font-size:13px;font-weight:600;line-height:1.6;margin-bottom:9px}
.ri-opts-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:9px}
.ri-opt{font-size:11px;padding:5px 9px;border-radius:7px;
  border:1px solid var(--border);background:var(--s1);color:var(--sub);line-height:1.4}
.ri-opt.correct-opt{border-color:var(--ok-bd);background:var(--ok-bg);
  color:var(--ok-txt);font-weight:700}
.ri-opt.wrong-opt{border-color:var(--bad-bd);background:var(--bad-bg);color:var(--bad-txt)}
.ri-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.ri-tags{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.ri-tag{padding:3px 8px;border-radius:6px;font-family:'Space Mono',monospace;
  font-size:10px;font-weight:700}
.ri-tag.yc{background:var(--ok-bg);border:1px solid var(--ok-bd);color:var(--ok-txt)}
.ri-tag.yw{background:var(--bad-bg);border:1px solid var(--bad-bd);color:var(--bad-txt)}
.ri-tag.ca{background:var(--ok-bg);border:1px solid var(--ok-bd);color:var(--ok-txt)}
.ri-tag.sk{background:var(--skip-bg);border:1px solid var(--skip-bd);color:var(--skip-txt)}
.ri-ai-btn{display:flex;align-items:center;gap:5px;padding:5px 11px;border-radius:8px;
  border:1px solid rgba(59,130,246,.35);background:rgba(59,130,246,.08);
  color:var(--blue);font-size:11px;font-weight:700;cursor:pointer;
  font-family:'Plus Jakarta Sans',sans-serif;transition:all .15s;white-space:nowrap}
.ri-ai-btn:hover{background:rgba(59,130,246,.16);border-color:rgba(59,130,246,.55)}

/* ═══════════════════════════════════════
   AI EXPLAIN BOTTOM SHEET
═══════════════════════════════════════ */
.ai-ov{display:none;position:fixed;inset:0;z-index:100;
  background:rgba(0,0,0,.7);backdrop-filter:blur(8px);
  align-items:flex-end;justify-content:center}
.ai-ov.show{display:flex}
.ai-sheet{width:100%;max-width:740px;max-height:88vh;background:var(--s1);
  border:1px solid var(--border2);border-bottom:none;
  border-radius:18px 18px 0 0;display:flex;flex-direction:column;overflow:hidden;
  animation:slideup .32s cubic-bezier(.22,1,.36,1) both}
@keyframes slideup{from{transform:translateY(100%);opacity:0}to{transform:translateY(0);opacity:1}}
.ai-handle{display:flex;justify-content:center;padding:8px 0 4px;flex-shrink:0}
.ai-handle-bar{width:38px;height:4px;border-radius:4px;background:var(--s3)}
.ai-head{padding:10px 18px;border-bottom:1px solid var(--border);
  display:flex;align-items:flex-start;gap:10px;flex-shrink:0}
.ai-head-icon{width:36px;height:36px;border-radius:10px;
  background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px}
.ai-head-info{flex:1}
.ai-head-name{font-size:14px;font-weight:700;margin-bottom:2px}
.ai-head-sub{font-size:11px;color:var(--sub)}
.ai-x{width:28px;height:28px;border-radius:8px;background:var(--s2);
  border:1px solid var(--border);color:var(--sub);cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:11px;transition:all .15s}
.ai-x:hover{color:var(--text)}
.ai-q-box{margin:10px 16px;padding:12px;background:var(--s2);border-radius:10px;
  font-size:13px;font-weight:600;line-height:1.6;border:1px solid var(--border)}
.ai-body{flex:1;overflow-y:auto;padding:4px 16px 16px}
.ai-body::-webkit-scrollbar{width:4px}
.ai-body::-webkit-scrollbar-thumb{background:var(--s3);border-radius:3px}
.ai-resp{font-size:14px;color:var(--sub);line-height:1.82;white-space:pre-wrap}
.ai-load{display:flex;align-items:center;gap:9px;padding:16px 0;color:var(--dim)}
.adot{width:8px;height:8px;border-radius:50%;background:var(--blue);animation:adotb .9s infinite}
.adot:nth-child(2){animation-delay:.15s}
.adot:nth-child(3){animation-delay:.3s}
@keyframes adotb{0%,80%,100%{transform:scale(.7);opacity:.5}40%{transform:scale(1);opacity:1}}

/* ─ LOADING ─ */
.load-ov{display:none;position:fixed;inset:0;z-index:90;
  background:rgba(12,4,5,.9);backdrop-filter:blur(6px);
  align-items:center;justify-content:center;flex-direction:column;gap:14px}
body.light .load-ov{background:rgba(242,232,217,.9)}
.load-ov.show{display:flex}
.loader{width:44px;height:44px;border-radius:50%;
  border:3px solid var(--s3);border-top-color:var(--accent);animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.load-msg{font-size:13px;font-weight:600;color:var(--sub);max-width:260px;text-align:center}

/* ─ RESPONSIVE ─ */
@media(max-width:620px){
  .mode-row{grid-template-columns:1fr}
  .test-hdr{padding:11px 12px}
  .q-body{padding:14px}
  .q-text{font-size:14.5px}
  .opt{padding:11px 12px}
  .ri-opts-grid{grid-template-columns:1fr}
  .wrap{padding:16px 12px 60px}
  .tb-name,.t-btn span{display:none}
}
</style>
</head>
<body>

<!-- Loading -->
<div class="load-ov" id="loadOv">
  <div class="loader"></div>
  <div class="load-msg" id="loadMsg">Loading questions…</div>
</div>

<!-- AI Explain Sheet -->
<div class="ai-ov" id="aiOv">
  <div class="ai-sheet">
    <div class="ai-handle"><div class="ai-handle-bar"></div></div>
    <div class="ai-head">
      <div class="ai-head-icon">🤖</div>
      <div class="ai-head-info">
        <div class="ai-head-name" id="aiName">Question Explanation</div>
        <div class="ai-head-sub">Powered by Groq · Llama 3.1 · JAMB Tutor</div>
      </div>
      <button class="ai-x" onclick="closeAI()"><i class="fa fa-xmark"></i></button>
    </div>
    <div class="ai-q-box" id="aiQBox"></div>
    <div class="ai-body">
      <div class="ai-load" id="aiLoad">
        <div class="adot"></div><div class="adot"></div><div class="adot"></div>
        <span style="font-size:13px">AI Tutor is thinking…</span>
      </div>
      <div class="ai-resp" id="aiResp" style="display:none"></div>
    </div>
  </div>
</div>

<!-- Topbar -->
<nav class="topbar">
  <div class="tb-brand">
    <div class="tb-logo">ES</div>
    <span class="tb-name">PRACTICE TEST</span>
  </div>
  <div class="tb-right">
    <a href="../ai/ai_helper.php" class="t-btn ai" target="_blank">
      <i class="fa fa-robot" style="font-size:11px"></i>
      <span>AI Tutor</span>
    </a>
    <button class="theme-btn" onclick="toggleTheme()">
      <span id="themeIco">🌙</span>
      <div class="tt-track"><div class="tt-thumb"></div></div>
    </button>
    <div class="user-chip">
      <?php if($dp):?><img src="<?=htmlspecialchars($dp)?>" alt=""><?php else:?>
      <div class="user-av"><?=htmlspecialchars(mb_strtoupper(mb_substr($dn,0,1)))?></div>
      <?php endif;?>
      <span><?=htmlspecialchars($dn)?></span>
    </div>
    <a href="../dashboard.php" class="t-btn" title="Dashboard"><i class="fa fa-house"></i></a>
  </div>
</nav>

<div class="wrap">

<!-- ══ SETUP SCREEN ══ -->
<div id="setupScreen">
  <div class="page-hero">
    <h1>📚 Practice Test</h1>
    <p>Live ALOC past questions — JAMB · WAEC · NECO</p>
  </div>

  <div class="mode-row">
    <div class="mode-card active" id="modeQuick" onclick="setMode('quick')">
      <div class="mc-icon">🎯</div>
      <div class="mc-title">Quick Practice</div>
      <div class="mc-sub">One subject, drill specific topics, perfect for targeted revision.</div>
      <div class="mc-pill">Default: 30 min</div>
    </div>
    <div class="mode-card" id="modeJamb" onclick="setMode('jamb')">
      <div class="mc-icon">🏆</div>
      <div class="mc-title">JAMB Style</div>
      <div class="mc-sub">Multi-subject full simulation, just like the real UTME exam.</div>
      <div class="mc-pill">Default: 2 hours</div>
    </div>
  </div>

  <!-- QUICK PANEL -->
  <div class="mode-panel show" id="panelQ">
    <div class="setup-card">
      <div class="setup-head">
        <div class="setup-title">🎯 Quick Practice</div>
        <div class="setup-sub">Pick one subject and start drilling with past questions.</div>
      </div>
      <div class="setup-body">
        <div class="form-row">
          <label class="form-lbl">Choose Subject</label>
          <div class="subj-grid" id="qSubjGrid"></div>
        </div>
        <div class="form-row">
          <label class="form-lbl">Exam Type</label>
          <div class="opt-btns" id="qTypeRow">
            <button class="ob on" data-v="utme" onclick="pickOpt(this,'qTypeRow','qType')">JAMB UTME</button>
            <button class="ob" data-v="wassce" onclick="pickOpt(this,'qTypeRow','qType')">WAEC SSCE</button>
            <button class="ob" data-v="neco" onclick="pickOpt(this,'qTypeRow','qType')">NECO</button>
          </div>
        </div>
        <div class="form-row">
          <label class="form-lbl">Questions</label>
          <div class="opt-btns" id="qCountRow">
            <button class="ob" data-v="10" onclick="pickOpt(this,'qCountRow','qCount')">10</button>
            <button class="ob" data-v="20" onclick="pickOpt(this,'qCountRow','qCount')">20</button>
            <button class="ob on" data-v="30" onclick="pickOpt(this,'qCountRow','qCount')">30</button>
            <button class="ob" data-v="40" onclick="pickOpt(this,'qCountRow','qCount')">40</button>
          </div>
        </div>
        <div class="form-row">
          <label class="form-lbl">Time Allowed</label>
          <div class="time-wrap">
            <div class="time-field">
              <input type="number" id="qTimeIn" value="30" min="5" max="120" step="5"
                oninput="onTimeInput('q')">
              <span class="time-unit">mins</span>
            </div>
            <div class="time-presets" id="qTimePresets">
              <span class="tp on" data-m="30" onclick="setTP(30,'q')">30m</span>
              <span class="tp" data-m="45" onclick="setTP(45,'q')">45m</span>
              <span class="tp" data-m="60" onclick="setTP(60,'q')">1h</span>
              <span class="tp" data-m="90" onclick="setTP(90,'q')">1h 30m</span>
            </div>
          </div>
          <div class="time-hint">Default is 30 min for single-subject practice</div>
        </div>
        <div class="err-msg" id="qErr"></div>
        <button class="start-btn" id="qStartBtn" onclick="startQuick()" disabled>
          <i class="fa fa-play" style="font-size:12px"></i>
          <span id="qStartLbl">Select a subject first</span>
        </button>
      </div>
    </div>
  </div>

  <!-- JAMB PANEL -->
  <div class="mode-panel" id="panelJ">
    <div class="setup-card">
      <div class="setup-head">
        <div class="setup-title">🏆 JAMB Style</div>
        <div class="setup-sub">Select 2–4 subjects. Questions are split equally and shuffled together.</div>
      </div>
      <div class="setup-body">
        <div class="form-row">
          <label class="form-lbl">Select Subjects (2–4)</label>
          <div class="subj-grid" id="jSubjGrid"></div>
          <div class="subj-note" id="jSubjNote">Select between 2 and 4 subjects</div>
        </div>
        <div class="form-row">
          <label class="form-lbl">Exam Type</label>
          <div class="opt-btns" id="jTypeRow">
            <button class="ob on" data-v="utme" onclick="pickOpt(this,'jTypeRow','jType')">JAMB UTME</button>
            <button class="ob" data-v="wassce" onclick="pickOpt(this,'jTypeRow','jType')">WAEC SSCE</button>
            <button class="ob" data-v="neco" onclick="pickOpt(this,'jTypeRow','jType')">NECO</button>
          </div>
        </div>
        <div class="form-row">
          <label class="form-lbl">Total Questions</label>
          <div class="opt-btns" id="jCountRow">
            <button class="ob" data-v="40" onclick="pickOpt(this,'jCountRow','jCount')">40</button>
            <button class="ob on" data-v="60" onclick="pickOpt(this,'jCountRow','jCount')">60</button>
            <button class="ob" data-v="80" onclick="pickOpt(this,'jCountRow','jCount')">80</button>
          </div>
          <div class="time-hint" id="jCountNote">20 per subject when 3 are selected</div>
        </div>
        <div class="form-row">
          <label class="form-lbl">Time Allowed</label>
          <div class="time-wrap">
            <div class="time-field">
              <input type="number" id="jTimeIn" value="120" min="30" max="180" step="15"
                oninput="onTimeInput('j')">
              <span class="time-unit">mins</span>
            </div>
            <div class="time-presets" id="jTimePresets">
              <span class="tp" data-m="60" onclick="setTP(60,'j')">1h</span>
              <span class="tp" data-m="90" onclick="setTP(90,'j')">1h 30m</span>
              <span class="tp on" data-m="120" onclick="setTP(120,'j')">2h</span>
              <span class="tp" data-m="180" onclick="setTP(180,'j')">3h</span>
            </div>
          </div>
          <div class="time-hint">Default is 2 hours for multi-subject JAMB style</div>
        </div>
        <div class="err-msg" id="jErr"></div>
        <button class="start-btn" id="jStartBtn" onclick="startJamb()" disabled>
          <i class="fa fa-play" style="font-size:12px"></i>
          <span id="jStartLbl">Select 2–4 subjects first</span>
        </button>
      </div>
    </div>
  </div>
</div><!-- /setup -->

<!-- ══ TEST SCREEN ══ -->
<div class="test-screen" id="testScreen">
  <div class="test-hdr">
    <div class="test-meta">
      <div class="test-title" id="testTitle">Mathematics · JAMB UTME</div>
      <div class="test-pb"><div class="test-pb-fill" id="pbFill" style="width:0%"></div></div>
      <div class="test-pb-lbl" id="pbLbl">Question 1 of 30</div>
    </div>
    <div class="timer-box">
      <div class="timer-num" id="timerNum">30:00</div>
      <div class="timer-lbl">Time Left</div>
    </div>
    <div class="hdr-btns">
      <a href="../ai/ai_helper.php" target="_blank" class="hdr-btn ai">
        <i class="fa fa-robot" style="font-size:10px"></i> Ask AI
      </a>
      <button class="hdr-btn submit" onclick="confirmSubmit()">
        <i class="fa fa-flag-checkered" style="font-size:10px"></i> Submit
      </button>
    </div>
  </div>

  <div class="q-card">
    <div class="q-card-head">
      <span class="q-num" id="qNumBadge">Q1</span>
      <div class="q-chips">
        <span class="q-chip" id="qSubjChip">Subject</span>
        <span class="q-chip" id="qYearChip" style="display:none"></span>
      </div>
    </div>
    <div class="q-body">
      <div class="q-text" id="qText">Loading…</div>
      <div class="opts" id="optsArea"></div>
      <div class="ans-fb" id="ansFb"></div>
      <div class="q-nav">
        <button class="nav-btn" id="prevBtn" onclick="goTo(cidx-1)" disabled>
          <i class="fa fa-chevron-left" style="font-size:10px"></i> Prev
        </button>
        <button class="skip-btn" id="skipBtn" onclick="skipQ()">Skip →</button>
        <button class="nav-btn prim" id="nextBtn" onclick="handleNext()">
          Next <i class="fa fa-chevron-right" style="font-size:10px"></i>
        </button>
      </div>
    </div>
  </div>

  <div class="grid-card">
    <div class="grid-lbl">Question Navigator — click to jump</div>
    <div class="q-grid" id="qGrid"></div>
  </div>
</div>

<!-- ══ RESULTS SCREEN ══ -->
<div class="results-screen" id="resultsScreen">
  <div class="results-card">
    <div class="results-hero">
      <div class="score-ring">
        <svg viewBox="0 0 130 130">
          <circle class="ring-bg" cx="65" cy="65" r="56"/>
          <circle class="ring-fill" id="ringFill" cx="65" cy="65" r="56"
                  stroke-dasharray="352" stroke-dashoffset="352"/>
        </svg>
        <div>
          <div class="score-pct" id="scorePct">0%</div>
          <div class="score-sub-lbl">Score</div>
        </div>
      </div>
      <div class="res-grade" id="resGrade"></div>
      <div class="res-msg" id="resMsg"></div>
    </div>
    <div class="res-stats">
      <div class="rs c"><div class="rs-n" id="rsC">0</div><div class="rs-l">✓ Correct</div></div>
      <div class="rs w"><div class="rs-n" id="rsW">0</div><div class="rs-l">✗ Wrong</div></div>
      <div class="rs s"><div class="rs-n" id="rsS">0</div><div class="rs-l">— Skipped</div></div>
      <div class="rs t"><div class="rs-n" id="rsT">0</div><div class="rs-l">Total</div></div>
    </div>
    <div class="res-actions">
      <button class="ra prim" onclick="retakeTest()"><i class="fa fa-rotate-right" style="font-size:11px"></i> Retake</button>
      <button class="ra" onclick="newTest()"><i class="fa fa-plus" style="font-size:11px"></i> New Test</button>
      <a href="../ai/ai_helper.php" class="ra"><i class="fa fa-robot" style="font-size:11px"></i> AI Tutor</a>
      <a href="../dashboard.php" class="ra"><i class="fa fa-house" style="font-size:11px"></i> Dashboard</a>
    </div>
    <div class="review-sec">
      <div class="review-sec-title">Full Review — All Questions &amp; Answers</div>
      <div id="reviewList"></div>
    </div>
  </div>
</div>

</div><!-- /wrap -->

<script>
/* ══ DATA ══ */
const SUBJECTS=<?=$subjJson?>;
const ICONS={'Mathematics':'📐','Physics':'⚡','Chemistry':'⚗️','Biology':'🧬','English Language':'📖'};

/* ══ STATE ══ */
let mode='quick';
let qSubject='', jSubjects=[], qType='utme', jType='utme';
let qCount=30, jCount=60, qTime=30, jTime=120;
let questions=[], cidx=0, answers={}, timerSecs=0, timerInt=null, retakeSecs=0;

/* ══ THEME ══ */
(function(){
  if(localStorage.getItem('es_theme')==='light')document.body.classList.add('light');
  syncThemeIco();
})();
function toggleTheme(){
  document.body.classList.toggle('light');
  localStorage.setItem('es_theme',document.body.classList.contains('light')?'light':'dark');
  syncThemeIco();
}
function syncThemeIco(){
  document.getElementById('themeIco').textContent=
    document.body.classList.contains('light')?'☀️':'🌙';
}

/* ══ BUILD SUBJECT GRIDS ══ */
function buildGrids(){
  ['qSubjGrid','jSubjGrid'].forEach((gid,i)=>{
    const multi=i>0;
    const el=document.getElementById(gid);
    el.innerHTML='';
    SUBJECTS.forEach(s=>{
      const d=document.createElement('div');
      d.className='subj-chip'; d.dataset.s=s;
      d.innerHTML=`<span class="chip-icon">${ICONS[s]||'📚'}</span>${s}<span class="chip-chk"><i class="fa fa-check" style="font-size:7px"></i></span>`;
      d.onclick=()=>multi?jToggle(s):qPick(s);
      el.appendChild(d);
    });
  });
}

/* ══ MODE ══ */
function setMode(m){
  mode=m;
  document.getElementById('modeQuick').classList.toggle('active',m==='quick');
  document.getElementById('modeJamb').classList.toggle('active',m==='jamb');
  document.getElementById('panelQ').classList.toggle('show',m==='quick');
  document.getElementById('panelJ').classList.toggle('show',m==='jamb');
}

/* ══ QUICK ══ */
function qPick(s){
  qSubject=s;
  document.querySelectorAll('#qSubjGrid .subj-chip').forEach(c=>c.classList.toggle('selected',c.dataset.s===s));
  document.getElementById('qStartBtn').disabled=false;
  document.getElementById('qStartLbl').textContent=`Start ${s} Test`;
}

async function startQuick(){
  if(!qSubject)return;
  const err=document.getElementById('qErr'); err.style.display='none';
  showLoad(`Loading ${qSubject} questions…`);
  try{
    const r=await fetch(`practice_test.php?action=fetch_questions&subject=${encodeURIComponent(qSubject)}&exam_type=${qType}&count=${qCount}`);
    const j=await r.json(); hideLoad();
    if(!j.success){err.textContent=j.error||'Failed. Try again.';err.style.display='block';return;}
    questions=j.questions;
    timerSecs=qTime*60; retakeSecs=timerSecs;
    const tname={utme:'JAMB UTME',wassce:'WAEC SSCE',neco:'NECO'}[qType]||qType;
    launchTest(`${qSubject} · ${tname}`);
  }catch(e){hideLoad();err.textContent='Network error.';err.style.display='block';}
}

/* ══ JAMB ══ */
function jToggle(s){
  if(jSubjects.includes(s)) jSubjects=jSubjects.filter(x=>x!==s);
  else if(jSubjects.length<4) jSubjects.push(s);
  document.querySelectorAll('#jSubjGrid .subj-chip').forEach(c=>c.classList.toggle('selected',jSubjects.includes(c.dataset.s)));
  updateJambUI();
}
function updateJambUI(){
  const n=jSubjects.length;
  document.getElementById('jSubjNote').textContent=
    n===0?'Select between 2 and 4 subjects':
    n===1?'⚠️ Add at least one more subject':
    `${n} subject${n>1?'s':''} selected`;
  document.getElementById('jStartBtn').disabled=n<2;
  document.getElementById('jStartLbl').textContent=
    n<2?'Select 2–4 subjects first':`Start ${n}-Subject JAMB Test`;
  if(n>1){
    const per=Math.round(jCount/n);
    document.getElementById('jCountNote').textContent=`${per} questions per subject (${n} subjects)`;
  }
}

async function startJamb(){
  if(jSubjects.length<2)return;
  const err=document.getElementById('jErr'); err.style.display='none';
  const per=Math.ceil(jCount/jSubjects.length);
  showLoad(`Loading ${jSubjects.length} subjects in parallel…`);
  try{
    const fetches=jSubjects.map(s=>
      fetch(`practice_test.php?action=fetch_questions&subject=${encodeURIComponent(s)}&exam_type=${jType}&count=${per}`)
        .then(r=>r.json())
    );
    const results=await Promise.all(fetches);
    hideLoad();
    let all=[];
    for(let i=0;i<results.length;i++){
      if(!results[i].success){
        err.textContent=`${jSubjects[i]}: ${results[i].error||'Failed'}`;
        err.style.display='block'; return;
      }
      all=all.concat(results[i].questions);
    }
    // Shuffle
    for(let i=all.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[all[i],all[j]]=[all[j],all[i]];}
    questions=all;
    timerSecs=jTime*60; retakeSecs=timerSecs;
    const tname={utme:'JAMB UTME',wassce:'WAEC SSCE',neco:'NECO'}[jType]||jType;
    launchTest(`${jSubjects.join(' · ')} · ${tname}`);
  }catch(e){hideLoad();err.textContent='Network error.';err.style.display='block';}
}

/* ══ OPTION PICKERS ══ */
const state={qType:'utme',jType:'utme',qCount:30,jCount:60};
function pickOpt(el,rowId,key){
  document.querySelectorAll(`#${rowId} .ob`).forEach(b=>b.classList.remove('on'));
  el.classList.add('on');
  const v=el.dataset.v;
  state[key]=v;
  if(key==='qType') qType=v;
  else if(key==='jType') jType=v;
  else if(key==='qCount') qCount=parseInt(v);
  else if(key==='jCount'){jCount=parseInt(v);updateJambUI();}
}
function setTP(m,which){
  const id=which==='q'?'qTimeIn':'jTimeIn';
  document.getElementById(id).value=m;
  if(which==='q') qTime=m; else jTime=m;
  syncTPs(which);
}
function onTimeInput(which){
  const id=which==='q'?'qTimeIn':'jTimeIn';
  const v=parseInt(document.getElementById(id).value)||30;
  if(which==='q') qTime=Math.max(5,Math.min(120,v));
  else jTime=Math.max(30,Math.min(180,v));
  syncTPs(which);
}
function syncTPs(which){
  const id=which==='q'?'qTimeIn':'jTimeIn';
  const v=parseInt(document.getElementById(id).value);
  const presetsId=which==='q'?'qTimePresets':'jTimePresets';
  document.querySelectorAll(`#${presetsId} .tp`).forEach(p=>{
    p.classList.toggle('on',parseInt(p.dataset.m)===v);
  });
}

/* ══ LAUNCH TEST ══ */
function launchTest(title){
  cidx=0; answers={};
  hide('setupScreen'); show('testScreen'); hide('resultsScreen');
  document.getElementById('testTitle').textContent=title;
  buildQGrid(); renderQ(0); startTimer();
}

/* ══ TIMER ══ */
function startTimer(){
  clearInterval(timerInt);
  renderTimer();
  timerInt=setInterval(()=>{timerSecs--;renderTimer();if(timerSecs<=0){clearInterval(timerInt);submitTest();}},1000);
}
function renderTimer(){
  const el=document.getElementById('timerNum');
  const m=Math.floor(timerSecs/60),s=timerSecs%60;
  el.textContent=`${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
  el.className='timer-num'+(timerSecs<=60?' danger':timerSecs<=180?' warn':'');
}

/* ══ RENDER QUESTION ══ */
function renderQ(idx){
  cidx=idx;
  const q=questions[idx];
  document.getElementById('qNumBadge').textContent=`Q${idx+1}`;
  document.getElementById('qSubjChip').textContent=q.subject||'';
  const yr=document.getElementById('qYearChip');
  if(q.year){yr.textContent=q.year;yr.style.display='';}else yr.style.display='none';
  document.getElementById('pbFill').style.width=Math.round((idx+1)/questions.length*100)+'%';
  document.getElementById('pbLbl').textContent=`Question ${idx+1} of ${questions.length}`;
  document.getElementById('prevBtn').disabled=idx===0;
  document.getElementById('qText').textContent=q.question||'(No question text)';

  const area=document.getElementById('optsArea'); area.innerHTML='';
  const ans=answers[idx];
  ['A','B','C','D'].forEach(l=>{
    const val=q['option_'+l.toLowerCase()];
    if(!val)return;
    const btn=document.createElement('button');
    btn.type='button'; btn.className='opt'; btn.dataset.l=l;
    btn.innerHTML=`<div class="opt-l">${l}</div><div class="opt-t">${esc(val)}</div><span class="opt-m"></span>`;
    if(ans&&!ans.skipped){
      btn.disabled=true;
      if(l===q.correct_answer){btn.classList.add('correct');btn.querySelector('.opt-m').textContent='✓';}
      else if(l===ans.chosen){btn.classList.add('wrong');btn.querySelector('.opt-m').textContent='✗';}
    } else btn.addEventListener('click',()=>submitAns(l));
    area.appendChild(btn);
  });

  const fb=document.getElementById('ansFb');
  const nb=document.getElementById('nextBtn');
  if(ans&&!ans.skipped){
    fb.style.display='block';
    fb.className='ans-fb '+(ans.isCorrect?'ok':'bad');
    fb.innerHTML=ans.isCorrect?'✓ <strong>Correct!</strong> Well done.':
      `✗ <strong>Wrong.</strong> Correct answer: <strong>Option ${esc(q.correct_answer)}</strong>`;
    document.getElementById('skipBtn').style.display='none';
    nb.innerHTML=idx<questions.length-1?'Next <i class="fa fa-chevron-right" style="font-size:10px"></i>':
      '<i class="fa fa-flag-checkered" style="font-size:10px"></i> See Results';
    nb.className='nav-btn prim';
  } else {
    fb.style.display='none';
    document.getElementById('skipBtn').style.display='';
    nb.innerHTML='Next <i class="fa fa-chevron-right" style="font-size:10px"></i>';
    nb.className='nav-btn';
  }
  syncGrid();
}

function submitAns(letter){
  const q=questions[cidx];
  const ok=letter.toUpperCase()===(q.correct_answer||'').toUpperCase();
  answers[cidx]={chosen:letter,correct:q.correct_answer,isCorrect:ok,skipped:false};
  renderQ(cidx);
}
function skipQ(){
  answers[cidx]={chosen:null,correct:questions[cidx].correct_answer,isCorrect:false,skipped:true};
  syncGrid();
  if(cidx<questions.length-1)goTo(cidx+1);else submitTest();
}
function handleNext(){
  if(answers[cidx]){if(cidx<questions.length-1)goTo(cidx+1);else submitTest();}
}
function goTo(idx){if(idx>=0&&idx<questions.length)renderQ(idx);}

/* ══ GRID ══ */
function buildQGrid(){
  const g=document.getElementById('qGrid'); g.innerHTML='';
  questions.forEach((_,i)=>{
    const d=document.createElement('div');
    d.className='qdot'; d.id='qd'+i; d.textContent=i+1;
    d.addEventListener('click',()=>goTo(i));
    g.appendChild(d);
  });
  syncGrid();
}
function syncGrid(){
  questions.forEach((_,i)=>{
    const d=document.getElementById('qd'+i); if(!d)return;
    const a=answers[i];
    d.className='qdot'+(i===cidx?' cur':'')
      +(a?a.skipped?' ans-s':a.isCorrect?' ans-c':' ans-w':'');
  });
}

/* ══ SUBMIT & RESULTS ══ */
function confirmSubmit(){
  const u=questions.length-Object.keys(answers).length;
  if(u>0&&!confirm(`${u} unanswered question${u>1?'s':''}. Submit anyway?`))return;
  submitTest();
}
function submitTest(){
  clearInterval(timerInt);
  let c=0,w=0,s=0;
  questions.forEach((_,i)=>{const a=answers[i];if(!a||a.skipped)s++;else if(a.isCorrect)c++;else w++;});
  const pct=Math.round(c/questions.length*100);
  const fd=new FormData();
  fd.append('action','save_score');fd.append('score',c);fd.append('total',questions.length);
  fetch('practice_test.php',{method:'POST',body:fd}).catch(()=>{});
  showResults(c,w,s,pct);
}

function showResults(c,w,s,pct){
  hide('testScreen'); show('resultsScreen');
  const circ=2*Math.PI*56; // ≈352
  setTimeout(()=>{document.getElementById('ringFill').style.strokeDashoffset=circ*(1-pct/100);},120);
  document.getElementById('scorePct').textContent=pct+'%';
  document.getElementById('rsC').textContent=c;
  document.getElementById('rsW').textContent=w;
  document.getElementById('rsS').textContent=s;
  document.getElementById('rsT').textContent=questions.length;

  const[grade,msg,col]=
    pct>=80?['A1 — Distinction 🏆','Outstanding! You are exam-ready.','var(--ok-txt)']:
    pct>=70?['B2 — Upper Credit 🎉','Great work! Push for Distinction.','var(--ok-txt)']:
    pct>=60?['C4 — Credit ✅','Solid! Review your mistakes.','var(--blue)']:
    pct>=50?['C6 — Pass 👍','You passed! Drill weaker areas.','var(--amber)']:
    pct>=40?['D7 — Near Pass 📚','Almost there! Keep studying.','var(--skip-txt)']:
            ['F9 — Fail 📖','Don\'t give up — practice wins.','var(--bad-txt)'];
  document.getElementById('resGrade').textContent=grade;
  document.getElementById('resGrade').style.color=col;
  document.getElementById('resMsg').textContent=msg;

  // Full review with all options shown
  const list=document.getElementById('reviewList'); list.innerHTML='';
  questions.forEach((q,i)=>{
    const a=answers[i];
    const isC=a&&!a.skipped&&a.isCorrect;
    const div=document.createElement('div');
    div.className='ri '+(isC?'ok':'bad');

    let optsHtml='';
    ['A','B','C','D'].forEach(l=>{
      const val=q['option_'+l.toLowerCase()];
      if(!val)return;
      const isAns=l===q.correct_answer;
      const isYour=a&&l===a.chosen&&!a.skipped;
      const cls=isAns?'correct-opt':isYour&&!isC?'wrong-opt':'';
      optsHtml+=`<div class="ri-opt ${cls}"><strong>${l}.</strong> ${esc(val)}${isAns?' ✓':''}</div>`;
    });

    const yTag=!a||a.skipped
      ?'<span class="ri-tag sk">Skipped</span>'
      :`<span class="ri-tag ${a.isCorrect?'yc':'yw'}">Your: ${esc(a.chosen||'?')}</span>`;

    const qd={q:q.question,a:q.option_a||'',b:q.option_b||'',c:q.option_c||'',
      d:q.option_d||'',ans:q.correct_answer||'',subj:q.subject||''};

    div.innerHTML=`
      <div class="ri-top">
        <div class="ri-meta">
          <span class="ri-num">Q${i+1}</span>
          <span class="ri-subj">${esc(q.subject||'')}</span>
          ${q.year?`<span class="ri-year">${esc(q.year)}</span>`:''}
        </div>
      </div>
      <div class="ri-q">${esc(q.question)}</div>
      <div class="ri-opts-grid">${optsHtml}</div>
      <div class="ri-foot">
        <div class="ri-tags">
          ${yTag}
          <span class="ri-tag ca">✓ Answer: ${esc(q.correct_answer)}</span>
        </div>
        <button class="ri-ai-btn" data-qd='${JSON.stringify(qd).replace(/'/g,"&#39;")}'>
          <i class="fa fa-robot" style="font-size:10px"></i> Explain
        </button>
      </div>`;
    list.appendChild(div);
  });

  // Attach AI explain clicks
  document.querySelectorAll('.ri-ai-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
      try{openAI(JSON.parse(btn.dataset.qd));}catch(e){}
    });
  });

  window.scrollTo({top:0,behavior:'smooth'});
}

function retakeTest(){
  answers={}; cidx=0; timerSecs=retakeSecs;
  hide('resultsScreen');
  launchTest(document.getElementById('testTitle').textContent);
}
function newTest(){
  clearInterval(timerInt);
  hide('testScreen'); hide('resultsScreen'); show('setupScreen');
  document.querySelectorAll('.subj-chip').forEach(c=>c.classList.remove('selected'));
  qSubject=''; jSubjects=[];
  document.getElementById('qStartBtn').disabled=true;
  document.getElementById('qStartLbl').textContent='Select a subject first';
  document.getElementById('jStartBtn').disabled=true;
  document.getElementById('jStartLbl').textContent='Select 2–4 subjects first';
  updateJambUI();
}

/* ══ AI EXPLAIN ══ */
function openAI(data){
  document.getElementById('aiName').textContent=(data.subj||'Exam')+' — AI Explanation';
  document.getElementById('aiQBox').textContent=data.q;
  document.getElementById('aiLoad').style.display='flex';
  document.getElementById('aiResp').style.display='none';
  document.getElementById('aiResp').textContent='';
  document.getElementById('aiOv').classList.add('show');
  document.body.style.overflow='hidden';
  callGroq(data);
}
async function callGroq(data){
  const question=`You are an expert ${data.subj||'JAMB'} teacher for Nigerian UTME/WAEC students.

A student answered this past-question:
Question: ${data.q}
A: ${data.a}
B: ${data.b}
C: ${data.c}
D: ${data.d}
Correct Answer: Option ${data.ans}

Explain clearly:
1. WHY Option ${data.ans} is correct — full explanation of the concept
2. What topic/formula is being tested
3. Why the wrong options are wrong (briefly)
4. A quick memory tip for this type of question

Keep it clear, exam-focused, and student-friendly.`;

  try{
    const r=await fetch('../ai/textbook_ai.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({question})
    });
    const d=await r.json();
    document.getElementById('aiLoad').style.display='none';
    document.getElementById('aiResp').style.display='block';
    document.getElementById('aiResp').textContent=
      d.answer||('⚠️ '+(d.error||'No response returned.'));
  }catch(e){
    document.getElementById('aiLoad').style.display='none';
    document.getElementById('aiResp').style.display='block';
    document.getElementById('aiResp').textContent='⚠️ Network error: '+e.message;
  }
}
function closeAI(){
  document.getElementById('aiOv').classList.remove('show');
  document.body.style.overflow='';
}
document.getElementById('aiOv').addEventListener('click',e=>{
  if(e.target===document.getElementById('aiOv'))closeAI();
});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAI();});

/* ══ UTILS ══ */
function show(id){document.getElementById(id).style.display='block';}
function hide(id){document.getElementById(id).style.display='none';}
function showLoad(m){document.getElementById('loadMsg').textContent=m;document.getElementById('loadOv').classList.add('show');}
function hideLoad(){document.getElementById('loadOv').classList.remove('show');}
function esc(s){return s?String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[c])):''}

buildGrids();
</script>
</body>
</html>
