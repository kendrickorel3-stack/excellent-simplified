<?php
// admin/activate_question.php
require_once "../config/db.php";
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$action = $input['action'] ?? null;
if (!$id || !in_array($action,['activate','deactivate'])) {
    echo json_encode(['success'=>false,'error'=>'invalid']); exit();
}
$status = $action === 'activate' ? 'active' : 'inactive';
$stmt = $conn->prepare("UPDATE questions SET status = ? WHERE id = ?");
$stmt->bind_param("si",$status,$id);
$ok = $stmt->execute();
$stmt->close();
echo json_encode(['success'=>$ok,'status'=>$status]);
