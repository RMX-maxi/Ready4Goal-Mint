<?php
session_start();
include("newconn.php");

if(!isset($_SESSION['player_id'])) {
    header("Location: player_login.php");
    exit;
}

$schedule_id = $_POST['schedule_id'];
$player_id = $_SESSION['player_id'];

if(isset($_FILES['video']) && $_FILES['video']['error'] == 0) {
    $filename = time().'_'.basename($_FILES['video']['name']);
    $target = "uploads/videos/".$filename;

    if(move_uploaded_file($_FILES['video']['tmp_name'], $target)) {
        // Insert into videos table
        $stmt = $conn->prepare("INSERT INTO videos (title, improvement_id, coach_id, file_path, uploaded_at) 
                                SELECT i.improvement_name, vs.improvement_id, c.id, ?, NOW()
                                FROM video_schedule vs
                                JOIN player p ON vs.player_id=p.id
                                JOIN coach c ON p.academy_id=c.academy_id
                                WHERE vs.id=? AND vs.player_id=?");
        $stmt->bind_param("sii", $target, $schedule_id, $player_id);
        $stmt->execute();

        // Update schedule
        $video_id = $conn->insert_id;
        $conn->query("UPDATE video_schedule SET status='uploaded', video_id=$video_id WHERE id=$schedule_id");

        header("Location: player_dashboard.php?success=Video uploaded successfully");
    } else {
        echo "Failed to upload video.";
    }
} else {
    echo "No video selected.";
}
?>
