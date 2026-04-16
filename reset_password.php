<?php
/**
 * Password Reset Page (public)
 * Accessible without login - validates token and allows password reset
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/auth.php';

session_name(FM_SESSION_ID);
session_start();

if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$error = '';
$success = '';

if (empty($token)) {
    $error = lng('Invalid or expired reset link.');
}

$reset_data = null;
if (!$error) {
    $reset_data = auth_validate_reset_token($token);
    if (!$reset_data) {
        $error = lng('This reset link is invalid or has expired.');
    }
}

// Handle form submission
if (empty($error) && isset($_POST['new_password'], $_POST['confirm_password'], $_POST['token'])) {
    if (!hash_equals($_SESSION['token'], $_POST['token'])) {
        $error = lng('Invalid security token.');
    } elseif (strlen($_POST['new_password']) < 6) {
        $error = lng('Password must be at least 6 characters.');
    } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
        $error = lng('Passwords do not match.');
    } else {
        if (auth_reset_password($token, $_POST['new_password'])) {
            $success = lng('Password reset successfully. You can now log in.');
        } else {
            $error = lng('Failed to reset password. The link may have expired.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo (FM_THEME == 'dark') ? 'dark' : 'light' ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo fm_enc(APP_TITLE) ?> - <?php echo lng('Reset Password') ?></title>
    <?php print_external('css-bootstrap'); ?>
    <style>
        body {
            background-color: #f7f9fb;
            font-size: 14px;
        }

        .card-wrapper {
            width: 380px;
            max-width: 100%;
            margin: 0 auto;
        }

        .card {
            border-color: transparent;
            box-shadow: 0 4px 8px rgba(0, 0, 0, .05);
        }

        .h-100vh {
            min-height: 100vh;
        }
    </style>
</head>

<body>
    <div class="container h-100">
        <div class="row justify-content-md-center align-content-center h-100vh">
            <div class="card-wrapper">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title text-center mb-3"><?php echo lng('Reset Password') ?></h4>
                        <hr>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success) ?></div>
                            <div class="text-center mt-3">
                                <a href="index.php" class="btn btn-primary"><?php echo lng('Login') ?></a>
                            </div>
                        <?php elseif ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error) ?></div>
                            <div class="text-center mt-3">
                                <a href="index.php" class="btn btn-outline-secondary"><?php echo lng('Back') ?></a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">
                                <?php echo lng('Hello') ?>, <strong><?php echo fm_enc($reset_data['username']) ?></strong>.
                                <?php echo lng('Enter your new password below.') ?>
                            </p>
                            <form method="post" action="?token=<?php echo urlencode($token) ?>">
                                <input type="hidden" name="token"
                                    value="<?php echo htmlspecialchars($_SESSION['token']) ?>">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label"><?php echo lng('New Password') ?></label>
                                    <input type="password" class="form-control" id="new_password" name="new_password"
                                        minlength="6" required autofocus>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password"
                                        class="form-label"><?php echo lng('Confirm Password') ?></label>
                                    <input type="password" class="form-control" id="confirm_password"
                                        name="confirm_password" minlength="6" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit"
                                        class="btn btn-success"><?php echo lng('Reset Password') ?></button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="index.php" class="text-muted small">&larr; <?php echo lng('Back to Login') ?></a>
                </div>
            </div>
        </div>
    </div>
    <?php print_external('js-bootstrap'); ?>
</body>

</html>