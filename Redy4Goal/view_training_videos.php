<?php
session_start();
include("newconn.php");

if(!isset($_SESSION['player_id'])) {
    header("Location: player_login.php?error=Please login first");
    exit;
}

$player_id = $_SESSION['player_id'];
$result = $conn->query("SELECT * FROM player WHERE id = $player_id");
$player = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Player Training Videos</title>
    <style>
        body { font-family: Arial; margin:0; padding:0; background:#f5f5f5; }
        .container { width:90%; margin:auto; padding:20px; }
        .video-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:15px; }
        .video-card { background:#fff; padding:10px; border-radius:8px; cursor:pointer; text-align:center; }
        .video-card video { width:100%; height:120px; border-radius:6px; object-fit:cover; }
        .video-card h4 { margin:5px 0 0; font-size:14px; }

        .video-player-section { display:none; flex-wrap:wrap; gap:20px; margin-top:20px; }
        .video-player { flex:2; }
        .video-player video { width:100%; height:350px; border-radius:8px; }
        .comment-section { flex:1; background:#fff; padding:10px; border-radius:8px; max-height:360px; overflow-y:auto; }
        .comment-section h4 { margin-top:0; }
        .comment-section textarea { width:100%; height:60px; margin-top:5px; }
        .comment-section button { margin-top:5px; padding:5px 10px; background:#0099cc; color:#fff; border:none; border-radius:4px; cursor:pointer; }
        .back-btn { margin-top:10px; padding:5px 10px; background:#555; color:#fff; border:none; border-radius:4px; cursor:pointer; }
        @media(max-width:1200px){ .video-grid { grid-template-columns:repeat(3,1fr); } }
        @media(max-width:800px){ .video-grid { grid-template-columns:repeat(2,1fr); } }
        @media(max-width:500px){ .video-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="container">
    <h2>Welcome <?php echo $player['name']; ?>! Training Videos</h2>

    <!-- Grid of Videos -->
    <div id="video-grid" class="video-grid">
        <?php
        $videos = $conn->query("
            SELECT v.*, i.improvement_name 
            FROM videos v 
            JOIN improvement_types i ON v.improvement_id=i.id
            WHERE v.improvement_id = ".$player['improvement_id']."
            ORDER BY v.uploaded_at DESC
        ");

        if($videos->num_rows > 0){
            while($v = $videos->fetch_assoc()){
                echo "
                <div class='video-card' onclick='openVideo({$v['id']}, \"{$v['title']}\", \"{$v['file_path']}\", \"{$v['improvement_name']}\")'>
                    <video><source src='{$v['file_path']}' type='video/mp4'></video>
                    <h4>{$v['title']}</h4>
                </div>
                ";
            }
        } else {
            echo "<p>No training videos available.</p>";
        }
        ?>
    </div>

    <!-- Video Player + Comments -->
    <div id="video-player-section" class="video-player-section">
        <div class="video-player">
            <video id="selected-video" controls>
                <source id="video-source" src="" type="video/mp4">
                Your browser does not support HTML video.
            </video>
            <h3 id="video-title"></h3>
            <small id="video-type"></small>
            <br>
            <button class="back-btn" onclick="backToGrid()">Back to Videos</button>
        </div>
        <div class="comment-section">
            <h4>Comments</h4>
            <div id="comments" style="max-height:250px; overflow-y:auto;"></div>
            <textarea id="comment_text" placeholder="Write a comment..."></textarea>
            <button onclick="addComment()">Post Comment</button>
        </div>
    </div>
</div>

<script>
let currentVideoId = null;

function openVideo(id, title, path, improvement) {
    currentVideoId = id;
    document.getElementById('video-grid').style.display = 'none';
    document.getElementById('video-player-section').style.display = 'flex';

    document.getElementById('video-source').src = path;
    document.getElementById('selected-video').load();
    document.getElementById('video-title').innerText = title;
    document.getElementById('video-type').innerText = improvement;

    loadComments();
}

function backToGrid(){
    document.getElementById('video-player-section').style.display = 'none';
    document.getElementById('video-grid').style.display = 'grid';
}

function loadComments(){
    fetch('fetch_comments.php?video_id='+currentVideoId)
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById('comments');
        container.innerHTML = '';
        data.forEach(c=>{
            container.innerHTML += `
                <div style="border-bottom:1px solid #ccc; margin-bottom:5px; padding-bottom:3px;">
                    <b>${c.name}</b><br>
                    <span>${c.comment_text}</span><br>
                    <small style="color:#666">${c.created_at}</small>
                </div>
            `;
        });
    });
}

function addComment(){
    const text = document.getElementById('comment_text').value.trim();
    if(text==='') return alert('Write a comment');
    
    fetch('add_player_comment.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`video_id=${currentVideoId}&comment_text=${encodeURIComponent(text)}`
})

    .then(res=>res.text())
    .then(data=>{
        if(data==='success'){
            document.getElementById('comment_text').value='';
            loadComments();
        } else {
            alert('Error posting comment');
        }
    });
}
</script>
</body>
</html>
