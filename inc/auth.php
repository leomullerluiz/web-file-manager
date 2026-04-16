<?php
/**
 * Authentication Functions
 */

/**
 * Authenticate user against database
 */
function auth_login($username, $password)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT id, username, password, email, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION[FM_SESSION_ID]['logged'] = $user['username'];
        $_SESSION[FM_SESSION_ID]['user_id'] = $user['id'];
        $_SESSION[FM_SESSION_ID]['role'] = $user['role'];
        $_SESSION[FM_SESSION_ID]['email'] = $user['email'];

        // Load user directories
        $dirs = get_user_directories($user['id']);
        $_SESSION[FM_SESSION_ID]['directories'] = $dirs;
        $_SESSION[FM_SESSION_ID]['active_dir'] = 0;

        return true;
    }
    return false;
}

/**
 * Check if user is logged in
 */
function auth_is_logged_in()
{
    return isset($_SESSION[FM_SESSION_ID]['logged'], $_SESSION[FM_SESSION_ID]['user_id']);
}

/**
 * Check if current user is admin
 */
function auth_is_admin()
{
    return isset($_SESSION[FM_SESSION_ID]['role']) && $_SESSION[FM_SESSION_ID]['role'] === 'admin';
}

/**
 * Get current user's username
 */
function auth_get_username()
{
    return isset($_SESSION[FM_SESSION_ID]['logged']) ? $_SESSION[FM_SESSION_ID]['logged'] : null;
}

/**
 * Get current user's ID
 */
function auth_get_user_id()
{
    return isset($_SESSION[FM_SESSION_ID]['user_id']) ? $_SESSION[FM_SESSION_ID]['user_id'] : null;
}

/**
 * Get current user's role
 */
function auth_get_role()
{
    return isset($_SESSION[FM_SESSION_ID]['role']) ? $_SESSION[FM_SESSION_ID]['role'] : null;
}

/**
 * Get directories assigned to a user
 */
function get_user_directories($user_id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT directory_path FROM user_directories WHERE user_id = ? ORDER BY directory_path');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get the active root path for the current user
 */
function auth_get_root_path()
{
    global $root_path;

    if (auth_is_admin()) {
        return FM_CLIENTS_DIR;
    }

    $dirs = isset($_SESSION[FM_SESSION_ID]['directories']) ? $_SESSION[FM_SESSION_ID]['directories'] : [];
    $active = isset($_SESSION[FM_SESSION_ID]['active_dir']) ? $_SESSION[FM_SESSION_ID]['active_dir'] : 0;

    if (!empty($dirs) && isset($dirs[$active])) {
        return rtrim($dirs[$active], '/\\');
    }

    return $root_path;
}

/**
 * Switch active directory for client users
 */
function auth_switch_directory($index)
{
    $dirs = isset($_SESSION[FM_SESSION_ID]['directories']) ? $_SESSION[FM_SESSION_ID]['directories'] : [];
    if (isset($dirs[$index])) {
        $_SESSION[FM_SESSION_ID]['active_dir'] = $index;
        return true;
    }
    return false;
}

/**
 * Logout
 */
function auth_logout()
{
    unset($_SESSION[FM_SESSION_ID]['logged']);
    unset($_SESSION[FM_SESSION_ID]['user_id']);
    unset($_SESSION[FM_SESSION_ID]['role']);
    unset($_SESSION[FM_SESSION_ID]['email']);
    unset($_SESSION[FM_SESSION_ID]['directories']);
    unset($_SESSION[FM_SESSION_ID]['active_dir']);
    unset($_SESSION['token']);
}

/**
 * Generate password reset token and send email
 */
function auth_send_reset_email($user_id, $email, $username)
{
    $pdo = db_connect();

    // Delete any existing reset tokens for this user
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?');
    $stmt->execute([$user_id]);

    // Generate token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', time() + (RESET_TOKEN_EXPIRY_HOURS * 3600));

    $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$user_id, $token, $expires_at]);

    // Build reset link
    $reset_link = APP_URL . '/reset_password.php?token=' . urlencode($token);

    // Send email
    $subject = APP_TITLE . ' - Password Reset';
    $message = "Hello {$username},\n\n";
    $message .= "A password reset was requested for your account.\n\n";
    $message .= "Click the link below to reset your password:\n";
    $message .= $reset_link . "\n\n";
    $message .= "This link will expire in " . RESET_TOKEN_EXPIRY_HOURS . " hours.\n\n";
    $message .= "If you did not request this, please ignore this email.\n";

    $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>' . "\r\n";
    $headers .= 'Reply-To: ' . MAIL_FROM . "\r\n";
    $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

    return @mail($email, $subject, $message, $headers);
}

/**
 * Validate reset token
 */
function auth_validate_reset_token($token)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT pr.user_id, u.username FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token = ? AND pr.expires_at > NOW()');
    $stmt->execute([$token]);
    return $stmt->fetch();
}

/**
 * Reset password using token
 */
function auth_reset_password($token, $new_password)
{
    $pdo = db_connect();

    $reset = auth_validate_reset_token($token);
    if (!$reset) {
        return false;
    }

    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hash, $reset['user_id']]);

    // Delete the used token
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE token = ?');
    $stmt->execute([$token]);

    return true;
}

/**
 * Handle forgot password request (from login page)
 */
function auth_handle_forgot_password($email)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        auth_send_reset_email($user['id'], $user['email'], $user['username']);
    }
    // Always return true to not leak info about which emails exist
    return true;
}
