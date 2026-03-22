<?php
require_once "../config/db.php";

$videos = $conn->query("SELECT * FROM videos ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>

<title>Admin - Videos</title>

<style>

body{
font-family:Arial;
background:#f4f6f9;
padding:30px;
}

.container{
max-width:900px;
margin:auto;
}

form{
background:white;
padding:20px;
border-radius:10px;
margin-bottom:30px;
box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

input,select{
width:100%;
padding:10px;
margin-top:10px;
margin-bottom:15px;
}

button{
background:#007bff;
color:white;
border:none;
padding:10px 15px;
cursor:pointer;
border-radius:6px;
}

.video{
background:white;
padding:15px;
border-radius:8px;
margin-bottom:15px;
box-shadow:0 2px 6px rgba(0,0,0,0.1);
}

</style>

</head>

<body>

<div class="container">

<h2>Publish Lesson Video</h2>

<form method="POST" action="save_video.php">

<input type="text" name="title" placeholder="Video Title" required>

<input type="text" name="youtube_link" placeholder="YouTube Link" required>

<select name="playlist">

<option value="Biology">Biology</option>
<option value="Mathematics">Mathematics</option>
<option value="Physics">Physics</option>
<option value="Chemistry">Chemistry</option>

</select>

<button type="submit">Publish Video</button>

</form>

<h3>Published Videos</h3>

<?php while($row = $videos->fetch_assoc()) { ?>

<div class="video">

<b><?php echo $row['title']; ?></b>

<br>

Playlist: <?php echo $row['playlist']; ?>

</div>

<?php } ?>

</div>

</body>
</html>
