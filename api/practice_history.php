<?php
// api/practice_history.php
require_once __DIR__ . '/../config/db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) { echo json_encode(['success'=>false,'error'=>'Not authenticated']); exit; }

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : null;

// aggregate
if ($exam_id) {
  $stmt = $conn->prepare("SELECT COUNT(*) AS total_questions, SUM(is_correct) AS correct_count FROM practice_attempts WHERE user_id = ? AND exam_id = ?");
  $stmt->bind_param('ii', $user_id, $exam_id);
} else {
  // last session (all attempts without exam_id grouping) — adjust as needed
  $stmt = $conn->prepare("SELECT COUNT(*) AS total_questions, SUM(is_correct) AS correct_count FROM practice_attempts WHERE user_id = ?");
  $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$total = (int)$res['total_questions'];
$correct = (int)$res['correct_count'];
$scorePercent = $total>0 ? round(($correct/$total)*100,2) : 0.0;

// optionally save to practice_results if exam_id present
if ($exam_id && $total>0) {
  $ins = $conn->prepare("INSERT INTO practice_results (user_id, exam_id, total_questions, correct_count, score, duration_seconds) VALUES (?, ?, ?, ?, ?, 0)");
  $ins->bind_param('iiids', $user_id, $exam_id, $total, $correct, $scorePercent);
  $ins->execute();
}

echo json_encode(['success'=>true,'total_questions'=>$total,'correct_count'=>$correct,'score'=>$scorePercent]);
