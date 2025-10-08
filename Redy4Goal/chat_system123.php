<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ready3goal");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Example: Set current user session (You can set this after login)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;       // Change to actual logged-in user's ID
    $_SESSION['role'] = 'coach';    // 'player', 'coach', 'academy', or 'admin'
}

// Handle sending message
if (isset($_POST['receiver_id'], $_POST['receiver_role'], $_POST['message'])) {
    $sender_id = $_SESSION['user_id'];
    $sender_role = $_SESSION['role'];
    $receiver_id = (int)$_POST['receiver_id'];
    $receiver_role = $_POST['receiver_role'];
    $message = $conn->real_escape_string($_POST['message']);

    $conn->query("INSERT INTO chat_messages (sender_id, sender_role, receiver_id, receiver_role, message) 
                  VALUES ($sender_id, '$sender_role', $receiver_id, '$receiver_role', '$message')");
    exit;
}

// Fetch messages
if (isset($_GET['fetch_messages'])) {
    $sender_id = $_SESSION['user_id'];
    $sender_role = $_SESSION['role'];
    $receiver_id = (int)$_GET['receiver_id'];
    $receiver_role = $_GET['receiver_role'];

    $result = $conn->query("
        SELECT * FROM chat_messages
        WHERE (sender_id=$sender_id AND sender_role='$sender_role' AND receiver_id=$receiver_id AND receiver_role='$receiver_role')
           OR (sender_id=$receiver_id AND sender_role='$receiver_role' AND receiver_id=$sender_id AND receiver_role='$sender_role')
        ORDER BY created_at ASC
    ");
    $messages = [];
    while ($row = $result->fetch_assoc()) $messages[] = $row;
    echo json_encode($messages);
    exit;
}

// Fetch users
if (isset($_GET['fetch_users'])) {
    $users = [];

    $res = $conn->query("SELECT id, name, 'player' AS role FROM player");
    while ($r = $res->fetch_assoc()) $users[] = $r;

    $res = $conn->query("SELECT id, name, 'coach' AS role FROM coach");
    while ($r = $res->fetch_assoc()) $users[] = $r;

    $res = $conn->query("SELECT id, academyname AS name, 'academy' AS role FROM academy");
    while ($r = $res->fetch_assoc()) $users[] = $r;

    $res = $conn->query("SELECT id, email AS name, 'admin' AS role FROM admin");
    while ($r = $res->fetch_assoc()) $users[] = $r;

    echo json_encode($users);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat System</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial; margin: 0; background: #f5f5f5; }
        .container { display: flex; height: 100vh; }
        .user-list { width: 250px; background: #0099cc; color: white; padding: 15px; overflow-y: auto; }
        .user-list h3 { text-align: center; }
        .user-list ul { list-style: none; padding: 0; }
        .user-list li {
            background: #0077aa; padding: 10px; margin: 6px 0; border-radius: 6px; cursor: pointer;
            transition: background 0.3s;
        }
        .user-list li:hover { background: #005f88; }

        .chat-box { flex: 1; display: flex; flex-direction: column; }
        .messages {
            flex: 1; padding: 20px; overflow-y: auto; background: #fff; border-bottom: 1px solid #ccc;
        }
        .msg { margin: 8px 0; padding: 10px; border-radius: 8px; max-width: 60%; }
        .sent { background: #cce5ff; align-self: flex-end; }
        .received { background: #e2e2e2; align-self: flex-start; }

        .chat-form {
            display: flex; padding: 10px; background: #f0f0f0;
        }
        .chat-form input[type=text] {
            flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 5px;
        }
        .chat-form button {
            background: #0099cc; color: white; border: none; padding: 10px 15px; border-radius: 5px;
            margin-left: 8px; cursor: pointer;
        }
        .chat-form button:hover { background: #0077aa; }
        .header { background: #0099cc; color: white; padding: 10px; text-align: center; font-size: 18px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="header">
    Logged in as: <b><?= ucfirst($_SESSION['role']) ?></b> (ID: <?= $_SESSION['user_id'] ?>)
</div>

<div class="container">
    <!-- User List -->
    <div class="user-list">
        <h3>Contacts</h3>
        <ul id="user-list"></ul>
    </div>

    <!-- Chat Area -->
    <div class="chat-box">
        <div id="messages" class="messages"></div>

        <form id="chat-form" class="chat-form">
            <input type="hidden" id="receiver_id">
            <input type="hidden" id="receiver_role">
            <input type="text" id="chat-message" placeholder="Type your message..." required>
            <button type="submit">Send</button>
        </form>
    </div>
</div>

<script>
let currentReceiverId = null;
let currentReceiverRole = null;

// Load all users
function loadUsers(){
    $.getJSON('chat_system.php?fetch_users=1', function(users){
        $('#user-list').empty();
        users.forEach(user => {
            if(user.role !== '<?= $_SESSION['role'] ?>' || user.id !== <?= $_SESSION['user_id'] ?>){
                $('#user-list').append(
                    `<li data-id="${user.id}" data-role="${user.role}">${user.name} (${user.role})</li>`
                );
            }
        });
    });
}

// Click user to open chat
$(document).on('click', '#user-list li', function(){
    currentReceiverId = $(this).data('id');
    currentReceiverRole = $(this).data('role');
    $('#receiver_id').val(currentReceiverId);
    $('#receiver_role').val(currentReceiverRole);
    fetchMessages();
});

// Fetch messages
function fetchMessages(){
    if(!currentReceiverId) return;
    $.getJSON('chat_system.php', {
        fetch_messages: 1,
        receiver_id: currentReceiverId,
        receiver_role: currentReceiverRole
    }, function(data){
        $('#messages').empty();
        data.forEach(msg => {
            let cls = (msg.sender_id == <?= $_SESSION['user_id'] ?> && msg.sender_role == '<?= $_SESSION['role'] ?>') ? 'sent' : 'received';
            $('#messages').append(`<div class="msg ${cls}">${msg.message}</div>`);
        });
        $('#messages').scrollTop($('#messages')[0].scrollHeight);
    });
}

// Send message
$('#chat-form').submit(function(e){
    e.preventDefault();
    if(!currentReceiverId) return alert('Select a user first!');
    $.post('chat_system.php', $(this).serialize(), function(){
        $('#chat-message').val('');
        fetchMessages();
    });
});

// Refresh messages
setInterval(fetchMessages, 2000);

loadUsers();
</script>
</body>
</html>
