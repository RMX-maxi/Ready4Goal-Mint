<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ready3goal");

// Sample login info for testing
$_SESSION['user_id'] = 1; 
$_SESSION['role'] = 'player'; 

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all users from every table
$users = [];
$result = $conn->query("SELECT id, name, 'player' AS role FROM player");
while ($row = $result->fetch_assoc()) $users[] = $row;
$result = $conn->query("SELECT id, name, 'coach' AS role FROM coach");
while ($row = $result->fetch_assoc()) $users[] = $row;
$result = $conn->query("SELECT ser_id AS id, academyname AS name, 'academy' AS role FROM academy");
while ($row = $result->fetch_assoc()) $users[] = $row;
$result = $conn->query("SELECT id, email AS name, 'admin' AS role FROM admin");
while ($row = $result->fetch_assoc()) $users[] = $row;
?>

<!DOCTYPE html>
<html>
<head>
  <title>Chat System</title>
  <style>
    body { font-family: Arial, sans-serif; display: flex; height: 100vh; margin: 0; }
    .sidebar { width: 250px; background: #333; color: white; padding: 10px; overflow-y: auto; }
    .chat-area { flex: 1; display: flex; flex-direction: column; }
    .chat-box { flex: 1; padding: 10px; overflow-y: auto; background: #f2f2f2; }
    .msg { margin: 8px 0; padding: 10px; border-radius: 8px; max-width: 60%; }
    .sent { background: #4CAF50; color: white; align-self: flex-end; }
    .received { background: #e0e0e0; align-self: flex-start; }
    .input-area { display: flex; padding: 10px; background: #ddd; }
    input { flex: 1; padding: 10px; border: none; border-radius: 5px; }
    button { padding: 10px 20px; border: none; background: #4CAF50; color: white; border-radius: 5px; margin-left: 10px; }
    h2 { margin-top: 0; }
    a.user { display: block; padding: 8px; color: white; text-decoration: none; }
    a.user:hover { background: #575757; }
  </style>
</head>
<body>

<div class="sidebar">
  <h2>Users</h2>
  <?php foreach ($users as $u) { 
      if ($u['id'] == $_SESSION['user_id'] && $u['role'] == $_SESSION['role']) continue; ?>
      <a class="user" href="?chat_with=<?php echo $u['id']; ?>&role=<?php echo $u['role']; ?>">
        <?php echo htmlspecialchars($u['name']) . " (" . $u['role'] . ")"; ?>
      </a>
  <?php } ?>
</div>

<div class="chat-area">
  <div class="chat-box" id="chat-box"></div>
  <?php if (isset($_GET['chat_with'])) { ?>
  <div class="input-area">
    <input type="text" id="message" placeholder="Type your message...">
    <button onclick="sendMessage()">Send</button>
  </div>
  <?php } else { ?>
    <p style="margin:auto;">Select a user to start chatting.</p>
  <?php } ?>
</div>

<script>
let chatWith = "<?php echo $_GET['chat_with'] ?? ''; ?>";
let chatRole = "<?php echo $_GET['role'] ?? ''; ?>";

function loadMessages() {
  if (!chatWith) return;
  fetch("fetch_messages.php?chat_with=" + chatWith + "&role=" + chatRole)
    .then(res => res.text())
    .then(data => {
      document.getElementById("chat-box").innerHTML = data;
      document.getElementById("chat-box").scrollTop = document.getElementById("chat-box").scrollHeight;
    });
}

function sendMessage() {
  let msg = document.getElementById("message").value;
  if (msg.trim() === '') return;
  fetch("send_message.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "receiver_id=" + chatWith + "&receiver_role=" + chatRole + "&message=" + encodeURIComponent(msg)
  }).then(() => {
    document.getElementById("message").value = '';
    loadMessages(); // refresh instantly
  });
}

setInterval(loadMessages, 2000);
loadMessages();
</script>

</body>
</html>
