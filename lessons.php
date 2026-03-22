<?php
session_start();
require_once "../config/db.php";

$result = $conn->query("SELECT * FROM videos ORDER BY playlist");

?>

<!DOCTYPE html>
<html>
<head>

<title>Lessons</title>

<style>

body{
font-family:Arial;
background:#f4f6f9;
margin:0;
padding:20px;
}

.topbar{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:30px;
}

.back{
text-decoration:none;
background:#007bff;
color:white;
padding:10px 15px;
border-radius:6px;
}

.video-grid{
display:grid;
grid-template-columns:repeat(auto-fill,minmax(250px,1fr));
gap:20px;
}

.card{
background:white;
border-radius:10px;
box-shadow:0 2px 8px rgba(0,0,0,0.1);
overflow:hidden;
}

.thumbnail{
width:100%;
height:150px;
background:#000;
}

.card-body{
padding:15px;
}

.title{
font-weight:bold;
margin-bottom:10px;
}

.playlist{
color:gray;
font-size:14px;
margin-bottom:10px;
}

.watch-btn{
display:inline-block;
background:#28a745;
color:white;
padding:8px 12px;
text-decoration:none;
border-radius:5px;
}

</style>

</head>

<body>

<div class="topbar">

<h2>Lessons</h2>

<a href="dashboard.php" class="back">⬅ Return Dashboard</a>

</div>

<div class="video-grid">

<?php while($row = $result->fetch_assoc()) {

$link = $row['youtube_link'];

$video_id = explode("v=",$link)[1];

$thumbnail = "https://img.youtube.com/vi/".$video_id."/0.jpg";

?>

<div class="card">

<img src="<?php echo $thumbnail; ?>" class="thumbnail">

<div class="card-body">

<div class="title">
<?php echo $row['title']; ?>
</div>

<div class="playlist">
<?php echo $row['playlist']; ?>
</div>

<a class="watch-btn"
href="watch_video.php?id=<?php echo $row['id']; ?>">
Watch Lesson
</a>

</div>

</div>

<?php } ?>

</div>

</body>
</html>
