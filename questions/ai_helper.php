<?php
// questions/ai_helper.php
require_once __DIR__.'/../config/db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$prompt = trim($_POST['prompt'] ?? '');
if (!$prompt) { echo json_encode(['success'=>false,'error'=>'No prompt']); exit; }

// STUB: try to find a short explanation from question table if exact match, otherwise return canned reply.
// (Replace this section with a call to your LLM provider using an API key.)
$stmt = $conn->prepare("SELECT id, question FROM questions WHERE MATCH(question) AGAINST(? IN NATURAL LANGUAGE MODE) LIMIT 1");
if ($stmt) {
  $stmt->bind_param('s', $prompt);
  $stmt->execute();
  $r = $stmt->get_result();
  if ($r && $r->num_rows) {
    $row = $r->fetch_assoc();
    echo json_encode(['success'=>true,'explanation'=>"Short explanation for related question: " . substr($row['question'],0,200)]);
    exit;
  }
}

echo json_encode(['success'=>true,'explanation'=>"AI helper (stub): try asking a focused question like 'Explain voltaic cell in simple steps'."]);
