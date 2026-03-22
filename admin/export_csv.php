<?php

require_once("../config/db.php");

/* CSV HEADERS */

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="brainstorm_results.csv"');

$output = fopen("php://output", "w");

/* COLUMN HEADERS */

fputcsv($output, [
    "Question ID",
    "Question",
    "Student Email",
    "Student Answer",
    "Correct Answer",
    "Is Correct",
    "Submitted Time"
]);

/* QUERY */

$sql = "
SELECT 
questions.id,
questions.question,
answers.student_email,
answers.answer,
questions.correct_answer,
answers.is_correct,
answers.created_at
FROM answers
JOIN questions ON answers.question_id = questions.id
ORDER BY answers.created_at DESC
";

$result = $conn->query($sql);

/* WRITE ROWS */

while($row = $result->fetch_assoc()){

    fputcsv($output, [
        $row['id'],
        $row['question'],
        $row['student_email'],
        $row['answer'],
        $row['correct_answer'],
        $row['is_correct'],
        $row['created_at']
    ]);

}

fclose($output);

exit;
?>
