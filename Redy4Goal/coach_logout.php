<?php
session_start();

// Destroy all session variables
$_SESSION = array();

// Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally destroy the session
session_destroy();

// Clear localStorage (to remove active section memory)
echo '<script>
localStorage.clear();
window.location.href = "registration.php?logout=success";
</script>';
exit;
?>
