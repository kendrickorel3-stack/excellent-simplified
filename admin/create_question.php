<?php
// admin/create_question.php
// Create a new question (supports subject_id + explanation)
// Save as: admin/create_question.php

require_once __DIR__ . "/../config/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

// Collect + sanitize inputs
$question    = trim($_POST['question'] ?? '');
$option_a    = trim($_POST['option_a'] ?? '');
$option_b    = trim($_POST['option_b'] ?? '');
$option_c    = trim($_POST['option_c'] ?? '');
$option_d    = trim($_POST['option_d'] ?? '');
$correct     = strtoupper(trim($_POST['correct_answer'] ?? ''));
$status      = trim($_POST['status'] ?? 'inactive');
$subject_id  = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : null;
$explanation = trim($_POST['explanation'] ?? '');

// Basic validation
if ($question === '' || $option_a === '' || $option_b === '' || $option_c === '' || $option_d === '' || $correct === '') {
    // missing required field
    http_response_code(400);
    echo "Missing required fields.";
    exit;
}
if (!in_array($correct, ['A','B','C','D'])) {
    http_response_code(400);
    echo "Correct answer must be one of: A, B, C, D.";
    exit;
}

// Defensive: ensure default for subject_id is NULL when not provided
if ($subject_id === 0) $subject_id = null;

// Prepare insert (supports explanation and subject_id)
$sql = "
INSERT INTO questions
(`question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `status`, `subject_id`, `explanation`, `created_at`)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    // debug-friendly error (you can remove in production)
    http_response_code(500);
    echo "DB prepare failed: " . $conn->error;
    exit;
}

/*
 bind_param types:
  s x 7 strings, i or null for subject_id, s for explanation
 For bind_param you must pass an int for 'i' even for NULL -> we will coerce to 0 and let DB accept NULL via passing null as PHP null if supported.
 To keep things simple and portable we will bind subject_id as integer or null using a small helper.
*/

$subject_param = $subject_id !== null ? $subject_id : 0;

// Use "sssssssis" types; explanation is last string
$stmt->bind_param(
    "sssssssis",
    $question,
    $option_a,
    $option_b,
    $option_c,
    $option_d,
    $correct,
    $status,
    $subject_param,
    $explanation
);

$ok = $stmt->execute();
if ($ok) {
    // success -> back to admin dashboard
    header("Location: dashboard.php?msg=question_created");
    exit;
} else {
    http_response_code(500);
    echo "Error creating question: " . htmlspecialchars($stmt->error);
    exit;
}
