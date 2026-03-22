<?php
require_once("../config/db.php");

$user_id = 1; // later this will come from session

$query = "
SELECT q.id, q.question, q.option_a, q.option_b, q.option_c, q.option_d,
q.correct_answer, q.explanation
FROM answers a
JOIN questions q ON a.question_id = q.id
WHERE a.user_id = ? AND a.is_correct = 0
GROUP BY q.id
ORDER BY a.id DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i",$user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>

<title>Review Wrong Questions</title>

<style>

body{
font-family:Arial;
background:#f4f4f4;
padding:20px;
}

.card{
background:white;
padding:20px;
margin-bottom:15px;
border-radius:10px;
box-shadow:0 0 10px rgba(0,0,0,0.1);
}

.correct{
color:green;
font-weight:bold;
}

</style>

</head>
<body>

<h2>Review Wrong Questions</h2>

<?php while($row=$result->fetch_assoc()){ ?>

<div class="card">

<h3><?php echo $row['question']; ?></h3>

<p>A. <?php echo $row['option_a']; ?></p>
<p>B. <?php echo $row['option_b']; ?></p>
<p>C. <?php echo $row['option_c']; ?></p>
<p>D. <?php echo $row['option_d']; ?></p>

<p class="correct">Correct Answer: <?php echo $row['correct_answer']; ?></p>

<p><b>Explanation:</b> <?php echo $row['explanation']; ?></p>

<a href="../exams/practice_test.php?question=<?php echo $row['id']; ?>">Retry Question</a>

</div>

<?php } ?>

</body>
</html>
