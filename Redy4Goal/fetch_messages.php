<?php
session_start();
include("newconn.php");

$user_id = $_SESSION['player_id'];
$user_role = 'player';
$chat_with = $_GET['chat_with'];
$chat_role = $_GET['role'];

$result = $conn->query("
    SELECT * FROM chat_messages 
    WHERE (sender_id=$user_id AND sender_role='$user_role' AND receiver_id=$chat_with AND receiver_role='$chat_role')
       OR (sender_id=$chat_with AND sender_role='$chat_role' AND receiver_id=$user_id AND receiver_role='$user_role')
    ORDER BY created_at ASC
");

while($msg = $result->fetch_assoc()) {
    $isSender = ($msg['sender_id'] == $user_id && $msg['sender_role'] == $user_role);
    $style = $isSender ? "background:#0099cc;color:white;align-self:flex-end;border-radius:10px 10px 0 10px;" :
                         "background:#eee;color:black;align-self:flex-start;border-radius:10px 10px 10px 0;";
    echo "<div style='margin:8px; padding:10px; display:inline-block; $style'>" . htmlspecialchars($msg['message']) . "</div><br>";
}
?>
