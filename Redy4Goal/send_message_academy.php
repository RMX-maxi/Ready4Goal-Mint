<?php
session_start();
include("newconn.php");

if (!isset($_SESSION['academy_id'])) exit;

$sender_id = $_SESSION['academy_id'];
$sender_role = 'academy';
$receiver_id = $_POST['receiver_id'];
$receiver_role = $_POST['receiver_role'];
$message = $conn->real_escape_string($_POST['message']);

$conn->query("INSERT INTO chat_messages (sender_id, sender_role, receiver_id, receiver_role, message)
              VALUES ('$sender_id', '$sender_role', '$receiver_id', '$receiver_role', '$message')");
?>
