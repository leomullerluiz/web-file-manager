<?php
/**
 * User Management Functions (Admin)
 */

/**
 * Get all users
 */
function users_get_all()
{
    $pdo = db_connect();
    $stmt = $pdo->query('SELECT u.*, GROUP_CONCAT(ud.directory_path SEPARATOR "\n") as directories FROM users u LEFT JOIN user_directories ud ON ud.user_id = u.id GROUP BY u.id ORDER BY u.username');
    return $stmt->fetchAll();
}

/**
 * Get a single user by ID
 */
function users_get_by_id($id)
{
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
        $user['directories'] = get_user_directories($user['id']);
    }
    return $user;
}

/**
 * Create a new user
 */
function users_create($username, $password, $email, $role, $directories = [])
{
    $pdo = db_connect();

    // Check if username already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['error' => 'Username already exists'];
    }

    // Check if email already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['error' => 'Email already exists'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $hash, $email, $role]);
        $user_id = $pdo->lastInsertId();

        // Add directories
        if (!empty($directories)) {
            users_set_directories($user_id, $directories);
        }

        $pdo->commit();

        // Create filesystem directories
        users_create_filesystem_dirs($directories);

        // Send credentials email
        users_send_credentials_email($email, $username, $password);

        return ['success' => true, 'user_id' => $user_id];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Failed to create user: ' . $e->getMessage()];
    }
}

/**
 * Update an existing user
 */
function users_update($id, $username, $email, $role, $password = null, $directories = [])
{
    $pdo = db_connect();

    // Check if username is taken by another user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
    $stmt->execute([$username, $id]);
    if ($stmt->fetch()) {
        return ['error' => 'Username already taken by another user'];
    }

    // Check if email is taken by another user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        return ['error' => 'Email already taken by another user'];
    }

    $pdo->beginTransaction();
    try {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET username = ?, password = ?, email = ?, role = ? WHERE id = ?');
            $stmt->execute([$username, $hash, $email, $role, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?');
            $stmt->execute([$username, $email, $role, $id]);
        }

        // Update directories
        users_set_directories($id, $directories);

        $pdo->commit();

        // Create filesystem directories (new ones that don't exist yet)
        users_create_filesystem_dirs($directories);

        return ['success' => true];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Failed to update user: ' . $e->getMessage()];
    }
}

/**
 * Delete a user
 */
function users_delete($id)
{
    $pdo = db_connect();
    // Prevent deleting yourself
    if ($id == auth_get_user_id()) {
        return ['error' => 'Cannot delete your own account'];
    }
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return ['success' => true];
}

/**
 * Create filesystem directories that don't exist yet
 */
function users_create_filesystem_dirs(array $directories)
{
    foreach ($directories as $dir) {
        $dir = trim($dir);
        if ($dir !== '' && !is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}

/**
 * Set directories for a user (replaces all existing)
 */
function users_set_directories($user_id, $directories)
{
    $pdo = db_connect();

    // Remove existing directories
    $stmt = $pdo->prepare('DELETE FROM user_directories WHERE user_id = ?');
    $stmt->execute([$user_id]);

    // Add new directories
    if (!empty($directories)) {
        $stmt = $pdo->prepare('INSERT INTO user_directories (user_id, directory_path) VALUES (?, ?)');
        foreach ($directories as $dir) {
            $dir = trim($dir);
            if ($dir !== '') {
                $stmt->execute([$user_id, $dir]);
            }
        }
    }
}

/**
 * Send login credentials to user via email
 */
function users_send_credentials_email($email, $username, $password)
{
    $subject = APP_TITLE . ' - Your Login Credentials';
    $message = "Hello,\n\n";
    $message .= "Your account has been created on " . APP_TITLE . ".\n\n";
    $message .= "Login URL: " . APP_URL . "\n";
    $message .= "Username: {$username}\n";
    $message .= "Password: {$password}\n\n";
    $message .= "Please change your password after your first login.\n";

    $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>' . "\r\n";
    $headers .= 'Reply-To: ' . MAIL_FROM . "\r\n";
    $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

    return @mail($email, $subject, $message, $headers);
}
