<?php
include("newconn.php");
$video_id = intval($_GET['video_id']);

$result = $conn->query("SELECT * FROM comments WHERE video_id = $video_id ORDER BY created_at DESC");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div class='comment'><b>Player:</b> " . htmlspecialchars($row['player_name']) . "<br>" .
             htmlspecialchars($row['comment']) . "<br><small>" . $row['created_at'] . "</small></div>";
    }
} else {
    echo "<p>No comments yet.</p>";
}
?>
