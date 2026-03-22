<?php
// admin/delete_question.php
require_once "../config/db.php";
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? $_POST['id'] ?? null;
if (!$id) { echo json_encode(['success'=>false,'error'=>'missing id']); exit(); }
$stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
$stmt->bind_param("i", $id);
$ok = $stmt->execute();
$stmt->close();
echo json_encode(['success'=>$ok]);
