<?php
session_start();
include("newconn.php");

if (!isset($_SESSION['coach_id'])) {
    die("Please login first");
}

$coach_id = $_SESSION['coach_id'];
$receiver_id = (int)$_POST['receiver_id'];
$receiver_role = $_POST['receiver_role'];
$message = trim($_POST['message']);

if (empty($message)) {
    die("Message cannot be empty");
}

// Insert message into database
$stmt = $conn->prepare("
    INSERT INTO chat_messages (sender_id, sender_role, receiver_id, receiver_role, message, created_at) 
    VALUES (?, 'coach', ?, ?, ?, NOW())
");
$stmt->bind_param("iiss", $coach_id, $receiver_id, $receiver_role, $message);

if ($stmt->execute()) {
    echo "Message sent successfully";
} else {
    echo "Error sending message: " . $conn->error;
}

$stmt->close();
?>