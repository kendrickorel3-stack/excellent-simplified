<?php
// admin/save_video.php
require_once "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
$title = trim($_POST['title'] ?? '');
$link = trim($_POST['youtube_link'] ?? '');

if (!$title || !$link || !$subject_id) {
    header("Location: dashboard.php");
    exit();
}

$stmt = $conn->prepare("INSERT INTO videos (subject_id, title, youtube_link) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $subject_id, $title, $link);
$stmt->execute();
$stmt->close();

header("Location: dashboard.php");
exit();
