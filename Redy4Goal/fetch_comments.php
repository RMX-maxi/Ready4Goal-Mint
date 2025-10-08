<?php
session_start();
include("newconn.php");

if(!isset($_GET['video_id'])) exit;

$video_id = intval($_GET['video_id']);

$player_id = $_SESSION['player_id'];

$sql = "
SELECT c.*, p.name
FROM comments c
JOIN player p ON c.player_id=p.id
WHERE c.video_id=$video_id
ORDER BY c.created_at ASC
";

$result = $conn->query($sql);
$comments = [];
while($row = $result->fetch_assoc()){
    $comments[] = $row;
}

header('Content-Type: application/json');
echo json_encode($comments);
