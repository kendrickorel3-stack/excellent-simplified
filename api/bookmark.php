<?php
// api/bookmark.php
require_once __DIR__ . '/../config/db.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$video_id = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
if (!$video_id) {
    echo json_encode(['success' => false, 'error' => 'Missing video_id']);
    exit;
}

try {
    // Check if bookmark exists
    $check = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND video_id = ? LIMIT 1");
    $check->bind_param('ii', $user_id, $video_id);
    $check->execute();
    $res = $check->get_result();

    if ($res && $res->num_rows > 0) {
        // remove bookmark
        $row = $res->fetch_assoc();
        $del = $conn->prepare("DELETE FROM bookmarks WHERE id = ?");
        $del->bind_param('i', $row['id']);
        $del->execute();
        echo json_encode(['success' => true, 'bookmarked' => false]);
        exit;
    } else {
        // add bookmark
        $ins = $conn->prepare("INSERT INTO bookmarks (user_id, video_id, created_at) VALUES (?, ?, NOW())");
        $ins->bind_param('ii', $user_id, $video_id);
        $ins->execute();
        echo json_encode(['success' => true, 'bookmarked' => true]);
        exit;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
