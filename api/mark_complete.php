<?php
// api/mark_complete.php
require_once __DIR__ . '/../config/db.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$video_id = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
$completed = isset($_POST['completed']) ? (int)$_POST['completed'] : 1; // default 1

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
if (!$video_id) {
    echo json_encode(['success' => false, 'error' => 'Missing video_id']);
    exit;
}

try {
    // Upsert pattern: try update first, if no rows inserted, insert new
    $update = $conn->prepare("UPDATE video_progress SET completed = ?, last_watched = NOW() WHERE user_id = ? AND video_id = ?");
    $update->bind_param('iii', $completed, $user_id, $video_id);
    $update->execute();

    if ($update->affected_rows === 0) {
        // insert new row
        $ins = $conn->prepare("INSERT INTO video_progress (user_id, video_id, completed, last_watched) VALUES (?, ?, ?, NOW())");
        $ins->bind_param('iii', $user_id, $video_id, $completed);
        $ins->execute();
    }

    // Optionally return total completed count for this video
    $countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM video_progress WHERE video_id = ? AND completed = 1");
    $countStmt->bind_param('i', $video_id);
    $countStmt->execute();
    $countRes = $countStmt->get_result();
    $countRow = $countRes->fetch_assoc();
    $completedCount = (int)$countRow['c'];

    echo json_encode(['success' => true, 'completed' => (bool)$completed, 'completed_count' => $completedCount]);
    exit;
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

