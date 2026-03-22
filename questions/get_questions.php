<?php
// questions/get_questions.php — Admin Live Brainstorm Control Center
// Works hand-in-hand with questions/live_brainstorm.php (shared DB tables)

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
session_start();
require_once __DIR__ . "/../config/db.php";

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// ── Ensure columns exist (safe, idempotent) ──────────────────────
@$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT DEFAULT 0");
@$conn->query("ALTER TABLE brainstorm_answers ADD COLUMN IF NOT EXISTS admin_liked TINYINT DEFAULT 0");
@$conn->query("ALTER TABLE brainstorm_answers ADD COLUMN IF NOT EXISTS admin_note VARCHAR(500) DEFAULT NULL");
@$conn->query("ALTER TABLE questions ADD COLUMN IF NOT EXISTS timer_ends_at DATETIME DEFAULT NULL");
@$conn->query("ALTER TABLE questions ADD COLUMN IF NOT EXISTS next_in_queue TINYINT DEFAULT 0");

$userRow = [];
if ($user_id) {
    $s = $conn->prepare("SELECT username, email, google_name, google_picture, is_admin FROM users WHERE id=? LIMIT 1");
    if ($s) { $s->bind_param('i',$user_id); $s->execute(); $userRow=$s->get_result()->fetch_assoc()??[]; $s->close(); }
}
$display_name    = $_SESSION['google_name']    ?? $userRow['google_name']    ?? $userRow['username'] ?? 'Admin';
$display_picture = $_SESSION['google_picture'] ?? $userRow['google_picture'] ?? null;

if (!$user_id) { header('Location: ../login.html'); exit; }

function jout(array $d): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE); exit;
}
function xss(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'utf-8'); }

$action = $_REQUEST['action'] ?? '';

// ── API: questions list ──────────────────────────────────────────
if ($action === 'questions_list') {
    $res = $conn->query("
        SELECT q.id, q.question, q.option_a, q.option_b, q.option_c, q.option_d,
               q.correct_answer, q.status, q.created_at, q.next_in_queue,
               q.timer_ends_at,
               s.name AS subject_name,
               (SELECT COUNT(*) FROM brainstorm_answers ba WHERE ba.question_id=q.id) AS total_answers,
               (SELECT COUNT(*) FROM brainstorm_answers ba WHERE ba.question_id=q.id AND ba.is_correct=1) AS correct_count
        FROM questions q LEFT JOIN subjects s ON s.id=q.subject_id
        ORDER BY FIELD(q.status,'active','inactive'), q.id DESC LIMIT 100");
    $rows=[];
    while($r=$res->fetch_assoc()) $rows[]=$r;
    jout(['success'=>true,'questions'=>$rows]);
}

// ── API: enriched answers for admin ─────────────────────────────
if ($action === 'answers') {
    $qid = (int)($_GET['qid']??0);
    if (!$qid) jout(['success'=>false,'error'=>'missing qid']);

    // Per-option counts
    $optCounts = ['A'=>0,'B'=>0,'C'=>0,'D'=>0];
    $ocStmt = $conn->prepare("SELECT selected_option, COUNT(*) AS c FROM brainstorm_answers WHERE question_id=? AND selected_option IS NOT NULL GROUP BY selected_option");
    $ocStmt->bind_param('i',$qid); $ocStmt->execute();
    $ocRes = $ocStmt->get_result();
    while ($oc = $ocRes->fetch_assoc()) {
        $l = strtoupper(trim($oc['selected_option']));
        if (isset($optCounts[$l])) $optCounts[$l] = (int)$oc['c'];
    }

    $stmt = $conn->prepare("
        SELECT ba.id, ba.user_id, ba.selected_option, ba.answer_text,
               ba.attachment, ba.voice_path, ba.is_correct, ba.admin_liked, ba.admin_note, ba.created_at,
               COALESCE(u.google_name, u.username, u.email, 'Student') AS student_name,
               u.google_picture AS student_pic,
               q.correct_answer, q.created_at AS q_created
        FROM brainstorm_answers ba
        LEFT JOIN users u ON u.id=ba.user_id
        LEFT JOIN questions q ON q.id=ba.question_id
        WHERE ba.question_id=? ORDER BY ba.created_at ASC LIMIT 500");
    $stmt->bind_param('i',$qid); $stmt->execute();
    $rows=[]; $rank=0; $crank=0;
    $res = $stmt->get_result();
    while ($r=$res->fetch_assoc()) {
        $rank++;
        if ((int)$r['is_correct']) $crank++;
        $secs = null;
        if ($r['q_created']) $secs = max(0,(int)(strtotime($r['created_at'])-strtotime($r['q_created'])));
        $rx = $conn->prepare("SELECT type, COUNT(*) c FROM brainstorm_reactions WHERE answer_id=? GROUP BY type");
        $rx->bind_param('i',$r['id']); $rx->execute();
        $reacts=[];
        while($rr=$rx->get_result()->fetch_assoc()) $reacts[$rr['type']]=(int)$rr['c'];
        $rx->close();
        $rows[] = [
            'id'=>(int)$r['id'], 'user_id'=>(int)$r['user_id'],
            'student_name'=>$r['student_name'], 'student_pic'=>$r['student_pic'],
            'selected_option'=>$r['selected_option'], 'answer_text'=>$r['answer_text'],
            'attachment'=>$r['attachment'], 'voice_path'=>$r['voice_path'],
            'is_correct'=>(int)$r['is_correct'], 'admin_liked'=>(int)$r['admin_liked'],
            'admin_note'=>$r['admin_note'], 'correct_answer'=>$r['correct_answer'],
            'created_at'=>$r['created_at'], 'rank'=>$rank,
            'correct_rank'=>((int)$r['is_correct'])?$crank:null,
            'secs_taken'=>$secs, 'reactions'=>$reacts,
        ];
    }
    $stmt->close();
    jout(['success'=>true,'answers'=>$rows,'opt_counts'=>$optCounts]);
}

// ── API: set_status (activate / deactivate) ──────────────────────
if ($action === 'set_status') {
    $qid    = (int)($_POST['qid']??0);
    $status = ($_POST['status']??'')==='active' ? 'active' : 'inactive';
    if (!$qid) jout(['success'=>false]);
    if ($status==='active') {
        $conn->query("UPDATE questions SET status='inactive', timer_ends_at=NULL");
    }
    $s = $conn->prepare("UPDATE questions SET status=? WHERE id=?");
    $s->bind_param('si',$status,$qid); $s->execute(); $s->close();
    jout(['success'=>true]);
}

// ── API: set_timer ───────────────────────────────────────────────
// Sets timer_ends_at on the currently active question.
// seconds=0 clears the timer.
if ($action === 'set_timer') {
    $qid  = (int)($_POST['qid']??0);
    $secs = (int)($_POST['seconds']??0);
    if (!$qid) jout(['success'=>false,'error'=>'missing qid']);
    if ($secs > 0) {
        $s = $conn->prepare("UPDATE questions SET timer_ends_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id=?");
        $s->bind_param('ii',$secs,$qid); $s->execute(); $s->close();
    } else {
        $s = $conn->prepare("UPDATE questions SET timer_ends_at = NULL WHERE id=?");
        $s->bind_param('i',$qid); $s->execute(); $s->close();
    }
    jout(['success'=>true]);
}

// ── API: get_timer (student page polls this) ─────────────────────
if ($action === 'get_timer') {
    $q = $conn->query("SELECT id, timer_ends_at FROM questions WHERE status='active' LIMIT 1");
    $row = $q ? $q->fetch_assoc() : null;
    if (!$row || !$row['timer_ends_at']) { jout(['success'=>true,'secs_left'=>null]); }
    $left = (int)(strtotime($row['timer_ends_at']) - time());
    jout(['success'=>true,'secs_left'=>max(0,$left),'qid'=>(int)$row['id']]);
}

// ── API: set_next_queue ──────────────────────────────────────────
// Marks a question as "next in queue". Only one at a time.
if ($action === 'set_next_queue') {
    $qid  = (int)($_POST['qid']??0);
    $val  = (int)($_POST['val']??1); // 1=queue, 0=dequeue
    $conn->query("UPDATE questions SET next_in_queue=0");
    if ($val && $qid) {
        $s = $conn->prepare("UPDATE questions SET next_in_queue=1 WHERE id=?");
        $s->bind_param('i',$qid); $s->execute(); $s->close();
    }
    jout(['success'=>true]);
}

// ── API: advance_to_next ─────────────────────────────────────────
// Deactivates current question, activates the queued-next one.
if ($action === 'advance_to_next') {
    $next = $conn->query("SELECT id FROM questions WHERE next_in_queue=1 LIMIT 1");
    $nextRow = $next ? $next->fetch_assoc() : null;
    if (!$nextRow) jout(['success'=>false,'error'=>'No question queued']);
    $nid = (int)$nextRow['id'];
    $conn->query("UPDATE questions SET status='inactive', timer_ends_at=NULL");
    $s = $conn->prepare("UPDATE questions SET status='active', next_in_queue=0 WHERE id=?");
    $s->bind_param('i',$nid); $s->execute(); $s->close();
    jout(['success'=>true,'qid'=>$nid]);
}

// ── API: like toggle ─────────────────────────────────────────────
if ($action === 'like') {
    $aid = (int)($_POST['aid']??0);
    if (!$aid) jout(['success'=>false]);
    $cur = (int)($conn->query("SELECT admin_liked FROM brainstorm_answers WHERE id=$aid LIMIT 1")->fetch_assoc()['admin_liked']??0);
    $new = $cur ? 0 : 1;
    $conn->query("UPDATE brainstorm_answers SET admin_liked=$new WHERE id=$aid");
    jout(['success'=>true,'liked'=>(bool)$new]);
}

// ── API: mark correct ────────────────────────────────────────────
if ($action === 'set_correct') {
    $aid = (int)($_POST['aid']??0);
    $c   = (int)($_POST['correct']??0) ? 1 : 0;
    if (!$aid) jout(['success'=>false]);
    $s = $conn->prepare("UPDATE brainstorm_answers SET is_correct=? WHERE id=?");
    $s->bind_param('ii',$c,$aid); $s->execute(); $s->close();
    jout(['success'=>true]);
}

// ── API: add note ────────────────────────────────────────────────
if ($action === 'add_note') {
    $aid  = (int)($_POST['aid']??0);
    $note = substr(trim($_POST['note']??''),0,500);
    if (!$aid) jout(['success'=>false]);
    $s = $conn->prepare("UPDATE brainstorm_answers SET admin_note=? WHERE id=?");
    $s->bind_param('si',$note,$aid); $s->execute(); $s->close();
    jout(['success'=>true]);
}

// ── API: clear answers ───────────────────────────────────────────
if ($action === 'clear_answers') {
    $qid = (int)($_POST['qid']??0);
    if (!$qid) jout(['success'=>false]);
    $conn->query("DELETE FROM brainstorm_answers WHERE question_id=$qid");
    jout(['success'=>true]);
}

// ── API: create question ─────────────────────────────────────────
if ($action === 'create_question') {
    $question = trim($_POST['question']??'');
    $opt_a    = trim($_POST['option_a']??'');
    $opt_b    = trim($_POST['option_b']??'');
    $opt_c    = trim($_POST['option_c']??'');
    $opt_d    = trim($_POST['option_d']??'');
    $correct  = strtoupper(trim($_POST['correct_answer']??'A'));
    $subj     = (int)($_POST['subject_id']??0) ?: null;
    $activate = (int)($_POST['activate']??0);
    if (!$question) jout(['success'=>false,'error'=>'Question text required']);
    if ($activate) $conn->query("UPDATE questions SET status='inactive'");
    $status = $activate ? 'active' : 'inactive';
    $s = $conn->prepare("INSERT INTO questions (question,option_a,option_b,option_c,option_d,correct_answer,subject_id,status,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())");
    $s->bind_param('ssssssss',$question,$opt_a,$opt_b,$opt_c,$opt_d,$correct,$subj,$status);
    if (!$s->execute()) jout(['success'=>false,'error'=>$conn->error]);
    $new_id=$conn->insert_id; $s->close();
    jout(['success'=>true,'qid'=>$new_id,'status'=>$status]);
}

// ── API: delete question ─────────────────────────────────────────
if ($action === 'delete_question') {
    $qid = (int)($_POST['qid']??0);
    if (!$qid) jout(['success'=>false]);
    $conn->query("DELETE FROM brainstorm_answers WHERE question_id=$qid");
    $conn->query("DELETE FROM questions WHERE id=$qid");
    jout(['success'=>true]);
}

// ── Page render ──────────────────────────────────────────────────
$subjects = [];
$r = $conn->query("SELECT id,name FROM subjects ORDER BY name");
if ($r) while($row=$r->fetch_assoc()) $subjects[]=$row;

$questions_init = [];
$r2 = $conn->query("
    SELECT q.id, q.question, q.option_a, q.option_b, q.option_c, q.option_d,
           q.correct_answer, q.status, q.created_at, q.next_in_queue,
           q.timer_ends_at,
           s.name AS subject_name,
           (SELECT COUNT(*) FROM brainstorm_answers ba WHERE ba.question_id=q.id) AS total_answers,
           (SELECT COUNT(*) FROM brainstorm_answers ba WHERE ba.question_id=q.id AND ba.is_correct=1) AS correct_count
    FROM questions q LEFT JOIN subjects s ON s.id=q.subject_id
    ORDER BY FIELD(q.status,'active','inactive'), q.id DESC LIMIT 100");
if ($r2) while($row=$r2->fetch_assoc()) $questions_init[]=$row;

$active_q = null;
$next_q   = null;
foreach ($questions_init as $q) {
    if ($q['status']==='active' && !$active_q) $active_q=$q;
    if ($q['next_in_queue'] && !$next_q)       $next_q=$q;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Brainstorm Control — Excellent Simplified</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#04020e;--s1:#0d1017;--s2:#131720;--s3:#1a1f2e;--s4:#202638;
  --border:rgba(255,255,255,.07);--border2:rgba(255,255,255,.13);
  --accent:#00c98a;--blue:#3b82f6;--amber:#f59e0b;--danger:#ff4757;--purple:#a78bfa;
  --text:#e8ecf4;--sub:#8a93ab;--dim:#4a5268;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);
  -webkit-font-smoothing:antialiased;
  background-image:radial-gradient(ellipse 80% 50% at 0 0,rgba(0,201,138,.05) 0,transparent 60%),
                   radial-gradient(ellipse 60% 50% at 100% 100%,rgba(59,130,246,.05) 0,transparent 60%)}
.app{display:flex;height:100vh;overflow:hidden}

/* ── LEFT: Question roster ── */
.ql{width:272px;flex-shrink:0;display:flex;flex-direction:column;background:var(--s1);border-right:1px solid var(--border);overflow:hidden}
.ql-top{padding:12px 12px 9px;flex-shrink:0;border-bottom:1px solid var(--border)}
.brand-row{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.brand-pill{display:flex;align-items:center;gap:7px;padding:5px 11px 5px 7px;background:var(--s3);border:1px solid var(--border);border-radius:20px}
.brand-dot{width:22px;height:22px;border-radius:6px;background:linear-gradient(135deg,var(--accent),var(--blue));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:8px;font-weight:700;color:#000}
.brand-name{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;letter-spacing:.07em;color:var(--sub)}
.ql-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.sec-lbl{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--dim)}
.new-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:7px;border:none;background:linear-gradient(135deg,var(--accent),var(--blue));color:#000;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.new-btn:hover{filter:brightness(1.1)}
.ql-srch{display:flex;align-items:center;gap:7px;background:var(--s3);border:1px solid var(--border);border-radius:8px;padding:7px 10px}
.ql-srch i{color:var(--dim);font-size:12px}
.ql-srch input{flex:1;background:none;border:none;outline:none;color:var(--text);font-size:13px;font-family:inherit}
.ql-srch input::placeholder{color:var(--dim)}
.q-list{flex:1;overflow-y:auto;padding:6px}
.q-list::-webkit-scrollbar{width:3px}
.q-list::-webkit-scrollbar-thumb{background:var(--s4)}
.qi{padding:10px 11px;border-radius:10px;cursor:pointer;border:1.5px solid transparent;margin-bottom:4px;transition:all .12s;position:relative}
.qi:hover{background:var(--s2)}
.qi.sel{background:rgba(0,201,138,.07);border-color:rgba(0,201,138,.2)}
.qi.live{background:rgba(0,201,138,.09);border-color:rgba(0,201,138,.3)}
.qi.queued{border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.04)}
.qi-br{display:flex;align-items:center;gap:5px;margin-bottom:4px;flex-wrap:wrap}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:20px;font-size:9px;font-weight:700;font-family:'Space Mono',monospace;flex-shrink:0}
.badge.on{background:rgba(0,201,138,.15);color:var(--accent)}
.badge.off{background:var(--s3);color:var(--dim)}
.badge.nxt{background:rgba(245,158,11,.15);color:var(--amber)}
.ldot{width:5px;height:5px;border-radius:50%;background:var(--accent);animation:lp 1.6s infinite}
@keyframes lp{0%,100%{box-shadow:0 0 0 0 rgba(0,201,138,.5)}60%{box-shadow:0 0 0 4px rgba(0,201,138,0)}}
.qi-txt{font-size:12.5px;font-weight:600;color:var(--text);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:4px}
.qi-meta{font-size:11px;color:var(--dim);font-family:'Space Mono',monospace;display:flex;gap:7px}
.qi-meta .gc{color:var(--accent)}
.qi-acts{position:absolute;top:8px;right:6px;display:flex;gap:3px;opacity:0;transition:opacity .12s}
.qi:hover .qi-acts,.qi.live .qi-acts{opacity:1}
.qi-act{width:24px;height:24px;border-radius:6px;background:none;border:1px solid var(--border);color:var(--dim);cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;transition:all .12s}
.qi-act:hover{color:var(--text);border-color:var(--border2)}
.qi-act.stop{color:var(--danger);border-color:rgba(255,71,87,.3)}
.qi-act.go{color:var(--accent);border-color:rgba(0,201,138,.3)}
.qi-act.nxt{color:var(--amber);border-color:rgba(245,158,11,.3)}

/* ── MAIN CENTER ── */
.main{flex:1;display:flex;flex-direction:column;min-width:0;overflow:hidden}

/* Topbar */
.tbar{height:52px;flex-shrink:0;display:flex;align-items:center;padding:0 14px;gap:9px;background:var(--s1);border-bottom:1px solid var(--border)}
.tbar-left{display:flex;align-items:center;gap:8px;flex:1;min-width:0}
.tbar-title{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;letter-spacing:.05em;white-space:nowrap}
.live-pill{display:flex;align-items:center;gap:6px;padding:4px 10px;border-radius:20px;border:1px solid rgba(0,201,138,.3);background:rgba(0,201,138,.07);font-size:10px;font-weight:700;color:var(--accent);font-family:'Space Mono',monospace;white-space:nowrap}
.standby-pill{display:flex;align-items:center;gap:6px;padding:4px 10px;border-radius:20px;border:1px solid var(--border);background:var(--s3);font-size:10px;font-weight:700;color:var(--dim);font-family:'Space Mono',monospace;white-space:nowrap}
.students-pill{display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;border:1px solid rgba(59,130,246,.25);background:rgba(59,130,246,.07);font-size:10px;font-weight:700;color:var(--blue);font-family:'Space Mono',monospace;white-space:nowrap}
.tbar-right{display:flex;gap:5px;flex-shrink:0}
.ticon{width:30px;height:30px;border-radius:8px;background:var(--s3);border:1px solid var(--border);color:var(--sub);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;text-decoration:none;transition:all .15s}
.ticon:hover{color:var(--text);border-color:var(--border2)}

/* Hero */
.hero{flex-shrink:0;padding:12px 14px;background:var(--s2);border-bottom:1px solid var(--border)}
.hero-inner{display:flex;align-items:flex-start;gap:12px;margin-bottom:10px}
.h-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.h-icon.on{background:rgba(0,201,138,.12);box-shadow:0 0 0 2px rgba(0,201,138,.22)}
.h-icon.off{background:var(--s3)}
.h-body{flex:1;min-width:0}
.h-label{font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);margin-bottom:3px;font-family:'Space Mono',monospace}
.h-q{font-size:14px;font-weight:700;color:var(--text);line-height:1.55;margin-bottom:7px}
.opts{display:flex;gap:5px;flex-wrap:wrap}
.opt{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:7px;background:var(--s3);border:1px solid var(--border);font-size:11px;font-weight:600;color:var(--sub);font-family:'Space Mono',monospace}
.opt.c{background:rgba(0,201,138,.1);border-color:rgba(0,201,138,.3);color:var(--accent)}
.h-acts-row{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.hbtn{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:8px;border:1px solid var(--border);background:var(--s3);color:var(--sub);font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap}
.hbtn:hover{color:var(--text);border-color:var(--border2)}
.hbtn.go{background:linear-gradient(135deg,var(--accent),var(--blue));color:#000;border-color:transparent;box-shadow:0 4px 14px rgba(0,201,138,.3)}
.hbtn.go:hover{filter:brightness(1.08)}
.hbtn.stop{border-color:rgba(255,71,87,.3);color:var(--danger)}
.hbtn.stop:hover{background:rgba(255,71,87,.08)}
.hbtn.clr{border-color:rgba(245,158,11,.25);color:var(--amber)}
.hbtn.clr:hover{background:rgba(245,158,11,.06)}
.hbtn.adv{border-color:rgba(59,130,246,.35);color:var(--blue)}
.hbtn.adv:hover{background:rgba(59,130,246,.08)}
.hbtn.nxtq{border-color:rgba(167,139,250,.35);color:var(--purple)}
.hbtn.nxtq:hover{background:rgba(167,139,250,.08)}

/* Timer strip */
.timer-strip{display:flex;align-items:center;gap:6px;padding:8px 14px;background:var(--s1);border-bottom:1px solid var(--border);flex-shrink:0;flex-wrap:wrap}
.ts-label{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--dim);flex-shrink:0}
.ts-presets{display:flex;gap:5px;flex-wrap:wrap}
.ts-btn{padding:5px 11px;border-radius:7px;border:1px solid var(--border);background:var(--s3);color:var(--sub);font-family:'Space Mono',monospace;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.ts-btn:hover{border-color:var(--blue);color:var(--blue)}
.ts-btn.active{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.4);color:var(--amber)}
.ts-custom{display:flex;align-items:center;gap:5px}
.ts-custom input{width:54px;padding:5px 8px;background:var(--s3);border:1px solid var(--border);border-radius:7px;color:var(--text);font-family:'Space Mono',monospace;font-size:11px;outline:none;text-align:center}
.ts-custom input:focus{border-color:rgba(59,130,246,.4)}
.ts-go{padding:5px 11px;border-radius:7px;border:none;background:var(--blue);color:#fff;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.ts-go:hover{filter:brightness(1.1)}
.ts-clr{padding:5px 9px;border-radius:7px;border:1px solid var(--border);background:var(--s3);color:var(--dim);font-size:11px;cursor:pointer;transition:all .15s}
.ts-clr:hover{color:var(--danger);border-color:rgba(255,71,87,.3)}
/* Countdown ring */
.ts-countdown{display:flex;align-items:center;gap:8px;margin-left:auto;flex-shrink:0}
.cdring{position:relative;width:38px;height:38px}
.cdring svg{position:absolute;inset:0;width:100%;height:100%;transform:rotate(-90deg)}
.cdring circle{fill:none;stroke-width:3;stroke-linecap:round}
.cdr-bg{stroke:var(--s3)}
.cdr-fill{stroke:var(--amber);transition:stroke-dashoffset .9s linear}
.cdr-num{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:var(--amber)}

/* Up-next strip */
.upnext-strip{display:none;flex-shrink:0;padding:7px 14px;background:rgba(245,158,11,.06);border-bottom:1px solid rgba(245,158,11,.2);align-items:center;gap:9px;flex-wrap:wrap}
.upnext-strip.show{display:flex}
.un-label{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--amber)}
.un-q{font-size:13px;font-weight:600;color:var(--text);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.un-adv{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:7px;border:none;background:var(--amber);color:#000;font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s;white-space:nowrap}
.un-adv:hover{filter:brightness(1.08)}
.un-rm{padding:5px 9px;border-radius:7px;border:1px solid rgba(245,158,11,.3);background:none;color:var(--amber);font-size:11px;cursor:pointer;transition:all .15s}
.un-rm:hover{background:rgba(245,158,11,.1)}

/* Stats */
.stats{display:flex;flex-shrink:0;background:var(--s1);border-bottom:1px solid var(--border)}
.stat{flex:1;padding:8px 10px;text-align:center;border-right:1px solid var(--border)}
.stat:last-child{border-right:none}
.stat-n{font-family:'Space Mono',monospace;font-size:18px;font-weight:700;line-height:1}
.stat-l{font-size:9px;color:var(--dim);margin-top:3px;text-transform:uppercase;letter-spacing:.06em}
.stat.st .stat-n{color:var(--blue)}
.stat.sc .stat-n{color:var(--accent)}
.stat.sw .stat-n{color:var(--danger)}
.stat.sf .stat-n{font-size:11px;color:var(--amber);line-height:1.6}

/* Option distribution bar */
.opt-dist{flex-shrink:0;padding:8px 14px;background:var(--s1);border-bottom:1px solid var(--border);display:none}
.opt-dist.show{display:block}
.od-title{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--dim);margin-bottom:6px}
.od-bars{display:flex;gap:6px}
.od-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px}
.od-track{width:100%;height:40px;background:var(--s3);border-radius:4px;overflow:hidden;position:relative;display:flex;align-items:flex-end}
.od-fill{width:100%;border-radius:4px;transition:height .4s ease;min-height:2px}
.od-col-a .od-fill{background:rgba(59,130,246,.7)}
.od-col-b .od-fill{background:rgba(167,139,250,.7)}
.od-col-c .od-fill{background:rgba(0,201,138,.7)}
.od-col-d .od-fill{background:rgba(245,158,11,.7)}
.od-correct .od-fill{background:rgba(0,201,138,.9)!important}
.od-ltr{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:var(--sub)}
.od-cnt{font-family:'Space Mono',monospace;font-size:10px;color:var(--text)}

/* Feed */
.feed{flex:1;overflow-y:auto;padding:8px 10px}
.feed::-webkit-scrollbar{width:4px}
.feed::-webkit-scrollbar-thumb{background:var(--s3);border-radius:2px}
.empty{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:8px;color:var(--sub)}
.empty .ei{font-size:38px;opacity:.3}
.empty .et{font-size:14px;font-weight:700;color:var(--text)}
.empty .es{font-size:13px}

/* First Responder Hero */
.first-hero{display:none;margin:8px 10px 0;padding:13px;background:linear-gradient(135deg,rgba(245,158,11,.12),rgba(245,158,11,.05));border:1.5px solid rgba(245,158,11,.4);border-radius:14px;animation:heroIn .4s cubic-bezier(.22,1,.36,1) both}
@keyframes heroIn{from{opacity:0;transform:scale(.97)}to{opacity:1;transform:none}}
.fh-label{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--amber);margin-bottom:8px}
.fh-row{display:flex;align-items:center;gap:11px}
.fh-av{width:44px;height:44px;border-radius:11px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:800;color:#fff;overflow:hidden;border:2px solid rgba(245,158,11,.5)}
.fh-av img{width:100%;height:100%;object-fit:cover}
.fh-info{flex:1;min-width:0}
.fh-name{font-size:15px;font-weight:800;color:var(--text);margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fh-chips{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
.fh-chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;font-family:'Space Mono',monospace}
.fh-chip.spd{background:rgba(0,201,138,.15);color:var(--accent)}
.fh-chip.cor{background:rgba(0,201,138,.15);color:var(--accent)}
.fh-chip.wrg{background:rgba(255,71,87,.1);color:var(--danger)}
.fh-chip.opt{background:var(--s3);color:var(--sub)}
.fh-chip.time{background:rgba(245,158,11,.1);color:var(--amber)}

/* Feed section label */
.feed-section{display:flex;align-items:center;gap:8px;padding:10px 12px 4px;font-family:'Space Mono',monospace;font-size:9px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--dim)}
.feed-section::after{content:'';flex:1;height:1px;background:var(--border)}

/* Answer card */
.ac{display:flex;align-items:flex-start;gap:9px;padding:10px 11px;border-radius:11px;background:var(--s2);border:1px solid var(--border);margin-bottom:6px;transition:border-color .2s,background .2s;position:relative}
.ac.ac-new{animation:acIn .3s cubic-bezier(.22,1,.36,1) both}
@keyframes acIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.ac.ac-c{background:rgba(0,201,138,.04);border-color:rgba(0,201,138,.2)}
.ac.ac-l{border-color:rgba(245,158,11,.32);background:rgba(245,158,11,.03)}
.ac.ac-fc{border-color:rgba(0,201,138,.45);background:rgba(0,201,138,.07)}
.ac.ac-fa{border-left:3px solid var(--amber)}
.rnk{width:32px;height:32px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700}
.rnk.r1{background:linear-gradient(135deg,#f59e0b,#d97706);color:#000;box-shadow:0 3px 10px rgba(245,158,11,.35)}
.rnk.r2{background:linear-gradient(135deg,#94a3b8,#64748b);color:#fff}
.rnk.r3{background:linear-gradient(135deg,#cd7c2e,#a05c20);color:#fff}
.rnk.rn{background:var(--s3);color:var(--dim)}
.ab{flex:1;min-width:0}
.ah{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:5px}
.aav{width:26px;height:26px;border-radius:7px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;overflow:hidden}
.aav img{width:100%;height:100%;object-fit:cover}
.aname{font-size:13px;font-weight:700;color:var(--text)}
.atime{font-size:10px;color:var(--dim);font-family:'Space Mono',monospace;margin-left:auto;white-space:nowrap}
.spd{font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;font-family:'Space Mono',monospace}
.spd.fast{background:rgba(0,201,138,.15);color:var(--accent)}
.spd.mid{background:rgba(245,158,11,.12);color:var(--amber)}
.spd.slow{background:var(--s3);color:var(--dim)}
.ao-row{display:flex;align-items:center;gap:6px;margin-bottom:4px;flex-wrap:wrap}
.ao{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-family:'Space Mono',monospace;font-size:11px;font-weight:700}
.ao.c{background:rgba(0,201,138,.12);color:var(--accent)}
.ao.w{background:rgba(255,71,87,.1);color:var(--danger)}
.ao.n{background:var(--s3);color:var(--sub)}
.verd{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;font-family:'Space Mono',monospace}
.verd.c{background:rgba(0,201,138,.12);color:var(--accent)}
.verd.w{background:rgba(255,71,87,.1);color:var(--danger)}
.atxt{font-size:13px;color:var(--sub);background:var(--s3);border-radius:7px;padding:6px 9px;margin-bottom:4px;line-height:1.5}
.aimg{max-height:70px;border-radius:7px;border:1px solid var(--border);margin-top:4px}
.aaud{width:100%;margin-top:5px}
.reacts{display:flex;gap:5px;flex-wrap:wrap;margin-top:4px}
.rpill{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:20px;background:var(--s3);border:1px solid var(--border);font-size:12px;color:var(--sub)}
.rpill span{font-size:10.5px;font-family:'Space Mono',monospace}
.anote{font-size:12px;color:var(--amber);background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.2);border-radius:6px;padding:4px 9px;margin-top:4px}
.aacts{display:flex;flex-direction:column;gap:4px;flex-shrink:0}
.aa{width:28px;height:28px;border-radius:7px;border:1px solid var(--border);background:var(--s3);color:var(--dim);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;transition:all .15s}
.aa:hover{border-color:var(--border2);color:var(--text)}
.aa.al{background:rgba(245,158,11,.15);border-color:rgba(245,158,11,.3);color:var(--amber)}
.aa.ac2{background:rgba(0,201,138,.12);border-color:rgba(0,201,138,.3);color:var(--accent)}
.aa.aw{background:rgba(255,71,87,.08);border-color:rgba(255,71,87,.25);color:var(--danger)}

/* ── RIGHT: Create form ── */
.cr{width:262px;flex-shrink:0;display:flex;flex-direction:column;background:var(--s1);border-left:1px solid var(--border);overflow:hidden}
.cr-head{padding:12px 12px 9px;flex-shrink:0;border-bottom:1px solid var(--border);font-family:'Space Mono',monospace;font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--dim)}
.cr-body{flex:1;overflow-y:auto;padding:11px}
.cr-body::-webkit-scrollbar{width:3px}
.cr-body::-webkit-scrollbar-thumb{background:var(--s3)}
.fg{margin-bottom:10px}
.fl{display:block;font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--dim);margin-bottom:4px;font-family:'Space Mono',monospace}
.fi{width:100%;padding:8px 11px;background:var(--s3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;outline:none;resize:vertical;transition:border-color .15s}
.fi:focus{border-color:rgba(59,130,246,.45);box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.fi::placeholder{color:var(--dim)}
.cg{display:grid;grid-template-columns:1fr 1fr;gap:5px}
.co{display:flex;align-items:center;justify-content:center;height:34px;border-radius:8px;border:1.5px solid var(--border);background:var(--s3);cursor:pointer;font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:var(--dim);transition:all .15s}
.co:hover{border-color:var(--blue);color:var(--blue)}
.co.on{background:rgba(0,201,138,.1);border-color:rgba(0,201,138,.4);color:var(--accent)}
.tog-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.tog-lbl{font-size:13px;font-weight:500}
.track{width:38px;height:20px;border-radius:20px;background:var(--s3);border:1px solid var(--border);position:relative;cursor:pointer;transition:background .2s;flex-shrink:0}
.track.on{background:var(--accent)}
.thumb{position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.4)}
.track.on .thumb{transform:translateX(18px)}
.sub-btn{width:100%;padding:11px;border-radius:10px;border:none;background:linear-gradient(135deg,var(--accent),var(--blue));color:#000;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 14px rgba(0,201,138,.25)}
.sub-btn:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(0,201,138,.38)}
.fmsg{margin-top:8px;padding:7px 10px;border-radius:8px;font-size:12px;display:none}
.fmsg.ok{background:rgba(0,201,138,.1);border:1px solid rgba(0,201,138,.2);color:var(--accent)}
.fmsg.err{background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.2);color:var(--danger)}

/* Note popup */
.npop{position:fixed;inset:0;z-index:90;background:rgba(0,0,0,.72);display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.npop.show{display:flex}
.nbox{width:100%;max-width:350px;background:var(--s2);border:1px solid var(--border2);border-radius:13px;overflow:hidden}
.nhead{padding:13px 15px;border-bottom:1px solid var(--border);font-size:14px;font-weight:700}
.nbody{padding:13px}
.nbody textarea{width:100%;background:var(--s3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:inherit;font-size:13px;padding:8px 10px;outline:none;resize:vertical;min-height:75px}
.nbody textarea:focus{border-color:rgba(245,158,11,.4)}
.nfoot{display:flex;gap:7px;justify-content:flex-end;padding:9px 13px;border-top:1px solid var(--border)}
.nb{padding:7px 16px;border-radius:8px;border:none;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer}
.nb.nc{background:var(--s3);color:var(--sub)}
.nb.ns{background:var(--amber);color:#000}

/* Picker modal */
.picker-ov{position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.8);display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(6px)}
.picker-ov.show{display:flex}
.picker-box{width:100%;max-width:640px;max-height:90vh;background:var(--s1);border:1px solid var(--border2);border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,.65);animation:popIn .22s cubic-bezier(.22,1,.36,1) both}
@keyframes popIn{from{opacity:0;transform:scale(.94) translateY(8px)}to{opacity:1;transform:none}}
.picker-head{padding:15px 16px 11px;flex-shrink:0;border-bottom:1px solid var(--border)}
.picker-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.picker-title{font-size:15px;font-weight:800;color:var(--text)}
.picker-close{width:28px;height:28px;border-radius:7px;background:var(--s3);border:1px solid var(--border);color:var(--sub);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s}
.picker-close:hover{color:var(--text)}
.picker-srch{display:flex;align-items:center;gap:8px;background:var(--s3);border:1px solid var(--border);border-radius:9px;padding:8px 11px}
.picker-srch i{color:var(--dim);font-size:13px}
.picker-srch input{flex:1;background:none;border:none;outline:none;color:var(--text);font-size:14px;font-family:inherit}
.picker-srch input::placeholder{color:var(--dim)}
.picker-body{flex:1;overflow-y:auto;padding:10px 12px 14px}
.picker-body::-webkit-scrollbar{width:4px}
.picker-body::-webkit-scrollbar-thumb{background:var(--s3)}
.pgrp-label{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);padding:10px 4px 5px;border-bottom:1px solid var(--border);margin-bottom:5px}
.pq-row{display:flex;align-items:center;gap:10px;padding:10px 11px;border-radius:10px;border:1px solid transparent;cursor:pointer;transition:all .13s;margin-bottom:4px}
.pq-row:hover{background:var(--s2);border-color:var(--border)}
.pq-row.is-live{background:rgba(0,201,138,.07);border-color:rgba(0,201,138,.25)}
.pq-row.is-next{background:rgba(245,158,11,.06);border-color:rgba(245,158,11,.3)}
.pq-num{width:30px;height:30px;border-radius:8px;background:var(--s3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:var(--sub);flex-shrink:0}
.pq-num.live{background:rgba(0,201,138,.15);border-color:rgba(0,201,138,.35);color:var(--accent)}
.pq-num.next{background:rgba(245,158,11,.15);border-color:rgba(245,158,11,.35);color:var(--amber)}
.pq-info{flex:1;min-width:0}
.pq-text{font-size:13px;font-weight:600;color:var(--text);line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px}
.pq-meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.pq-badge{display:inline-flex;padding:1px 7px;border-radius:20px;font-size:9px;font-weight:700;font-family:'Space Mono',monospace}
.pq-badge.live{background:rgba(0,201,138,.15);color:var(--accent)}
.pq-badge.nxt{background:rgba(245,158,11,.15);color:var(--amber)}
.pq-badge.off{background:var(--s3);color:var(--dim)}
.pq-ans{font-size:11px;color:var(--dim);font-family:'Space Mono',monospace}
.pq-cor{font-size:11px;color:var(--accent);font-family:'Space Mono',monospace}
.pq-acts{display:flex;gap:5px;flex-shrink:0;align-items:center}
.pq-act{display:flex;align-items:center;gap:5px;padding:6px 12px;border-radius:7px;border:none;font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s;white-space:nowrap}
.pq-go{background:linear-gradient(135deg,var(--accent),var(--blue));color:#000}
.pq-go:hover{filter:brightness(1.1)}
.pq-stop{background:rgba(255,71,87,.1);border:1px solid rgba(255,71,87,.3)!important;color:var(--danger)}
.pq-stop:hover{background:rgba(255,71,87,.18)}
.pq-setnxt{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3)!important;color:var(--amber)}
.pq-setnxt:hover{background:rgba(245,158,11,.2)}
.pq-unsetnxt{background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.4)!important;color:var(--amber)}
.pq-eye{width:28px;height:28px;border-radius:7px;background:var(--s3);border:1px solid var(--border)!important;color:var(--sub);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;transition:all .15s}
.pq-eye:hover{color:var(--text)}
.pq-expand{display:none;padding:8px 12px 10px;margin:-4px 0 4px;background:var(--s3);border-radius:0 0 10px 10px;border:1px solid var(--border);border-top:none;font-size:12.5px;color:var(--sub);line-height:1.6}
.pq-expand.show{display:block}
.pq-opts{display:flex;gap:5px;flex-wrap:wrap;margin-top:5px}
.pq-opt{display:inline-flex;padding:3px 9px;border-radius:6px;background:var(--s4);font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--sub)}
.pq-opt.cor{background:rgba(0,201,138,.12);color:var(--accent)}
.picker-empty{text-align:center;padding:40px 20px;color:var(--dim)}
.picker-empty .pe-i{font-size:34px;opacity:.3;margin-bottom:8px}

@media(max-width:1000px){.cr{display:none}}
@media(max-width:660px){.ql{display:none}}
@media(max-width:520px){.stat.sf{display:none}}
</style>
</head>
<body>

<!-- Note popup -->
<div class="npop" id="npop">
  <div class="nbox">
    <div class="nhead">📝 Admin Note</div>
    <div class="nbody"><textarea id="ntxt" placeholder="Write a note on this answer…"></textarea></div>
    <div class="nfoot">
      <button class="nb nc" onclick="closeNote()">Cancel</button>
      <button class="nb ns" onclick="saveNote()">Save</button>
    </div>
  </div>
</div>

<!-- Picker Modal -->
<div class="picker-ov" id="pickerOv">
  <div class="picker-box">
    <div class="picker-head">
      <div class="picker-top">
        <div class="picker-title">🎯 Choose Question to Go Live</div>
        <button class="picker-close" onclick="closePicker()"><i class="fa fa-xmark"></i></button>
      </div>
      <div class="picker-srch">
        <i class="fa fa-magnifying-glass"></i>
        <input type="text" id="pickerSrch" placeholder="Search questions or subjects…" oninput="renderPicker()">
      </div>
    </div>
    <div class="picker-body" id="pickerBody"></div>
  </div>
</div>

<div class="app">

<!-- ══ LEFT: Question roster ══ -->
<aside class="ql">
  <div class="ql-top">
    <div class="brand-row">
      <div class="brand-pill">
        <div class="brand-dot">ES</div>
        <span class="brand-name">Control Room</span>
      </div>
    </div>
    <div class="ql-hdr">
      <span class="sec-lbl">Questions</span>
      <button class="new-btn" onclick="scrollCreate()"><i class="fa fa-plus" style="font-size:10px"></i> New</button>
    </div>
    <div class="ql-srch">
      <i class="fa fa-magnifying-glass"></i>
      <input type="text" id="qSrch" placeholder="Search questions…">
    </div>
  </div>
  <div class="q-list" id="qList"></div>
</aside>

<!-- ══ MAIN CENTER ══ -->
<div class="main">

  <!-- Topbar -->
  <div class="tbar">
    <div class="tbar-left">
      <span class="tbar-title">BRAINSTORM CONTROL</span>
      <div class="live-pill" id="livePill" style="display:none"><span class="ldot"></span> LIVE</div>
      <div class="standby-pill" id="standbyPill">● STANDBY</div>
      <div class="students-pill" id="studentsPill" style="display:none">
        <i class="fa fa-users" style="font-size:9px"></i>
        <span id="studentCount">0</span> students
      </div>
    </div>
    <div class="tbar-right">
      <button class="ticon" onclick="openPicker()" title="Choose question to go live"><i class="fa fa-list-check"></i></button>
      <a href="live_brainstorm.php" class="ticon" title="Open student view" target="_blank"><i class="fa fa-users"></i></a>
      <a href="../admin/dashboard.php" class="ticon" title="Dashboard"><i class="fa fa-house"></i></a>
      <a href="../auth/logout.php" class="ticon"><i class="fa fa-right-from-bracket"></i></a>
    </div>
  </div>

  <!-- Hero: current active question -->
  <div class="hero" id="hero">
    <div class="hero-inner">
      <div class="h-icon off" id="hIcon">❓</div>
      <div class="h-body">
        <div class="h-label" id="hLabel">No question active</div>
        <div class="h-q" id="hQ">Select a question from the left panel or use the picker to go live.</div>
        <div class="opts" id="hOpts"></div>
      </div>
    </div>
    <div class="h-acts-row" id="hActs"></div>
  </div>

  <!-- Timer strip -->
  <div class="timer-strip" id="timerStrip">
    <span class="ts-label">⏱ Timer</span>
    <div class="ts-presets">
      <button class="ts-btn" data-s="30"  onclick="setTimer(30)">30s</button>
      <button class="ts-btn" data-s="60"  onclick="setTimer(60)">1m</button>
      <button class="ts-btn" data-s="90"  onclick="setTimer(90)">1m30</button>
      <button class="ts-btn" data-s="120" onclick="setTimer(120)">2m</button>
      <button class="ts-btn" data-s="180" onclick="setTimer(180)">3m</button>
    </div>
    <div class="ts-custom">
      <input type="number" id="customSecs" placeholder="sec" min="5" max="600">
      <button class="ts-go" onclick="setCustomTimer()">Set</button>
    </div>
    <button class="ts-clr" onclick="clearTimer()" title="Clear timer"><i class="fa fa-xmark"></i></button>
    <!-- Countdown ring -->
    <div class="ts-countdown" id="tsCountdown" style="display:none">
      <div class="cdring">
        <svg viewBox="0 0 38 38">
          <circle class="cdr-bg" cx="19" cy="19" r="16"/>
          <circle class="cdr-fill" id="cdrFill" cx="19" cy="19" r="16" stroke-dasharray="100.5" stroke-dashoffset="0"/>
        </svg>
        <div class="cdr-num" id="cdrNum">--</div>
      </div>
      <div style="font-family:'Space Mono',monospace;font-size:11px;color:var(--amber)" id="cdrLabel">seconds left</div>
    </div>
  </div>

  <!-- Up-next strip -->
  <div class="upnext-strip" id="upNextStrip">
    <span class="un-label">⏭ Up Next</span>
    <span class="un-q" id="unQ">—</span>
    <button class="un-adv" onclick="advanceNow()"><i class="fa fa-forward" style="font-size:11px"></i> Go Live Now</button>
    <button class="un-rm" onclick="clearQueue()">✕ Remove</button>
  </div>

  <!-- Stats bar -->
  <div class="stats">
    <div class="stat st"><div class="stat-n" id="sT">0</div><div class="stat-l">Answered</div></div>
    <div class="stat sc"><div class="stat-n" id="sC">0</div><div class="stat-l">Correct ✓</div></div>
    <div class="stat sw"><div class="stat-n" id="sW">0</div><div class="stat-l">Wrong ✗</div></div>
    <div class="stat sf" id="sFirst">
      <div class="stat-n" id="sF" style="font-size:11px;line-height:1.5">—</div>
      <div class="stat-l">1st Answer ⚡</div>
    </div>
  </div>

  <!-- Option distribution bars -->
  <div class="opt-dist" id="optDist">
    <div class="od-title">Answer Distribution</div>
    <div class="od-bars">
      <div class="od-col od-col-a" id="odA">
        <div class="od-track"><div class="od-fill" id="odFillA" style="height:0%"></div></div>
        <div class="od-ltr">A</div>
        <div class="od-cnt" id="odCntA">0</div>
      </div>
      <div class="od-col od-col-b" id="odB">
        <div class="od-track"><div class="od-fill" id="odFillB" style="height:0%"></div></div>
        <div class="od-ltr">B</div>
        <div class="od-cnt" id="odCntB">0</div>
      </div>
      <div class="od-col od-col-c" id="odC">
        <div class="od-track"><div class="od-fill" id="odFillC" style="height:0%"></div></div>
        <div class="od-ltr">C</div>
        <div class="od-cnt" id="odCntC">0</div>
      </div>
      <div class="od-col od-col-d" id="odD">
        <div class="od-track"><div class="od-fill" id="odFillD" style="height:0%"></div></div>
        <div class="od-ltr">D</div>
        <div class="od-cnt" id="odCntD">0</div>
      </div>
    </div>
  </div>

  <!-- First Responder Hero -->
  <div class="first-hero" id="firstHero">
    <div class="fh-label">🥇 First Responder</div>
    <div class="fh-row">
      <div class="fh-av" id="fhAv"></div>
      <div class="fh-info">
        <div class="fh-name" id="fhName"></div>
        <div class="fh-chips" id="fhChips"></div>
      </div>
    </div>
  </div>

  <!-- Live feed -->
  <div class="feed" id="feed">
    <div class="empty"><div class="ei">📡</div><div class="et">Waiting for answers</div><div class="es">Activate a question — student answers appear here live</div></div>
  </div>
</div>

<!-- ══ RIGHT: Create question ══ -->
<aside class="cr">
  <div class="cr-head">Create Question</div>
  <div class="cr-body" id="crBody">
    <div class="fg"><label class="fl">Question</label><textarea class="fi" id="fQ" rows="3" placeholder="Type your question…"></textarea></div>
    <div class="fg"><label class="fl">Option A</label><input class="fi" id="fA" placeholder="Option A"></div>
    <div class="fg"><label class="fl">Option B</label><input class="fi" id="fB" placeholder="Option B"></div>
    <div class="fg"><label class="fl">Option C</label><input class="fi" id="fC" placeholder="Option C (optional)"></div>
    <div class="fg"><label class="fl">Option D</label><input class="fi" id="fD" placeholder="Option D (optional)"></div>
    <div class="fg">
      <label class="fl">Correct Answer</label>
      <div class="cg" id="cgrid">
        <div class="co on" data-v="A" onclick="pickC('A')">A</div>
        <div class="co" data-v="B" onclick="pickC('B')">B</div>
        <div class="co" data-v="C" onclick="pickC('C')">C</div>
        <div class="co" data-v="D" onclick="pickC('D')">D</div>
      </div>
    </div>
    <div class="fg"><label class="fl">Subject</label>
      <select class="fi" id="fSubj">
        <option value="">— None —</option>
        <?php foreach($subjects as $s): ?><option value="<?=xss((string)$s['id'])?>"><?=xss($s['name'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="tog-row">
      <span class="tog-lbl">Go live immediately</span>
      <div class="track" id="aTrack" onclick="togA()"><div class="thumb"></div></div>
    </div>
    <button class="sub-btn" onclick="createQ()"><i class="fa fa-bolt" style="font-size:11px"></i> Create &amp; Post</button>
    <div class="fmsg" id="fmsg"></div>
  </div>
</aside>
</div>

<script>
const API = 'get_questions.php';
let allQ         = <?=json_encode(array_values($questions_init), JSON_UNESCAPED_UNICODE)?>;
let selId        = <?=$active_q ? (int)$active_q['id'] : 0?>;
let nextQId      = <?=$next_q  ? (int)$next_q['id']   : 0?>;
let lastAnswerIds= new Set();
let correctA     = 'A';
let activateNow  = false;
let noteAid      = null;
let timerTotal   = 0;    // seconds the timer was originally set to
let timerEndsAt  = null; // Date object
let timerRaf     = null;
let expandedPqId = null;

/* ── UTILS ── */
function esc(s){return s?String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[c])):''}
function ts(d){try{return new Date(d).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit',second:'2-digit'})}catch{return d||''}}
const AVC=['#0095f6','#e6683c','#dc2743','#cc2366','#8a3ab9','#0cba78','#f58529','#1877f2','#7209b7','#f72585'];
function avBg(n){let h=0;for(const c of(n||''))h=(h*31+c.charCodeAt(0))&0xffffff;return AVC[Math.abs(h)%AVC.length]}
function avI(s){return(s||'?').trim().charAt(0).toUpperCase()}
async function post(action, data={}) {
  const fd = new FormData();
  Object.entries(data).forEach(([k,v])=>fd.append(k,v));
  return fetch(`${API}?action=${action}`,{method:'POST',body:fd}).then(r=>r.json());
}

/* ── QUESTION LIST ── */
function buildList(){
  const el = document.getElementById('qList');
  el.innerHTML = '';
  const q = (document.getElementById('qSrch').value||'').toLowerCase();
  allQ.filter(x=>!q||x.question.toLowerCase().includes(q)).forEach(x=>{
    const live = x.status==='active';
    const nxt  = parseInt(x.next_in_queue||0)===1 && !live;
    const sel  = x.id==selId;
    const tot  = parseInt(x.total_answers||0);
    const cor  = parseInt(x.correct_count||0);
    const d = document.createElement('div');
    d.className = `qi${live?' live':''}${nxt?' queued':''}${sel?' sel':''}`;
    d.id='qi-'+x.id;
    d.innerHTML = `
      <div class="qi-br">
        <span class="badge ${live?'on':nxt?'nxt':'off'}">${live?'<span class="ldot"></span> LIVE':nxt?'⏭ NEXT':'● IDLE'}</span>
        ${x.subject_name?`<span style="font-size:10px;color:var(--dim)">${esc(x.subject_name)}</span>`:''}
      </div>
      <div class="qi-txt">${esc(x.question)}</div>
      <div class="qi-meta"><span>${tot} ans</span>${tot?`<span class="gc">${cor} ✓</span>`:''}</div>
      <div class="qi-acts">
        ${live
          ? `<button class="qi-act stop" title="Stop" onclick="setStatus(${x.id},'inactive',event)"><i class="fa fa-stop" style="font-size:8px"></i></button>`
          : `<button class="qi-act go" title="Go Live" onclick="setStatus(${x.id},'active',event)"><i class="fa fa-play" style="font-size:8px"></i></button>`
        }
        ${!live && !nxt
          ? `<button class="qi-act nxt" title="Set as Next" onclick="setNextQueue(${x.id},1,event)"><i class="fa fa-forward" style="font-size:8px"></i></button>`
          : nxt
          ? `<button class="qi-act nxt" title="Remove from queue" onclick="setNextQueue(0,0,event)"><i class="fa fa-xmark" style="font-size:8px"></i></button>`
          : ''
        }
      </div>`;
    d.addEventListener('click',()=>selQ(x.id));
    el.appendChild(d);
  });
}
document.getElementById('qSrch').addEventListener('input',buildList);

/* ── SELECT QUESTION ── */
function selQ(id){
  selId=id; buildList();
  const q=allQ.find(x=>x.id==id);
  if(q) renderHero(q);
  lastAnswerIds=new Set();
  document.getElementById('firstHero').style.display='none';
  document.getElementById('optDist').classList.remove('show');
  document.getElementById('feed').innerHTML=`<div class="empty"><div class="ei">⏳</div><div class="et">Loading…</div></div>`;
  loadAnswers(id);
}

/* ── HERO ── */
function renderHero(q){
  const live = q.status==='active';
  // Topbar pills
  document.getElementById('livePill').style.display    = live ? 'flex' : 'none';
  document.getElementById('standbyPill').style.display = live ? 'none' : 'flex';
  const hi = document.getElementById('hIcon');
  hi.className = 'h-icon '+(live?'on':'off');
  hi.textContent = live?'📡':'📋';
  document.getElementById('hLabel').textContent = [q.subject_name||'', `Q#${q.id}`, live?'· 🔴 LIVE':''].filter(Boolean).join(' ');
  document.getElementById('hQ').textContent = q.question;
  const ca = (q.correct_answer||'').toUpperCase();
  document.getElementById('hOpts').innerHTML = ['A','B','C','D'].map(l=>{
    const v = q['option_'+l.toLowerCase()];
    return v ? `<span class="opt${ca===l?' c':''}">${l}: ${esc(v)}</span>` : '';
  }).join('');
  document.getElementById('hActs').innerHTML = live
    ? `<button class="hbtn stop" onclick="setStatus(${q.id},'inactive',event)"><i class="fa fa-stop" style="font-size:9px"></i> Stop</button>
       <button class="hbtn clr" onclick="clrAnswers(${q.id})"><i class="fa fa-trash" style="font-size:9px"></i> Clear Answers</button>`
    : `<button class="hbtn go" onclick="setStatus(${q.id},'active',event)"><i class="fa fa-play" style="font-size:9px"></i> Go Live</button>
       <button class="hbtn clr" onclick="clrAnswers(${q.id})"><i class="fa fa-trash" style="font-size:9px"></i> Clear Answers</button>
       <button class="hbtn nxtq" onclick="openPicker()"><i class="fa fa-list-check" style="font-size:9px"></i> Pick Question</button>`;

  // Up-next strip
  syncUpNextStrip();
  // Timer: restore countdown if timer_ends_at is set and in the future
  if (live && q.timer_ends_at) {
    const endsMs = new Date(q.timer_ends_at.replace(' ','T')+'Z').getTime();
    const nowMs  = Date.now();
    if (endsMs > nowMs) {
      timerEndsAt = new Date(endsMs);
      // We don't know original total from PHP here, approximate
      timerTotal  = timerTotal || Math.ceil((endsMs - nowMs) / 1000);
      startCountdownDisplay();
    }
  }
}

/* ── STATUS ── */
async function setStatus(qid, status, e){
  if(e) e.stopPropagation();
  const j = await post('set_status',{qid,status});
  if(!j.success) return;
  allQ.forEach(q=>{
    if(q.id==qid) q.status=status;
    else if(status==='active') q.status='inactive';
  });
  if(status==='inactive') { timerEndsAt=null; stopCountdownDisplay(); }
  buildList();
  const q=allQ.find(x=>x.id==qid);
  if(q) renderHero(q);
  if(qid==selId) loadAnswers(qid);
}

/* ── TIMER ── */
async function setTimer(secs){
  if(!selId){ alert('Select a question first, then go live before setting a timer.'); return; }
  const q = allQ.find(x=>x.id==selId);
  if(!q || q.status!=='active'){ alert('The question must be live before you can start the timer.'); return; }
  document.querySelectorAll('.ts-btn').forEach(b=>b.classList.toggle('active', parseInt(b.dataset.s)===secs));
  timerTotal  = secs;
  timerEndsAt = new Date(Date.now() + secs*1000);
  startCountdownDisplay();
  await post('set_timer',{qid:selId, seconds:secs});
}
async function setCustomTimer(){
  const v = parseInt(document.getElementById('customSecs').value||'0');
  if(v>=5 && v<=600) await setTimer(v);
}
async function clearTimer(){
  timerEndsAt = null; timerTotal = 0;
  document.querySelectorAll('.ts-btn').forEach(b=>b.classList.remove('active'));
  stopCountdownDisplay();
  if(selId) await post('set_timer',{qid:selId, seconds:0});
}
function startCountdownDisplay(){
  stopCountdownDisplay();
  document.getElementById('tsCountdown').style.display='flex';
  renderCountdown();
  timerRaf = setInterval(()=>{
    renderCountdown();
    // Check if expired — auto-advance if queued next exists
    if(timerEndsAt && Date.now() >= timerEndsAt.getTime()){
      timerEndsAt=null; timerTotal=0; stopCountdownDisplay();
      if(nextQId) advanceNow();
    }
  }, 500);
}
function stopCountdownDisplay(){
  clearInterval(timerRaf); timerRaf=null;
  document.getElementById('tsCountdown').style.display='none';
}
function renderCountdown(){
  if(!timerEndsAt) return;
  const left = Math.max(0, Math.ceil((timerEndsAt.getTime()-Date.now())/1000));
  const pct  = timerTotal>0 ? (1-(left/timerTotal))*100 : 0;
  // ring: stroke-dasharray=100.5 (circumference for r=16 approx)
  const offset = 100.5 * (1 - left/Math.max(timerTotal,1));
  document.getElementById('cdrFill').style.strokeDashoffset = offset;
  const el = document.getElementById('cdrNum');
  if(left > 60)       el.textContent = Math.ceil(left/60)+'m';
  else                el.textContent = left+'s';
  el.style.color = left<=10 ? 'var(--danger)' : left<=30 ? 'var(--amber)' : 'var(--accent)';
  document.getElementById('cdrLabel').textContent = left<=0 ? 'Time up!' : `sec left`;
}

/* ── QUEUE NEXT ── */
async function setNextQueue(qid, val, e){
  if(e) e.stopPropagation();
  await post('set_next_queue',{qid:qid||0, val});
  nextQId = val ? qid : 0;
  allQ.forEach(q=>{ q.next_in_queue = (q.id==qid && val) ? 1 : 0; });
  buildList(); syncUpNextStrip();
  renderPicker && renderPicker();
}
function syncUpNextStrip(){
  const strip = document.getElementById('upNextStrip');
  const nq    = allQ.find(x=>parseInt(x.next_in_queue)===1 && x.status!=='active');
  if(nq){ strip.classList.add('show'); document.getElementById('unQ').textContent=nq.question; nextQId=nq.id; }
  else  { strip.classList.remove('show'); nextQId=0; }
}
async function advanceNow(){
  const j = await post('advance_to_next');
  if(!j.success){ alert('No question is queued as next. Set one using ⏭ on the left.'); return; }
  allQ.forEach(q=>{
    if(q.id==j.qid){ q.status='active'; q.next_in_queue=0; }
    else           { q.status='inactive'; }
  });
  nextQId=0; selId=j.qid;
  buildList();
  const q=allQ.find(x=>x.id==j.qid);
  if(q) renderHero(q);
  loadAnswers(j.qid);
  syncUpNextStrip();
  timerEndsAt=null; timerTotal=0; stopCountdownDisplay();
}
async function clearQueue(){
  await setNextQueue(0,0,null);
}

/* ── CLEAR ANSWERS ── */
async function clrAnswers(qid){
  if(!confirm('Clear ALL answers for this question?')) return;
  await post('clear_answers',{qid});
  lastAnswerIds=new Set();
  ['sT','sC','sW'].forEach(id=>document.getElementById(id).textContent='0');
  document.getElementById('sF').textContent='—';
  document.getElementById('firstHero').style.display='none';
  document.getElementById('optDist').classList.remove('show');
  document.getElementById('feed').innerHTML=`<div class="empty"><div class="ei">🗑</div><div class="et">Cleared</div><div class="es">Waiting for new answers…</div></div>`;
}

/* ── LOAD ANSWERS ── */
async function loadAnswers(qid){
  if(!qid) return;
  try{
    const j = await fetch(`${API}?action=answers&qid=${qid}`).then(r=>r.json());
    if(!j.success) return;
    const ans = j.answers||[];
    const oc  = j.opt_counts||{A:0,B:0,C:0,D:0};

    // Stats
    const tot=ans.length, cor=ans.filter(a=>a.is_correct).length;
    document.getElementById('sT').textContent=tot;
    document.getElementById('sC').textContent=cor;
    document.getElementById('sW').textContent=tot-cor;

    // Option distribution
    if(tot>0){
      const dist=document.getElementById('optDist');
      dist.classList.add('show');
      const maxC=Math.max(1,Math.max(oc.A,oc.B,oc.C,oc.D));
      const q=allQ.find(x=>x.id==qid);
      const ca=(q?.correct_answer||'').toUpperCase();
      ['A','B','C','D'].forEach(l=>{
        const c=oc[l]||0;
        const h=Math.round((c/maxC)*100);
        const fill=document.getElementById('odFill'+l);
        const cnt=document.getElementById('odCnt'+l);
        if(fill) fill.style.height=h+'%';
        if(cnt)  cnt.textContent=c;
        const col=document.getElementById('od'+l);
        if(col) col.classList.toggle('od-correct', ca===l);
      });
    } else {
      document.getElementById('optDist').classList.remove('show');
    }

    // First responder hero
    const first=ans[0];
    const fh=document.getElementById('firstHero');
    if(first){
      const nm=first.student_name||'Student', bg=avBg(nm);
      const isC=!!first.is_correct, secs=first.secs_taken, opt=(first.selected_option||'').toUpperCase();
      document.getElementById('fhAv').style.background=bg;
      document.getElementById('fhAv').innerHTML=first.student_pic
        ?`<img src="${esc(first.student_pic)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:9px">`
        :`<span>${esc(avI(nm))}</span>`;
      document.getElementById('fhName').textContent=nm;
      document.getElementById('fhChips').innerHTML=[
        `<span class="fh-chip time">⚡ First</span>`,
        secs!==null?`<span class="fh-chip spd">${secs}s`:``,
        opt?`<span class="fh-chip opt">Option ${esc(opt)}</span>`:'',
        `<span class="fh-chip ${isC?'cor':'wrg'}">${isC?'✓ Correct':'✗ Wrong'}</span>`,
      ].join('');
      document.getElementById('sF').textContent=nm.slice(0,12)+(secs!==null?` · ${secs}s`:'');
      fh.style.display='block';
    } else {
      fh.style.display='none';
      document.getElementById('sF').textContent='—';
    }

    // Feed
    const feed=document.getElementById('feed');
    if(!ans.length){
      feed.innerHTML=`<div class="empty"><div class="ei">📡</div><div class="et">No answers yet</div><div class="es">Students appear here as they answer</div></div>`;
      lastAnswerIds=new Set(); return;
    }
    const newIds=new Set(ans.map(a=>a.id).filter(id=>!lastAnswerIds.has(id)));
    lastAnswerIds=new Set(ans.map(a=>a.id));
    // Preserve admin DOM state
    const preserved={};
    ans.forEach(a=>{const card=document.getElementById('ac-'+a.id);if(card)preserved[a.id]={liked:card.classList.contains('ac-l'),note:card.querySelector('.anote')?.textContent?.replace('📝 ','')||a.admin_note||''};});
    feed.innerHTML='';
    // Section: first to answer
    if(ans[0]){
      const s=document.createElement('div');s.className='feed-section';s.textContent='⚡ First to Answer';feed.appendChild(s);
      const c=buildCard(ans[0],newIds.has(ans[0].id),preserved[ans[0].id]);c.classList.add('ac-fa');feed.appendChild(c);
    }
    // Section: first correct (if different)
    const fc=ans.find(a=>a.is_correct);
    if(fc&&fc.id!==ans[0]?.id){
      const s=document.createElement('div');s.className='feed-section';s.textContent='🏆 First Correct';feed.appendChild(s);
      const c=buildCard(fc,newIds.has(fc.id),preserved[fc.id]);c.classList.add('ac-fc');feed.appendChild(c);
    }
    // All responses
    if(ans.length>0){
      const s=document.createElement('div');s.className='feed-section';s.textContent=`All Responses · ${tot}`;feed.appendChild(s);
      ans.forEach((a,i)=>{
        const c=buildCard(a,newIds.has(a.id),preserved[a.id]);
        if(a.is_correct&&a.correct_rank===1)c.classList.add('ac-fc');
        if(i===0)c.classList.add('ac-fa');
        feed.appendChild(c);
      });
    }
  }catch(err){console.error(err);}
}

/* ── BUILD CARD ── */
function buildCard(a, isNew, dom){
  const isC=!!a.is_correct, isL=dom?.liked??!!a.admin_liked, isFC=isC&&a.correct_rank===1;
  const card=document.createElement('div');
  card.className=`ac${isC?' ac-c':''}${isL?' ac-l':''}${isFC?' ac-fc':''}${isNew?' ac-new':''}`;
  card.id='ac-'+a.id;
  const rn=a.rank, rCls=rn===1?'r1':rn===2?'r2':rn===3?'r3':'rn', rLbl=rn===1?'🥇':rn===2?'🥈':rn===3?'🥉':'#'+rn;
  const nm=a.student_name||'Student', bg=avBg(nm);
  const avH=a.student_pic?`<img src="${esc(a.student_pic)}" alt="">`:esc(avI(nm));
  const opt=(a.selected_option||'').toUpperCase(), oCls=opt?(isC?'c':'w'):'n';
  const secs=a.secs_taken??null, sCls=secs===null?'':secs<=10?'fast':secs<=30?'mid':'slow', sLbl=secs===null?'':secs<=10?`⚡ ${secs}s`:secs<=30?`⏱ ${secs}s`:`${secs}s`;
  const reH=Object.entries(a.reactions||{}).filter(([,c])=>c>0).map(([t,c])=>`<span class="rpill">${t} <span>${c}</span></span>`).join('');
  const noteText=dom?.note||a.admin_note||'', noteH=noteText?`<div class="anote">📝 ${esc(noteText)}</div>`:'';
  const attH=a.attachment?`<br><img class="aimg" src="../${esc(a.attachment)}" alt="">`:'' ;
  const voiH=a.voice_path?`<audio class="aaud" controls src="../${esc(a.voice_path)}"></audio>`:'';
  card.innerHTML=`
    <div class="rnk ${rCls}">${rLbl}</div>
    <div class="ab">
      <div class="ah">
        <div class="aav" style="background:${bg}">${avH}</div>
        <span class="aname">${esc(nm)}</span>
        <span class="verd ${isC?'c':'w'}">${isC?'✓ Correct':'✗ Wrong'}</span>
        ${sLbl?`<span class="spd ${sCls}">${sLbl}</span>`:''}
        ${rn===1?`<span class="spd fast" style="background:rgba(245,158,11,.15);color:var(--amber)">⚡ 1st</span>`:''}
        <span class="atime">${ts(a.created_at)}</span>
      </div>
      <div class="ao-row">
        <span class="ao ${oCls}">${opt?`Option ${opt}`:'No option'}</span>
        ${a.correct_answer?`<span style="font-size:10px;color:var(--dim);font-family:'Space Mono',monospace">Ans: ${esc(a.correct_answer.toUpperCase())}</span>`:''}
      </div>
      ${a.answer_text?`<div class="atxt">${esc(a.answer_text)}${attH}${voiH}</div>`:attH||voiH?`<div>${attH}${voiH}</div>`:''}
      ${reH?`<div class="reacts">${reH}</div>`:''}
      ${noteH}
    </div>
    <div class="aacts">
      <button class="aa${isL?' al':''}" title="${isL?'Unstar':'Star'}" onclick="toggleLike(${a.id},this)">${isL?'⭐':'☆'}</button>
      <button class="aa ${isC?'ac2':'aw'}" title="Toggle correct" onclick="toggleCor(${a.id},${isC?0:1},this)">
        <i class="fa ${isC?'fa-check':'fa-times'}" style="font-size:10px"></i>
      </button>
      <button class="aa" title="Note" onclick="openNote(${a.id})"><i class="fa fa-pen" style="font-size:9px"></i></button>
    </div>`;
  return card;
}

/* ── LIKE ── */
async function toggleLike(aid,btn){
  const j=await post('like',{aid});
  if(!j.success) return;
  const card=document.getElementById('ac-'+aid);
  if(j.liked){btn.classList.add('al');btn.textContent='⭐';card?.classList.add('ac-l');}
  else{btn.classList.remove('al');btn.textContent='☆';card?.classList.remove('ac-l');}
}

/* ── TOGGLE CORRECT ── */
async function toggleCor(aid,nv,btn){
  const j=await post('set_correct',{aid,correct:nv});
  if(!j.success) return;
  const card=document.getElementById('ac-'+aid);
  const vd=card?.querySelector('.verd'), ao=card?.querySelector('.ao');
  if(nv){btn.className='aa ac2';btn.innerHTML='<i class="fa fa-check" style="font-size:10px"></i>';vd&&(vd.className='verd c',vd.textContent='✓ Correct');ao&&(ao.className='ao c');card?.classList.add('ac-c');}
  else{btn.className='aa aw';btn.innerHTML='<i class="fa fa-times" style="font-size:10px"></i>';vd&&(vd.className='verd w',vd.textContent='✗ Wrong');ao&&(ao.className='ao w');card?.classList.remove('ac-c');}
  const c=document.querySelectorAll('.verd.c').length, tot=document.querySelectorAll('.verd').length;
  document.getElementById('sC').textContent=c; document.getElementById('sW').textContent=tot-c;
}

/* ── NOTE ── */
function openNote(aid){
  noteAid=aid;
  const card=document.getElementById('ac-'+aid), n=card?.querySelector('.anote');
  document.getElementById('ntxt').value=n?n.textContent.replace('📝 ',''):'';
  document.getElementById('npop').classList.add('show');
  setTimeout(()=>document.getElementById('ntxt').focus(),80);
}
function closeNote(){document.getElementById('npop').classList.remove('show');noteAid=null;}
async function saveNote(){
  if(!noteAid) return;
  const note=document.getElementById('ntxt').value.trim();
  await post('add_note',{aid:noteAid,note});
  const card=document.getElementById('ac-'+noteAid);
  if(card){let n=card.querySelector('.anote');if(note){if(!n){n=document.createElement('div');n.className='anote';card.querySelector('.ab').appendChild(n);}n.textContent='📝 '+note;}else if(n)n.remove();}
  closeNote();
}
document.getElementById('npop').addEventListener('click',e=>{if(e.target.id==='npop')closeNote();});

/* ── CREATE QUESTION ── */
function pickC(v){correctA=v;document.querySelectorAll('.co').forEach(o=>o.classList.toggle('on',o.dataset.v===v));}
function togA(){activateNow=!activateNow;document.getElementById('aTrack').classList.toggle('on',activateNow);}
function scrollCreate(){document.getElementById('crBody').scrollIntoView({behavior:'smooth'});}
async function createQ(){
  const q=document.getElementById('fQ').value.trim(), msg=document.getElementById('fmsg');
  if(!q){msg.className='fmsg err';msg.textContent='Question text required.';msg.style.display='block';return;}
  const fd=new FormData();
  fd.append('question',q); fd.append('option_a',document.getElementById('fA').value.trim());
  fd.append('option_b',document.getElementById('fB').value.trim()); fd.append('option_c',document.getElementById('fC').value.trim());
  fd.append('option_d',document.getElementById('fD').value.trim()); fd.append('correct_answer',correctA);
  fd.append('subject_id',document.getElementById('fSubj').value); fd.append('activate',activateNow?'1':'0');
  const j=await fetch(API+'?action=create_question',{method:'POST',body:fd}).then(r=>r.json());
  if(!j.success){msg.className='fmsg err';msg.textContent=j.error||'Failed';msg.style.display='block';return;}
  msg.className='fmsg ok';msg.textContent=activateNow?'✓ Created and now LIVE!':'✓ Question created';msg.style.display='block';
  setTimeout(()=>msg.style.display='none',3000);
  ['fQ','fA','fB','fC','fD'].forEach(id=>document.getElementById(id).value='');
  await refreshQ();
  if(activateNow&&j.qid) selQ(j.qid);
}

/* ── POLL ── */
async function refreshQ(){
  try{
    const j=await fetch(API+'?action=questions_list').then(r=>r.json());
    if(j.success){ allQ=j.questions; buildList(); syncUpNextStrip(); }
  }catch(e){}
}
async function poll(){
  await refreshQ();
  if(selId) await loadAnswers(selId);
}

/* ── BOOT ── */
buildList();
syncUpNextStrip();
if(selId){const q=allQ.find(x=>x.id==selId);if(q){renderHero(q);loadAnswers(selId);}}
setInterval(poll,2000);
document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});

/* ════════════════════════════════════
   PICKER MODAL
════════════════════════════════════ */
function openPicker(){renderPicker();document.getElementById('pickerOv').classList.add('show');setTimeout(()=>document.getElementById('pickerSrch').focus(),80);}
function closePicker(){document.getElementById('pickerOv').classList.remove('show');document.getElementById('pickerSrch').value='';expandedPqId=null;}
document.getElementById('pickerOv').addEventListener('click',e=>{if(e.target.id==='pickerOv')closePicker();});

function renderPicker(){
  const q=(document.getElementById('pickerSrch').value||'').toLowerCase();
  const body=document.getElementById('pickerBody');
  body.innerHTML='';
  const filtered=allQ.filter(x=>!q||x.question.toLowerCase().includes(q)||(x.subject_name||'').toLowerCase().includes(q));
  if(!filtered.length){body.innerHTML=`<div class="picker-empty"><div class="pe-i">🔍</div><div>No matching questions</div></div>`;return;}
  const groups={};
  filtered.forEach(x=>{const g=x.subject_name||'No Subject';if(!groups[g])groups[g]=[];groups[g].push(x);});
  Object.entries(groups).forEach(([grp,qs])=>{
    const lbl=document.createElement('div');lbl.className='pgrp-label';lbl.textContent=`${grp} (${qs.length})`;body.appendChild(lbl);
    qs.forEach(q=>body.appendChild(buildPqRow(q)));
  });
}

function buildPqRow(q){
  const live=q.status==='active', nxt=parseInt(q.next_in_queue||0)===1&&!live;
  const tot=parseInt(q.total_answers||0), cor=parseInt(q.correct_count||0);
  const ca=(q.correct_answer||'').toUpperCase();
  const wrap=document.createElement('div');wrap.style.marginBottom='2px';
  const row=document.createElement('div');
  row.className=`pq-row${live?' is-live':nxt?' is-next':''}`;row.id='pqr-'+q.id;
  row.innerHTML=`
    <div class="pq-num ${live?'live':nxt?'next':''}">${live?'📡':nxt?'⏭':'#'+q.id}</div>
    <div class="pq-info">
      <div class="pq-text">${esc(q.question)}</div>
      <div class="pq-meta">
        <span class="pq-badge ${live?'live':nxt?'nxt':'off'}">${live?'● LIVE':nxt?'⏭ NEXT':'● IDLE'}</span>
        <span class="pq-ans">${tot} ans</span>
        ${tot?`<span class="pq-cor">${cor} ✓`:''}
        ${q.subject_name?`<span style="font-size:10px;color:var(--dim)">${esc(q.subject_name)}</span>`:''}
      </div>
    </div>
    <div class="pq-acts">
      <button class="pq-act pq-eye" title="Preview" onclick="togglePqExpand(${q.id},event)"><i class="fa fa-eye" style="font-size:10px"></i></button>
      ${live
        ?`<button class="pq-act pq-stop" onclick="pickerStop(${q.id},event)"><i class="fa fa-stop" style="font-size:10px"></i> Stop</button>`
        :`<button class="pq-act pq-go" onclick="pickerGo(${q.id},event)"><i class="fa fa-play" style="font-size:10px"></i> Go Live</button>`
      }
      ${!live
        ?(nxt
          ?`<button class="pq-act pq-unsetnxt" onclick="pickerUnsetNext(${q.id},event)">✕ Next</button>`
          :`<button class="pq-act pq-setnxt" onclick="pickerSetNext(${q.id},event)"><i class="fa fa-forward" style="font-size:9px"></i> Set Next</button>`
        ):''
      }
    </div>`;
  const expand=document.createElement('div');
  expand.className='pq-expand'+(expandedPqId===q.id?' show':'');expand.id='pqx-'+q.id;
  const opts=['A','B','C','D'].map(l=>{const v=q['option_'+l.toLowerCase()];return v?`<span class="pq-opt ${ca===l?'cor':''}">${l}: ${esc(v)}</span>`:''}).join('');
  expand.innerHTML=`<strong style="color:var(--text)">${esc(q.question)}</strong><div class="pq-opts">${opts}</div>`;
  wrap.appendChild(row);wrap.appendChild(expand);return wrap;
}

function togglePqExpand(qid,e){e.stopPropagation();expandedPqId=expandedPqId===qid?null:qid;renderPicker();}

async function pickerGo(qid,e){
  e.stopPropagation();
  const cur=allQ.find(x=>x.status==='active');
  if(cur&&cur.id!=qid&&!confirm(`Stop "${(cur.question||'').slice(0,50)}…" and go live with new question?`))return;
  await setStatus(qid,'active',null);
  selQ(qid); closePicker();
}
async function pickerStop(qid,e){
  e.stopPropagation();
  await setStatus(qid,'inactive',null);
  renderPicker();
}
async function pickerSetNext(qid,e){e.stopPropagation();await setNextQueue(qid,1,null);renderPicker();}
async function pickerUnsetNext(qid,e){e.stopPropagation();await setNextQueue(0,0,null);renderPicker();}
</script>
</body>
</html>
