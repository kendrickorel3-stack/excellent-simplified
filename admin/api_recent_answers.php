<?php
// admin/api_recent_answers.php
require_once "../config/db.php";
$question_id = isset($_GET['question_id']) ? (int)$_GET['question_id'] : 0;
if (!$question_id) { echo json_encode([]); exit(); }

// return latest answers for this question, order by answered_at asc (so first answer first)
$res = $conn->query("SELECT a.*, u.username FROM answers a LEFT JOIN users u ON u.id=a.user_id WHERE a.question_id = $question_id ORDER BY a.created_at ASC LIMIT 200");
$out = [];
while($r = $res->fetch_assoc()){
    $out[] = $r;
}
header('Content-Type: application/json');
echo json_encode($out);
