<?php
session_start();
include("newconn.php");

if (!isset($_SESSION['coach_id'])) {
    header("Location: coach_login.php");
    exit;
}

$player_id = $_POST['player_id'];
$improvement_id = $_POST['improvement_id'];
$scheduled_at = $_POST['scheduled_at'];

$stmt = $conn->prepare("INSERT INTO video_schedule (player_id, improvement_id, scheduled_at) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $player_id, $improvement_id, $scheduled_at);

if($stmt->execute()) {
    header("Location: coach_dashboard.php?success=Video upload scheduled successfully");
} else {
    echo "Error: " . $stmt->error;
}
