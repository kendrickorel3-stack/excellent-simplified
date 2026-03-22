<?php
session_start();
require_once "../config/db.php";

$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents("php://input"), true);

$answers = $data['answers'];

$results = [];

foreach($answers as $qid => $user_answer){

    $stmt = $conn->prepare("SELECT correct_answer, explanation, question FROM questions WHERE id=?");
    $stmt->bind_param("i",$qid);
    $stmt->execute();
    $q = $stmt->get_result()->fetch_assoc();

    $correct = $q['correct_answer'];

    $is_correct = ($user_answer == $correct);

    if(!$is_correct){

        $fail = $conn->prepare("
        INSERT INTO failed_questions(user_id,question_id)
        VALUES(?,?)
        ON DUPLICATE KEY UPDATE attempts = attempts+1
        ");

        $fail->bind_param("ii",$user_id,$qid);
        $fail->execute();
    }

    $results[] = [
        "question"=>$q['question'],
        "your_answer"=>$user_answer,
        "correct"=>$correct,
        "explanation"=>$q['explanation'],
        "is_correct"=>$is_correct
    ];
}

echo json_encode([
 "success"=>true,
 "results"=>$results
]);
