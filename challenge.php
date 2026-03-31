<?php
// challenge.php — Real-time 2-Player Challenge Panel
// Place at ~/excellent-academy/challenge.php
error_reporting(E_ERROR|E_PARSE); ini_set('display_errors','0');
session_start();
require_once __DIR__.'/config/db.php';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if (!$user_id) { header('Location: login.php?redirect=challenge.php'); exit; }

$s = $conn->prepare("SELECT username,google_name,google_picture,points FROM users WHERE id=? LIMIT 1");
$me = [];
if ($s){ $s->bind_param('i',$user_id); $s->execute(); $me=$s->get_result()->fetch_assoc()??[]; $s->close(); }
$my_name    = $_SESSION['google_name'] ?? $me['google_name'] ?? $me['username'] ?? 'Player';
$my_pic     = $_SESSION['google_picture'] ?? $me['google_picture'] ?? null;
$my_points  = (float)($me['points'] ?? 0);

define('ALOC_TOKEN','QB-b67089074cbb68438091');
define('ALOC_BASE','https://questions.aloc.com.ng/api/v2');
define('SECS_PER_Q', 10);
define('PTS_PER_Q', 1.25);

// ── DB SETUP ──────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS challenges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code CHAR(6) NOT NULL UNIQUE,
  creator_id INT NOT NULL,
  opponent_id INT DEFAULT NULL,
  subject VARCHAR(80) NOT NULL,
  exam_type VARCHAR(20) DEFAULT 'utme',
  q_count INT DEFAULT 10,
  questions_json MEDIUMTEXT DEFAULT NULL,
  status ENUM('waiting','active','finished') DEFAULT 'waiting',
  started_at DATETIME DEFAULT NULL,
  finished_at DATETIME DEFAULT NULL,
  winner_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS challenge_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  challenge_id INT NOT NULL,
  user_id INT NOT NULL,
  q_index INT NOT NULL,
  chosen CHAR(1) DEFAULT NULL,
  is_correct TINYINT DEFAULT 0,
  answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ans (challenge_id,user_id,q_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$SUBJECTS = [
  'Mathematics'     => ['slug'=>'mathematics',  'icon'=>'📐','color'=>'#4f8ef7'],
  'Physics'         => ['slug'=>'physics',       'icon'=>'⚡','color'=>'#f59e0b'],
  'Chemistry'       => ['slug'=>'chemistry',     'icon'=>'⚗️','color'=>'#00c98a'],
  'Biology'         => ['slug'=>'biology',       'icon'=>'🧬','color'=>'#a78bfa'],
  'English Language'=> ['slug'=>'english',       'icon'=>'📖','color'=>'#f43f5e'],
];

// ── ALOC FETCH ────────────────────────────────────────────────
function aloc_fetch(string $slug, string $type, int $n): array {
  $qs=[]; $seen=[]; $att=0; $max=$n*6;
  while(count($qs)<$n && $att<$max){
    $att++;
    $ch=curl_init(ALOC_BASE.'/q?subject='.urlencode($slug).'&type='.urlencode($type));
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,
      CURLOPT_HTTPHEADER=>['AccessToken: '.ALOC_TOKEN,'Accept: application/json'],
      CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_FOLLOWLOCATION=>true]);
    $raw=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    if(!$raw||$code!==200){usleep(120000);continue;}
    $d=json_decode($raw,true)['data']??null;
    if(!$d){usleep(80000);continue;}
    $id=$d['id']??uniqid();
    if(in_array($id,$seen,true)){usleep(40000);continue;}
    $seen[]=$id;
    $qs[]=['id'=>$id,'question'=>$d['question']??'',
      'a'=>$d['option']['a']??$d['a']??'','b'=>$d['option']['b']??$d['b']??'',
      'c'=>$d['option']['c']??$d['c']??'','d'=>$d['option']['d']??$d['d']??'',
      'answer'=>strtoupper(trim($d['answer']??'')),'year'=>$d['year']??''];
    usleep(80000);
  }
  return $qs;
}

// ══════════════════════════════════════════════════════════════
//  AJAX ACTIONS
// ══════════════════════════════════════════════════════════════
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($action) { header('Content-Type: application/json; charset=utf-8'); }

// ── CREATE CHALLENGE ──
if ($action === 'create') {
  $subj  = trim($_POST['subject'] ?? '');
  $etype = trim($_POST['exam_type'] ?? 'utme');
  if (!isset($SUBJECTS[$subj])) { echo json_encode(['ok'=>false,'error'=>'Invalid subject']); exit; }
  $slug = $SUBJECTS[$subj]['slug'];
  // Generate unique 6-char code
  do {
    $code = strtoupper(substr(md5(uniqid(mt_rand(),true)),0,6));
    $ck = $conn->query("SELECT id FROM challenges WHERE code='$code' LIMIT 1");
  } while ($ck && $ck->num_rows > 0);
  // Fetch questions now (so both players get same set)
  $qs = aloc_fetch($slug, $etype, 10);
  if (count($qs) < 3) { echo json_encode(['ok'=>false,'error'=>'Could not load questions. Try again.']); exit; }
  $qj = $conn->real_escape_string(json_encode($qs));
  $conn->query("INSERT INTO challenges (code,creator_id,subject,exam_type,q_count,questions_json) VALUES ('$code',$user_id,'".addslashes($subj)."','$etype',".count($qs).",'$qj')");
  $cid = $conn->insert_id;
  echo json_encode(['ok'=>true,'code'=>$code,'challenge_id'=>$cid,'q_count'=>count($qs)]); exit;
}

// ── JOIN CHALLENGE ──
if ($action === 'join') {
  $code = strtoupper(trim($_POST['code'] ?? ''));
  $r = $conn->query("SELECT * FROM challenges WHERE code='$code' LIMIT 1");
  $ch = $r ? $r->fetch_assoc() : null;
  if (!$ch) { echo json_encode(['ok'=>false,'error'=>'Challenge code not found. Check and try again.']); exit; }
  if ($ch['status'] !== 'waiting') { echo json_encode(['ok'=>false,'error'=>'This challenge has already started or finished.']); exit; }
  if ((int)$ch['creator_id'] === $user_id) { echo json_encode(['ok'=>false,'error'=>'You cannot join your own challenge. Share the code with a friend!']); exit; }
  if ($ch['opponent_id']) { echo json_encode(['ok'=>false,'error'=>'This challenge already has two players.']); exit; }
  // Join + start
  $now = date('Y-m-d H:i:s');
  $conn->query("UPDATE challenges SET opponent_id=$user_id,status='active',started_at='$now' WHERE id={$ch['id']}");
  $oppr = $conn->query("SELECT google_name,username,google_picture,points FROM users WHERE id={$ch['creator_id']} LIMIT 1");
  $opp  = $oppr ? $oppr->fetch_assoc() : [];
  echo json_encode(['ok'=>true,'challenge_id'=>(int)$ch['id'],'code'=>$code,
    'subject'=>$ch['subject'],'q_count'=>(int)$ch['q_count'],
    'opponent_name'=>$opp['google_name']??$opp['username']??'Challenger',
    'opponent_pic' =>$opp['google_picture']??null,
    'started_at'   =>$now]);
  exit;
}

// ── POLL (get current game state) ──
if ($action === 'poll') {
  $cid = (int)($_GET['challenge_id'] ?? 0);
  $r = $conn->query("SELECT c.*,
    u1.google_name AS p1_name, u1.username AS p1_uname, u1.google_picture AS p1_pic, u1.points AS p1_pts,
    u2.google_name AS p2_name, u2.username AS p2_uname, u2.google_picture AS p2_pic, u2.points AS p2_pts
    FROM challenges c
    LEFT JOIN users u1 ON u1.id=c.creator_id
    LEFT JOIN users u2 ON u2.id=c.opponent_id
    WHERE c.id=$cid LIMIT 1");
  $ch = $r ? $r->fetch_assoc() : null;
  if (!$ch) { echo json_encode(['ok'=>false,'error'=>'Challenge not found']); exit; }
  // Check if user is part of this challenge
  if ((int)$ch['creator_id']!==$user_id && (int)$ch['opponent_id']!==$user_id) {
    echo json_encode(['ok'=>false,'error'=>'You are not part of this challenge']); exit;
  }

  $elapsed = $ch['started_at'] ? (time() - strtotime($ch['started_at'])) : 0;
  $q_count = (int)$ch['q_count'];
  $total_secs = $q_count * SECS_PER_Q;
  $cur_q_idx  = min($q_count-1, (int)floor($elapsed / SECS_PER_Q));
  $q_secs_elapsed = $elapsed % SECS_PER_Q;
  $secs_left  = max(0, SECS_PER_Q - $q_secs_elapsed);
  $is_over    = ($elapsed >= $total_secs) || $ch['status'] === 'finished';

  // Grab answers
  $ar = $conn->query("SELECT user_id,q_index,is_correct FROM challenge_answers WHERE challenge_id=$cid");
  $answers = []; while($row=$ar->fetch_assoc()) $answers[]=$row;

  // Scores
  $p1_score=0; $p2_score=0;
  foreach($answers as $a){
    if((int)$a['is_correct']){
      if((int)$a['user_id']===(int)$ch['creator_id'])  $p1_score += PTS_PER_Q;
      if((int)$a['user_id']===(int)$ch['opponent_id']) $p2_score += PTS_PER_Q;
    }
  }

  // My answered indices
  $my_answers=[];
  foreach($answers as $a){ if((int)$a['user_id']===$user_id) $my_answers[(int)$a['q_index']]=(int)$a['is_correct']; }
  $my_answered_q = array_key_exists($cur_q_idx, $my_answers);
  $opp_answered_q = false;
  $opp_id = ((int)$ch['creator_id']===$user_id) ? (int)$ch['opponent_id'] : (int)$ch['creator_id'];
  foreach($answers as $a){ if((int)$a['user_id']===$opp_id && (int)$a['q_index']===$cur_q_idx) $opp_answered_q=true; }

  // Finish if time is up and not yet finished
  if ($is_over && $ch['status']==='active') {
    $winner_id = $p1_score>$p2_score ? (int)$ch['creator_id'] : ((int)$ch['opponent_id'] && $p2_score>$p1_score ? (int)$ch['opponent_id'] : 0);
    $conn->query("UPDATE challenges SET status='finished',finished_at=NOW(),winner_id=".($winner_id?:0)." WHERE id=$cid AND status='active'");
    if ($winner_id) {
      // Transfer points
      $loser_id = $winner_id===(int)$ch['creator_id'] ? (int)$ch['opponent_id'] : (int)$ch['creator_id'];
      $w_pts = $winner_id===(int)$ch['creator_id'] ? $p1_score : $p2_score;
      $l_pts = $winner_id===(int)$ch['creator_id'] ? $p2_score : $p1_score;
      $total_won = round($w_pts + $l_pts, 2);
      $conn->query("UPDATE users SET points=points+$total_won WHERE id=$winner_id");
      if ($l_pts > 0) $conn->query("UPDATE users SET points=GREATEST(0,points-$l_pts) WHERE id=$loser_id");
    }
    $ch['status']='finished'; $ch['winner_id']=$winner_id?:null;
  }

  // Get questions (strip answers for active games)
  $qs = json_decode($ch['questions_json']??'[]',true);
  $safe_qs = [];
  foreach($qs as $i=>$q){
    $safe = ['i'=>$i,'q'=>$q['question'],'a'=>$q['a'],'b'=>$q['b'],'c'=>$q['c'],'d'=>$q['d'],'year'=>$q['year']??''];
    if($ch['status']==='finished' || isset($my_answers[$i])) $safe['answer']=$q['answer'];
    $safe_qs[]=$safe;
  }

  echo json_encode([
    'ok'=>true,
    'status'       => $ch['status'],
    'elapsed'      => $elapsed,
    'cur_q'        => $cur_q_idx,
    'secs_left'    => $secs_left,
    'q_count'      => $q_count,
    'questions'    => $safe_qs,
    'p1_id'        => (int)$ch['creator_id'],
    'p1_name'      => $ch['p1_name']??$ch['p1_uname']??'P1',
    'p1_pic'       => $ch['p1_pic'],
    'p1_score'     => $p1_score,
    'p2_id'        => (int)($ch['opponent_id']??0),
    'p2_name'      => $ch['p2_name']??$ch['p2_uname']??'Waiting…',
    'p2_pic'       => $ch['p2_pic'],
    'p2_score'     => $p2_score,
    'winner_id'    => (int)($ch['winner_id']??0),
    'my_answers'   => $my_answers,
    'my_answered_q'=> $my_answered_q,
    'opp_answered_q'=> $opp_answered_q,
    'subject'      => $ch['subject'],
    'started_at'   => $ch['started_at'],
  ]);
  exit;
}

// ── SUBMIT ANSWER ──
if ($action === 'answer') {
  $cid   = (int)($_POST['challenge_id'] ?? 0);
  $qidx  = (int)($_POST['q_index'] ?? 0);
  $chose = strtoupper(trim($_POST['chosen'] ?? ''));
  if (!in_array($chose,['A','B','C','D'])) { echo json_encode(['ok'=>false,'error'=>'Invalid answer']); exit; }
  $r = $conn->query("SELECT questions_json,status,started_at,q_count FROM challenges WHERE id=$cid LIMIT 1");
  $ch=$r?$r->fetch_assoc():null;
  if(!$ch||$ch['status']!=='active'){echo json_encode(['ok'=>false,'error'=>'Challenge not active']);exit;}
  // Validate question is current or past (within window)
  $elapsed=time()-strtotime($ch['started_at']);
  $cur=min((int)$ch['q_count']-1,(int)floor($elapsed/SECS_PER_Q));
  // Allow answering current or any unanswered past question
  $qs=json_decode($ch['questions_json'],true);
  $q=$qs[$qidx]??null;
  if(!$q){echo json_encode(['ok'=>false,'error'=>'Question not found']);exit;}
  $correct=$chose===$q['answer']?1:0;
  $conn->query("INSERT IGNORE INTO challenge_answers (challenge_id,user_id,q_index,chosen,is_correct)
    VALUES ($cid,$user_id,$qidx,'$chose',$correct)");
  echo json_encode(['ok'=>true,'correct'=>$correct,'answer'=>$q['answer']]);
  exit;
}

// ── CHECK CODE (waiting room poll) ──
if ($action === 'check_code') {
  $code=strtoupper(trim($_GET['code']??''));
  $r=$conn->query("SELECT c.*,u.google_name,u.username,u.google_picture FROM challenges c
    LEFT JOIN users u ON u.id=c.opponent_id WHERE c.code='$code' LIMIT 1");
  $ch=$r?$r->fetch_assoc():null;
  if(!$ch){echo json_encode(['ok'=>false,'error'=>'Not found']);exit;}
  echo json_encode(['ok'=>true,'status'=>$ch['status'],
    'challenge_id'=>(int)$ch['id'],
    'opponent_name'=>($ch['google_name']??$ch['username']??null),
    'opponent_pic' =>$ch['google_picture']??null,
    'started_at'   =>$ch['started_at'],
    'q_count'      =>(int)$ch['q_count'],
    'subject'      =>$ch['subject']]);
  exit;
}

// ── CANCEL CHALLENGE ──
if ($action === 'cancel') {
  $cid=(int)($_POST['challenge_id']??0);
  $conn->query("DELETE FROM challenges WHERE id=$cid AND creator_id=$user_id AND status='waiting'");
  echo json_encode(['ok'=>true]); exit;
}

$sj=json_encode($SUBJECTS,JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Challenge Arena — Excellent Simplified</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Sora:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#060810;--s1:#0c1020;--s2:#111828;--s3:#161e30;--s4:#1c2438;
  --border:rgba(255,255,255,.07);--border2:rgba(255,255,255,.14);
  --accent:#00c98a;--blue:#4f8ef7;--amber:#f59e0b;--danger:#f43f5e;--purple:#a78bfa;
  --text:#eef2ff;--sub:#8896b3;--dim:#3d4f6e;
  --gold:#ffd700;--gold-glow:rgba(255,215,0,.35);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;overflow-x:hidden}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
  background:
    radial-gradient(ellipse 65% 45% at 15% 0%,rgba(0,201,138,.07) 0,transparent 60%),
    radial-gradient(ellipse 50% 40% at 85% 100%,rgba(79,142,247,.06) 0,transparent 55%)}

/* ── TOPBAR ── */
.topbar{position:sticky;top:0;z-index:50;height:58px;background:rgba(6,8,16,.95);
  backdrop-filter:blur(20px);border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 18px;gap:11px}
.tb-logo{width:32px;height:32px;border-radius:9px;
  background:linear-gradient(135deg,var(--accent),var(--blue));
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:#000;
  flex-shrink:0;box-shadow:0 3px 14px rgba(0,201,138,.3)}
.tb-title{font-family:'Sora',sans-serif;font-size:15px;font-weight:800}
.tb-sub{font-size:11px;color:var(--sub)}
.tb-flex{flex:1}
.tb-right{display:flex;gap:8px;align-items:center}
.t-btn{height:32px;padding:0 12px;border-radius:8px;background:var(--s2);
  border:1px solid var(--border);color:var(--sub);cursor:pointer;
  display:flex;align-items:center;gap:7px;font-family:'Plus Jakarta Sans',sans-serif;
  font-size:12px;font-weight:600;text-decoration:none;transition:all .15s;flex-shrink:0}
.t-btn:hover{color:var(--text);border-color:var(--border2)}
.user-chip{display:flex;align-items:center;gap:7px;padding:4px 11px;
  background:var(--s2);border:1px solid var(--border);border-radius:20px;
  font-size:12px;font-weight:600;flex-shrink:0}
.user-chip img{width:22px;height:22px;border-radius:6px;object-fit:cover}
.user-av{width:22px;height:22px;border-radius:6px;
  background:linear-gradient(135deg,var(--accent),var(--blue));
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:#000}

/* ── PAGE WRAP ── */
.wrap{max-width:780px;margin:0 auto;padding:24px 16px 70px;position:relative;z-index:1}

/* ══════════════════════════════════
   LOBBY SCREEN
══════════════════════════════════ */
.screen{display:none}
.screen.show{display:block}

.hero{text-align:center;padding:8px 0 32px}
.hero-badge{display:inline-flex;align-items:center;gap:7px;padding:5px 14px;
  border-radius:20px;border:1px solid rgba(255,215,0,.3);background:rgba(255,215,0,.07);
  font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
  letter-spacing:.1em;text-transform:uppercase;color:var(--gold);margin-bottom:14px}
.hero h1{font-family:'Sora',sans-serif;font-size:clamp(26px,6vw,44px);
  font-weight:900;line-height:1.1;margin-bottom:10px;
  background:linear-gradient(135deg,#fff 30%,var(--gold));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hero p{font-size:14px;color:var(--sub);max-width:420px;margin:0 auto}

/* Rules strip */
.rules-strip{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:28px}
.rule-card{background:var(--s1);border:1px solid var(--border);border-radius:12px;
  padding:14px 12px;text-align:center}
.rule-icon{font-size:24px;margin-bottom:7px}
.rule-title{font-size:12px;font-weight:700;margin-bottom:3px}
.rule-sub{font-size:11px;color:var(--sub)}

/* Mode cards */
.mode-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:28px}
.mode-card{padding:22px 18px;border-radius:14px;border:2px solid var(--border);
  background:var(--s1);cursor:pointer;transition:all .22s;position:relative;overflow:hidden}
.mode-card::before{content:'';position:absolute;inset:0;
  background:var(--mc-color,var(--accent));opacity:0;transition:opacity .2s}
.mode-card:hover::before{opacity:.06}
.mode-card:hover{border-color:var(--mc-color,var(--accent));transform:translateY(-2px)}
.mc-icon{font-size:28px;margin-bottom:10px}
.mc-title{font-family:'Sora',sans-serif;font-size:16px;font-weight:800;margin-bottom:5px}
.mc-desc{font-size:12px;color:var(--sub);line-height:1.55}
.mc-arrow{position:absolute;top:14px;right:14px;color:var(--dim);font-size:12px;
  transition:transform .2s}
.mode-card:hover .mc-arrow{transform:translateX(4px);color:var(--sub)}

/* ── CREATE / JOIN FORM ── */
.form-card{background:var(--s1);border:1px solid var(--border);border-radius:16px;overflow:hidden}
.fc-head{padding:18px 20px 14px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:11px}
.fc-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;
  justify-content:center;font-size:18px;flex-shrink:0}
.fc-title{font-family:'Sora',sans-serif;font-size:16px;font-weight:800}
.fc-sub{font-size:12px;color:var(--sub);margin-top:2px}
.fc-body{padding:18px 20px}
.form-row{margin-bottom:16px}
.form-lbl{font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  color:var(--dim);margin-bottom:8px;display:block;font-family:'Space Mono',monospace}

/* Subject chips */
.subj-row{display:flex;flex-wrap:wrap;gap:8px}
.sc{display:flex;align-items:center;gap:7px;padding:9px 14px;border-radius:9px;
  border:2px solid var(--border);background:var(--s2);cursor:pointer;
  font-size:13px;font-weight:600;color:var(--sub);transition:all .15s;user-select:none}
.sc:hover{border-color:var(--border2);color:var(--text)}
.sc.sel{background:rgba(0,201,138,.1);border-color:rgba(0,201,138,.4);color:var(--accent)}
.sc-emoji{font-size:16px}

/* Type buttons */
.type-row{display:flex;gap:7px;flex-wrap:wrap}
.tb{padding:7px 14px;border-radius:8px;border:1.5px solid var(--border);
  background:var(--s2);cursor:pointer;font-size:13px;font-weight:600;
  color:var(--sub);transition:all .15s}
.tb:hover{border-color:var(--border2);color:var(--text)}
.tb.on{background:rgba(79,142,247,.1);border-color:rgba(79,142,247,.35);color:var(--blue)}

/* Code input */
.code-input-wrap{position:relative}
.code-input{width:100%;padding:14px 16px;border-radius:10px;
  background:var(--s2);border:2px solid var(--border);color:var(--text);
  font-family:'Space Mono',monospace;font-size:24px;font-weight:700;
  letter-spacing:.2em;text-align:center;text-transform:uppercase;outline:none;
  transition:border-color .15s}
.code-input:focus{border-color:rgba(79,142,247,.5)}

/* Buttons */
.btn-main{width:100%;padding:14px;border-radius:11px;border:none;
  background:linear-gradient(135deg,var(--accent),var(--blue));color:#000;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;
  cursor:pointer;transition:all .2s;display:flex;align-items:center;
  justify-content:center;gap:9px;box-shadow:0 4px 18px rgba(0,201,138,.3);margin-top:18px}
.btn-main:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 8px 26px rgba(0,201,138,.4)}
.btn-main:disabled{opacity:.45;cursor:not-allowed;transform:none}
.btn-ghost{width:100%;padding:11px;border-radius:10px;border:1px solid var(--border);
  background:var(--s2);color:var(--sub);font-family:'Plus Jakarta Sans',sans-serif;
  font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;margin-top:10px;
  display:flex;align-items:center;justify-content:center;gap:7px}
.btn-ghost:hover{color:var(--text);border-color:var(--border2)}
.err-msg{padding:10px 14px;border-radius:8px;background:rgba(244,63,94,.08);
  border:1px solid rgba(244,63,94,.2);color:var(--danger);font-size:13px;
  margin-top:12px;display:none}
.err-msg.show{display:block}

/* ══════════════════════════════════
   WAITING ROOM
══════════════════════════════════ */
.waiting-card{background:var(--s1);border:1px solid var(--border);
  border-radius:16px;overflow:hidden;text-align:center}
.wc-head{padding:22px 20px 16px;border-bottom:1px solid var(--border)}
.wc-head-title{font-family:'Sora',sans-serif;font-size:18px;font-weight:800;margin-bottom:4px}
.wc-head-sub{font-size:12px;color:var(--sub)}
.wc-body{padding:28px 20px}
.wc-code-label{font-size:11px;color:var(--sub);margin-bottom:10px}
.wc-code{font-family:'Space Mono',monospace;font-size:38px;font-weight:700;
  color:var(--gold);letter-spacing:.25em;margin-bottom:16px;
  text-shadow:0 0 30px var(--gold-glow)}
.wc-share{font-size:13px;color:var(--sub);margin-bottom:20px;line-height:1.6}
.wc-share strong{color:var(--text)}
.wc-pulse{display:flex;align-items:center;justify-content:center;gap:10px;
  margin-bottom:18px;color:var(--sub);font-size:13px}
.pulse-dot{width:10px;height:10px;border-radius:50%;background:var(--amber);
  animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.8)}}
.opp-joined{display:none;padding:14px 18px;border-radius:11px;margin:14px 0;
  background:rgba(0,201,138,.08);border:1px solid rgba(0,201,138,.25)}
.opp-joined.show{display:flex;align-items:center;gap:10px}
.oj-av{width:36px;height:36px;border-radius:10px;object-fit:cover;flex-shrink:0}
.oj-name{font-size:13px;font-weight:700;color:var(--text)}
.oj-sub{font-size:11px;color:var(--accent)}

/* ══════════════════════════════════
   GAME SCREEN
══════════════════════════════════ */
.game-screen{}

/* VS header */
.vs-bar{background:var(--s1);border:1px solid var(--border);border-radius:14px;
  padding:14px 16px;margin-bottom:14px;display:grid;
  grid-template-columns:1fr auto 1fr;align-items:center;gap:10px}
.vs-player{display:flex;align-items:center;gap:9px}
.vs-player.right{flex-direction:row-reverse;text-align:right}
.vsp-av{width:38px;height:38px;border-radius:10px;object-fit:cover;
  flex-shrink:0;border:2px solid var(--border)}
.vsp-av.mine{border-color:var(--accent)}
.vsp-name{font-size:13px;font-weight:700;line-height:1.2}
.vsp-score{font-family:'Space Mono',monospace;font-size:18px;font-weight:700;color:var(--accent)}
.vsp-ptspp{font-size:10px;color:var(--sub)}
.vs-mid{text-align:center}
.vs-badge{font-family:'Sora',sans-serif;font-size:14px;font-weight:900;
  color:var(--gold);text-shadow:0 0 14px var(--gold-glow)}
.vs-prog-row{margin-top:6px;height:3px;border-radius:3px;
  background:var(--s3);overflow:hidden}
.vs-prog-fill{height:100%;border-radius:3px;
  background:linear-gradient(90deg,var(--accent),var(--blue));transition:width .4s}

/* Timer */
.timer-wrap{text-align:center;margin-bottom:14px}
.timer-ring{position:relative;width:80px;height:80px;margin:0 auto}
.timer-ring svg{position:absolute;inset:0;transform:rotate(-90deg)}
.timer-ring circle{fill:none;stroke-width:6;stroke-linecap:round}
.tr-bg{stroke:var(--s3)}
.tr-fill{stroke:var(--accent);transition:stroke-dashoffset .1s linear}
.tr-danger{stroke:var(--danger)}
.timer-num{position:absolute;inset:0;display:flex;align-items:center;
  justify-content:center;font-family:'Space Mono',monospace;
  font-size:22px;font-weight:700;color:var(--accent)}
.timer-num.danger{color:var(--danger);animation:tpulse 1s infinite}
@keyframes tpulse{0%,100%{opacity:1}50%{opacity:.4}}
.q-progress-lbl{font-size:11px;color:var(--sub);margin-top:7px;
  font-family:'Space Mono',monospace;text-align:center}

/* Question card */
.q-card{background:var(--s1);border:1px solid var(--border);
  border-radius:14px;overflow:hidden;margin-bottom:14px}
.q-card-top{padding:11px 16px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between}
.q-badge{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;
  padding:3px 10px;border-radius:20px;
  background:rgba(0,201,138,.1);border:1px solid rgba(0,201,138,.2);color:var(--accent)}
.q-opp-status{font-size:11px;color:var(--sub);display:flex;align-items:center;gap:5px}
.opp-dot{width:7px;height:7px;border-radius:50%;background:var(--amber)}
.opp-dot.done{background:var(--accent)}
.q-body{padding:18px}
.q-text{font-size:16px;font-weight:600;line-height:1.7;margin-bottom:18px}
.q-opts{display:flex;flex-direction:column;gap:9px}
.q-opt{display:flex;align-items:center;gap:11px;padding:13px 15px;border-radius:11px;
  border:2px solid var(--border);background:var(--s2);cursor:pointer;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:500;
  text-align:left;width:100%;color:var(--text);transition:all .18s}
.q-opt:hover:not(:disabled){border-color:var(--border2);background:var(--s3);transform:translateX(3px)}
.q-opt:disabled{cursor:default;transform:none}
.q-opt-l{width:34px;height:34px;border-radius:9px;flex-shrink:0;
  background:var(--s3);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:12px;font-weight:700;
  color:var(--sub);transition:all .18s}
.q-opt-t{flex:1;line-height:1.5}
.q-opt-m{margin-left:auto;font-size:15px;opacity:0;transition:opacity .18s;flex-shrink:0}
.q-opt.correct{border-color:rgba(0,201,138,.45);background:rgba(0,201,138,.07)}
.q-opt.correct .q-opt-l{background:var(--accent);color:#000;border-color:var(--accent)}
.q-opt.correct .q-opt-m{opacity:1}
.q-opt.wrong{border-color:rgba(244,63,94,.45);background:rgba(244,63,94,.07)}
.q-opt.wrong .q-opt-l{background:var(--danger);color:#fff;border-color:var(--danger)}
.q-opt.wrong .q-opt-m{opacity:1}
.q-opt.selected-pending{border-color:rgba(245,158,11,.4);background:rgba(245,158,11,.07)}
.q-opt.selected-pending .q-opt-l{background:var(--amber);color:#000;border-color:var(--amber)}

/* Answered stamp */
.answered-stamp{margin:8px 16px 14px;padding:9px 14px;border-radius:9px;
  font-size:13px;font-weight:700;display:none}
.answered-stamp.correct{background:rgba(0,201,138,.08);border:1px solid rgba(0,201,138,.25);color:var(--accent)}
.answered-stamp.wrong{background:rgba(244,63,94,.07);border:1px solid rgba(244,63,94,.2);color:var(--danger)}
.answered-stamp.skip{background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.2);color:var(--amber)}
.answered-stamp.show{display:block}

/* Opponent answered indicator */
.opp-answered-bar{margin:0 16px 10px;padding:8px 12px;border-radius:8px;
  background:rgba(79,142,247,.07);border:1px solid rgba(79,142,247,.2);
  font-size:12px;color:var(--blue);display:none;align-items:center;gap:7px}
.opp-answered-bar.show{display:flex}

/* ══════════════════════════════════
   RESULTS SCREEN
══════════════════════════════════ */
.results-screen{}
.result-card{background:var(--s1);border:1px solid var(--border);border-radius:16px;overflow:hidden}
.res-hero{padding:30px 20px;text-align:center;
  background:linear-gradient(135deg,var(--s2),var(--s1));
  border-bottom:1px solid var(--border);position:relative;overflow:hidden}
.res-hero::before{content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse 80% 55% at 50% -10%,rgba(255,215,0,.1) 0,transparent 55%)}
.trophy{font-size:56px;margin-bottom:12px;
  animation:trophyBounce .6s cubic-bezier(.34,1.56,.64,1) both;display:inline-block}
@keyframes trophyBounce{from{opacity:0;transform:scale(.4)}to{opacity:1;transform:scale(1)}}
.res-outcome{font-family:'Sora',sans-serif;font-size:26px;font-weight:900;
  margin-bottom:6px;line-height:1.1}
.res-sub{font-size:13px;color:var(--sub);margin-bottom:20px}
.res-vs-row{display:grid;grid-template-columns:1fr auto 1fr;
  align-items:center;gap:12px;max-width:440px;margin:0 auto}
.res-player{text-align:center;padding:14px 12px;border-radius:12px;
  background:var(--s2);border:1px solid var(--border)}
.res-player.winner{border-color:var(--gold);background:rgba(255,215,0,.07);
  animation:glow 2s ease infinite}
@keyframes glow{0%,100%{box-shadow:0 0 0 0 var(--gold-glow)}50%{box-shadow:0 0 20px 4px var(--gold-glow)}}
.res-av{width:48px;height:48px;border-radius:12px;object-fit:cover;margin:0 auto 8px}
.res-name{font-size:13px;font-weight:700;margin-bottom:4px}
.res-score{font-family:'Space Mono',monospace;font-size:22px;font-weight:700;color:var(--accent)}
.res-pts{font-size:11px;color:var(--sub);margin-top:2px}
.vs-chip{font-family:'Space Mono',monospace;font-size:13px;font-weight:700;
  color:var(--dim);padding:8px 12px;border-radius:8px;background:var(--s3)}

/* Points transfer banner */
.pts-transfer{margin:16px 18px;padding:14px 16px;border-radius:12px;text-align:center;
  background:rgba(255,215,0,.06);border:1px solid rgba(255,215,0,.2)}
.pts-transfer-title{font-family:'Sora',sans-serif;font-size:15px;font-weight:800;
  color:var(--gold);margin-bottom:4px}
.pts-transfer-sub{font-size:12px;color:var(--sub)}
.pts-arrow{font-size:24px;margin:6px 0}

/* Review */
.res-review{padding:14px 18px 20px}
.rr-title{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
  letter-spacing:.1em;text-transform:uppercase;color:var(--dim);margin-bottom:12px}
.rr-item{display:flex;gap:10px;padding:12px;border-radius:10px;
  background:var(--s2);border:1px solid var(--border);margin-bottom:8px}
.rri-num{font-family:'Space Mono',monospace;font-size:10px;color:var(--dim);
  flex-shrink:0;padding-top:2px;width:22px}
.rri-q{font-size:12.5px;font-weight:600;line-height:1.5;margin-bottom:5px}
.rri-row{display:flex;gap:6px;flex-wrap:wrap}
.rri-tag{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
  padding:2px 8px;border-radius:6px}
.rri-tag.yours-c{background:rgba(0,201,138,.1);border:1px solid rgba(0,201,138,.25);color:var(--accent)}
.rri-tag.yours-w{background:rgba(244,63,94,.08);border:1px solid rgba(244,63,94,.2);color:var(--danger)}
.rri-tag.correct{background:rgba(0,201,138,.1);border:1px solid rgba(0,201,138,.25);color:var(--accent)}
.rri-tag.skip{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);color:var(--amber)}
.res-actions{padding:14px 18px;display:flex;gap:9px;flex-wrap:wrap;border-top:1px solid var(--border)}
.ra{flex:1;min-width:120px;padding:11px;border-radius:10px;
  border:1px solid var(--border);background:var(--s2);color:var(--sub);
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:600;
  cursor:pointer;transition:all .15s;display:flex;align-items:center;
  justify-content:center;gap:7px;text-decoration:none}
.ra:hover{color:var(--text);border-color:var(--border2)}
.ra.prim{background:linear-gradient(135deg,var(--gold),#d97706);
  color:#000;border-color:transparent;box-shadow:0 4px 14px var(--gold-glow)}

/* Loading overlay */
.load-ov{display:none;position:fixed;inset:0;z-index:90;
  background:rgba(6,8,16,.9);backdrop-filter:blur(6px);
  align-items:center;justify-content:center;flex-direction:column;gap:14px}
.load-ov.show{display:flex}
.loader{width:44px;height:44px;border-radius:50%;
  border:3px solid var(--s3);border-top-color:var(--accent);animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.load-msg{font-size:13px;color:var(--sub);font-weight:600;text-align:center;max-width:240px}

/* Toast */
.toast{position:fixed;bottom:22px;right:20px;z-index:200;
  padding:10px 16px;border-radius:10px;font-size:13px;font-weight:600;
  display:none;align-items:center;gap:8px;box-shadow:0 4px 22px rgba(0,0,0,.5)}
.toast.show{display:flex}
.toast.ok{background:rgba(0,201,138,.14);border:1px solid rgba(0,201,138,.3);color:var(--accent)}
.toast.err{background:rgba(244,63,94,.12);border:1px solid rgba(244,63,94,.3);color:var(--danger)}
.toast.info{background:rgba(79,142,247,.12);border:1px solid rgba(79,142,247,.3);color:var(--blue)}

/* Confetti particle (pure CSS) */
.confetti-wrap{position:fixed;inset:0;pointer-events:none;z-index:80;overflow:hidden}
.confetti-piece{position:absolute;width:9px;height:9px;border-radius:2px;
  animation:confettiFall linear forwards}
@keyframes confettiFall{
  0%{opacity:1;transform:translateY(-20px) rotate(0)}
  100%{opacity:0;transform:translateY(110vh) rotate(720deg)}
}

/* Mobile */
@media(max-width:600px){
  .rules-strip{grid-template-columns:1fr;gap:8px}
  .mode-row{grid-template-columns:1fr}
  .vs-bar{grid-template-columns:1fr auto 1fr;gap:6px;padding:11px 12px}
  .vsp-name{font-size:11px}
  .vsp-score{font-size:16px}
  .q-text{font-size:14.5px}
  .q-opt{padding:11px 12px;font-size:13px}
  .res-vs-row{grid-template-columns:1fr auto 1fr;gap:8px}
  .topbar{padding:0 13px}
  .tb-sub,.user-chip span{display:none}
  .t-btn span{display:none}
  .t-btn{width:32px;height:32px;padding:0;justify-content:center}
  .wc-code{font-size:30px}
  .timer-ring{width:70px;height:70px}
}
</style>
</head>
<body>

<!-- Loading overlay -->
<div class="load-ov" id="loadOv">
  <div class="loader"></div>
  <div class="load-msg" id="loadMsg">Creating challenge…</div>
</div>
<!-- Toast -->
<div class="toast" id="toast"></div>
<!-- Confetti -->
<div class="confetti-wrap" id="confettiWrap"></div>

<!-- Topbar -->
<nav class="topbar">
  <div class="tb-logo">ES</div>
  <div>
    <div class="tb-title">Challenge Arena</div>
    <div class="tb-sub">1v1 Quiz Battle</div>
  </div>
  <div class="tb-flex"></div>
  <div class="tb-right">
    <a href="exams/practice_test.php" class="t-btn">
      <i class="fa fa-clipboard-check" style="font-size:11px"></i>
      <span>Practice</span>
    </a>
    <a href="dashboard.php" class="t-btn">
      <i class="fa fa-house" style="font-size:11px"></i>
    </a>
    <div class="user-chip">
      <?php if($my_pic):?><img src="<?=htmlspecialchars($my_pic)?>" alt=""><?php else:?>
      <div class="user-av"><?=htmlspecialchars(mb_strtoupper(mb_substr($my_name,0,1)))?></div>
      <?php endif;?>
      <span><?=htmlspecialchars($my_name)?></span>
    </div>
  </div>
</nav>

<div class="wrap">

<!-- ══ LOBBY ══ -->
<div class="screen show" id="screenLobby">
  <div class="hero">
    <div class="hero-badge">
      <i class="fa fa-bolt" style="font-size:9px"></i>
      1v1 Live Challenge
    </div>
    <h1>Challenge a Friend</h1>
    <p>Same questions, 10 seconds each. Winner claims the loser's points — no mercy.</p>
  </div>

  <div class="rules-strip">
    <div class="rule-card">
      <div class="rule-icon">⚡</div>
      <div class="rule-title">10 Seconds</div>
      <div class="rule-sub">Per question — quick thinking wins</div>
    </div>
    <div class="rule-card">
      <div class="rule-icon">🏆</div>
      <div class="rule-title">1.25 pts each</div>
      <div class="rule-sub">Correct answer awards 1.25 points</div>
    </div>
    <div class="rule-card">
      <div class="rule-icon">💀</div>
      <div class="rule-title">Winner takes all</div>
      <div class="rule-sub">Loser's points transfer to winner</div>
    </div>
  </div>

  <div class="mode-row">
    <div class="mode-card" style="--mc-color:var(--accent)" onclick="showCreate()">
      <div class="mc-icon">✏️</div>
      <div class="mc-title">Create Challenge</div>
      <div class="mc-desc">Pick a subject, get a 6-digit code, and share it with your opponent.</div>
      <i class="fa fa-arrow-right mc-arrow"></i>
    </div>
    <div class="mode-card" style="--mc-color:var(--blue)" onclick="showJoin()">
      <div class="mc-icon">🔗</div>
      <div class="mc-title">Join Challenge</div>
      <div class="mc-desc">Got a code from a friend? Enter it and jump straight into battle.</div>
      <i class="fa fa-arrow-right mc-arrow"></i>
    </div>
  </div>

  <!-- Create form (hidden by default) -->
  <div id="createForm" style="display:none">
    <div class="form-card">
      <div class="fc-head">
        <div class="fc-icon" style="background:rgba(0,201,138,.1);border:1px solid rgba(0,201,138,.2)">✏️</div>
        <div>
          <div class="fc-title">Create a Challenge</div>
          <div class="fc-sub">10 questions will be loaded from ALOC past questions</div>
        </div>
      </div>
      <div class="fc-body">
        <div class="form-row">
          <label class="form-lbl">Choose Subject</label>
          <div class="subj-row" id="createSubjRow"></div>
        </div>
        <div class="form-row">
          <label class="form-lbl">Exam Type</label>
          <div class="type-row" id="createTypeRow">
            <span class="tb on" data-v="utme" onclick="pickType(this,'createTypeRow')">JAMB UTME</span>
            <span class="tb" data-v="wassce" onclick="pickType(this,'createTypeRow')">WAEC SSCE</span>
            <span class="tb" data-v="neco" onclick="pickType(this,'createTypeRow')">NECO</span>
          </div>
        </div>
        <div class="err-msg" id="createErr"></div>
        <button class="btn-main" id="createBtn" onclick="createChallenge()" disabled>
          <i class="fa fa-bolt" style="font-size:12px"></i>
          <span id="createBtnLbl">Select a subject first</span>
        </button>
        <button class="btn-ghost" onclick="hideCreate()">
          <i class="fa fa-xmark" style="font-size:11px"></i> Cancel
        </button>
      </div>
    </div>
  </div>

  <!-- Join form (hidden by default) -->
  <div id="joinForm" style="display:none">
    <div class="form-card">
      <div class="fc-head">
        <div class="fc-icon" style="background:rgba(79,142,247,.1);border:1px solid rgba(79,142,247,.2)">🔗</div>
        <div>
          <div class="fc-title">Join a Challenge</div>
          <div class="fc-sub">Enter the 6-digit code from your opponent</div>
        </div>
      </div>
      <div class="fc-body">
        <div class="form-row">
          <label class="form-lbl">Challenge Code</label>
          <input class="code-input" id="joinCodeInput" maxlength="6"
            placeholder="ABC123" oninput="this.value=this.value.toUpperCase()"
            onkeydown="if(event.key==='Enter')joinChallenge()">
        </div>
        <div class="err-msg" id="joinErr"></div>
        <button class="btn-main" style="background:linear-gradient(135deg,var(--blue),#2563eb);box-shadow:0 4px 18px rgba(79,142,247,.3)"
          onclick="joinChallenge()">
          <i class="fa fa-arrow-right-to-bracket" style="font-size:12px"></i> Join Battle
        </button>
        <button class="btn-ghost" onclick="hideJoin()">
          <i class="fa fa-xmark" style="font-size:11px"></i> Cancel
        </button>
      </div>
    </div>
  </div>
</div><!-- /lobby -->

<!-- ══ WAITING ROOM ══ -->
<div class="screen" id="screenWaiting">
  <div class="waiting-card">
    <div class="wc-head">
      <div class="wc-head-title">⏳ Waiting for Opponent</div>
      <div class="wc-head-sub" id="wcSubjLbl">Subject · Exam Type</div>
    </div>
    <div class="wc-body">
      <div class="wc-code-label">Share this code with your opponent:</div>
      <div class="wc-code" id="wcCode">------</div>
      <div class="wc-share">
        Ask your opponent to go to<br>
        <strong>Excellent Simplified → Challenge Arena → Join</strong><br>
        and enter the code above
      </div>
      <div class="wc-pulse">
        <div class="pulse-dot"></div>
        Waiting for someone to join…
      </div>
      <div class="opp-joined" id="oppJoined">
        <div style="font-size:28px;flex-shrink:0">🎉</div>
        <div>
          <div class="oj-name" id="oppJoinedName">Opponent joined!</div>
          <div class="oj-sub">Game starting in 3 seconds…</div>
        </div>
      </div>
      <button class="btn-ghost" onclick="cancelChallenge()">
        <i class="fa fa-trash" style="font-size:11px"></i> Cancel Challenge
      </button>
    </div>
  </div>
</div><!-- /waiting -->

<!-- ══ GAME ══ -->
<div class="screen" id="screenGame">
  <!-- VS bar -->
  <div class="vs-bar">
    <div class="vs-player">
      <div id="p1av" style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--blue));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:13px;font-weight:700;color:#000;flex-shrink:0;border:2px solid var(--accent)">P1</div>
      <div>
        <div class="vsp-name" id="p1name">Player 1</div>
        <div class="vsp-score" id="p1score">0</div>
        <div class="vsp-ptspp">pts</div>
      </div>
    </div>
    <div class="vs-mid">
      <div class="vs-badge">VS</div>
      <div class="vs-prog-row">
        <div class="vs-prog-fill" id="vsProg" style="width:0%"></div>
      </div>
    </div>
    <div class="vs-player right">
      <div id="p2av" style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--danger),#c53030);display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;border:2px solid var(--border)">P2</div>
      <div style="text-align:right">
        <div class="vsp-name" id="p2name">Player 2</div>
        <div class="vsp-score" id="p2score">0</div>
        <div class="vsp-ptspp">pts</div>
      </div>
    </div>
  </div>

  <!-- Timer -->
  <div class="timer-wrap">
    <div class="timer-ring">
      <svg viewBox="0 0 80 80">
        <circle class="tr-bg" cx="40" cy="40" r="34"/>
        <circle class="tr-fill" id="trFill" cx="40" cy="40" r="34"
          stroke-dasharray="214" stroke-dashoffset="0"/>
      </svg>
      <div class="timer-num" id="timerNum">10</div>
    </div>
    <div class="q-progress-lbl" id="qProgLbl">Question 1 of 10</div>
  </div>

  <!-- Question card -->
  <div class="q-card">
    <div class="q-card-top">
      <span class="q-badge" id="qBadge">Q1</span>
      <div class="q-opp-status">
        <div class="opp-dot" id="oppDot"></div>
        <span id="oppStatusText">Opponent thinking…</span>
      </div>
    </div>
    <div class="q-body">
      <div class="q-text" id="qText">Loading…</div>
      <div class="q-opts" id="qOpts"></div>
    </div>
    <div class="answered-stamp" id="answeredStamp"></div>
    <div class="opp-answered-bar" id="oppAnsweredBar">
      <i class="fa fa-check-circle" style="font-size:12px"></i>
      <span id="oppAnsweredText">Opponent answered this question</span>
    </div>
  </div>
</div><!-- /game -->

<!-- ══ RESULTS ══ -->
<div class="screen" id="screenResults">
  <div class="result-card">
    <div class="res-hero">
      <div class="trophy" id="resTophy">🏆</div>
      <div class="res-outcome" id="resOutcome">You Win!</div>
      <div class="res-sub" id="resSub">Challenge Complete</div>
      <div class="res-vs-row">
        <div class="res-player" id="resP1Card">
          <div id="resP1Av" style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--blue));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:16px;font-weight:700;color:#000;margin:0 auto 8px">P1</div>
          <div class="res-name" id="resP1Name">P1</div>
          <div class="res-score" id="resP1Score">0</div>
          <div class="res-pts" id="resP1Pts">pts</div>
        </div>
        <div class="vs-chip">VS</div>
        <div class="res-player" id="resP2Card">
          <div id="resP2Av" style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,var(--danger),#c53030);display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:16px;font-weight:700;color:#fff;margin:0 auto 8px">P2</div>
          <div class="res-name" id="resP2Name">P2</div>
          <div class="res-score" id="resP2Score">0</div>
          <div class="res-pts" id="resP2Pts">pts</div>
        </div>
      </div>
    </div>
    <div class="pts-transfer" id="ptsTransferBanner"></div>
    <div class="res-review">
      <div class="rr-title">Question Review</div>
      <div id="reviewList"></div>
    </div>
    <div class="res-actions">
      <button class="ra prim" onclick="showLobby()">
        <i class="fa fa-swords" style="font-size:11px"></i> Play Again
      </button>
      <a href="exams/practice_test.php" class="ra">
        <i class="fa fa-clipboard-check" style="font-size:11px"></i> Practice
      </a>
      <a href="dashboard.php" class="ra">
        <i class="fa fa-house" style="font-size:11px"></i> Home
      </a>
    </div>
  </div>
</div>

</div><!-- /wrap -->
<script>
/* ══ CONSTANTS ══ */
const ME_ID   = <?= $user_id ?>;
const ME_NAME = <?= json_encode($my_name) ?>;
const ME_PIC  = <?= json_encode($my_pic) ?>;
const ME_PTS  = <?= $my_points ?>;
const SECS_PER_Q = 10;
const SUBJECTS = <?= $sj ?>;

/* ══ STATE ══ */
let challengeId  = null;
let challengeCode = null;
let creatorMode  = false; // true if I created the challenge
let pollInt      = null;
let gameQuestions= [];
let gameState    = null; // last poll response
let prevQIdx     = -1;
let toastTimer   = null;

/* ══ SCREEN MANAGEMENT ══ */
function showScreen(id){
  document.querySelectorAll('.screen').forEach(s=>s.classList.remove('show'));
  document.getElementById(id).classList.add('show');
  window.scrollTo({top:0,behavior:'smooth'});
}
function showLobby(){ showScreen('screenLobby'); hideCreate(); hideJoin(); stopPoll(); }

/* ══ LOBBY ══ */
function showCreate(){
  document.getElementById('createForm').style.display='block';
  document.getElementById('joinForm').style.display='none';
  if(!document.querySelector('#createSubjRow .sc')) buildSubjRow();
}
function hideCreate(){ document.getElementById('createForm').style.display='none'; }
function showJoin(){
  document.getElementById('joinForm').style.display='block';
  document.getElementById('createForm').style.display='none';
  document.getElementById('joinCodeInput').focus();
}
function hideJoin(){ document.getElementById('joinForm').style.display='none'; }

let createSubject='', createType='utme';
function buildSubjRow(){
  const row=document.getElementById('createSubjRow'); row.innerHTML='';
  Object.entries(SUBJECTS).forEach(([name,info])=>{
    const d=document.createElement('div');
    d.className='sc'; d.dataset.name=name;
    d.innerHTML=`<span class="sc-emoji">${info.icon||'📚'}</span>${name}`;
    d.onclick=()=>pickSubj(d,name);
    row.appendChild(d);
  });
}
function pickSubj(el,name){
  createSubject=name;
  document.querySelectorAll('#createSubjRow .sc').forEach(c=>c.classList.remove('sel'));
  el.classList.add('sel');
  const btn=document.getElementById('createBtn');
  btn.disabled=false;
  document.getElementById('createBtnLbl').textContent=`Create ${name} Challenge`;
}
function pickType(el,rowId){
  const v=el.dataset.v;
  if(rowId==='createTypeRow') createType=v;
  document.querySelectorAll(`#${rowId} .tb`).forEach(b=>b.classList.remove('on'));
  el.classList.add('on');
}

/* ══ CREATE CHALLENGE ══ */
async function createChallenge(){
  if(!createSubject) return;
  showLoad('Loading questions from ALOC…\nThis may take a few seconds.');
  const err=document.getElementById('createErr'); err.classList.remove('show');
  const fd=new FormData();
  fd.append('action','create'); fd.append('subject',createSubject); fd.append('exam_type',createType);
  try{
    const r=await fetch('challenge.php',{method:'POST',body:fd});
    const j=await r.json(); hideLoad();
    if(!j.ok){err.textContent=j.error||'Failed to create'; err.classList.add('show'); return;}
    challengeId=j.challenge_id; challengeCode=j.code; creatorMode=true;
    document.getElementById('wcCode').textContent=j.code;
    document.getElementById('wcSubjLbl').textContent=`${createSubject} · ${createType.toUpperCase()}`;
    showScreen('screenWaiting');
    startWaitingPoll();
  }catch(e){ hideLoad(); err.textContent='Network error: '+e.message; err.classList.add('show'); }
}

/* ══ JOIN CHALLENGE ══ */
async function joinChallenge(){
  const code=document.getElementById('joinCodeInput').value.trim().toUpperCase();
  const err=document.getElementById('joinErr'); err.classList.remove('show');
  if(code.length!==6){err.textContent='Enter the full 6-character code';err.classList.add('show');return;}
  showLoad('Joining challenge…');
  const fd=new FormData(); fd.append('action','join'); fd.append('code',code);
  try{
    const r=await fetch('challenge.php',{method:'POST',body:fd});
    const j=await r.json(); hideLoad();
    if(!j.ok){err.textContent=j.error||'Failed to join';err.classList.add('show');return;}
    challengeId=j.challenge_id; challengeCode=j.code; creatorMode=false;
    gameQuestions=[];
    showScreen('screenGame');
    startGamePoll();
    toast('🎮 Joined! Battle starting…','info');
  }catch(e){ hideLoad(); err.textContent='Network error: '+e.message; err.classList.add('show'); }
}

/* ══ WAITING ROOM POLL ══ */
function startWaitingPoll(){
  stopPoll();
  pollInt=setInterval(async()=>{
    try{
      const r=await fetch(`challenge.php?action=check_code&code=${challengeCode}`);
      const j=await r.json();
      if(!j.ok) return;
      if(j.status==='active'){
        stopPoll();
        const opp=j.opponent_name||'Opponent';
        const oppBox=document.getElementById('oppJoined');
        document.getElementById('oppJoinedName').textContent=opp+' joined!';
        oppBox.classList.add('show');
        document.querySelector('.wc-pulse').style.display='none';
        setTimeout(()=>{
          gameQuestions=[];
          showScreen('screenGame');
          startGamePoll();
        },2500);
      }
    }catch(e){}
  },2000);
}

/* ══ CANCEL ══ */
async function cancelChallenge(){
  if(!confirm('Cancel this challenge?')) return;
  stopPoll();
  if(challengeId){
    const fd=new FormData(); fd.append('action','cancel'); fd.append('challenge_id',challengeId);
    fetch('challenge.php',{method:'POST',body:fd}).catch(()=>{});
  }
  challengeId=null; challengeCode=null;
  showLobby();
}

/* ══ GAME POLL ══ */
function startGamePoll(){
  stopPoll();
  poll();
  pollInt=setInterval(poll,1000);
}
function stopPoll(){ clearInterval(pollInt); pollInt=null; }

async function poll(){
  if(!challengeId) return;
  try{
    const r=await fetch(`challenge.php?action=poll&challenge_id=${challengeId}`);
    const j=await r.json();
    if(!j.ok) return;
    gameState=j;
    if(j.status==='waiting') return;
    if(j.questions&&j.questions.length>0) gameQuestions=j.questions;
    renderGame(j);
    if(j.status==='finished') {
      stopPoll();
      setTimeout(()=>showResults(j),600);
    }
  }catch(e){}
}

/* ══ RENDER GAME ══ */
function renderGame(s){
  const iAmP1 = ME_ID===s.p1_id;
  const p1Score= +s.p1_score, p2Score=+s.p2_score;
  const myScore= iAmP1?p1Score:p2Score;
  const oppScore=iAmP1?p2Score:p1Score;

  // VS bar scores
  document.getElementById('p1name').textContent  = s.p1_name;
  document.getElementById('p2name').textContent  = s.p2_id?s.p2_name:'Waiting…';
  document.getElementById('p1score').textContent = p1Score.toFixed(2);
  document.getElementById('p2score').textContent = p2Score.toFixed(2);
  setAvatar('p1av',s.p1_pic,s.p1_name);
  if(s.p2_id) setAvatar('p2av',s.p2_pic,s.p2_name);

  // Highlight my player name
  document.getElementById(iAmP1?'p1name':'p2name').style.color='var(--accent)';

  // Progress bar (my score vs opp)
  const total=(+s.q_count)*1.25||12.5;
  const pct=Math.min(100,Math.round(myScore/total*100));
  document.getElementById('vsProg').style.width=pct+'%';

  // Timer
  const secsLeft=Math.max(0,s.secs_left);
  const circumference=2*Math.PI*34; // r=34
  const offset=circumference*(1-secsLeft/SECS_PER_Q);
  const trFill=document.getElementById('trFill');
  trFill.setAttribute('stroke-dashoffset',offset);
  trFill.className=secsLeft<=3?'tr-fill tr-danger':'tr-fill';
  const tn=document.getElementById('timerNum');
  tn.textContent=secsLeft;
  tn.className='timer-num'+(secsLeft<=3?' danger':'');

  // Q index
  const qi=+s.cur_q;
  document.getElementById('qBadge').textContent='Q'+(qi+1);
  document.getElementById('qProgLbl').textContent=`Question ${qi+1} of ${s.q_count}`;

  // If new question, reset UI
  if(qi!==prevQIdx){
    prevQIdx=qi;
    document.getElementById('answeredStamp').className='answered-stamp';
    document.getElementById('oppAnsweredBar').className='opp-answered-bar';
  }

  // Question
  const q=gameQuestions[qi];
  if(!q){document.getElementById('qText').textContent='Loading question…'; return;}
  if(document.getElementById('qText').textContent!==q.q)
    document.getElementById('qText').textContent=q.q;

  // Options
  const alreadyAnswered=s.my_answers&&s.my_answers[qi]!==undefined;
  const myAnswer=alreadyAnswered?getMyCho(s,qi):null;
  renderOptions(q,alreadyAnswered,myAnswer,s.status==='finished');

  // Answered stamp
  const stamp=document.getElementById('answeredStamp');
  if(alreadyAnswered && stamp.style.display!=='block'){
    const correct=!!s.my_answers[qi];
    stamp.className='answered-stamp '+(correct?'correct':'wrong')+' show';
    stamp.innerHTML=correct
      ?'<i class="fa fa-check-circle"></i> Correct! +1.25 pts'
      :'<i class="fa fa-times-circle"></i> Wrong. The answer was <strong>Option '+(q.answer||'?')+'</strong>';
  }

  // Opponent status
  const oppBar=document.getElementById('oppAnsweredBar');
  const oppDot=document.getElementById('oppDot');
  const oppStatusText=document.getElementById('oppStatusText');
  if(s.opp_answered_q){
    oppDot.classList.add('done');
    oppStatusText.textContent=(iAmP1?s.p2_name:s.p1_name)+' answered ✓';
    oppBar.classList.add('show');
    document.getElementById('oppAnsweredBar').innerHTML=
      '<i class="fa fa-check-circle" style="font-size:12px"></i>&nbsp;'+
      (iAmP1?s.p2_name:s.p1_name)+' has answered this question';
  } else {
    oppDot.classList.remove('done');
    oppStatusText.textContent=(iAmP1?s.p2_name:s.p1_name)+' is thinking…';
    oppBar.classList.remove('show');
  }
}

function getMyCho(s,qi){
  // We don't send the chosen letter in poll, derive from answer correct/wrong
  return s.my_answers[qi]!==undefined ? (s.my_answers[qi]?'correct_flag':'wrong_flag') : null;
}

function renderOptions(q,answered,myAnswer,finished){
  const area=document.getElementById('qOpts');
  const cur=area.innerHTML;
  // Only re-render if not yet answered (to avoid flicker)
  if(answered && !finished) return;
  area.innerHTML='';
  ['A','B','C','D'].forEach(l=>{
    const val=q[l.toLowerCase()]||'';
    if(!val) return;
    const btn=document.createElement('button');
    btn.type='button'; btn.className='q-opt'; btn.dataset.l=l;
    btn.innerHTML=`<div class="q-opt-l">${l}</div><div class="q-opt-t">${esc(val)}</div><span class="q-opt-m"></span>`;
    if(answered||finished){
      btn.disabled=true;
      if(q.answer&&l===q.answer){
        btn.classList.add('correct');
        btn.querySelector('.q-opt-m').textContent='✓';
      }
    } else {
      btn.addEventListener('click',()=>submitAnswer(q.i,l));
    }
    area.appendChild(btn);
  });
}

/* ══ SUBMIT ANSWER ══ */
async function submitAnswer(qIdx,chosen){
  if(!challengeId||!gameState||gameState.status!=='active') return;
  // Optimistically disable buttons
  document.querySelectorAll('.q-opt').forEach(b=>b.disabled=true);
  const fd=new FormData();
  fd.append('action','answer'); fd.append('challenge_id',challengeId);
  fd.append('q_index',qIdx); fd.append('chosen',chosen);
  try{
    const r=await fetch('challenge.php',{method:'POST',body:fd});
    const j=await r.json();
    if(j.ok){
      // Highlight chosen button immediately
      const btn=document.querySelector(`.q-opt[data-l="${chosen}"]`);
      if(btn){
        if(j.correct){
          btn.classList.add('correct');
          btn.querySelector('.q-opt-m').textContent='✓';
        } else {
          btn.classList.add('wrong');
          btn.querySelector('.q-opt-m').textContent='✗';
          // Show correct
          const corBtn=document.querySelector(`.q-opt[data-l="${j.answer}"]`);
          if(corBtn){corBtn.classList.add('correct');corBtn.querySelector('.q-opt-m').textContent='✓';}
        }
      }
    }
  }catch(e){}
}

/* ══ RESULTS ══ */
function showResults(s){
  showScreen('screenResults');
  const iAmP1 = ME_ID===s.p1_id;
  const p1Score=+s.p1_score, p2Score=+s.p2_score;
  const myScore=iAmP1?p1Score:p2Score;
  const oppScore=iAmP1?p2Score:p1Score;
  const iWon = s.winner_id===ME_ID;
  const isDraw = !s.winner_id && s.status==='finished';

  // Trophy & outcome
  document.getElementById('resTophy').textContent = iWon?'🏆':isDraw?'🤝':'💀';
  document.getElementById('resOutcome').textContent = iWon?'You Win!':isDraw?'It\'s a Draw!':'You Lose';
  document.getElementById('resOutcome').style.color = iWon?'var(--gold)':isDraw?'var(--amber)':'var(--danger)';
  document.getElementById('resSub').textContent = `${s.subject} · ${s.q_count} questions`;

  // Player cards
  document.getElementById('resP1Name').textContent=s.p1_name;
  document.getElementById('resP2Name').textContent=s.p2_name;
  document.getElementById('resP1Score').textContent=p1Score.toFixed(2)+' pts';
  document.getElementById('resP2Score').textContent=p2Score.toFixed(2)+' pts';
  document.getElementById('resP1Pts').textContent=(1.25*s.q_count)+' pts max';
  document.getElementById('resP2Pts').textContent=(1.25*s.q_count)+' pts max';
  setAvatar('resP1Av',s.p1_pic,s.p1_name);
  setAvatar('resP2Av',s.p2_pic,s.p2_name);

  // Highlight winner
  const winIsP1 = s.winner_id===s.p1_id;
  document.getElementById('resP1Card').classList.toggle('winner',winIsP1&&!isDraw);
  document.getElementById('resP2Card').classList.toggle('winner',!winIsP1&&!isDraw&&!!s.winner_id);

  // Points transfer banner
  const banner=document.getElementById('ptsTransferBanner');
  if(!isDraw && s.winner_id){
    const winnerName = s.winner_id===s.p1_id?s.p1_name:s.p2_name;
    const winnerScore = s.winner_id===s.p1_id?p1Score:p2Score;
    const loserScore  = s.winner_id===s.p1_id?p2Score:p1Score;
    const totalWon = winnerScore+loserScore;
    banner.innerHTML=`
      <div class="pts-transfer-title">🏆 ${winnerName} wins ${totalWon.toFixed(2)} points!</div>
      <div class="pts-arrow">↑</div>
      <div class="pts-transfer-sub">
        ${winnerScore.toFixed(2)} pts (own) + ${loserScore.toFixed(2)} pts (from loser) = <strong style="color:var(--gold)">${totalWon.toFixed(2)} points</strong> added to account
      </div>`;
    banner.style.display='block';
  } else if(isDraw){
    banner.innerHTML=`<div class="pts-transfer-title">🤝 Draw — no points transferred</div>
      <div class="pts-transfer-sub" style="margin-top:4px">Both players keep their points</div>`;
    banner.style.display='block';
  }

  // Review
  const list=document.getElementById('reviewList'); list.innerHTML='';
  const myAnswers = s.my_answers||{};
  gameQuestions.forEach((q,i)=>{
    const myIs = myAnswers[i];
    const div=document.createElement('div'); div.className='rr-item';
    const yourTag = myIs===undefined
      ?'<span class="rri-tag skip">Skipped</span>'
      :`<span class="rri-tag ${myIs?'yours-c':'yours-w'}">Your: ${myIs?'✓ Correct':'✗ Wrong'}</span>`;
    div.innerHTML=`<div class="rri-num">${i+1}</div>
      <div>
        <div class="rri-q">${esc(q.q)}</div>
        <div class="rri-row">
          ${yourTag}
          <span class="rri-tag correct">Answer: ${esc(q.answer||'?')}</span>
          ${q.year?`<span class="rri-tag" style="color:var(--sub);border:1px solid var(--border)">${esc(q.year)}</span>`:''}
        </div>
      </div>`;
    list.appendChild(div);
  });

  if(iWon) fireConfetti();
}

/* ══ CONFETTI ══ */
function fireConfetti(){
  const wrap=document.getElementById('confettiWrap');
  const colors=['#ffd700','#00c98a','#4f8ef7','#f43f5e','#a78bfa','#f59e0b'];
  for(let i=0;i<60;i++){
    const p=document.createElement('div');
    p.className='confetti-piece';
    p.style.cssText=`
      left:${Math.random()*100}%;
      background:${colors[Math.floor(Math.random()*colors.length)]};
      animation-duration:${1.2+Math.random()*2}s;
      animation-delay:${Math.random()*1.2}s;
      transform:rotate(${Math.random()*360}deg);
      border-radius:${Math.random()>0.5?'50%':'2px'}`;
    wrap.appendChild(p);
  }
  setTimeout(()=>{ wrap.innerHTML=''; },4000);
}

/* ══ AVATAR HELPER ══ */
function setAvatar(id, pic, name){
  const el=document.getElementById(id);
  if(!el) return;
  if(pic){
    el.style.cssText='width:38px;height:38px;border-radius:10px;object-fit:cover;flex-shrink:0;border:2px solid var(--accent)';
    el.outerHTML=`<img id="${id}" src="${esc(pic)}" alt="" style="width:38px;height:38px;border-radius:10px;object-fit:cover;flex-shrink:0;border:2px solid var(--border)">`;
  } else {
    el.textContent=(name||'?').charAt(0).toUpperCase();
  }
}

/* ══ TOAST ══ */
function toast(msg,type='ok'){
  const el=document.getElementById('toast');
  el.className=`toast show ${type}`;
  el.textContent=msg;
  clearTimeout(toastTimer);
  toastTimer=setTimeout(()=>el.className='toast',3000);
}

/* ══ LOADING ══ */
function showLoad(msg){ document.getElementById('loadMsg').textContent=msg||'Loading…'; document.getElementById('loadOv').classList.add('show'); }
function hideLoad(){ document.getElementById('loadOv').classList.remove('show'); }

/* ══ UTIL ══ */
function esc(s){ return s?String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])):''; }
</script>
</body>
</html>
