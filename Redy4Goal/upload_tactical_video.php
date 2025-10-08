<?php
session_start();
include("newconn.php"); // Database connection

if (!isset($_SESSION['academy_id'])) {
    die("⚠️ Academy not logged in");
}

$coach_id = $_SESSION['academy_id'];

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['video']) && $_FILES['video']['error'] === 0) {

        $title = $_POST['title'];
        $upload_dir = "uploads/";

        // Make sure folder exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $video_name = basename($_FILES["video"]["name"]);
        $target_file = $upload_dir . time() . "_" . $video_name;

        // Move uploaded file
        if (move_uploaded_file($_FILES["video"]["tmp_name"], $target_file)) {
            // Save to DB
            $sql = "INSERT INTO training_videos (player_id, coach_id, improvement_id, title, file_path, uploaded_at)
                    VALUES (NULL, '$coach_id', NULL, '$title', '$target_file', NOW())";

            if ($conn->query($sql) === TRUE) {
                echo "✅ Video uploaded successfully and shared with all players!";
            } else {
                echo "❌ Database error: " . $conn->error;
            }
        } else {
            echo "⚠️ Error uploading file. Please check folder permissions.";
        }
    } else {
        echo "⚠️ No video selected or upload failed. Error code: " . ($_FILES['video']['error'] ?? 'unknown');
    }
}
?>
