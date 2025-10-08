<?php
session_start();
include("newconn.php");

if (!isset($_SESSION['coach_id'])) {
    header("Location: coach_login.php?error=Please login first");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $coach_id = $_SESSION['coach_id'];
    $video_id = $_POST['video_id'];
    $comment_text = trim($_POST['comment_text']);

    if (!empty($comment_text)) {
        $stmt = $conn->prepare("INSERT INTO comments (video_id, coach_id, comment_text) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $video_id, $coach_id, $comment_text);
        $stmt->execute();
    }
}

header("Location: coach_dashboard.php#view-videos");
exit;
?>
