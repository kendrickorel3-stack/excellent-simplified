<?php
// admin/aloc_panel.php — ALOC Question Browser & Brainstorm Launcher
// Links from admin/dashboard.php  ·  Writes to the same `questions` table
// used by questions/get_questions.php (brainstorm control center)

error_reporting(E_ERROR|E_PARSE); ini_set('display_errors','0');
session_start();
require_once __DIR__.'/../config/db.php';

// ── Auth: admin only ─────────────────────────────────────────────
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if (!$user_id) { header('Location: ../login.php'); exit; }

// Check admin flag
$isAdmin = false;
$ar = $conn->prepare("SELECT is_admin FROM users WHERE id=? LIMIT 1");
if ($ar) { $ar->bind_param('i',$user_id); $ar->execute();
  $isAdmin = (bool)($ar->get_result()->fetch_assoc()['is_admin'] ?? 0); $ar->close(); }
if (!$isAdmin) { header('Location: ../dashboard.php'); exit; }

$dn = $_SESSION['google_name'] ?? 'Admin';
$dp = $_SESSION['google_picture'] ?? null;

// ── ALOC config ───────────────────────────────────────────────────
define('ALOC_TOKEN','QB-b67089074cbb68438091');
define('ALOC_BASE','https://questions.aloc.com.ng/api/v2');

$SUBJECTS = [
  'Physics'         => ['slug'=>'physics',       'icon'=>'⚡','color'=>'#f59e0b'],
  'Chemistry'       => ['slug'=>'chemistry',     'icon'=>'⚗️','color'=>'#00c98a'],
  'Biology'         => ['slug'=>'biology',       'icon'=>'🧬','color'=>'#a78bfa'],
  'Mathematics'     => ['slug'=>'mathematics',  'icon'=>'📐','color'=>'#3b82f6'],
  'English'         => ['slug'=>'english',       'icon'=>'📖','color'=>'#ff6b6b'],
];

$TOPICS = [
  'Physics' => [
    'Mechanics','Motion','Velocity & Acceleration','Newton Laws of Motion',
    'Work Energy & Power','Simple Machines','Pressure','Archimedes Principle',
    'Waves & Sound','Light & Optics','Reflection & Refraction','Lens & Mirrors',
    'Electricity','Current & Resistance','Ohm Law','Electric Circuits',
    'Magnetism','Electromagnetic Induction','Capacitors','Transformers',
    'Heat & Temperature','Thermal Expansion','Gas Laws','Specific Heat Capacity',
    'Atomic Structure','Radioactivity','Nuclear Physics','X-Rays',
    'Simple Harmonic Motion','Gravitational Field','Satellites & Orbits',
    'Projectile Motion','Friction','Equilibrium','Moments & Couples',
  ],
  'Chemistry' => [
    'Atomic Structure','Electronic Configuration','Periodic Table','Periodicity',
    'Chemical Bonding','Ionic Bonding','Covalent Bonding','Metallic Bonding',
    'Acids & Bases','pH Scale','Neutralization','Salts',
    'Organic Chemistry','Hydrocarbons','Alkanes','Alkenes','Alkynes',
    'Alcohols','Carboxylic Acids','Esters','Polymers',
    'Electrochemistry','Electrolysis','Galvanic Cells','Oxidation & Reduction',
    'Rates of Reaction','Catalysts','Equilibrium','Le Chatelier Principle',
    'Gases','Kinetic Theory','Gas Laws','Mole Concept',
    'Metals','Extraction of Metals','Corrosion','Alloys',
    'Water','Hardness of Water','Purification','Environmental Chemistry',
    'Redox Reactions','Oxidation Numbers','Stoichiometry','Chemical Equations',
  ],
  'Biology' => [
    'Cell Biology','Cell Structure','Cell Division','Osmosis & Diffusion',
    'Genetics','DNA & RNA','Inheritance','Mendels Laws','Mutations',
    'Ecology','Food Chains','Ecosystems','Population','Conservation',
    'Evolution','Natural Selection','Adaptation','Fossil Records',
    'Photosynthesis','Leaf Structure','Light & Dark Reactions','Chlorophyll',
    'Respiration','Aerobic Respiration','Anaerobic Respiration','ATP',
    'Nutrition','Digestion','Digestive System','Enzymes','Food Tests',
    'Reproduction','Sexual Reproduction','Asexual Reproduction','Fertilization',
    'Nervous System','Brain','Spinal Cord','Sense Organs','Reflex Action',
    'Hormones','Endocrine System','Insulin','Adrenaline',
    'Excretion','Kidney','Liver','Skin','Lungs',
    'Circulatory System','Heart','Blood','Blood Groups',
    'Respiratory System','Breathing','Gas Exchange',
    'Diseases & Immunity','Vaccines','Pathogens','White Blood Cells',
    'Plants','Phototropism','Transpiration','Root & Shoot','Flowers',
    'Classification','Kingdom','Phylum','Species','Taxonomy',
    'Human Biology','Skeleton','Muscles','Joints','Movement',
  ],
  'Mathematics' => [
    'Algebra','Linear Equations','Simultaneous Equations','Inequalities',
    'Quadratic Equations','Factorization','Completing the Square','Formula',
    'Functions','Domain & Range','Composite Functions','Inverse Functions',
    'Calculus','Differentiation','Integration','Rates of Change','Maxima & Minima',
    'Statistics','Mean Median Mode','Standard Deviation','Frequency Distribution',
    'Probability','Sample Space','Tree Diagrams','Permutation & Combination',
    'Geometry','Angles','Triangles','Circles','Polygons',
    'Trigonometry','Sin Cos Tan','Sine Rule','Cosine Rule','Bearings',
    'Mensuration','Area','Volume','Surface Area','Perimeter',
    'Sequences & Series','Arithmetic Progression','Geometric Progression','Sum of Series',
    'Matrices','Matrix Operations','Determinants','Inverse Matrix',
    'Logarithms','Laws of Logarithms','Natural Log','Change of Base',
    'Number Theory','Prime Numbers','HCF & LCM','Surds','Indices',
    'Sets','Union & Intersection','Venn Diagrams','Complement',
    'Vectors','Vector Addition','Scalar & Dot Product','Magnitude',
    'Coordinate Geometry','Straight Lines','Gradient','Midpoint','Distance',
  ],
  'English' => [
    'Comprehension','Reading Skills','Inference','Summary Writing',
    'Grammar','Parts of Speech','Nouns','Pronouns','Verbs','Adjectives','Adverbs',
    'Sentence Structure','Clauses','Phrases','Simple Compound Complex',
    'Tenses','Present Past Future','Perfect Tenses','Continuous Tenses',
    'Vocabulary','Synonyms','Antonyms','Homonyms','Word Formation',
    'Idioms & Proverbs','Common Idioms','Nigerian Proverbs','Figurative Language',
    'Oral English','Vowel Sounds','Consonants','Stress & Intonation','Phonemes',
    'Essay Writing','Formal Letters','Informal Letters','Report Writing',
    'Figures of Speech','Simile','Metaphor','Personification','Hyperbole',
    'Punctuation','Comma Usage','Apostrophe','Semicolon','Quotation Marks',
    'Literature','Poetry','Prose','Drama','African Literature',
    'Concord & Agreement','Subject Verb Agreement','Pronoun Agreement',
    'Direct & Indirect Speech','Reported Speech','Question Tags',
  ],
];

$EXAM_TYPES = ['utme'=>'JAMB UTME','wassce'=>'WAEC SSCE','neco'=>'NECO'];

// ── DB helpers ────────────────────────────────────────────────────
// Ensure subjects & questions tables exist (safe)
$conn->query("CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT DEFAULT NULL,
  question TEXT NOT NULL,
  option_a TEXT, option_b TEXT, option_c TEXT, option_d TEXT,
  correct_answer VARCHAR(5) DEFAULT NULL,
  status ENUM('active','inactive') DEFAULT 'inactive',
  timer_ends_at DATETIME DEFAULT NULL,
  next_in_queue TINYINT DEFAULT 0,
  aloc_year VARCHAR(10) DEFAULT NULL,
  aloc_source VARCHAR(30) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");




// ── AJAX: Fetch questions from ALOC ──────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
  header('Content-Type: application/json; charset=utf-8');
  $subj_name = trim($_GET['subject'] ?? '');
  $exam_type = trim($_GET['exam_type'] ?? 'utme');
  $count     = min(60, max(5, (int)($_GET['count'] ?? 20)));

  if (!$subj_name || !isset($SUBJECTS[$subj_name])) {
    echo json_encode(['success'=>false,'error'=>'Unknown subject']); exit;
  }
  $slug = $SUBJECTS[$subj_name]['slug'];

  $questions = []; $seen = []; $attempts = 0; $max = $count * 5;
  while (count($questions) < $count && $attempts < $max) {
    $attempts++;
    $url = ALOC_BASE.'/q?subject='.urlencode($slug).'&type='.urlencode($exam_type);
    $ch  = curl_init($url);
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15,
      CURLOPT_HTTPHEADER=>['AccessToken: '.ALOC_TOKEN,'Accept: application/json'],
      CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_FOLLOWLOCATION=>true
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (!$raw || $code !== 200) { usleep(120000); continue; }
    $data = json_decode($raw, true);
    if (empty($data['data'])) { usleep(80000); continue; }
    $d = $data['data'];
    $id = $d['id'] ?? uniqid('q_');
    if (in_array($id, $seen, true)) { usleep(40000); continue; }
    $seen[] = $id;
    $questions[] = [
      'aloc_id'        => $id,
      'question'       => $d['question'] ?? '',
      'option_a'       => $d['option']['a'] ?? $d['a'] ?? '',
      'option_b'       => $d['option']['b'] ?? $d['b'] ?? '',
      'option_c'       => $d['option']['c'] ?? $d['c'] ?? '',
      'option_d'       => $d['option']['d'] ?? $d['d'] ?? '',
      'correct_answer' => strtoupper(trim($d['answer'] ?? '')),
      'subject'        => $subj_name,
      'year'           => $d['year'] ?? '',
      'exam_type'      => $exam_type,
    ];
    usleep(80000);
  }
  if (empty($questions)) {
    echo json_encode(['success'=>false,'error'=>"No questions returned for {$subj_name} ({$exam_type}). Try a different type."]);
    exit;
  }
  echo json_encode(['success'=>true,'questions'=>$questions,'count'=>count($questions)]);
  exit;
}

// ── AJAX: Search — fetch 50 questions, filter by keyword/year ────
if (isset($_GET['action']) && $_GET['action'] === 'search') {
  header('Content-Type: application/json; charset=utf-8');

  $subj_name  = trim($_GET['subject']   ?? '');
  $exam_type  = trim($_GET['exam_type'] ?? 'utme');
  $keyword    = strtolower(trim($_GET['keyword']  ?? ''));
  $year_filter= trim($_GET['year']      ?? '');   // e.g. "2019" or "" for any
  $target     = 50; // always fetch 50

  if (!$subj_name || !isset($SUBJECTS[$subj_name])) {
    echo json_encode(['success'=>false,'error'=>'Unknown subject']); exit;
  }
  $slug = $SUBJECTS[$subj_name]['slug'];

  // Build ALOC URL — append year if provided (ALOC supports ?year=YYYY)
  $base_url = ALOC_BASE.'/q?subject='.urlencode($slug).'&type='.urlencode($exam_type);
  if ($year_filter && preg_match('/^\d{4}$/', $year_filter)) {
    $base_url .= '&year='.urlencode($year_filter);
  }

  $questions = []; $seen = []; $attempts = 0;
  // We try up to 8× the target to account for duplicates & misses
  $max = $target * 8;

  while (count($questions) < $target && $attempts < $max) {
    $attempts++;
    $ch = curl_init($base_url);
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15,
      CURLOPT_HTTPHEADER=>['AccessToken: '.ALOC_TOKEN,'Accept: application/json'],
      CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_FOLLOWLOCATION=>true
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (!$raw || $code !== 200) { usleep(120000); continue; }
    $data = json_decode($raw, true);
    if (empty($data['data'])) { usleep(80000); continue; }
    $d  = $data['data'];
    $id = $d['id'] ?? uniqid('q_');
    if (in_array($id, $seen, true)) { usleep(40000); continue; }
    $seen[] = $id;

    $q_text = $d['question'] ?? '';
    $q_year = trim($d['year'] ?? '');

    // ── keyword filter (server-side) ────────────────────────────
    if ($keyword !== '') {
      $haystack = strtolower($q_text . ' ' .
        ($d['option']['a'] ?? $d['a'] ?? '') . ' ' .
        ($d['option']['b'] ?? $d['b'] ?? '') . ' ' .
        ($d['option']['c'] ?? $d['c'] ?? '') . ' ' .
        ($d['option']['d'] ?? $d['d'] ?? '') . ' ' .
        $q_year);
      if (strpos($haystack, $keyword) === false) {
        usleep(40000); continue;  // not a match — skip
      }
    }

    $questions[] = [
      'aloc_id'        => $id,
      'question'       => $q_text,
      'option_a'       => $d['option']['a'] ?? $d['a'] ?? '',
      'option_b'       => $d['option']['b'] ?? $d['b'] ?? '',
      'option_c'       => $d['option']['c'] ?? $d['c'] ?? '',
      'option_d'       => $d['option']['d'] ?? $d['d'] ?? '',
      'correct_answer' => strtoupper(trim($d['answer'] ?? '')),
      'subject'        => $subj_name,
      'year'           => $q_year,
      'exam_type'      => $exam_type,
    ];
    usleep(80000);
  }

  if (empty($questions)) {
    $msg = $keyword
      ? "No questions found matching \"".htmlspecialchars($keyword)."\" in {$subj_name}."
        ." Try a broader keyword or different exam type."
      : "No questions returned for {$subj_name} ({$exam_type}). Try a different type.";
    echo json_encode(['success'=>false,'error'=>$msg]); exit;
  }

  // Sort by year descending so newest appear first
  usort($questions, fn($a,$b) => strcmp($b['year'],$a['year']));

  echo json_encode([
    'success'   => true,
    'questions' => $questions,
    'count'     => count($questions),
    'keyword'   => $keyword,
    'year'      => $year_filter,
    'subject'   => $subj_name,
    'exam_type' => $exam_type,
  ]);
  exit;
}

// ── AJAX: Set question live for brainstorm ───────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='set_live') {
  header('Content-Type: application/json; charset=utf-8');
  $question  = trim($_POST['question'] ?? '');
  $opt_a     = trim($_POST['option_a'] ?? '');
  $opt_b     = trim($_POST['option_b'] ?? '');
  $opt_c     = trim($_POST['option_c'] ?? '');
  $opt_d     = trim($_POST['option_d'] ?? '');
  $ans       = strtoupper(trim($_POST['correct_answer'] ?? ''));
  $subj_name = trim($_POST['subject'] ?? '');
  $year      = trim($_POST['year'] ?? '');
  $etype     = trim($_POST['exam_type'] ?? '');

  if (!$question) { echo json_encode(['success'=>false,'error'=>'Empty question']); exit; }

  // Ensure subject exists
  $subject_id = null;
  if ($subj_name) {
    $conn->query("INSERT IGNORE INTO subjects (name) VALUES ('".addslashes($subj_name)."')");
    $sr = $conn->query("SELECT id FROM subjects WHERE name='".addslashes($subj_name)."' LIMIT 1");
    if ($sr) $subject_id = (int)($sr->fetch_assoc()['id'] ?? 0) ?: null;
  }

  // Deactivate all existing live questions
  $conn->query("UPDATE questions SET status='inactive', timer_ends_at=NULL");

  // Check if identical question already in DB (avoid duplicates)
  $chk = $conn->prepare("SELECT id FROM questions WHERE question=? LIMIT 1");
  $existing_id = null;
  if ($chk) {
    $chk->bind_param('s',$question); $chk->execute();
    $existing_id = $chk->get_result()->fetch_assoc()['id'] ?? null;
    $chk->close();
  }

  if ($existing_id) {
    // Re-use existing, just activate it
    $up = $conn->prepare("UPDATE questions SET status='active',option_a=?,option_b=?,option_c=?,option_d=?,correct_answer=?,subject_id=?,aloc_year=?,aloc_source=? WHERE id=?");
    if ($up) { $up->bind_param('ssssssssi',$opt_a,$opt_b,$opt_c,$opt_d,$ans,$subject_id,$year,$etype,$existing_id); $up->execute(); $up->close(); }
    // Clear old answers for this question
    $conn->query("DELETE FROM brainstorm_answers WHERE question_id=$existing_id");
    echo json_encode(['success'=>true,'question_id'=>$existing_id,'reused'=>true]);
  } else {
    // Insert fresh
    $ins = $conn->prepare("INSERT INTO questions (question,option_a,option_b,option_c,option_d,correct_answer,subject_id,status,aloc_year,aloc_source,created_at) VALUES (?,?,?,?,?,?,?,'active',?,?,NOW())");
    if (!$ins) { echo json_encode(['success'=>false,'error'=>$conn->error]); exit; }
    $ins->bind_param('sssssssss',$question,$opt_a,$opt_b,$opt_c,$opt_d,$ans,$subject_id,$year,$etype);
    $ins->execute();
    $new_id = (int)$conn->insert_id;
    $ins->close();
    echo json_encode(['success'=>true,'question_id'=>$new_id,'reused'=>false]);
  }
  exit;
}

// ── AJAX: Get current live question ─────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_live') {
  header('Content-Type: application/json; charset=utf-8');
  $r = $conn->query("SELECT q.*,s.name AS subject_name FROM questions q LEFT JOIN subjects s ON s.id=q.subject_id WHERE q.status='active' LIMIT 1");
  $live = $r ? $r->fetch_assoc() : null;
  echo json_encode(['success'=>true,'live'=>$live]);
  exit;
}

// ── AJAX: Deactivate live question ───────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='stop_live') {
  header('Content-Type: application/json; charset=utf-8');
  $conn->query("UPDATE questions SET status='inactive', timer_ends_at=NULL");
  echo json_encode(['success'=>true]);
  exit;
}

// ── Load subjects for sidebar ─────────────────────────────────────
$subjects_json = json_encode($SUBJECTS, JSON_UNESCAPED_UNICODE);
$topics_json = json_encode($TOPICS, JSON_UNESCAPED_UNICODE);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ALOC Question Panel — Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#04020e;--s1:#0d1017;--s2:#131720;--s3:#1a1f2e;--s4:#202638;
  --border:rgba(255,255,255,.07);--border2:rgba(255,255,255,.14);
  --accent:#00c98a;--blue:#3b82f6;--amber:#f59e0b;--danger:#ff4757;--purple:#a78bfa;
  --text:#e8ecf4;--sub:#8a93ab;--dim:#4a5268;
  --live:#ff4757;--live-glow:rgba(255,71,87,.4);
}
/* ── WOOD RED DARK MODE ── */
body.dark-wood{
  --bg:#1a0a06;--s1:#230e08;--s2:#2d1209;--s3:#38160c;--s4:#43190e;
  --border:rgba(200,100,60,.12);--border2:rgba(200,100,60,.25);
  --accent:#c0392b;--blue:#e74c3c;--amber:#e67e22;--danger:#ff6b35;--purple:#d35400;
  --text:#f5e6e0;--sub:#b08070;--dim:#6b3a2a;
  --live:#ff4500;--live-glow:rgba(255,69,0,.4);
}
/* ── LIGHT MODE ── */
body.light-mode{
  --bg:#f8f9fa;--s1:#ffffff;--s2:#f1f3f5;--s3:#e9ecef;--s4:#dee2e6;
  --border:rgba(0,0,0,.08);--border2:rgba(0,0,0,.15);
  --accent:#00a870;--blue:#2563eb;--amber:#d97706;--danger:#dc2626;--purple:#7c3aed;
  --text:#1a1d23;--sub:#4a5568;--dim:#9ca3af;
  --live:#dc2626;--live-glow:rgba(220,38,38,.3);
}
/* ── TOPIC POPUP MODAL ── */
.topic-modal-ov{
  display:none;position:fixed;inset:0;z-index:150;
  background:rgba(4,2,14,.88);backdrop-filter:blur(10px);
  align-items:center;justify-content:center;padding:20px;
}
.topic-modal-ov.show{display:flex}
.topic-modal{
  width:100%;max-width:680px;max-height:85vh;
  background:var(--s1);border:1px solid var(--border2);
  border-radius:20px;overflow:hidden;
  animation:popIn .25s cubic-bezier(.22,1,.36,1) both;
  display:flex;flex-direction:column;
}
.topic-modal-head{
  padding:16px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:12px;flex-shrink:0;
}
.topic-modal-icon{
  width:36px;height:36px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;font-size:18px;
}
.topic-modal-title{font-size:15px;font-weight:700;flex:1}
.topic-modal-sub{font-size:11px;color:var(--sub);margin-top:2px}
.topic-modal-x{
  width:30px;height:30px;border-radius:8px;background:var(--s2);
  border:1px solid var(--border);color:var(--sub);cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:12px;transition:all .15s;
}
.topic-modal-x:hover{color:var(--text)}
.topic-modal-body{padding:16px 20px;overflow-y:auto;flex:1}
.topic-section{margin-bottom:16px}
.topic-section-title{
  font-family:'Space Mono',monospace;font-size:9px;font-weight:700;
  letter-spacing:.1em;text-transform:uppercase;color:var(--dim);
  margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--border);
}
.topic-chips{display:flex;flex-wrap:wrap;gap:7px}
.topic-chip{
  padding:7px 14px;border-radius:22px;font-size:12px;font-weight:600;
  border:1.5px solid var(--border2);background:var(--s2);color:var(--sub);
  cursor:pointer;transition:all .18s;white-space:nowrap;
}
.topic-chip:hover{color:var(--text);border-color:var(--accent);background:var(--s3);transform:translateY(-1px)}
.topic-chip.active{
  background:rgba(0,201,138,.15);border-color:var(--accent);
  color:var(--accent);font-weight:700;
}
body.dark-wood .topic-chip.active{
  background:rgba(192,57,43,.15);border-color:var(--accent);color:var(--accent);
}
.topic-chip.all-chip{
  background:var(--s3);border-color:var(--border2);color:var(--text);font-weight:700;
}
.topic-chip.all-chip.active{
  background:rgba(59,130,246,.15);border-color:var(--blue);color:var(--blue);
}
.topic-modal-foot{
  padding:12px 20px;border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:10px;flex-shrink:0;
}
.topic-selected-label{font-size:12px;color:var(--sub)}
.topic-selected-val{font-weight:700;color:var(--accent)}
.topic-load-btn{
  display:flex;align-items:center;gap:7px;padding:9px 20px;border-radius:10px;
  background:linear-gradient(135deg,var(--accent),var(--blue));color:#000;
  border:none;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:700;
  cursor:pointer;transition:all .18s;box-shadow:0 3px 12px rgba(0,201,138,.2);
}
.topic-load-btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,201,138,.3)}
/* Topic button in sidebar */
.topic-open-btn{
  display:flex;align-items:center;gap:7px;width:calc(100% - 24px);
  margin:4px 12px 8px;padding:8px 12px;border-radius:10px;
  background:rgba(0,201,138,.08);border:1.5px dashed rgba(0,201,138,.3);
  color:var(--accent);cursor:pointer;font-size:12px;font-weight:600;
  transition:all .15s;justify-content:center;
}
.topic-open-btn:hover{background:rgba(0,201,138,.15);border-style:solid}
.topic-open-btn.has-topic{
  background:rgba(0,201,138,.15);border-style:solid;
}
/* ── THEME TOGGLE BTN ── */
.theme-toggle{
  height:30px;padding:0 11px;border-radius:7px;background:var(--s2);
  border:1px solid var(--border);color:var(--sub);cursor:pointer;
  display:flex;align-items:center;gap:6px;font-family:'Plus Jakarta Sans',sans-serif;
  font-size:12px;font-weight:600;transition:all .15s;flex-shrink:0;
}
.theme-toggle:hover{color:var(--text);border-color:var(--border2)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:var(--bg);color:var(--text);
  font-family:'Plus Jakarta Sans',sans-serif;-webkit-font-smoothing:antialiased;overflow:hidden}

/* ── TOPBAR ── */
.topbar{height:56px;background:rgba(13,16,23,.97);backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);display:flex;align-items:center;
  padding:0 16px;gap:10px;flex-shrink:0;position:sticky;top:0;z-index:60}
.tb-logo{width:30px;height:30px;border-radius:8px;
  background:linear-gradient(135deg,var(--accent),var(--blue));
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:#000;flex-shrink:0}
.tb-title{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;letter-spacing:.06em}
.tb-badge{padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;
  font-family:'Space Mono',monospace;background:rgba(255,71,87,.15);
  border:1px solid rgba(255,71,87,.3);color:var(--danger);flex-shrink:0}
.tb-flex{flex:1}
.tb-right{display:flex;gap:7px;align-items:center}
.t-btn{height:30px;padding:0 11px;border-radius:7px;background:var(--s2);
  border:1px solid var(--border);color:var(--sub);cursor:pointer;
  display:flex;align-items:center;gap:6px;font-family:'Plus Jakarta Sans',sans-serif;
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

/* ── APP BODY ── */
.app{display:flex;height:calc(100vh - 56px);overflow:hidden}

/* ── SIDEBAR ── */
.sidebar{width:220px;flex-shrink:0;background:var(--s1);border-right:1px solid var(--border);
  display:flex;flex-direction:column;overflow:hidden}
.sb-inner{flex:1;overflow-y:auto;padding:8px}
.sb-inner::-webkit-scrollbar{width:3px}
.sb-inner::-webkit-scrollbar-thumb{background:var(--s3);border-radius:3px}
.sb-section{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
  letter-spacing:.1em;text-transform:uppercase;color:var(--dim);padding:10px 8px 6px}
.subj-btn{width:100%;display:flex;align-items:center;gap:9px;padding:9px 9px;
  border-radius:10px;border:1.5px solid transparent;background:transparent;
  cursor:pointer;text-align:left;color:var(--sub);transition:all .15s;
  margin-bottom:2px;font-family:'Plus Jakarta Sans',sans-serif}
.subj-btn:hover{background:var(--s2);color:var(--text)}
.subj-btn.active{background:var(--s2);border-color:var(--border2);color:var(--text)}
.subj-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;
  justify-content:center;font-size:15px;flex-shrink:0}
.subj-meta{flex:1;min-width:0}
.subj-name{font-size:12px;font-weight:700;line-height:1.2;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-footer{padding:8px;border-top:1px solid var(--border);flex-shrink:0}
.sb-link{display:flex;align-items:center;gap:7px;padding:8px 9px;border-radius:8px;
  text-decoration:none;color:var(--dim);font-size:12px;font-weight:500;transition:all .15s}
.sb-link:hover{background:var(--s2);color:var(--text)}

/* ── MAIN ── */
.main{flex:1;display:flex;flex-direction:column;min-width:0;overflow:hidden}

/* ── TOOLBAR ── */
.toolbar{padding:10px 16px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:10px;flex-shrink:0;background:var(--s1);flex-wrap:wrap}
.toolbar-left{display:flex;align-items:center;gap:8px;flex:1;flex-wrap:wrap}
.tb-select{height:32px;padding:0 10px;border-radius:8px;background:var(--s2);
  border:1px solid var(--border);color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;
  font-size:13px;cursor:pointer;transition:all .15s;outline:none;min-width:120px}
.tb-select:focus{border-color:var(--border2)}
.count-row{display:flex;gap:5px}
.cnt-btn{padding:5px 11px;border-radius:7px;border:1px solid var(--border);
  background:var(--s2);color:var(--sub);font-size:12px;font-weight:600;cursor:pointer;transition:all .15s}
.cnt-btn:hover{color:var(--text);border-color:var(--border2)}
.cnt-btn.on{background:rgba(59,130,246,.1);border-color:rgba(59,130,246,.35);color:var(--blue)}
.load-btn{display:flex;align-items:center;gap:7px;padding:8px 18px;border-radius:9px;
  background:linear-gradient(135deg,var(--accent),var(--blue));color:#000;
  border:none;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:700;
  cursor:pointer;transition:all .18s;box-shadow:0 3px 12px rgba(0,201,138,.25);flex-shrink:0}
.load-btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,201,138,.35)}
.load-btn:disabled{opacity:.45;cursor:not-allowed;transform:none}
.search-box{display:flex;align-items:center;gap:7px;background:var(--s2);
  border:1.5px solid var(--border);border-radius:9px;padding:7px 11px;
  flex:1;max-width:240px;transition:border-color .15s}
.search-box:focus-within{border-color:rgba(0,201,138,.4)}
.search-box i{color:var(--dim);font-size:11px;flex-shrink:0}
.search-box input{flex:1;background:none;border:none;outline:none;color:var(--text);
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13px}
.search-box input::placeholder{color:var(--dim)}

/* ── LIVE BANNER ── */
.live-banner{margin:12px 16px 0;padding:12px 16px;border-radius:12px;
  background:rgba(255,71,87,.08);border:1.5px solid rgba(255,71,87,.3);
  display:none;align-items:center;gap:12px;flex-shrink:0}
.live-banner.show{display:flex}
.live-dot{width:10px;height:10px;border-radius:50%;background:var(--live);
  box-shadow:0 0 0 3px var(--live-glow);animation:livepulse 1.5s infinite;flex-shrink:0}
@keyframes livepulse{0%,100%{box-shadow:0 0 0 3px var(--live-glow)}50%{box-shadow:0 0 0 6px rgba(255,71,87,.1)}}
.live-info{flex:1;min-width:0}
.live-label{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
  letter-spacing:.1em;text-transform:uppercase;color:var(--danger);margin-bottom:3px}
.live-q{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.live-stop{display:flex;align-items:center;gap:5px;padding:6px 13px;border-radius:8px;
  border:1px solid rgba(255,71,87,.35);background:rgba(255,71,87,.08);color:var(--danger);
  font-size:12px;font-weight:700;cursor:pointer;transition:all .15s;flex-shrink:0}
.live-stop:hover{background:rgba(255,71,87,.16)}
.live-jumpto{display:flex;align-items:center;gap:5px;padding:6px 13px;border-radius:8px;
  border:1px solid var(--border);background:var(--s2);color:var(--sub);
  font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .15s;flex-shrink:0}
.live-jumpto:hover{color:var(--text);border-color:var(--border2)}

/* ── QUESTION SCROLL ── */
.q-scroll{flex:1;overflow-y:auto;padding:12px 16px 40px}
.q-scroll::-webkit-scrollbar{width:5px}
.q-scroll::-webkit-scrollbar-thumb{background:var(--s3);border-radius:3px}

/* State panels */
.state-panel{display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:60px 20px;text-align:center;color:var(--dim);height:100%}
.state-panel .big{font-size:48px;margin-bottom:14px;opacity:.5}
.state-panel p{font-size:14px;line-height:1.6;color:var(--sub)}

/* Subject header */
.subj-header{display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:10px;
  border-bottom:1px solid var(--border)}
.sh-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;
  justify-content:center;font-size:20px;flex-shrink:0}
.sh-title{font-size:17px;font-weight:800}
.sh-meta{font-size:12px;color:var(--sub);margin-top:2px}
.sh-count{margin-left:auto;font-family:'Space Mono',monospace;font-size:11px;
  color:var(--dim);font-weight:700;flex-shrink:0}

/* Question cards */
.q-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:10px}
.q-card{background:var(--s1);border:1.5px solid var(--border);border-radius:13px;
  overflow:hidden;transition:all .2s;display:flex;flex-direction:column}
.q-card:hover{border-color:var(--border2);box-shadow:0 4px 18px rgba(0,0,0,.4)}
.q-card.is-live{border-color:rgba(255,71,87,.45);
  box-shadow:0 0 0 2px rgba(255,71,87,.15),0 4px 18px rgba(0,0,0,.4)}
.q-card-head{padding:10px 13px 8px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:8px;flex-shrink:0}
.q-badge{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
  padding:2px 8px;border-radius:20px;background:var(--s3);color:var(--dim)}
.q-badge.live{background:rgba(255,71,87,.15);border:1px solid rgba(255,71,87,.3);
  color:var(--danger);animation:badgepulse 1.5s infinite}
@keyframes badgepulse{0%,100%{opacity:1}50%{opacity:.6}}
.q-tags{display:flex;gap:5px}
.q-tag{font-size:10px;padding:2px 7px;border-radius:20px;
  background:var(--s3);color:var(--dim);font-family:'Space Mono',monospace}
.q-body{padding:12px 13px;flex:1}
.q-text{font-size:13px;font-weight:600;line-height:1.6;margin-bottom:11px;color:var(--text)}
.q-opts{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:11px}
.q-opt{font-size:11px;padding:5px 8px;border-radius:7px;
  border:1px solid var(--border);background:var(--s2);color:var(--sub);
  line-height:1.4;transition:all .15s}
.q-opt.correct{border-color:rgba(0,201,138,.4);background:rgba(0,201,138,.08);
  color:var(--accent);font-weight:700}
.q-opt.wrong-pick{border-color:rgba(255,71,87,.35);background:rgba(255,71,87,.06);
  color:var(--danger)}
.q-foot{padding:8px 13px 11px;border-top:1px solid var(--border);flex-shrink:0}
.q-foot-row{display:flex;align-items:center;justify-content:space-between;gap:8px}
.ans-info{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;
  color:var(--accent);display:flex;align-items:center;gap:5px}
.foot-btns{display:flex;gap:6px}
.preview-btn{display:flex;align-items:center;gap:4px;padding:6px 12px;border-radius:8px;
  border:1px solid var(--border);background:var(--s2);color:var(--sub);
  font-size:11px;font-weight:600;cursor:pointer;transition:all .15s}
.preview-btn:hover{color:var(--text);border-color:var(--border2)}
.go-live-btn{display:flex;align-items:center;gap:5px;padding:6px 14px;border-radius:8px;
  background:linear-gradient(135deg,var(--danger),#c53030);color:#fff;
  border:none;font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:700;
  cursor:pointer;transition:all .18s;box-shadow:0 3px 10px rgba(255,71,87,.3);white-space:nowrap}
.go-live-btn:hover{transform:scale(1.04);box-shadow:0 5px 16px rgba(255,71,87,.5)}
.go-live-btn:disabled{opacity:.45;cursor:not-allowed;transform:none}
.go-live-btn.already{background:rgba(255,71,87,.15);color:var(--danger);
  border:1px solid rgba(255,71,87,.35);box-shadow:none}
.set-done{background:rgba(0,201,138,.1);color:var(--accent);
  border:1px solid rgba(0,201,138,.3);box-shadow:none;cursor:default}

/* Loading spinner (inline) */
.q-loading{display:none;align-items:center;justify-content:center;padding:60px;flex-direction:column;gap:14px}
.q-loading.show{display:flex}
.spinner{width:40px;height:40px;border-radius:50%;border:3px solid var(--s3);
  border-top-color:var(--accent);animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.spin-msg{font-size:13px;color:var(--sub);font-weight:600;text-align:center}

/* ── PREVIEW MODAL ── */
.modal-ov{display:none;position:fixed;inset:0;z-index:100;
  background:rgba(4,2,14,.85);backdrop-filter:blur(8px);
  align-items:center;justify-content:center;padding:20px}
.modal-ov.show{display:flex}
.modal{width:100%;max-width:600px;background:var(--s1);border:1px solid var(--border2);
  border-radius:16px;overflow:hidden;animation:popIn .25s cubic-bezier(.22,1,.36,1) both}
@keyframes popIn{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:scale(1)}}
.modal-head{padding:14px 18px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:10px}
.modal-title{font-size:14px;font-weight:700;flex:1}
.modal-x{width:28px;height:28px;border-radius:8px;background:var(--s2);
  border:1px solid var(--border);color:var(--sub);cursor:pointer;font-size:11px;
  display:flex;align-items:center;justify-content:center;transition:all .15s}
.modal-x:hover{color:var(--text)}
.modal-body{padding:20px 18px}
.modal-q{font-size:15px;font-weight:700;line-height:1.65;margin-bottom:16px}
.modal-opts{display:flex;flex-direction:column;gap:9px;margin-bottom:16px}
.modal-opt{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:10px;
  border:1.5px solid var(--border);background:var(--s2);font-size:13px;font-weight:500}
.modal-opt.correct{border-color:rgba(0,201,138,.45);background:rgba(0,201,138,.08);color:var(--accent)}
.opt-letter{width:30px;height:30px;border-radius:8px;background:var(--s3);
  border:1px solid var(--border);display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:11px;font-weight:700;flex-shrink:0}
.modal-opt.correct .opt-letter{background:var(--accent);color:#000;border-color:var(--accent)}
.modal-ans{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;
  color:var(--accent);padding:8px 12px;background:rgba(0,201,138,.08);
  border-radius:8px;display:inline-flex;align-items:center;gap:6px}
.modal-foot{padding:12px 18px;border-top:1px solid var(--border);display:flex;
  align-items:center;justify-content:space-between;gap:10px}

/* Toast */
.toast{position:fixed;bottom:24px;right:24px;z-index:200;
  padding:11px 18px;border-radius:10px;font-size:13px;font-weight:600;
  display:none;align-items:center;gap:8px;
  box-shadow:0 4px 20px rgba(0,0,0,.5);animation:toastIn .25s ease both}
@keyframes toastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.toast.show{display:flex}
.toast.ok{background:rgba(0,201,138,.15);border:1px solid rgba(0,201,138,.35);color:var(--accent)}
.toast.err{background:rgba(255,71,87,.12);border:1px solid rgba(255,71,87,.3);color:var(--danger)}
.toast.info{background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);color:var(--blue)}

/* ── SEARCH TOOLBAR (second row) ── */
.search-toolbar{
  padding:10px 16px;border-bottom:1px solid var(--border);
  background:var(--s2);display:flex;align-items:center;gap:8px;
  flex-shrink:0;flex-wrap:wrap}
.search-main{
  display:flex;align-items:center;gap:7px;background:var(--s1);
  border:1.5px solid var(--border);border-radius:10px;padding:8px 13px;
  flex:1;min-width:180px;transition:border-color .15s}
.search-main:focus-within{border-color:rgba(0,201,138,.4)}
.search-main i{color:var(--dim);font-size:13px;flex-shrink:0}
.search-main input{
  flex:1;background:none;border:none;outline:none;color:var(--text);
  font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:500}
.search-main input::placeholder{color:var(--dim)}
.search-clear{
  width:22px;height:22px;border-radius:6px;background:var(--s3);
  border:none;color:var(--sub);cursor:pointer;font-size:11px;
  display:none;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.search-clear.show{display:flex}
.search-clear:hover{color:var(--text)}
.year-select{
  height:38px;padding:0 10px;border-radius:9px;background:var(--s1);
  border:1.5px solid var(--border);color:var(--text);
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;
  cursor:pointer;outline:none;transition:border-color .15s;min-width:110px;flex-shrink:0}
.year-select:focus{border-color:rgba(0,201,138,.4)}
.search-btn{
  display:flex;align-items:center;gap:7px;padding:8px 18px;border-radius:10px;
  background:linear-gradient(135deg,var(--accent),var(--blue));color:#000;
  border:none;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:700;
  cursor:pointer;transition:all .18s;box-shadow:0 3px 12px rgba(0,201,138,.2);flex-shrink:0}
.search-btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,201,138,.3)}
.search-btn:disabled{opacity:.45;cursor:not-allowed;transform:none}

/* ── ACTIVE FILTER CHIPS ── */
.filter-chips{
  padding:8px 16px;border-bottom:1px solid var(--border);
  display:none;align-items:center;gap:7px;flex-shrink:0;flex-wrap:wrap;
  background:var(--s1)}
.filter-chips.show{display:flex}
.fchip-label{font-family:'Space Mono',monospace;font-size:10px;
  font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--dim)}
.fchip{
  display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;
  font-size:11px;font-weight:700;cursor:pointer;transition:all .15s;
  border:1px solid var(--border);background:var(--s2);color:var(--sub)}
.fchip.keyword{background:rgba(0,201,138,.1);border-color:rgba(0,201,138,.3);color:var(--accent)}
.fchip.year{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.3);color:var(--amber)}
.fchip:hover{opacity:.7}
.fchip i{font-size:9px}

/* ── YEAR GROUP SECTIONS ── */
.year-group{margin-bottom:24px}
.year-group-head{
  display:flex;align-items:center;gap:10px;margin-bottom:10px;
  cursor:pointer;user-select:none;padding:6px 0}
.yg-year{
  font-family:'Space Mono',monospace;font-size:13px;font-weight:700;
  color:var(--amber);background:rgba(245,158,11,.1);
  border:1px solid rgba(245,158,11,.25);padding:3px 12px;border-radius:20px;flex-shrink:0}
.yg-count{font-size:11px;color:var(--dim);font-family:'Space Mono',monospace}
.yg-line{flex:1;height:1px;background:var(--border)}
.yg-toggle{color:var(--dim);font-size:11px;transition:transform .2s}
.yg-toggle.open{transform:rotate(90deg)}
.yg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:10px}

/* ── MODE TABS ── */
.mode-tabs{
  display:flex;gap:0;padding:0 16px;border-bottom:1px solid var(--border);
  background:var(--s1);flex-shrink:0}
.mode-tab{
  padding:11px 16px;font-size:12px;font-weight:700;cursor:pointer;
  border-bottom:2.5px solid transparent;color:var(--sub);transition:all .15s;
  font-family:'Space Mono',monospace;letter-spacing:.04em;white-space:nowrap}
.mode-tab:hover{color:var(--text)}
.mode-tab.active{color:var(--accent);border-bottom-color:var(--accent)}

/* ── SEARCH RESULT SUMMARY ── */
.result-summary{
  padding:10px 16px;font-size:12px;color:var(--sub);
  display:none;align-items:center;gap:8px;flex-shrink:0;
  background:var(--s1);border-bottom:1px solid var(--border)}
.result-summary.show{display:flex}
.rs-count{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:var(--text)}
.rs-sorted{font-size:11px;color:var(--dim)}

/* Override existing empty bar */
.err-bar{padding:10px 14px;border-radius:9px;background:rgba(255,71,87,.08);
  border:1px solid rgba(255,71,87,.2);color:var(--danger);font-size:13px;
  margin:10px 16px 0;display:none}
.err-bar.show{display:block}

/* ── SIDEBAR OVERLAY (mobile backdrop) ── */
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(4,2,14,.65);
  backdrop-filter:blur(3px);z-index:49;transition:opacity .25s}
.sb-overlay.show{display:block}

/* ── SIDEBAR CLOSE BUTTON ── */
.sb-close{display:none;width:100%;align-items:center;justify-content:space-between;
  padding:10px 12px 6px;flex-shrink:0;border-bottom:1px solid var(--border)}
.sb-close-title{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;color:var(--sub)}
.sb-close-btn{width:28px;height:28px;border-radius:8px;background:var(--s2);
  border:1px solid var(--border);color:var(--sub);cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:11px;transition:all .15s}
.sb-close-btn:hover{color:var(--text);border-color:var(--border2)}

/* ── HAMBURGER BTN (hidden on desktop) ── */
.menu-btn{display:none;width:32px;height:32px;border-radius:8px;background:var(--s2);
  border:1px solid var(--border);color:var(--sub);cursor:pointer;
  align-items:center;justify-content:center;font-size:13px;
  transition:all .15s;flex-shrink:0}
.menu-btn:hover{color:var(--text);border-color:var(--border2)}

/* ══ RESPONSIVE BREAKPOINTS ══ */

/* ── Tablet (≤900px): shrink sidebar ── */
@media(max-width:900px){
  .sidebar{width:190px}
  .q-grid{grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}
  .tb-right .t-btn span{display:none}
  .tb-right .t-btn{padding:0 9px}
}

/* ── Mobile (≤680px): slide-over sidebar ── */
@media(max-width:680px){
  /* Show hamburger */
  .menu-btn{display:flex}

  /* Sidebar becomes a fixed drawer */
  .sidebar{
    position:fixed;left:0;top:56px;height:calc(100vh - 56px);
    z-index:50;transform:translateX(-100%);
    transition:transform .28s cubic-bezier(.22,1,.36,1);
    box-shadow:4px 0 32px rgba(0,0,0,.6);
    width:240px;
  }
  .sidebar.open{transform:translateX(0)}

  /* Show the close header inside sidebar on mobile */
  .sb-close{display:flex}

  /* Toolbar wraps into two rows */
  .toolbar{padding:8px 12px;row-gap:8px}
  .toolbar-left{width:100%;order:2}
  .load-btn{order:1;width:100%}
  .search-box{max-width:100%;flex:1}
  .count-row{flex-wrap:wrap}

  /* Search toolbar stacks */
  .search-toolbar{padding:8px 12px;row-gap:8px}
  .search-main{width:100%;min-width:0}
  .year-select{flex:1}
  .search-btn{width:100%;justify-content:center}

  /* Mode tabs compact */
  .mode-tabs{padding:0 12px}
  .mode-tab{font-size:11px;padding:10px 10px}

  /* Cards full width */
  .q-grid,.yg-grid{grid-template-columns:1fr}

  /* Topbar: hide long labels */
  .tb-title{display:none}
  .tb-badge{display:none}
  .user-chip span{display:none}
  .user-chip{padding:4px 6px}
  .tb-right .t-btn span{display:none}
  .tb-right .t-btn{width:32px;height:32px;padding:0;justify-content:center}

  /* Live banner stacks */
  .live-banner{flex-wrap:wrap;margin:8px 12px 0;padding:10px 12px;gap:8px}
  .live-info{width:100%;min-width:0}
  .live-jumpto,.live-stop{flex:1;justify-content:center}

  /* Question card footer stacks */
  .q-foot-row{flex-direction:column;align-items:flex-start;gap:9px}
  .foot-btns{width:100%}
  .go-live-btn,.preview-btn{flex:1;justify-content:center}

  /* Modal full-screen on mobile */
  .modal-ov{padding:0;align-items:flex-end}
  .modal{border-radius:16px 16px 0 0;max-width:100%;max-height:92vh}

  /* q-scroll padding */
  .q-scroll{padding:10px 12px 40px}

  /* State panel */
  .state-panel{padding:40px 16px}
  .state-panel .big{font-size:38px}

  /* Filter chips wrap */
  .filter-chips{gap:5px}
}
</style>
</head>
<body>

<!-- Sidebar overlay (mobile backdrop) -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<!-- Preview Modal -->
<div class="modal-ov" id="modalOv" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title" id="modalTitle">Question Preview</div>
      <button class="modal-x" onclick="closeModal()"><i class="fa fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div class="modal-q" id="modalQ"></div>
      <div class="modal-opts" id="modalOpts"></div>
      <div class="modal-ans" id="modalAns"></div>
    </div>
    <div class="modal-foot">
      <div style="font-size:12px;color:var(--sub)" id="modalMeta"></div>
      <button class="go-live-btn" id="modalGoLive" onclick="goLiveFromModal()">
        <i class="fa fa-tower-broadcast" style="font-size:10px"></i> Set This Live
      </button>
    </div>
  </div>
</div>

<!-- Topbar -->
<nav class="topbar">
  <button class="menu-btn" id="menuBtn" onclick="toggleSidebar()" aria-label="Toggle menu">
    <i class="fa fa-bars"></i>
  </button>
  <div class="tb-logo">ES</div>
  <span class="tb-title">ALOC PANEL</span>
  <span class="tb-badge">ADMIN</span>
  <div class="tb-flex"></div>
  <div class="tb-right">
    <button class="theme-toggle" id="themeBtn" onclick="cycleTheme()">
      <i class="fa fa-moon" style="font-size:11px"></i> <span>Dark</span>
    </button>
    <a href="live_brainstorm_control.php" class="t-btn" style="color:var(--danger);border-color:rgba(255,71,87,.3);background:rgba(255,71,87,.08)">
      <i class="fa fa-tower-broadcast" style="font-size:11px"></i> Brainstorm Control
    </a>
    <a href="dashboard.php" class="t-btn">
      <i class="fa fa-house" style="font-size:11px"></i> Dashboard
    </a>
    <div class="user-chip">
      <?php if($dp):?><img src="<?=htmlspecialchars($dp)?>" alt=""><?php else:?>
      <div class="user-av"><?=htmlspecialchars(mb_strtoupper(mb_substr($dn,0,1)))?></div>
      <?php endif;?>
      <span><?=htmlspecialchars($dn)?></span>
    </div>
  </div>
</nav>

<div class="app">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <!-- Close row (visible on mobile only) -->
    <div class="sb-close">
      <span class="sb-close-title">Subjects</span>
      <button class="sb-close-btn" onclick="closeSidebar()" aria-label="Close sidebar">
        <i class="fa fa-xmark"></i>
      </button>
    </div>
    <div class="sb-inner">
      <div class="sb-section">Subjects</div>
      <div id="sbList"></div>
    </div>
    <div id="topicBtnWrap" style="display:none">
      <button class="topic-open-btn" id="topicOpenBtn" onclick="openTopicModal()">
        <i class="fa fa-layer-group" style="font-size:11px"></i>
        <span id="topicBtnLabel">Choose Topic</span>
      </button>
    </div>
    <div class="sb-footer">
      <a href="live_brainstorm_control.php" class="sb-link">
        <i class="fa fa-tower-broadcast" style="width:14px;color:var(--danger)"></i>
        Brainstorm Control
      </a>
      <a href="../questions/live_brainstorm.php" class="sb-link" target="_blank">
        <i class="fa fa-users" style="width:14px"></i>
        Student View
      </a>
      <a href="../exams/practice_test.php" class="sb-link">
        <i class="fa fa-clipboard-check" style="width:14px"></i>
        Practice Tests
      </a>
    </div>
  </aside>

  <!-- Main -->
  <div class="main">

    <!-- Mode tabs -->
    <div class="mode-tabs">
      <div class="mode-tab active" id="tabBrowse" onclick="switchMode('browse')">
        <i class="fa fa-layer-group" style="font-size:10px;margin-right:5px"></i>Browse by Subject
      </div>
      <div class="mode-tab" id="tabSearch" onclick="switchMode('search')">
        <i class="fa fa-magnifying-glass" style="font-size:10px;margin-right:5px"></i>Search &amp; Filter
      </div>
    </div>

    <!-- Browse toolbar (subject + type + count + Load) -->
    <div class="toolbar" id="browseToolbar">
      <div class="toolbar-left">
        <select class="tb-select" id="examTypeSelect">
          <option value="utme">JAMB UTME</option>
          <option value="wassce">WAEC SSCE</option>
          <option value="neco">NECO</option>
        </select>
        <div class="count-row" id="countRow">
          <span class="cnt-btn" data-n="10" onclick="setCount(10,this)">10</span>
          <span class="cnt-btn on" data-n="20" onclick="setCount(20,this)">20</span>
          <span class="cnt-btn" data-n="30" onclick="setCount(30,this)">30</span>
          <span class="cnt-btn" data-n="50" onclick="setCount(50,this)">50</span>
        </div>
        <div class="search-box">
          <i class="fa fa-magnifying-glass"></i>
          <input type="text" id="searchIn" placeholder="Filter loaded questions…" oninput="filterQ(this.value)">
        </div>
      </div>
      <button class="load-btn" id="loadBtn" onclick="loadQuestions()" disabled>
        <i class="fa fa-cloud-arrow-down" style="font-size:12px"></i>
        <span id="loadBtnLabel">Select a subject</span>
      </button>
    </div>

    <!-- Search toolbar (keyword + year + exam type + Search button) -->
    <div class="search-toolbar" id="searchToolbar" style="display:none">
      <div class="search-main">
        <i class="fa fa-magnifying-glass"></i>
        <input type="text" id="kwInput"
          placeholder="Search topic, keyword or concept…  e.g. photosynthesis, Newton, quadratic"
          onkeydown="if(event.key==='Enter')doSearch()"
          oninput="syncClearBtn()">
        <button class="search-clear" id="kwClear" onclick="clearSearch()">
          <i class="fa fa-xmark"></i>
        </button>
      </div>
      <select class="year-select" id="yearSelect">
        <option value="">Any year</option>
        <option value="2024">2024</option>
        <option value="2023">2023</option>
        <option value="2022">2022</option>
        <option value="2021">2021</option>
        <option value="2020">2020</option>
        <option value="2019">2019</option>
        <option value="2018">2018</option>
        <option value="2017">2017</option>
        <option value="2016">2016</option>
        <option value="2015">2015</option>
        <option value="2014">2014</option>
        <option value="2013">2013</option>
        <option value="2012">2012</option>
        <option value="2011">2011</option>
        <option value="2010">2010</option>
      </select>
      <select class="year-select" id="searchExamType" style="min-width:130px">
        <option value="utme">JAMB UTME</option>
        <option value="wassce">WAEC SSCE</option>
        <option value="neco">NECO</option>
      </select>
      <button class="search-btn" id="searchBtn" onclick="doSearch()" disabled>
        <i class="fa fa-satellite-dish" style="font-size:11px"></i>
        Search 50 Questions
      </button>
    </div>

    <!-- Active filter chips -->
    <div class="filter-chips" id="filterChips">
      <span class="fchip-label">Active filters:</span>
      <span class="fchip keyword" id="chipKeyword" style="display:none" onclick="clearKeyword()">
        <i class="fa fa-xmark"></i> <span id="chipKwText"></span>
      </span>
      <span class="fchip year" id="chipYear" style="display:none" onclick="clearYear()">
        <i class="fa fa-xmark"></i> <span id="chipYrText"></span>
      </span>
      <span class="fchip" id="chipSubject" style="display:none"></span>
    </div>

    <!-- Result summary bar -->
    <div class="result-summary" id="resultSummary">
      <span class="rs-count" id="rsSummaryCount"></span>
      <span class="rs-sorted" id="rsSummarySorted"></span>
    </div>

    <!-- Live banner -->
    <div class="live-banner" id="liveBanner">
      <div class="live-dot"></div>
      <div class="live-info">
        <div class="live-label">🔴 Live Now — Brainstorm</div>
        <div class="live-q" id="liveBannerQ">—</div>
      </div>
      <a href="live_brainstorm_control.php" class="live-jumpto">
        <i class="fa fa-arrow-up-right-from-square" style="font-size:10px"></i> Open Control
      </a>
      <button class="live-stop" onclick="stopLive()">
        <i class="fa fa-stop" style="font-size:10px"></i> Stop
      </button>
    </div>

    <!-- Error bar -->
    <div class="err-bar" id="errBar"></div>

    <!-- Question area -->
    <div class="q-scroll" id="qScroll">
      <div class="state-panel" id="stateEmpty">
        <div class="big">📡</div>
        <p id="stateEmptyText">Select a subject from the sidebar,<br>choose an exam type, then click <strong>Load Questions</strong><br>— or switch to <strong>Search &amp; Filter</strong> to find specific topics.</p>
      </div>
      <div class="q-loading" id="qLoading">
        <div class="spinner"></div>
        <div class="spin-msg" id="spinMsg">Fetching questions from ALOC…</div>
      </div>
      <div id="qContent" style="display:none">
        <div class="subj-header" id="qHeader"></div>
        <div class="q-grid" id="qGrid"></div>
      </div>
    </div>
  </div>

</div><!-- /app -->

<!-- Topic Modal -->
<div class="topic-modal-ov" id="topicModalOv" onclick="if(event.target===this)closeTopicModal()">
  <div class="topic-modal">
    <div class="topic-modal-head">
      <div class="topic-modal-icon" id="topicModalIcon">📚</div>
      <div>
        <div class="topic-modal-title" id="topicModalTitle">Choose a Topic</div>
        <div class="topic-modal-sub">Questions will load automatically for selected topic</div>
      </div>
      <button class="topic-modal-x" onclick="closeTopicModal()"><i class="fa fa-xmark"></i></button>
    </div>
    <div class="topic-modal-body" id="topicModalBody"></div>
    <div class="topic-modal-foot">
      <div class="topic-selected-label">
        Selected: <span class="topic-selected-val" id="topicSelectedVal">All Topics</span>
      </div>
      <button class="topic-load-btn" onclick="loadFromTopicModal()">
        <i class="fa fa-cloud-arrow-down" style="font-size:12px"></i>
        Load Questions
      </button>
    </div>
  </div>
</div>

<script>
/* ══ DATA ══ */
const SUBJECTS = <?= $subjects_json ?>;
const TOPICS   = <?= $topics_json ?>;

/* ══ STATE ══ */
let curMode      = 'browse'; // 'browse' | 'search'
let curSubject   = null;
let curTopic     = null;
let curTheme     = localStorage.getItem('aloc_theme') || 'dark';
let fetchCount   = 20;
let loadedQs     = [];
let filteredQs   = [];
let liveQId      = null;
let previewQ     = null;
let lastSearchKw = '';
let lastSearchYr = '';

/* ══ INIT ══ */
buildSidebar();
pollLive();
setInterval(pollLive, 5000);
applyTheme(curTheme);

/* ══ MODE TABS ══ */
function switchMode(mode) {
  curMode = mode;
  document.getElementById('tabBrowse').classList.toggle('active', mode==='browse');
  document.getElementById('tabSearch').classList.toggle('active', mode==='search');
  document.getElementById('browseToolbar').style.display = mode==='browse' ? '' : 'none';
  document.getElementById('searchToolbar').style.display = mode==='search' ? 'flex' : 'none';
  // Reset content
  loadedQs = []; filteredQs = [];
  document.getElementById('qContent').style.display = 'none';
  document.getElementById('stateEmpty').style.display = 'flex';
  document.getElementById('filterChips').classList.remove('show');
  document.getElementById('resultSummary').classList.remove('show');
  document.getElementById('errBar').classList.remove('show');
  document.getElementById('stateEmptyText').innerHTML = mode === 'search'
    ? 'Select a subject from the sidebar, type a keyword or topic,<br>optionally pick a year, then click <strong>Search 50 Questions</strong>.'
    : 'Select a subject from the sidebar,<br>choose an exam type, then click <strong>Load Questions</strong><br>— or switch to <strong>Search &amp; Filter</strong> to find specific topics.';
}

/* ══ SIDEBAR TOGGLE ══ */
function toggleSidebar(){
  const sb  = document.getElementById('sidebar');
  const ov  = document.getElementById('sbOverlay');
  const isOpen = sb.classList.contains('open');
  if(isOpen){ closeSidebar(); } else {
    sb.classList.add('open');
    ov.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sbOverlay').classList.remove('show');
  document.body.style.overflow = '';
}

/* ══ SIDEBAR BUILD ══ */
function buildSidebar(){
  const el = document.getElementById('sbList');
  el.innerHTML = '';
  Object.entries(SUBJECTS).forEach(([name, info]) => {
    const b = document.createElement('div');
    b.className = 'subj-btn'; b.dataset.name = name;
    b.innerHTML = `
      <div class="subj-icon" style="background:${info.color}18;border:1px solid ${info.color}25">
        ${info.emoji||info.icon||'📚'}
      </div>
      <div class="subj-meta"><div class="subj-name">${name}</div></div>`;
    b.onclick = () => selectSubject(name);
    el.appendChild(b);
  });
}

/* ══ THEME ══ */
function applyTheme(theme){
  curTheme = theme;
  document.body.className = '';
  if(theme === 'dark-wood') document.body.classList.add('dark-wood');
  else if(theme === 'light') document.body.classList.add('light-mode');
  localStorage.setItem('aloc_theme', theme);
  const btn = document.getElementById('themeBtn');
  if(btn){
    btn.innerHTML = theme === 'dark' 
      ? '<i class="fa fa-moon" style="font-size:11px"></i> <span>Dark</span>'
      : theme === 'dark-wood'
      ? '<i class="fa fa-fire" style="font-size:11px"></i> <span>Wood</span>'
      : '<i class="fa fa-sun" style="font-size:11px"></i> <span>Light</span>';
  }
}
function cycleTheme(){
  const themes = ['dark','dark-wood','light'];
  const idx = themes.indexOf(curTheme);
  applyTheme(themes[(idx+1) % themes.length]);
}

/* ══ TOPIC MODAL ══ */
function openTopicModal(){
  if(!curSubject){ toast('⚠️ Select a subject first', 'err'); return; }
  const topics = TOPICS[curSubject] || [];
  const body = document.getElementById('topicModalBody');
  const info = SUBJECTS[curSubject];
  document.getElementById('topicModalTitle').textContent = curSubject + ' Topics';
  document.getElementById('topicModalIcon').textContent = info.icon || '📚';
  document.getElementById('topicModalIcon').style.background = (info.color||'#00c98a') + '22';
  body.innerHTML = '';
  // All Topics chip
  const allWrap = document.createElement('div');
  allWrap.className = 'topic-section';
  allWrap.innerHTML = '<div class="topic-section-title">🌐 General</div>';
  const allChips = document.createElement('div');
  allChips.className = 'topic-chips';
  const allChip = document.createElement('span');
  allChip.className = 'topic-chip all-chip' + (curTopic===null?' active':'');
  allChip.textContent = '📚 All Topics';
  allChip.onclick = () => { selectTopic(null); };
  allChips.appendChild(allChip);
  allWrap.appendChild(allChips);
  body.appendChild(allWrap);
  // Topic chips
  const topicWrap = document.createElement('div');
  topicWrap.className = 'topic-section';
  topicWrap.innerHTML = '<div class="topic-section-title">📌 Specific Topics</div>';
  const topicChips = document.createElement('div');
  topicChips.className = 'topic-chips';
  topics.forEach(t => {
    const chip = document.createElement('span');
    chip.className = 'topic-chip' + (curTopic===t?' active':'');
    chip.textContent = t;
    chip.onclick = () => { selectTopic(t); };
    topicChips.appendChild(chip);
  });
  topicWrap.appendChild(topicChips);
  body.appendChild(topicWrap);
  updateTopicSelectedVal();
  document.getElementById('topicModalOv').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeTopicModal(){
  document.getElementById('topicModalOv').classList.remove('show');
  document.body.style.overflow = '';
}

function selectTopic(topic){
  curTopic = topic;
  // Update active chip in modal
  document.querySelectorAll('#topicModalBody .topic-chip').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('#topicModalBody .topic-chip').forEach(c => {
    if(topic === null && c.classList.contains('all-chip')) c.classList.add('active');
    else if(c.textContent === topic) c.classList.add('active');
  });
  updateTopicSelectedVal();
  // Update sidebar button
  updateTopicBtn();
  // If in search mode, auto-fill keyword
  if(curMode === 'search' && topic){
    document.getElementById('kwInput').value = topic;
    syncClearBtn();
  } else if(curMode === 'search'){
    document.getElementById('kwInput').value = '';
    syncClearBtn();
  }
}

function updateTopicSelectedVal(){
  const el = document.getElementById('topicSelectedVal');
  if(el) el.textContent = curTopic || 'All Topics';
}

function updateTopicBtn(){
  const btn = document.getElementById('topicOpenBtn');
  const label = document.getElementById('topicBtnLabel');
  if(!btn) return;
  if(curTopic){
    btn.classList.add('has-topic');
    label.textContent = '📌 ' + curTopic;
  } else {
    btn.classList.remove('has-topic');
    label.textContent = 'Choose Topic';
  }
}

function loadFromTopicModal(){
  closeTopicModal();
  // Auto load questions with selected topic
  if(curMode === 'browse'){
    loadQuestions();
  } else {
    if(curTopic) document.getElementById('kwInput').value = curTopic;
    doSearch();
  }
}

function selectSubject(name){
  curSubject = name;
  curTopic = null;
  document.querySelectorAll('.subj-btn').forEach(b => {
    const on = b.dataset.name === name;
    b.classList.toggle('active', on);
    b.style.borderColor = on ? SUBJECTS[name].color + '44' : 'transparent';
  });
  // Enable both mode buttons
  document.getElementById('loadBtn').disabled = false;
  document.getElementById('loadBtnLabel').textContent = `Load ${name}`;
  document.getElementById('searchBtn').disabled = false;
  // Show topic button
  const wrap = document.getElementById('topicBtnWrap');
  if(wrap) wrap.style.display = 'block';
  updateTopicBtn();
  // Clear previous
  loadedQs = []; filteredQs = [];
  document.getElementById('qContent').style.display = 'none';
  document.getElementById('stateEmpty').style.display = 'flex';
  document.getElementById('filterChips').classList.remove('show');
  document.getElementById('resultSummary').classList.remove('show');
  document.getElementById('searchIn').value = '';
  document.getElementById('errBar').classList.remove('show');
  if(window.innerWidth <= 680) closeSidebar();
}

/* ══ COUNT ══ */
function setCount(n, el){
  fetchCount = n;
  document.querySelectorAll('#countRow .cnt-btn').forEach(b => b.classList.remove('on'));
  el.classList.add('on');
}

/* ══ BROWSE MODE — LOAD QUESTIONS ══ */
async function loadQuestions(){
  if(!curSubject) return;
  const err = document.getElementById('errBar');
  err.classList.remove('show');
  document.getElementById('stateEmpty').style.display = 'none';
  document.getElementById('qContent').style.display = 'none';
  document.getElementById('resultSummary').classList.remove('show');
  showLoading(`Fetching ${fetchCount} questions for ${curSubject}…`);
  document.getElementById('loadBtn').disabled = true;

  const examType = document.getElementById('examTypeSelect').value;
  try{
    const topicKw = curTopic ? `&keyword=${encodeURIComponent(curTopic)}` : '';
    const r = await fetch(`aloc_panel.php?action=fetch&subject=${encodeURIComponent(curSubject)}&exam_type=${examType}&count=${fetchCount}${topicKw}&_=${Date.now()}`);
    const j = await r.json();
    hideLoading();
    document.getElementById('loadBtn').disabled = false;
    if(!j.success){
      err.textContent = j.error || 'Failed to load questions.';
      err.classList.add('show'); return;
    }
    loadedQs   = j.questions;
    filteredQs = [...loadedQs];
    lastSearchKw = ''; lastSearchYr = '';
    document.getElementById('filterChips').classList.remove('show');
    renderQuestions(false); // flat browse render
    toast(`✅ Loaded ${j.count} questions for ${curSubject}`, 'ok');
  }catch(e){
    hideLoading();
    document.getElementById('loadBtn').disabled = false;
    err.textContent = 'Network error: ' + e.message;
    err.classList.add('show');
  }
}

/* ══ SEARCH MODE — FETCH 50 ══ */
async function doSearch(){
  if(!curSubject) { toast('⚠️ Select a subject from the sidebar first', 'err'); return; }
  const kw  = document.getElementById('kwInput').value.trim();
  const yr  = document.getElementById('yearSelect').value;
  const et  = document.getElementById('searchExamType').value;
  const err = document.getElementById('errBar');
  err.classList.remove('show');
  document.getElementById('stateEmpty').style.display = 'none';
  document.getElementById('qContent').style.display = 'none';
  document.getElementById('resultSummary').classList.remove('show');

  let msg = `Searching ${curSubject}`;
  if(kw)  msg += ` for "${kw}"`;
  if(yr)  msg += ` in ${yr}`;
  msg += ' — fetching 50 questions…';
  showLoading(msg);
  document.getElementById('searchBtn').disabled = true;

  try{
    const url = `aloc_panel.php?action=search`
      + `&subject=${encodeURIComponent(curSubject)}`
      + `&exam_type=${encodeURIComponent(et)}`
      + `&keyword=${encodeURIComponent(kw)}`
      + `&year=${encodeURIComponent(yr)}`;
    const r = await fetch(url);
    const j = await r.json();
    hideLoading();
    document.getElementById('searchBtn').disabled = false;
    if(!j.success){
      err.textContent = j.error || 'No results found. Try a different keyword or year.';
      err.classList.add('show'); return;
    }
    loadedQs   = j.questions;
    filteredQs = [...loadedQs];
    lastSearchKw = kw; lastSearchYr = yr;
    // Show active filter chips
    updateFilterChips(kw, yr, curSubject);
    renderQuestions(true); // grouped-by-year render
    // Result summary
    const typeLabels = {utme:'JAMB UTME',wassce:'WAEC SSCE',neco:'NECO'};
    const rs = document.getElementById('resultSummary');
    document.getElementById('rsSummaryCount').textContent = `${j.count} question${j.count!==1?'s':''} found`;
    document.getElementById('rsSummarySorted').textContent =
      `${typeLabels[et]||et} · ${curSubject}${kw?' · keyword: "'+kw+'"':''}${yr?' · '+yr:''} · sorted newest first`;
    rs.classList.add('show');
    toast(`✅ Found ${j.count} questions`, 'ok');
  }catch(e){
    hideLoading();
    document.getElementById('searchBtn').disabled = false;
    err.textContent = 'Network error: ' + e.message;
    err.classList.add('show');
  }
}

/* ══ FILTER CHIPS ══ */
function updateFilterChips(kw, yr, subj) {
  const chips = document.getElementById('filterChips');
  const ckw   = document.getElementById('chipKeyword');
  const cyr   = document.getElementById('chipYear');
  const csub  = document.getElementById('chipSubject');
  const hasAny = kw || yr || subj;
  chips.classList.toggle('show', !!hasAny);
  if(kw){ ckw.style.display='flex'; document.getElementById('chipKwText').textContent='"'+kw+'"'; }
  else ckw.style.display='none';
  if(yr){ cyr.style.display='flex'; document.getElementById('chipYrText').textContent=yr; }
  else cyr.style.display='none';
  if(subj){ csub.style.display='flex'; csub.textContent=subj; }
  else csub.style.display='none';
}
function clearKeyword(){
  document.getElementById('kwInput').value='';
  document.getElementById('kwClear').classList.remove('show');
  lastSearchKw='';
  if(loadedQs.length){ doSearch(); }
}
function clearYear(){
  document.getElementById('yearSelect').value='';
  lastSearchYr='';
  if(loadedQs.length){ doSearch(); }
}
function syncClearBtn(){
  const v = document.getElementById('kwInput').value;
  document.getElementById('kwClear').classList.toggle('show', v.length>0);
}
function clearSearch(){
  document.getElementById('kwInput').value='';
  syncClearBtn();
}

/* ══ RENDER — FLAT (browse) or GROUPED BY YEAR (search) ══ */
function renderQuestions(groupByYear){
  const info = SUBJECTS[curSubject] || {};

  // Header for browse mode
  document.getElementById('qHeader').innerHTML = groupByYear ? '' : `
    <div class="sh-icon" style="background:${info.color||'#888'}18;border:1px solid ${info.color||'#888'}25">
      ${info.emoji||info.icon||'📚'}
    </div>
    <div>
      <div class="sh-title" style="color:${info.color||'var(--accent)'}">${curSubject}</div>
      <div class="sh-meta">${document.getElementById('examTypeSelect').options[document.getElementById('examTypeSelect').selectedIndex]?.text||''} · Arranged by question order</div>
    </div>
    <div class="sh-count">${filteredQs.length} questions</div>`;

  const grid = document.getElementById('qGrid');
  grid.innerHTML = '';

  if(groupByYear){
    // Group by year, each year gets a collapsible section
    const byYear = {};
    filteredQs.forEach((q,i) => {
      const yr = q.year || 'Unknown Year';
      if(!byYear[yr]) byYear[yr] = [];
      byYear[yr].push({q, i});
    });

    // Sort years descending
    const sortedYears = Object.keys(byYear).sort((a,b)=>{
      const na=parseInt(a)||0, nb=parseInt(b)||0;
      return nb - na;
    });

    sortedYears.forEach(yr => {
      const group = document.createElement('div');
      group.className = 'year-group';
      const gid = 'yg-' + yr.replace(/\s+/g,'_');
      group.innerHTML = `
        <div class="year-group-head" onclick="toggleYearGroup('${gid}')">
          <span class="yg-year">${esc(yr)}</span>
          <span class="yg-count">${byYear[yr].length} question${byYear[yr].length!==1?'s':''}</span>
          <div class="yg-line"></div>
          <i class="fa fa-chevron-right yg-toggle open" id="toggle-${gid}"></i>
        </div>
        <div class="yg-grid" id="${gid}"></div>`;
      grid.appendChild(group);
      const ygrid = document.getElementById(gid);
      byYear[yr].forEach(({q, i}) => ygrid.appendChild(buildCard(q, i)));
    });
  } else {
    // Flat grid
    grid.className = 'q-grid';
    filteredQs.forEach((q,i) => grid.appendChild(buildCard(q,i)));
  }

  document.getElementById('qContent').style.display = 'block';
  document.getElementById('stateEmpty').style.display = 'none';
  markLiveCard();
}

function toggleYearGroup(gid){
  const el = document.getElementById(gid);
  const ic = document.getElementById('toggle-'+gid);
  if(!el) return;
  const open = el.style.display !== 'none';
  el.style.display = open ? 'none' : '';
  if(ic) ic.classList.toggle('open', !open);
}

function buildCard(q, idx){
  const info = SUBJECTS[curSubject] || {};
  const isLive = (liveQId !== null && q._db_id && q._db_id === liveQId);
  const div = document.createElement('div');
  div.className = 'q-card' + (isLive ? ' is-live' : '');
  div.id = 'qcard-' + (q.aloc_id || idx);

  const opts = ['A','B','C','D'].map(l => {
    const val = q['option_'+l.toLowerCase()] || '';
    if(!val) return '';
    const isCorrect = l === (q.correct_answer||'').toUpperCase();
    return `<div class="q-opt ${isCorrect?'correct':''}">
      <strong>${l}.</strong> ${esc(val)}${isCorrect?' ✓':''}
    </div>`;
  }).join('');

  const liveBadge = isLive
    ? '<span class="q-badge live">🔴 LIVE</span>'
    : `<span class="q-badge">Q${idx+1}</span>`;

  const liveBtn = isLive
    ? `<button class="go-live-btn already" disabled>🔴 Live Now</button>`
    : `<button class="go-live-btn" onclick="setLive(${idx})">
        <i class="fa fa-tower-broadcast" style="font-size:10px"></i> Set Live
       </button>`;

  div.innerHTML = `
    <div class="q-card-head">
      ${liveBadge}
      <div class="q-tags">
        ${q.year ? `<span class="q-tag" style="background:rgba(245,158,11,.1);color:var(--amber);border:1px solid rgba(245,158,11,.2)">${esc(q.year)}</span>` : ''}
        <span class="q-tag">${esc(q.exam_type||'utme').toUpperCase()}</span>
      </div>
    </div>
    <div class="q-body">
      <div class="q-text">${esc(q.question)}</div>
      <div class="q-opts">${opts}</div>
    </div>
    <div class="q-foot">
      <div class="q-foot-row">
        <span class="ans-info">
          <i class="fa fa-check-circle" style="font-size:11px"></i>
          Answer: Option ${esc(q.correct_answer||'?')}
        </span>
        <div class="foot-btns">
          <button class="preview-btn" onclick="openPreview(${idx})">
            <i class="fa fa-eye" style="font-size:10px"></i> Preview
          </button>
          ${liveBtn}
        </div>
      </div>
    </div>`;
  return div;
}

/* ══ BROWSE FILTER (inline filter on loaded cards) ══ */
function filterQ(v){
  const q = v.toLowerCase().trim();
  filteredQs = q ? loadedQs.filter(x =>
    (x.question||'').toLowerCase().includes(q) ||
    (x.year||'').toLowerCase().includes(q) ||
    (x.correct_answer||'').toLowerCase().includes(q) ||
    (x.option_a||'').toLowerCase().includes(q) ||
    (x.option_b||'').toLowerCase().includes(q) ||
    (x.option_c||'').toLowerCase().includes(q) ||
    (x.option_d||'').toLowerCase().includes(q)
  ) : [...loadedQs];
  if(loadedQs.length) renderQuestions(curMode==='search');
}

/* ══ SET LIVE ══ */
async function setLive(idx){
  const q = filteredQs[idx];
  if(!q) return;
  if(!confirm(`Set this question live for brainstorming?\n\n"${q.question.slice(0,100)}…"\n\nThis will deactivate the current live question.`)) return;
  document.querySelectorAll('.go-live-btn').forEach(b => { b.disabled = true; });
  const fd = new FormData();
  fd.append('action','set_live');
  fd.append('question',   q.question);
  fd.append('option_a',   q.option_a||'');
  fd.append('option_b',   q.option_b||'');
  fd.append('option_c',   q.option_c||'');
  fd.append('option_d',   q.option_d||'');
  fd.append('correct_answer', q.correct_answer||'');
  fd.append('subject',    q.subject||curSubject||'');
  fd.append('year',       q.year||'');
  fd.append('exam_type',  q.exam_type||'');
  try{
    const r = await fetch('aloc_panel.php', {method:'POST',body:fd});
    const j = await r.json();
    if(!j.success){ toast('⚠️ '+( j.error||'Failed'), 'err'); return; }
    filteredQs[idx]._db_id = j.question_id;
    loadedQs.forEach(lq => { if(lq.aloc_id === q.aloc_id) lq._db_id = j.question_id; });
    liveQId = j.question_id;
    toast('🔴 Question is now LIVE for brainstorming!', 'ok', 4000);
    pollLive();
    renderQuestions(curMode==='search');
  }catch(e){
    toast('⚠️ Network error: '+e.message, 'err');
  } finally {
    document.querySelectorAll('.go-live-btn:not(.already)').forEach(b => { b.disabled = false; });
  }
}

/* ══ STOP LIVE ══ */
async function stopLive(){
  if(!confirm('Stop the current live question?')) return;
  try{
    const fd = new FormData(); fd.append('action','stop_live');
    await fetch('aloc_panel.php',{method:'POST',body:fd});
    liveQId = null;
    document.getElementById('liveBanner').classList.remove('show');
    toast('⏹ Brainstorm stopped', 'info');
    if(loadedQs.length) renderQuestions(curMode==='search');
  }catch(e){ toast('⚠️ Error stopping: '+e.message,'err'); }
}

/* ══ POLL LIVE ══ */
async function pollLive(){
  try{
    const r = await fetch('aloc_panel.php?action=get_live');
    const j = await r.json();
    const banner = document.getElementById('liveBanner');
    if(j.live){
      liveQId = j.live.id;
      banner.classList.add('show');
      document.getElementById('liveBannerQ').textContent = j.live.question || '—';
    } else {
      liveQId = null;
      banner.classList.remove('show');
    }
  }catch(e){}
}

function markLiveCard(){
  document.querySelectorAll('.q-card').forEach(c => c.classList.remove('is-live'));
  if(liveQId === null) return;
  loadedQs.forEach((q,i) => {
    if(q._db_id && q._db_id === liveQId){
      const card = document.getElementById('qcard-'+(q.aloc_id||i));
      if(card){
        card.classList.add('is-live');
        const badge = card.querySelector('.q-badge');
        if(badge){ badge.className='q-badge live'; badge.textContent='🔴 LIVE'; }
        const btn = card.querySelector('.go-live-btn');
        if(btn){ btn.textContent='🔴 Live Now'; btn.disabled=true; btn.className='go-live-btn already'; }
      }
    }
  });
}

/* ══ PREVIEW MODAL ══ */
function openPreview(idx){
  previewQ = filteredQs[idx];
  if(!previewQ) return;
  document.getElementById('modalTitle').textContent =
    `Q${idx+1} · ${esc(previewQ.subject||'')} ${previewQ.year?'· '+previewQ.year:''}`;
  document.getElementById('modalQ').textContent = previewQ.question;
  const optsEl = document.getElementById('modalOpts');
  optsEl.innerHTML = '';
  ['A','B','C','D'].forEach(l => {
    const val = previewQ['option_'+l.toLowerCase()]||'';
    if(!val) return;
    const isC = l === (previewQ.correct_answer||'').toUpperCase();
    const d = document.createElement('div');
    d.className = 'modal-opt ' + (isC?'correct':'');
    d.innerHTML = `<div class="opt-letter">${l}</div><div class="opt-txt">${esc(val)}</div>`;
    optsEl.appendChild(d);
  });
  document.getElementById('modalAns').innerHTML =
    `<i class="fa fa-check-circle" style="font-size:11px"></i> Correct Answer: Option ${esc(previewQ.correct_answer||'?')}`;
  document.getElementById('modalMeta').textContent =
    `${esc(previewQ.exam_type||'').toUpperCase()}${previewQ.year?' · '+previewQ.year:''}`;
  const mlb = document.getElementById('modalGoLive');
  const isLive = liveQId !== null && previewQ._db_id && previewQ._db_id === liveQId;
  mlb.textContent = isLive ? '🔴 Already Live' : '🔴 Set This Live';
  mlb.disabled = !!isLive;
  mlb.className = isLive ? 'go-live-btn already' : 'go-live-btn';
  document.getElementById('modalOv').classList.add('show');
  document.body.style.overflow = 'hidden';
  document.getElementById('modalGoLive').dataset.idx = idx;
}
function goLiveFromModal(){
  const idx = parseInt(document.getElementById('modalGoLive').dataset.idx);
  closeModal(); setLive(idx);
}
function closeModal(){
  document.getElementById('modalOv').classList.remove('show');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => {
  if(e.key==='Escape'){ closeModal(); closeSidebar(); closeTopicModal(); }
});

/* ══ LOADING ══ */
function showLoading(msg){
  document.getElementById('qLoading').classList.add('show');
  document.getElementById('spinMsg').textContent = msg || 'Loading…';
}
function hideLoading(){
  document.getElementById('qLoading').classList.remove('show');
}

/* ══ TOAST ══ */
let toastTimer;
function toast(msg, type='ok', dur=2800){
  const el = document.getElementById('toast');
  el.className = `toast show ${type}`;
  el.innerHTML = `<i class="fa ${type==='ok'?'fa-circle-check':type==='err'?'fa-circle-exclamation':'fa-circle-info'}" style="font-size:13px"></i> ${esc(msg)}`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(()=>{ el.className='toast'; }, dur);
}

/* ══ UTIL ══ */
function esc(s){ return s?String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[c])):''; }
</script>
</body>
</html>

