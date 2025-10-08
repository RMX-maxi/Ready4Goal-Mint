<?php
session_start();
include("newconn.php");

$sender_id = $_SESSION['player_id'];
$sender_role = 'player';
$receiver_id = $_POST['receiver_id'];
$receiver_role = $_POST['receiver_role'];
$message = $_POST['message'];

$conn->query("INSERT INTO chat_messages (sender_id, sender_role, receiver_id, receiver_role, message) 
VALUES ('$sender_id', '$sender_role', '$receiver_id', '$receiver_role', '$message')");
?>
