<?php
// questions/live_brainstorm.php
// Student live-brainstorm page
// Place at ~/excellent-academy/questions/live_brainstorm.php

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
session_start();
require_once __DIR__ . '/../config/db.php';

// ── Auth ────────────────────────────────────────────────────────
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$userRow = [];
if ($user_id) {
    $s = $conn->prepare("SELECT username, email, google_name, google_picture FROM users WHERE id=? LIMIT 1");
    if ($s) { $s->bind_param('i',$user_id); $s->execute(); $userRow=$s->get_result()->fetch_assoc()??[]; $s->close(); }
}
$user_name    = $_SESSION['google_name'] ?? $userRow['google_name'] ?? $userRow['username'] ?? $userRow['email'] ?? 'Student';
$user_picture = $_SESSION['google_picture'] ?? $userRow['google_picture'] ?? null;

if (!$user_id) {
    if (!empty($_REQUEST['action'])) { echo json_encode(['success'=>false,'error'=>'Not authenticated']); exit; }
    header('Location: ../login.php'); exit;
}

// ── Helpers ─────────────────────────────────────────────────────
function get_active_question($conn): ?array {
    $q = $conn->prepare("SELECT id,question,option_a,option_b,option_c,option_d,correct_answer
                          FROM questions WHERE status='active' ORDER BY id DESC LIMIT 1");
    $q->execute();
    $res = $q->get_result();
    return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
}

function store_uploaded_file(string $field, string $sub, array $mimes, int $max = 5<<20): ?array {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$field];
    if ($f['size'] > $max) return ['error'=>'File too large'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $f['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $mimes, true)) return ['error'=>'Invalid file type'];
    $ext  = pathinfo($f['name'], PATHINFO_EXTENSION);
    $name = bin2hex(random_bytes(10)) . '.' . ($ext ?: 'bin');
    $dir  = __DIR__ . '/../uploads/' . $sub;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($f['tmp_name'], $dir.'/'.$name)) return ['error'=>'Upload failed'];
    return ['path' => 'uploads/'.$sub.'/'.$name];
}

// ── JSON output ─────────────────────────────────────────────────
if (!empty($_REQUEST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['action'];

    // ── get_active_id: lightweight poll to detect question changes
    if ($action === 'get_active_id') {
        $q = get_active_question($conn);
        echo json_encode(['success'=>true, 'id'=> $q ? (int)$q['id'] : null]);
        exit;
    }

    // ── get_question
    if ($action === 'get_question') {
        $q = get_active_question($conn);
        if (!$q) { echo json_encode(['success'=>true,'active'=>false]); exit; }
        echo json_encode(['success'=>true,'active'=>true,'question'=>[
            'id'       => (int)$q['id'],
            'text'     => $q['question'],
            'option_a' => $q['option_a'],
            'option_b' => $q['option_b'],
            'option_c' => $q['option_c'],
            'option_d' => $q['option_d'],
        ]]);
        exit;
    }

    // ── get_all_questions
    if ($action === 'get_all_questions') {
        $res = $conn->query("SELECT id,question,option_a,option_b,option_c,option_d,correct_answer,status
                              FROM questions ORDER BY FIELD(status,'active','inactive'), id DESC LIMIT 50");
        $qs = [];
        if ($res) while ($r = $res->fetch_assoc()) $qs[] = $r;
        echo json_encode(['success'=>true,'questions'=>$qs]);
        exit;
    }

    // ── get_answers (returns per-type my_reactions array, NOT single bool)
    if ($action === 'get_answers') {
        $qid = !empty($_GET['question_id']) ? (int)$_GET['question_id'] : 0;
        if (!$qid) {
            $q = get_active_question($conn);
            if (!$q) { echo json_encode(['success'=>true,'answers',[]]); exit; }
            $qid = (int)$q['id'];
        }
        $stmt = $conn->prepare("
            SELECT ba.id, ba.user_id, ba.selected_option, ba.answer_text,
                   ba.attachment, ba.voice_path, ba.is_correct, ba.created_at,
                   COALESCE(u.google_name,u.username,u.email,'Student') AS user_name
            FROM brainstorm_answers ba
            LEFT JOIN users u ON u.id = ba.user_id
            WHERE ba.question_id = ?
            ORDER BY ba.created_at ASC LIMIT 500");
        $stmt->bind_param('i', $qid);
        $stmt->execute();
        $rows = $stmt->get_result();
        $out  = [];
        while ($row = $rows->fetch_assoc()) {
            // Reaction counts per type
            $rStmt = $conn->prepare("SELECT type, COUNT(*) AS c FROM brainstorm_reactions WHERE answer_id=? GROUP BY type");
            $rStmt->bind_param('i', $row['id']); $rStmt->execute();
            $rRes  = $rStmt->get_result(); $reactions = [];
            while ($rr = $rRes->fetch_assoc()) $reactions[$rr['type']] = (int)$rr['c'];
            // Which types THIS user reacted with (the bug fix: per-type, not single bool)
            $myTypes = [];
            $mStmt = $conn->prepare("SELECT type FROM brainstorm_reactions WHERE answer_id=? AND user_id=?");
            $mStmt->bind_param('ii', $row['id'], $user_id); $mStmt->execute();
            $mRes  = $mStmt->get_result();
            while ($mr = $mRes->fetch_assoc()) $myTypes[] = $mr['type'];
            $out[] = [
                'id'              => (int)$row['id'],
                'user_id'         => (int)$row['user_id'],
                'name'            => $row['user_name'],
                'selected_option' => $row['selected_option'],
                'answer_text'     => $row['answer_text'],
                'attachment'      => $row['attachment'],
                'voice_path'      => $row['voice_path'],
                'is_correct'      => (bool)$row['is_correct'],
                'created_at'      => $row['created_at'],
                'reactions'       => $reactions,
                'my_reactions'    => $myTypes,   // ← array of type strings, e.g. ['like','fire']
            ];
        }
        echo json_encode(['success'=>true,'answers'=>$out], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── submit_answer
    if ($action === 'submit_answer') {
        $qid     = (int)($_POST['question_id'] ?? 0);
        $opt     = !empty($_POST['selected_option']) ? strtoupper(substr($_POST['selected_option'],0,1)) : null;
        $txt     = trim($_POST['answer_text'] ?? '');

        if (!$qid) { echo json_encode(['success'=>false,'error'=>'Missing question ID']); exit; }
        $qRow = $conn->prepare("SELECT id,correct_answer FROM questions WHERE id=? LIMIT 1");
        $qRow->bind_param('i',$qid); $qRow->execute();
        $qData = $qRow->get_result()->fetch_assoc();
        if (!$qData) { echo json_encode(['success'=>false,'error'=>'Question not found']); exit; }

        // Duplicate check
        $dup = $conn->prepare("SELECT id FROM brainstorm_answers WHERE user_id=? AND question_id=? LIMIT 1");
        $dup->bind_param('ii',$user_id,$qid); $dup->execute();
        if ($dup->get_result()->num_rows > 0) { echo json_encode(['success'=>false,'error'=>'Already answered']); exit; }

        // File uploads
        $attach = null;
        if (!empty($_FILES['attachment']['name'])) {
            $r = store_uploaded_file('attachment','images',['image/jpeg','image/png','image/webp','image/gif'],6<<20);
            if (isset($r['error'])) { echo json_encode(['success'=>false,'error'=>$r['error']]); exit; }
            $attach = $r['path'];
        }
        $voice = null;
        if (!empty($_FILES['voice']['name'])) {
            $r = store_uploaded_file('voice','voices',['audio/webm','audio/ogg','audio/wav','audio/mpeg'],8<<20);
            if (isset($r['error'])) { echo json_encode(['success'=>false,'error'=>$r['error']]); exit; }
            $voice = $r['path'];
        }

        // Is correct?
        $ca = strtoupper(trim($qData['correct_answer'] ?? ''));
        $is_correct = 0;
        if ($opt && $ca)       $is_correct = ($opt === $ca) ? 1 : 0;
        elseif ($txt && $ca)   $is_correct = (mb_strtolower(trim($txt)) === mb_strtolower($ca)) ? 1 : 0;

        $ins = $conn->prepare("INSERT INTO brainstorm_answers (user_id,question_id,selected_option,answer_text,attachment,voice_path,is_correct,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
        $ins->bind_param('iissssi',$user_id,$qid,$opt,$txt,$attach,$voice,$is_correct);
        if (!$ins->execute()) { echo json_encode(['success'=>false,'error'=>$conn->error]); exit; }
        $aid = $conn->insert_id;

        // Points
        $pts = 0.75;
        @$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS points DECIMAL(10,2) DEFAULT 0");
        $up = $conn->prepare("UPDATE users SET points=points+? WHERE id=?");
        if ($up) { $up->bind_param('di',$pts,$user_id); $up->execute(); }

        // Rank among correct
        $rank = null;
        if ($is_correct) {
            $rk = $conn->prepare("SELECT COUNT(*) AS c FROM brainstorm_answers WHERE question_id=? AND is_correct=1 AND created_at<=(SELECT created_at FROM brainstorm_answers WHERE id=?)");
            $rk->bind_param('ii',$qid,$aid); $rk->execute();
            $rank = (int)$rk->get_result()->fetch_assoc()['c'];
        }
        echo json_encode(['success'=>true,'answer_id'=>$aid,'is_correct'=>(bool)$is_correct,'rank'=>$rank,'correct_answer'=>$ca]);
        exit;
    }

    // ── react (toggle per type)
    if ($action === 'react') {
        $aid  = (int)($_POST['answer_id'] ?? 0);
        $type = trim($_POST['type'] ?? 'like');
        if (!$aid) { echo json_encode(['success'=>false,'error'=>'Missing answer_id']); exit; }
        $chk = $conn->prepare("SELECT id FROM brainstorm_reactions WHERE answer_id=? AND user_id=? AND type=? LIMIT 1");
        $chk->bind_param('iis',$aid,$user_id,$type); $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        if ($row) {
            $del = $conn->prepare("DELETE FROM brainstorm_reactions WHERE id=?");
            $del->bind_param('i',$row['id']); $del->execute(); $done = 'removed';
        } else {
            $ins = $conn->prepare("INSERT INTO brainstorm_reactions (user_id,answer_id,type,created_at) VALUES (?,?,?,NOW())");
            $ins->bind_param('iis',$user_id,$aid,$type); $ins->execute(); $done = 'added';
        }
        $cnt = $conn->prepare("SELECT type, COUNT(*) AS c FROM brainstorm_reactions WHERE answer_id=? GROUP BY type");
        $cnt->bind_param('i',$aid); $cnt->execute();
        $cres = $cnt->get_result(); $counts = [];
        while ($c = $cres->fetch_assoc()) $counts[$c['type']] = (int)$c['c'];
        echo json_encode(['success'=>true,'action'=>$done,'counts'=>$counts]);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action']);
    exit;
}

// ── Page render ──────────────────────────────────────────────────
$activeQ     = get_active_question($conn);
$allQuestions = [];
$aqRes = $conn->query("SELECT id,question,option_a,option_b,option_c,option_d,correct_answer,status FROM questions ORDER BY FIELD(status,'active','inactive'), id DESC LIMIT 50");
if ($aqRes) while ($r = $aqRes->fetch_assoc()) $allQuestions[] = $r;

$startIdx = 0;
foreach ($allQuestions as $i => $q) { if ($q['status']==='active') { $startIdx=$i; break; } }

// Questions this user already answered (for nav dot colours)
$myAnsweredIds = [];
if ($user_id && !empty($allQuestions)) {
    $ids = implode(',', array_map(fn($q)=>(int)$q['id'], $allQuestions));
    $aRes = $conn->query("SELECT question_id,is_correct FROM brainstorm_answers WHERE user_id=$user_id AND question_id IN ($ids)");
    if ($aRes) while ($r = $aRes->fetch_assoc()) $myAnsweredIds[(int)$r['question_id']] = (bool)$r['is_correct'];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Live Brainstorm — Excellent Simplified</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── TOKENS ── */
:root{
  --bg:#04020e;--s1:#0d1017;--s2:#131720;--s3:#1a1f2e;--s4:#202638;
  --border:rgba(255,255,255,.07);--border2:rgba(255,255,255,.13);
  --accent:#00c98a;--blue:#3b82f6;--amber:#f59e0b;--danger:#ff4757;--purple:#a78bfa;
  --text:#e8ecf4;--sub:#8a93ab;--dim:#4a5268;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);
  min-height:100vh;-webkit-font-smoothing:antialiased;
  background-image:
    radial-gradient(ellipse 80% 50% at 0 0,rgba(0,201,138,.05) 0,transparent 60%),
    radial-gradient(ellipse 60% 50% at 100% 100%,rgba(59,130,246,.05) 0,transparent 60%)}

/* ── TOPBAR ── */
.topbar{position:sticky;top:0;z-index:40;height:56px;background:rgba(13,16,23,.92);
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;padding:0 20px;gap:12px}
.tb-brand{display:flex;align-items:center;gap:9px}
.tb-logo{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--accent),var(--blue));
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:#000;flex-shrink:0}
.tb-name{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;letter-spacing:.05em}
.live-pill{display:flex;align-items:center;gap:6px;padding:4px 10px;border-radius:20px;
  border:1px solid rgba(0,201,138,.3);background:rgba(0,201,138,.07);
  font-size:10px;font-weight:700;color:var(--accent);font-family:'Space Mono',monospace;transition:all .3s}
.live-pill.no-live{border-color:rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:var(--dim)}
.ldot{width:5px;height:5px;border-radius:50%;background:var(--accent);animation:lp 1.6s infinite;flex-shrink:0}
.no-live .ldot{background:var(--dim);animation:none}
@keyframes lp{0%,100%{box-shadow:0 0 0 0 rgba(0,201,138,.5)}60%{box-shadow:0 0 0 4px rgba(0,201,138,0)}}
.tb-right{display:flex;align-items:center;gap:8px}
.user-chip{display:flex;align-items:center;gap:7px;padding:5px 11px;border-radius:20px;
  background:var(--s2);border:1px solid var(--border);font-size:12px;font-weight:600}
.user-chip img{width:22px;height:22px;border-radius:6px;object-fit:cover}
.user-av{width:22px;height:22px;border-radius:6px;
  background:linear-gradient(135deg,var(--accent),var(--blue));
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:#000}
.t-btn{width:32px;height:32px;border-radius:8px;background:var(--s2);border:1px solid var(--border);
  color:var(--sub);cursor:pointer;display:flex;align-items:center;justify-content:center;
  font-size:12px;text-decoration:none;transition:all .15s}
.t-btn:hover{color:var(--text);border-color:var(--border2)}

/* ── TOAST ── */
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);
  z-index:100;padding:10px 20px;border-radius:22px;font-size:13px;font-weight:700;
  background:var(--s4);border:1px solid var(--border2);color:var(--text);
  display:flex;align-items:center;gap:9px;
  box-shadow:0 8px 32px rgba(0,0,0,.5);
  transition:transform .35s cubic-bezier(.22,1,.36,1),opacity .35s;opacity:0;pointer-events:none}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.toast.live{background:rgba(0,201,138,.12);border-color:rgba(0,201,138,.35);color:var(--accent)}

/* ── LAYOUT ── */
.wrap{max-width:1060px;margin:0 auto;padding:20px 16px}
.grid{display:grid;grid-template-columns:1fr 310px;gap:16px;align-items:start}
@media(max-width:860px){.grid{grid-template-columns:1fr}}
@media(max-width:480px){.wrap{padding:12px 10px}}

/* ── CARDS ── */
.card{background:var(--s1);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:14px}
.card-head{padding:11px 16px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.card-title{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
  letter-spacing:.1em;text-transform:uppercase;color:var(--sub);display:flex;align-items:center;gap:7px}
.card-body{padding:18px}

/* ── NAVIGATOR DOTS ── */
.q-nav{display:flex;align-items:center;gap:6px;padding:10px 14px;
  overflow-x:auto;border-bottom:1px solid var(--border);scrollbar-width:none}
.q-nav::-webkit-scrollbar{display:none}
.qn-count{font-family:'Space Mono',monospace;font-size:10px;color:var(--dim);white-space:nowrap;flex-shrink:0}
.qn-sep{width:1px;height:16px;background:var(--border);flex-shrink:0}
.qn-dot{width:30px;height:30px;border-radius:8px;flex-shrink:0;
  background:var(--s3);border:1.5px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:var(--dim);
  cursor:pointer;transition:all .15s}
.qn-dot:hover{border-color:var(--blue);color:var(--blue)}
.qn-dot.is-live{background:rgba(0,201,138,.1);border-color:rgba(0,201,138,.35);color:var(--accent)}
.qn-dot.is-live.is-current{background:var(--accent);border-color:var(--accent);color:#000}
.qn-dot.is-current{background:var(--blue);border-color:var(--blue);color:#fff;transform:scale(1.12)}
.qn-dot.done-c{background:rgba(0,201,138,.15);border-color:rgba(0,201,138,.4);color:var(--accent)}
.qn-dot.done-c.is-current{background:var(--accent);border-color:var(--accent);color:#000}
.qn-dot.done-w{background:rgba(255,71,87,.1);border-color:rgba(255,71,87,.3);color:var(--danger)}
.qn-dot.done-w.is-current{background:var(--danger);border-color:var(--danger);color:#fff}

/* ── QUESTION SLIDE ── */
.q-slide{animation:qslide .22s ease both}
@keyframes qslide{from{opacity:0;transform:translateX(8px)}to{opacity:1;transform:none}}
.q-tag{display:flex;align-items:center;gap:7px;font-family:'Space Mono',monospace;
  font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  color:var(--accent);margin-bottom:10px}
.q-text{font-size:18px;font-weight:700;color:var(--text);line-height:1.65;margin-bottom:20px}
@media(max-width:480px){.q-text{font-size:15px}}

/* ── OPTIONS ── */
.options{display:flex;flex-direction:column;gap:8px;margin-bottom:16px}
.opt-btn{display:flex;align-items:center;gap:12px;padding:13px 15px;border-radius:11px;
  border:1.5px solid var(--border);background:var(--s2);cursor:pointer;text-align:left;
  width:100%;color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;
  font-weight:500;transition:all .18s}
.opt-btn:hover:not(:disabled){border-color:rgba(59,130,246,.5);background:rgba(59,130,246,.05);transform:translateX(3px)}
.opt-btn:disabled{cursor:default;transform:none}
.opt-ltr{width:34px;height:34px;border-radius:9px;flex-shrink:0;background:var(--s3);
  border:1px solid var(--border);display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:var(--blue);transition:all .18s}
.opt-text{font-size:14px;line-height:1.5;font-weight:500;flex:1}
.opt-mark{margin-left:auto;font-size:15px;opacity:0;transition:opacity .2s}
.opt-btn.selected{border-color:rgba(59,130,246,.6);background:rgba(59,130,246,.08)}
.opt-btn.selected .opt-ltr{background:var(--blue);color:#fff;border-color:var(--blue)}
.opt-btn.correct{border-color:rgba(0,201,138,.5);background:rgba(0,201,138,.08);animation:cf .4s ease}
.opt-btn.correct .opt-ltr{background:var(--accent);color:#000;border-color:var(--accent)}
.opt-btn.correct .opt-mark{opacity:1}
.opt-btn.wrong{border-color:rgba(255,71,87,.5);background:rgba(255,71,87,.08);animation:ws .35s ease}
.opt-btn.wrong .opt-ltr{background:var(--danger);color:#fff;border-color:var(--danger)}
.opt-btn.wrong .opt-mark{opacity:1}
@keyframes cf{0%{transform:scale(1)}30%{transform:scale(1.025)}100%{transform:scale(1)}}
@keyframes ws{0%,100%{transform:translateX(0)}25%{transform:translateX(-6px)}75%{transform:translateX(6px)}}

/* Divider */
.or-div{display:flex;align-items:center;gap:10px;margin:12px 0;
  color:var(--dim);font-size:11px;font-family:'Space Mono',monospace}
.or-div::before,.or-div::after{content:'';flex:1;height:1px;background:var(--border)}

/* ── TEXT + UPLOAD ── */
.field{background:var(--s2);border:1.5px solid var(--border);border-radius:10px;
  color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;
  padding:11px 14px;outline:none;width:100%;transition:border-color .15s;resize:vertical}
.field:focus{border-color:rgba(59,130,246,.45);box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.field::placeholder{color:var(--dim)}
.upload-row{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}
.upload-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:20px;
  border:1px solid var(--border);background:var(--s2);color:var(--sub);font-size:12px;
  font-weight:500;cursor:pointer;transition:all .15s;user-select:none}
.upload-chip:hover{border-color:var(--blue);color:var(--text)}
.upload-chip.recording{border-color:var(--danger);color:var(--danger);
  background:rgba(255,71,87,.08);animation:rec 1s infinite}
@keyframes rec{0%,100%{opacity:1}50%{opacity:.55}}
.upload-chip.has-file{border-color:var(--accent);color:var(--accent)}
.preview-img{max-height:90px;border-radius:8px;margin-top:8px;border:1px solid var(--border)}
.audio-preview{width:100%;margin-top:6px;border-radius:8px}

/* ── SUBMIT ROW ── */
.submit-row{display:flex;align-items:center;justify-content:flex-end;margin-top:14px}
.submit-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:10px;
  border:none;background:linear-gradient(135deg,var(--accent),var(--blue));
  color:#000;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;
  cursor:pointer;transition:all .18s;box-shadow:0 4px 14px rgba(0,201,138,.25)}
.submit-btn:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 8px 22px rgba(0,201,138,.38)}
.submit-btn:disabled{opacity:.5;cursor:not-allowed;transform:none}
.submit-btn.next-mode{background:linear-gradient(135deg,var(--blue),var(--purple))}

/* ── RESULT BANNER ── */
.result-banner{margin-top:14px;padding:14px 16px;border-radius:12px;display:none;animation:si .3s ease}
@keyframes si{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.result-banner.ok{background:rgba(0,201,138,.1);border:1px solid rgba(0,201,138,.3)}
.result-banner.err{background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.25)}
.rb-row{display:flex;align-items:center;gap:10px;margin-bottom:5px}
.rb-icon{font-size:22px}
.rb-title{font-size:16px;font-weight:800;color:var(--text)}
.rb-detail{font-size:13px;color:var(--sub);line-height:1.5}
.rank-badge{display:inline-flex;align-items:center;gap:5px;margin-top:8px;padding:5px 12px;
  border-radius:20px;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);
  color:var(--amber);font-size:13px;font-weight:700;font-family:'Space Mono',monospace}

/* ── FOOTER NAV ── */
.q-footer{display:flex;align-items:center;gap:10px;padding:11px 16px;
  border-top:1px solid var(--border);flex-wrap:wrap}
.nav-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:9px;
  border:1px solid var(--border);background:var(--s2);color:var(--sub);
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:600;
  cursor:pointer;transition:all .15s}
.nav-btn:hover:not(:disabled){color:var(--text);border-color:var(--border2)}
.nav-btn:disabled{opacity:.35;cursor:not-allowed}
.prog-wrap{flex:1;display:flex;flex-direction:column;gap:3px;min-width:80px}
.prog-bar{height:4px;border-radius:4px;background:var(--s3);overflow:hidden}
.prog-fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--blue));transition:width .4s}
.prog-lbl{font-family:'Space Mono',monospace;font-size:9px;color:var(--dim)}

/* ── WINNER BANNER ── */
.winner-banner{display:none;align-items:center;gap:10px;padding:11px 16px;border-radius:11px;
  margin-bottom:12px;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);
  font-size:14px;font-weight:700;color:var(--amber);animation:si .3s ease}

/* ── NO QUESTION ── */
.no-q{text-align:center;padding:40px 20px;color:var(--sub)}
.no-q .nq-icon{font-size:42px;opacity:.3;margin-bottom:12px}

/* ── LIVE FEED ── */
.feed-wrap{position:sticky;top:70px}
.feed-qlabel{padding:8px 14px;background:var(--s2);border-bottom:1px solid var(--border);
  font-size:11px;color:var(--dim);font-family:'Space Mono',monospace;line-height:1.4}
.feed{padding:10px;max-height:72vh;overflow-y:auto}
.feed::-webkit-scrollbar{width:3px}
.feed::-webkit-scrollbar-thumb{background:var(--s3)}
.empty-feed{display:flex;flex-direction:column;align-items:center;
  padding:28px 16px;color:var(--dim);gap:8px;text-align:center}
.w-dots{display:flex;gap:5px;margin-bottom:4px}
.w-dot{width:7px;height:7px;border-radius:50%;background:var(--s3);animation:wd 1.2s infinite}
.w-dot:nth-child(2){animation-delay:.2s}
.w-dot:nth-child(3){animation-delay:.4s}
@keyframes wd{0%,80%,100%{transform:scale(.7);opacity:.4}40%{transform:scale(1);opacity:1}}

/* Feed item */
.feed-item{display:flex;align-items:flex-start;gap:9px;padding:10px;border-radius:10px;
  background:var(--s2);border:1px solid var(--border);margin-bottom:7px;
  transition:border-color .15s;animation:fi .2s cubic-bezier(.22,1,.36,1) both}
@keyframes fi{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
.feed-item.mine{border-color:rgba(59,130,246,.25);background:rgba(59,130,246,.05)}
.fi-av{width:32px;height:32px;border-radius:9px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:12px;font-weight:700}
.fi-body{flex:1;min-width:0}
.fi-name{font-size:12.5px;font-weight:700;color:var(--text);margin-bottom:2px;
  display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.fi-time{font-size:10px;color:var(--dim);font-family:'Space Mono',monospace;margin-bottom:5px}
.fi-option{display:inline-flex;padding:2px 9px;border-radius:6px;
  background:var(--s3);border:1px solid var(--border);
  font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--sub);margin-bottom:4px}
.fi-text{font-size:12.5px;color:var(--sub);line-height:1.5;
  background:var(--s3);border-radius:7px;padding:6px 9px;margin-bottom:4px}
.fi-attach img{max-height:70px;border-radius:6px;border:1px solid var(--border);margin-top:4px}
.fi-attach audio{width:100%;margin-top:4px}
.fi-right{flex-shrink:0}
.correct-tag{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;
  font-size:10px;font-weight:700;font-family:'Space Mono',monospace}
.correct-tag.c{background:rgba(0,201,138,.12);color:var(--accent)}
.correct-tag.w{background:rgba(255,71,87,.08);color:var(--danger)}

/* Reactions */
.react-row{display:flex;gap:4px;flex-wrap:wrap;margin-top:6px}
.react-btn{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:20px;
  background:var(--s3);border:1px solid var(--border);font-size:12px;cursor:pointer;
  transition:all .12s;user-select:none}
.react-btn:hover{border-color:var(--border2);transform:scale(1.08)}
.react-btn.reacted{background:rgba(59,130,246,.12);border-color:rgba(59,130,246,.3)}
.react-count{font-size:10.5px;color:var(--sub);font-family:'Space Mono',monospace}
</style>
</head>
<body>

<!-- Toast -->
<div class="toast" id="toast"></div>

<nav class="topbar">
  <div class="tb-brand">
    <div class="tb-logo">ES</div>
    <span class="tb-name">BRAINSTORM</span>
    <div class="live-pill <?= $activeQ ? '' : 'no-live' ?>" id="livePill">
      <span class="ldot"></span>
      <span id="livePillText"><?= $activeQ ? 'LIVE' : 'STANDBY' ?></span>
    </div>
  </div>
  <div class="tb-right">
    <div class="user-chip">
      <?php if($user_picture):?><img src="<?=htmlspecialchars($user_picture)?>" alt=""><?php else:?><div class="user-av"><?=htmlspecialchars(mb_strtoupper(mb_substr($user_name,0,1)))?></div><?php endif;?>
      <span><?=htmlspecialchars($user_name)?></span>
    </div>
    <a href="../dashboard.php" class="t-btn" title="Dashboard"><i class="fa fa-house"></i></a>
  </div>
</nav>

<div class="wrap">
  <div class="grid">

    <!-- ══ LEFT: Question ══ -->
    <div>
      <div class="winner-banner" id="winnerBanner">
        <i class="fa fa-trophy" style="color:var(--amber)"></i>
        <span id="winnerText">First correct answer!</span>
      </div>

      <div class="card">
        <div class="card-head">
          <span class="card-title"><span class="ldot"></span> Question</span>
          <span id="qIdLabel" style="font-family:'Space Mono',monospace;font-size:10px;color:var(--dim)"></span>
        </div>

        <?php if(count($allQuestions) > 1): ?>
        <div class="q-nav" id="qNav"></div>
        <?php endif; ?>

        <div id="qSlide" class="q-slide">
          <div class="card-body">
            <div class="q-tag"><span class="ldot" style="width:6px;height:6px"></span><span id="qNumLabel">Question</span></div>
            <div class="q-text" id="qText">
              <?php if(!empty($allQuestions)): ?>
                <?=htmlspecialchars($allQuestions[$startIdx]['question'])?>
              <?php else: ?>
                No questions yet
              <?php endif; ?>
            </div>

            <?php if(empty($allQuestions)): ?>
            <div class="no-q">
              <div class="nq-icon">📡</div>
              <div style="font-size:15px;font-weight:700;color:var(--text)">Waiting for teacher…</div>
              <div style="font-size:13px;margin-top:6px">This page updates automatically when a question goes live.</div>
            </div>
            <?php else: ?>

            <form id="answerForm">
              <input type="hidden" name="question_id" id="questionId" value="<?=(int)($allQuestions[$startIdx]['id']??0)?>">

              <div class="options" id="optionsArea">
                <?php foreach(['A'=>'option_a','B'=>'option_b','C'=>'option_c','D'=>'option_d'] as $ltr=>$col): ?>
                <?php if(!empty($allQuestions[$startIdx][$col])): ?>
                <button type="button" class="opt-btn" data-value="<?=$ltr?>">
                  <div class="opt-ltr"><?=$ltr?></div>
                  <div class="opt-text"><?=htmlspecialchars($allQuestions[$startIdx][$col])?></div>
                  <span class="opt-mark"></span>
                </button>
                <?php endif; ?>
                <?php endforeach; ?>
              </div>

              <div class="or-div">or type your answer</div>
              <textarea name="answer_text" id="answerText" class="field" rows="2" placeholder="Type an answer or explanation…"></textarea>

              <div class="upload-row">
                <label>
                  <input type="file" name="attachment" id="attachInput" accept="image/*" style="display:none">
                  <div class="upload-chip" id="imgChip" onclick="document.getElementById('attachInput').click()">
                    <i class="fa fa-image" style="font-size:11px"></i> Add Image
                  </div>
                </label>
                <div class="upload-chip" id="recBtn">
                  <i class="fa fa-microphone" style="font-size:11px"></i>
                  <span id="recLabel">Record Voice</span>
                </div>
                <span id="recStatus" style="font-size:11px;color:var(--dim)"></span>
              </div>
              <div id="imgPreview"></div>
              <div id="audioPreview"></div>

              <div class="submit-row">
                <button type="submit" class="submit-btn" id="submitBtn">
                  <i class="fa fa-paper-plane" style="font-size:11px"></i> Submit Answer
                </button>
              </div>
            </form>

            <div class="result-banner" id="resultBanner">
              <div class="rb-row"><span class="rb-icon" id="rbIcon"></span><span class="rb-title" id="rbTitle"></span></div>
              <div class="rb-detail" id="rbDetail"></div>
              <div id="rbRank"></div>
            </div>

            <?php endif; ?>
          </div>
        </div>

        <?php if(count($allQuestions) > 1): ?>
        <div class="q-footer" id="qFooter">
          <button class="nav-btn" id="prevBtn" disabled>
            <i class="fa fa-chevron-left" style="font-size:10px"></i> Prev
          </button>
          <div class="prog-wrap">
            <div class="prog-bar"><div class="prog-fill" id="progFill" style="width:0%"></div></div>
            <div class="prog-lbl" id="progLbl">0 / <?=count($allQuestions)?> answered</div>
          </div>
          <button class="nav-btn" id="nextBtn">
            Next <i class="fa fa-chevron-right" style="font-size:10px"></i>
          </button>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ══ RIGHT: Live Feed ══ -->
    <aside>
      <div class="card feed-wrap">
        <div class="card-head">
          <span class="card-title"><span class="ldot"></span> Live Answers</span>
          <span id="ansCount" style="font-family:'Space Mono',monospace;font-size:11px;color:var(--sub)">0</span>
        </div>
        <div class="feed-qlabel" id="feedQLabel">Current question answers</div>
        <div class="feed" id="feed">
          <div class="empty-feed">
            <div class="w-dots"><div class="w-dot"></div><div class="w-dot"></div><div class="w-dot"></div></div>
            <span>No answers yet — be first! 🚀</span>
          </div>
        </div>
      </div>
    </aside>

  </div>
</div>

<script>
/* ════════════════════════════════════════
   DATA FROM PHP
════════════════════════════════════════ */
const ALL_QS      = <?=json_encode(array_values($allQuestions), JSON_UNESCAPED_UNICODE)?>;
const START_IDX   = <?=(int)$startIdx?>;
const MY_NAME     = <?=json_encode($user_name)?>;
const MY_ID       = <?=json_encode($user_id)?>;
const MY_ANSWERED = <?=json_encode($myAnsweredIds, JSON_UNESCAPED_UNICODE)?>;

/* ════════════════════════════════════════
   STATE
════════════════════════════════════════ */
let currentIdx   = START_IDX;
let userAnswers  = {};      // qid → { correct, correctAnswer, rank, chosen, preAnswered }
let feedPollInt  = null;    // setInterval for live feed
let activeQPoll  = null;    // setInterval for active-question change detection
let lastActiveId = null;    // track current teacher question
let selOption    = null;
let recordedBlob = null;
let mediaRec, audioChunks;

// Pre-fill known answers from server
Object.entries(MY_ANSWERED).forEach(([qid, correct]) => {
  userAnswers[parseInt(qid)] = { correct, correctAnswer: null, preAnswered: true };
});

// Track the active question ID at page load
<?php if($activeQ): ?>
lastActiveId = <?=(int)$activeQ['id']?>;
<?php endif; ?>

/* ════════════════════════════════════════
   UTILS
════════════════════════════════════════ */
function esc(s) {
  return s ? String(s).replace(/[&<>"']/g, c => (
    { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;" }[c]
  )) : '';
}
const AVC = ['#0095f6','#e6683c','#dc2743','#cc2366','#8a3ab9','#0cba78','#f58529','#1877f2','#7209b7','#f72585'];
function avBg(n) { let h=0; for(const c of (n||'')) h=(h*31+c.charCodeAt(0))&0xffffff; return AVC[Math.abs(h)%AVC.length]; }
function avIni(s) { return (s||'?').trim().charAt(0).toUpperCase(); }
async function apiFetch(url, opts={}) {
  const r = await fetch(url, { credentials:'same-origin', ...opts });
  return r.json();
}

/* ════════════════════════════════════════
   TOAST
════════════════════════════════════════ */
let toastTimer;
function showToast(msg, type='') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast show' + (type ? ' '+type : '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3500);
}

/* ════════════════════════════════════════
   ACTIVE QUESTION POLL
   Every 5s, check if teacher changed the active question.
   If so: update nav dots + show toast + auto-navigate.
════════════════════════════════════════ */
async function pollActiveQuestion() {
  try {
    const r = await apiFetch('live_brainstorm.php?action=get_active_id');
    if (!r.success) return;
    const newId = r.id;

    if (newId === null) {
      // No active question — update pill
      updateLivePill(false);
      return;
    }
    updateLivePill(true);

    if (newId !== lastActiveId) {
      lastActiveId = newId;
      // Find the index of the new active question in our list
      const idx = ALL_QS.findIndex(q => +q.id === +newId);
      if (idx !== -1) {
        ALL_QS.forEach(q => q.status = (+q.id === +newId ? 'active' : 'inactive'));
        syncNav();
        // Only auto-jump if user hasn't already answered that question
        if (!userAnswers[newId]) {
          goTo(idx);
          showToast('🔴 New question is live!', 'live');
        } else {
          // Just update the nav dot styling
          syncNav();
          showToast('🔴 Teacher activated a new question!', 'live');
        }
      }
    }
  } catch(e) {}
}

function updateLivePill(isLive) {
  const pill = document.getElementById('livePill');
  const text = document.getElementById('livePillText');
  if (isLive) {
    pill.classList.remove('no-live');
    text.textContent = 'LIVE';
  } else {
    pill.classList.add('no-live');
    text.textContent = 'STANDBY';
  }
}

/* ════════════════════════════════════════
   NAVIGATOR DOTS
════════════════════════════════════════ */
function buildNav() {
  const nav = document.getElementById('qNav');
  if (!nav) return;
  nav.innerHTML = `<span class="qn-count">${ALL_QS.length} Q</span><div class="qn-sep"></div>`;
  ALL_QS.forEach((q, i) => {
    const d = document.createElement('div');
    d.className = 'qn-dot'; d.id = 'dot_'+i; d.textContent = i+1;
    d.title = (q.question||'').slice(0,60);
    const qa = userAnswers[q.id];
    if (q.status==='active')    d.classList.add('is-live');
    if (qa)                     d.classList.add(qa.correct ? 'done-c' : 'done-w');
    if (i === currentIdx)       d.classList.add('is-current');
    d.addEventListener('click', () => goTo(i));
    nav.appendChild(d);
  });
  updateProg();
}

function syncNav() {
  ALL_QS.forEach((q, i) => {
    const d = document.getElementById('dot_'+i); if (!d) return;
    d.className = 'qn-dot';
    if (q.status==='active')    d.classList.add('is-live');
    const qa = userAnswers[q.id];
    if (qa)                     d.classList.add(qa.correct ? 'done-c' : 'done-w');
    if (i === currentIdx)       d.classList.add('is-current');
  });
  updateProg();
}

function updateProg() {
  const done = Object.keys(userAnswers).length;
  const pct  = ALL_QS.length ? Math.round(100*done/ALL_QS.length) : 0;
  const pf   = document.getElementById('progFill');
  const pl   = document.getElementById('progLbl');
  if (pf) pf.style.width = pct+'%';
  if (pl) pl.textContent = `${done} / ${ALL_QS.length} answered`;
}

/* ════════════════════════════════════════
   RENDER QUESTION
════════════════════════════════════════ */
function renderQuestion(idx) {
  currentIdx = idx;
  const q = ALL_QS[idx]; if (!q) return;

  // Slide animation
  const slide = document.getElementById('qSlide');
  if (slide) { slide.classList.remove('q-slide'); void slide.offsetWidth; slide.classList.add('q-slide'); }

  // Labels
  const numEl = document.getElementById('qNumLabel');
  if (numEl) numEl.textContent = `Question ${idx+1} of ${ALL_QS.length}${q.status==='active' ? ' · 🔴 Live' : ''}`;
  const idEl = document.getElementById('qIdLabel');
  if (idEl) idEl.textContent = '#'+q.id;
  const qtEl = document.getElementById('qText');
  if (qtEl) qtEl.textContent = q.question;
  const qidEl = document.getElementById('questionId');
  if (qidEl) qidEl.value = q.id;

  // Options
  const area = document.getElementById('optionsArea');
  if (area) {
    area.innerHTML = '';
    const qa = userAnswers[q.id];
    ['A','B','C','D'].forEach(l => {
      const val = q['option_'+l.toLowerCase()]; if (!val) return;
      const btn = document.createElement('button');
      btn.type='button'; btn.className='opt-btn'; btn.dataset.value=l;
      btn.innerHTML = `<div class="opt-ltr">${l}</div><div class="opt-text">${esc(val)}</div><span class="opt-mark"></span>`;
      if (qa) {
        btn.disabled = true;
        if (qa.correctAnswer && l===qa.correctAnswer) {
          btn.classList.add('correct'); btn.querySelector('.opt-mark').textContent='✓';
        } else if (qa.chosen && l===qa.chosen && !qa.correct) {
          btn.classList.add('wrong'); btn.querySelector('.opt-mark').textContent='✗';
        }
      } else {
        btn.addEventListener('click', () => {
          document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('selected'));
          btn.classList.add('selected'); selOption = l;
        });
      }
      area.appendChild(btn);
    });
  }

  // Result banner
  const rb = document.getElementById('resultBanner');
  if (rb) {
    const qa = userAnswers[q.id];
    if (qa && !qa.preAnswered) showResult(qa.correct, qa.correctAnswer, qa.rank, null);
    else rb.style.display = 'none';
  }

  // Submit btn
  const sb = document.getElementById('submitBtn');
  if (sb) sb.disabled = !!userAnswers[q.id];

  // Nav buttons
  const pb = document.getElementById('prevBtn');
  const nb = document.getElementById('nextBtn');
  if (pb) pb.disabled = idx === 0;
  if (nb) {
    nb.disabled = idx === ALL_QS.length-1;
    nb.className = 'nav-btn';
    nb.innerHTML = 'Next <i class="fa fa-chevron-right" style="font-size:10px"></i>';
  }

  selOption = null;
  syncNav();

  // Feed: cancel old poll, start fresh for this question
  clearInterval(feedPollInt);
  refreshFeed(q.id);
  const fl = document.getElementById('feedQLabel');
  if (fl) fl.textContent = `Q${idx+1}: ${(q.question||'').slice(0,42)}…`;
  feedPollInt = setInterval(() => refreshFeed(q.id), 2000);
}

/* ════════════════════════════════════════
   NAV
════════════════════════════════════════ */
function goTo(idx) {
  selOption = null;
  renderQuestion(idx);
  window.scrollTo({ top:0, behavior:'smooth' });
}
document.getElementById('prevBtn')?.addEventListener('click', () => goTo(currentIdx-1));
document.getElementById('nextBtn')?.addEventListener('click', () => goTo(currentIdx+1));

/* ════════════════════════════════════════
   IMAGE PREVIEW
════════════════════════════════════════ */
document.getElementById('attachInput')?.addEventListener('change', function() {
  const w = document.getElementById('imgPreview'); w.innerHTML='';
  if (this.files && this.files[0]) {
    const chip = document.getElementById('imgChip');
    chip.classList.add('has-file');
    chip.innerHTML = '<i class="fa fa-check" style="font-size:11px"></i> Image ready';
    const img = document.createElement('img');
    img.className='preview-img'; img.src=URL.createObjectURL(this.files[0]);
    w.appendChild(img);
  }
});

/* ════════════════════════════════════════
   VOICE RECORDING
════════════════════════════════════════ */
const recBtn   = document.getElementById('recBtn');
const recLabel = document.getElementById('recLabel');
const recStatus= document.getElementById('recStatus');

async function startRecording() {
  if (!navigator.mediaDevices?.getUserMedia) { alert('Recording not supported'); return; }
  const stream = await navigator.mediaDevices.getUserMedia({ audio:true });
  mediaRec    = new MediaRecorder(stream);
  audioChunks = [];
  mediaRec.ondataavailable = e => audioChunks.push(e.data);
  mediaRec.onstop = () => {
    recordedBlob = new Blob(audioChunks, { type:'audio/webm' });
    recStatus.textContent = 'Recorded ✓';
    recBtn.classList.remove('recording'); recBtn.classList.add('has-file');
    recLabel.textContent = 'Re-record';
    const w = document.getElementById('audioPreview'); w.innerHTML='';
    const au = document.createElement('audio');
    au.className='audio-preview'; au.controls=true;
    au.src = URL.createObjectURL(recordedBlob); w.appendChild(au);
  };
  mediaRec.start();
  recBtn.classList.add('recording'); recBtn.classList.remove('has-file');
  recLabel.textContent = 'Stop'; recStatus.textContent = '🔴 Recording…';
}
function stopRecording() { if (mediaRec && mediaRec.state!=='inactive') mediaRec.stop(); }

recBtn?.addEventListener('click', () => {
  if (!mediaRec || mediaRec.state==='inactive')
    startRecording().catch(e => alert('Cannot start recording: '+e.message));
  else stopRecording();
});

/* ════════════════════════════════════════
   SUBMIT — uses FormData (supports files)
════════════════════════════════════════ */
document.getElementById('answerForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const qid = parseInt(document.getElementById('questionId').value);
  if (!qid) { alert('No question selected'); return; }
  if (userAnswers[qid]) { alert('Already answered!'); return; }

  const sb = document.getElementById('submitBtn');
  sb.disabled = true;
  sb.innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size:11px"></i> Submitting…';

  const fd = new FormData();
  fd.append('question_id', qid);
  if (selOption) fd.append('selected_option', selOption);
  const txt = document.getElementById('answerText').value.trim();
  if (txt) fd.append('answer_text', txt);
  const fi = document.getElementById('attachInput');
  if (fi?.files?.[0]) fd.append('attachment', fi.files[0]);
  if (recordedBlob) fd.append('voice', recordedBlob, 'voice.webm');

  try {
    const j = await apiFetch('live_brainstorm.php?action=submit_answer', { method:'POST', body:fd });
    if (!j.success) {
      showResult(false, null, null, j.error || 'Submission failed');
      sb.disabled = false;
      sb.innerHTML = '<i class="fa fa-paper-plane" style="font-size:11px"></i> Submit Answer';
      return;
    }

    userAnswers[qid] = { correct:j.is_correct, correctAnswer:j.correct_answer, rank:j.rank, chosen:selOption };

    // Reveal correct/wrong on buttons
    document.querySelectorAll('.opt-btn').forEach(b => {
      const l = b.getAttribute('data-value');
      if (l === j.correct_answer) { b.classList.add('correct'); b.querySelector('.opt-mark').textContent='✓'; }
      else if (l === selOption && !j.is_correct) { b.classList.add('wrong'); b.querySelector('.opt-mark').textContent='✗'; }
      else b.classList.remove('selected');
      b.disabled = true;
    });

    showResult(j.is_correct, j.correct_answer, j.rank, null);
    syncNav();

    // Pulse "Next" after 1.2s
    setTimeout(() => {
      const nb = document.getElementById('nextBtn');
      if (nb && currentIdx < ALL_QS.length-1) {
        nb.className = 'nav-btn submit-btn next-mode';
        nb.innerHTML = 'Next Question <i class="fa fa-chevron-right" style="font-size:10px"></i>';
      }
    }, 1200);

    // Reset form extras
    document.getElementById('answerText').value = '';
    if (fi) fi.value='';
    selOption=null; recordedBlob=null; recStatus.textContent='';
    if (recBtn) { recBtn.classList.remove('recording','has-file'); recLabel.textContent='Record Voice'; }
    document.getElementById('imgPreview').innerHTML  = '';
    document.getElementById('audioPreview').innerHTML = '';
    const chip = document.getElementById('imgChip');
    if (chip) { chip.classList.remove('has-file'); chip.innerHTML='<i class="fa fa-image" style="font-size:11px"></i> Add Image'; }

    await refreshFeed(qid);

  } catch(err) {
    showResult(false, null, null, 'Network error: '+err.message);
    sb.disabled = false;
    sb.innerHTML = '<i class="fa fa-paper-plane" style="font-size:11px"></i> Submit Answer';
  }
});

/* ════════════════════════════════════════
   SHOW RESULT BANNER
════════════════════════════════════════ */
function showResult(ok, ca, rank, err) {
  const b = document.getElementById('resultBanner'); if (!b) return;
  if (err) {
    b.className='result-banner err';
    document.getElementById('rbIcon').textContent='⚠️';
    document.getElementById('rbTitle').textContent='Error';
    document.getElementById('rbDetail').textContent=err;
    document.getElementById('rbRank').innerHTML='';
  } else if (ok) {
    b.className='result-banner ok';
    document.getElementById('rbIcon').textContent='🎉';
    document.getElementById('rbTitle').textContent='Correct!';
    document.getElementById('rbDetail').textContent='Great job! You got it right.';
    document.getElementById('rbRank').innerHTML = rank ? `<div class="rank-badge">🏅 You are #${rank} correct</div>` : '';
  } else {
    b.className='result-banner err';
    document.getElementById('rbIcon').textContent='❌';
    document.getElementById('rbTitle').textContent='Incorrect';
    document.getElementById('rbDetail').textContent = ca ? `Correct answer was: Option ${esc(ca)}` : 'Better luck next time!';
    document.getElementById('rbRank').innerHTML='';
  }
  b.style.display='block';
}

/* ════════════════════════════════════════
   REACTIONS — per-type toggling
   my_reactions is now an array of type strings
════════════════════════════════════════ */
async function toggleReact(answerId, type, btn) {
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('answer_id', answerId);
    fd.append('type', type);
    const r = await apiFetch('live_brainstorm.php?action=react', { method:'POST', body:fd });
    if (r.success) {
      // Toggle the .reacted class on THIS button only
      btn.classList.toggle('reacted', r.action==='added');
      // Update count displayed on this button
      const cnt = r.counts?.[type] || 0;
      let countEl = btn.querySelector('.react-count');
      if (cnt > 0) {
        if (!countEl) { countEl = document.createElement('span'); countEl.className='react-count'; btn.appendChild(countEl); }
        countEl.textContent = cnt;
      } else {
        countEl?.remove();
      }
    }
  } catch(e) {}
  btn.disabled = false;
}

/* ════════════════════════════════════════
   FEED
════════════════════════════════════════ */
const REACT_EMOJIS = { like:'👍', heart:'❤️', fire:'🔥', clap:'👏' };

async function refreshFeed(qid) {
  const id = qid || ALL_QS[currentIdx]?.id;
  if (!id) return;
  try {
    const r = await apiFetch(`live_brainstorm.php?action=get_answers&question_id=${id}`);
    if (!r.success) return;

    const feed = document.getElementById('feed');
    const cnt  = document.getElementById('ansCount');
    if (cnt) cnt.textContent = r.answers.length;
    feed.innerHTML = '';

    if (!r.answers.length) {
      feed.innerHTML = '<div class="empty-feed"><div class="w-dots"><div class="w-dot"></div><div class="w-dot"></div><div class="w-dot"></div></div><span>No answers yet — be first! 🚀</span></div>';
      const wb = document.getElementById('winnerBanner');
      if (wb) wb.style.display = 'none';
      return;
    }

    r.answers.forEach((a, i) => {
      const isMe = (+a.user_id === +MY_ID) || (a.name === MY_NAME);
      const bg   = avBg(a.name || '?');

      // Build reaction buttons — per-type, using my_reactions array
      let rHtml = '<div class="react-row">';
      Object.entries(REACT_EMOJIS).forEach(([type, emoji]) => {
        const c        = a.reactions?.[type] || 0;
        const reacted  = Array.isArray(a.my_reactions) && a.my_reactions.includes(type);
        rHtml += `<button class="react-btn${reacted?' reacted':''}" onclick="toggleReact(${a.id},'${type}',this)">`
               + emoji
               + (c > 0 ? `<span class="react-count">${c}</span>` : '')
               + '</button>';
      });
      rHtml += '</div>';

      const item = document.createElement('div');
      item.className = 'feed-item' + (isMe ? ' mine' : '');
      item.setAttribute('data-aid', a.id);
      item.innerHTML = `
        <div class="fi-av" style="background:${bg};color:#fff">${esc(avIni(a.name||'?'))}</div>
        <div class="fi-body">
          <div class="fi-name">
            ${esc(a.name||'Anon')}
            ${isMe  ? '<span style="font-size:10px;color:var(--blue)">(you)</span>'   : ''}
            ${i===0 ? '<span style="font-size:10px;color:var(--amber)">⚡ First</span>' : ''}
          </div>
          <div class="fi-time">${a.created_at||''}</div>
          ${a.selected_option ? `<div class="fi-option">Option ${esc(a.selected_option)}</div>` : ''}
          ${a.answer_text     ? `<div class="fi-text">${esc(a.answer_text)}</div>` : ''}
          ${a.attachment      ? `<div class="fi-attach"><img src="../${esc(a.attachment)}" alt=""></div>` : ''}
          ${a.voice_path      ? `<div class="fi-attach"><audio controls class="audio-preview" src="../${esc(a.voice_path)}"></audio></div>` : ''}
          ${rHtml}
        </div>
        <div class="fi-right">
          <span class="correct-tag ${a.is_correct?'c':'w'}">${a.is_correct?'✓':'✗'}</span>
        </div>`;
      feed.appendChild(item);
    });

    // Winner banner: first correct answer
    const fw = r.answers.find(x => x.is_correct);
    const wb = document.getElementById('winnerBanner');
    if (fw && wb) {
      wb.style.display = 'flex';
      document.getElementById('winnerText').textContent = `🥇 First correct: ${fw.name}`;
    } else if (wb) {
      wb.style.display = 'none';
    }
  } catch(e) {}
}

/* ════════════════════════════════════════
   BOOT
════════════════════════════════════════ */
buildNav();
renderQuestion(START_IDX);

// Active-question detection poll (every 5s)
activeQPoll = setInterval(pollActiveQuestion, 5000);

// Slow down feed + active poll when tab is hidden; speed back up on focus
document.addEventListener('visibilitychange', () => {
  clearInterval(feedPollInt);
  clearInterval(activeQPoll);
  const qid = ALL_QS[currentIdx]?.id;
  const feedRate   = document.hidden ? 8000 : 2000;
  const activeRate = document.hidden ? 15000 : 5000;
  feedPollInt  = setInterval(() => refreshFeed(qid), feedRate);
  activeQPoll  = setInterval(pollActiveQuestion, activeRate);
  if (!document.hidden) { refreshFeed(qid); pollActiveQuestion(); }
});
</script>
</body>
</html>

