<?php
session_start();
include("newconn.php");

$player_id = $_SESSION['player_id'];
$player_name = $_SESSION['player_name'];
$video_id = intval($_POST['video_id']);
$comment = trim($_POST['comment']);

if ($comment != "") {
    $stmt = $conn->prepare("INSERT INTO comments (video_id, player_id, player_name, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiss", $video_id, $player_id, $player_name, $comment);
    $stmt->execute();
    echo "success";
}
?>
