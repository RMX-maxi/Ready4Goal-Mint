<?php
session_start();
include("newconn.php");

if (!isset($_SESSION['coach_id'])) {
    exit("Access denied");
}

if (!isset($_GET['video_id'])) {
    exit("Invalid request");
}

$video_id = (int)$_GET['video_id'];

// Fetch video details
$video_res = $conn->query("
    SELECT v.*, i.improvement_name 
    FROM videos v
    JOIN improvement_types i ON v.improvement_id = i.id
    WHERE v.id = $video_id
");
if ($video_res->num_rows == 0) {
    exit("Video not found");
}
$video = $video_res->fetch_assoc();
?>

<div id="modal-<?php echo $video['id']; ?>" class="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal(<?php echo $video['id']; ?>)">&times;</button>
        
        <div class="modal-left">
            <video controls>
                <source src="<?php echo $video['file_path']; ?>" type="video/mp4">
            </video>
            <h3><?php echo $video['title']; ?></h3>
            <p>Improvement: <?php echo $video['improvement_name']; ?></p>
            <p>Uploaded: <?php echo $video['uploaded_at']; ?></p>
        </div>

        <div class="modal-right">
            <h4>Comments</h4>
            
            <form class="comment-form" method="POST" action="add_comment.php">
                <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                <textarea name="comment_text" placeholder="Add a comment..." required></textarea>
                <button type="submit">Post</button>
            </form>

            <?php
            // Fetch comments for this video
            $comments_res = $conn->query("
                SELECT c.comment_text, c.created_at, coach.name 
                FROM comments c
                JOIN coach ON c.coach_id = coach.id
                WHERE c.video_id = {$video['id']}
                ORDER BY c.created_at DESC
            ");
            if ($comments_res->num_rows > 0) {
                while ($c = $comments_res->fetch_assoc()) {
                    echo "<div class='comment'>
                            <strong>{$c['name']}</strong>
                            <small>{$c['created_at']}</small>
                            <div>{$c['comment_text']}</div>
                          </div>";
                }
            } else {
                echo "<p>No comments yet.</p>";
            }
            ?>
        </div>
    </div>
</div>
