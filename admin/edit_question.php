<?php
require_once "../config/db.php";
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $q = trim($_POST['question']);
    $a = trim($_POST['option_a']); $b = trim($_POST['option_b']); $c = trim($_POST['option_c']); $d = trim($_POST['option_d']);
    $correct = strtoupper(substr(trim($_POST['correct_answer']),0,1));
    $status = in_array($_POST['status'],['active','inactive'])?$_POST['status']:'inactive';
    $stmt = $conn->prepare("UPDATE questions SET question=?,option_a=?,option_b=?,option_c=?,option_d=?,correct_answer=?,status=? WHERE id=?");
    $stmt->bind_param("sssssssi",$q,$a,$b,$c,$d,$correct,$status,$id);
    $stmt->execute(); $stmt->close();
    header("Location: dashboard.php"); exit();
}
$row = $conn->query("SELECT * FROM questions WHERE id = $id")->fetch_assoc();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Edit question</title></head><body>
<h2>Edit Question</h2>
<form method="POST" action="edit_question.php">
<input type="hidden" name="id" value="<?php echo $id ?>">
<label>Question</label><br>
<textarea name="question" rows="3"><?php echo htmlspecialchars($row['question']) ?></textarea><br>
<input name="option_a" value="<?php echo htmlspecialchars($row['option_a']) ?>"><br>
<input name="option_b" value="<?php echo htmlspecialchars($row['option_b']) ?>"><br>
<input name="option_c" value="<?php echo htmlspecialchars($row['option_c']) ?>"><br>
<input name="option_d" value="<?php echo htmlspecialchars($row['option_d']) ?>"><br>
<label>Correct (A/B/C/D)</label><input name="correct_answer" maxlength="1" value="<?php echo htmlspecialchars($row['correct_answer']) ?>"><br>
<label>Status</label>
<select name="status">
  <option value="inactive" <?php if($row['status']=='inactive') echo 'selected' ?>>inactive</option>
  <option value="active" <?php if($row['status']=='active') echo 'selected' ?>>active</option>
</select><br>
<button type="submit">Save</button>
</form>
</body></html>
