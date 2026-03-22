<?php
// admin/create_subject.php
require_once "../config/db.php";
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['subject_name'])) {
    header("Location: dashboard.php"); exit();
}
$name = trim($_POST['subject_name']);
$stmt = $conn->prepare("INSERT INTO subjects (name) VALUES (?)");
$stmt->bind_param("s",$name);
$stmt->execute(); $stmt->close();
header("Location: dashboard.php"); exit();

