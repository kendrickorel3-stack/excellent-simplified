<?php
// admin/edit_video.php
require_once "../config/db.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $subject_id = (int)$_POST['subject_id'];
    $title = trim($_POST['title']);
    $link = trim($_POST['youtube_link']);
    $stmt = $conn->prepare("UPDATE videos SET subject_id=?, title=?, youtube_link=? WHERE id=?");
    $stmt->bind_param("issi",$subject_id,$title,$link,$id);
    $stmt->execute(); $stmt->close();
    header("Location: dashboard.php"); exit();
}

$row = $conn->query("SELECT * FROM videos WHERE id = $id")->fetch_assoc();
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name");
?>
<!doctype html><html><head><meta charset="utf-8"><title>Edit video</title></head><body>
<h2>Edit Video</h2>
<form method="POST" action="edit_video.php">
<input type="hidden" name="id" value="<?php echo $id ?>">
<label>Subject</label>
<select name="subject_id">
  <?php while($s = $subjects->fetch_assoc()): ?>
    <option value="<?php echo $s['id'] ?>" <?php if($s['id']==$row['subject_id']) echo 'selected' ?>><?php echo htmlspecialchars($s['name']) ?></option>
  <?php endwhile; ?>
</select><br>
<label>Title</label>
<input name="title" value="<?php echo htmlspecialchars($row['title']) ?>"><br>
<label>YouTube Link</label>
<input name="youtube_link" value="<?php echo htmlspecialchars($row['youtube_link']) ?>"><br>
<button type="submit">Save</button>
</form>
</body></html>
