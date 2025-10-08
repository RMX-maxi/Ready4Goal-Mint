<?php
session_start();
include("newconn.php");

if(!isset($_SESSION['player_id'])) exit;
if(!isset($_POST['video_id'], $_POST['comment_text'])) exit;

$video_id = intval($_POST['video_id']);
$player_id = $_SESSION['player_id'];
$comment_text = $conn->real_escape_string($_POST['comment_text']);

$sql = "INSERT INTO comments (video_id, player_id, comment_text, created_at) 
        VALUES ($video_id, $player_id, '$comment_text', NOW())";

if($conn->query($sql)){
    echo "success";
}else{
    echo "error";
}
