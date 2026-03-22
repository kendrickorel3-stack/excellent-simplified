<?php
// admin/delete_subject.php
require_once "../config/db.php";
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? $_POST['id'] ?? null;
if (!$id) { echo json_encode(['success'=>false,'error'=>'missing id']); exit(); }

/* before deleting you might want to reassign or delete videos with subject — here we set subject_id NULL */
$stmt = $conn->prepare("UPDATE videos SET subject_id = NULL WHERE subject_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
$stmt->bind_param("i", $id);
$ok = $stmt->execute();
$stmt->close();
echo json_encode(['success'=>$ok]);
