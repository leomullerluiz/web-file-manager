<?php
/**
 * Login Page Template
 * Shows login form and handles forgot password form
 */

// Handle forgot password form
$forgot_msg = '';
$forgot_error = '';
if (isset($_POST['forgot_email'], $_POST['token']) && verifyToken($_POST['token'])) {
    $email = trim($_POST['forgot_email']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        auth_handle_forgot_password($email);
        $forgot_msg = 'If that email is registered, a reset link has been sent.';
    } else {
        $forgot_error = 'Please enter a valid email address.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo (FM_THEME == 'dark') ? 'dark' : 'light' ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="googlebot" content="noindex">
    <title><?php echo fm_enc(APP_TITLE) ?></title>
    <?php print_external('css-bootstrap'); ?>
    <style>
        body.fm-login-page {
            background-color: #f7f9fb;
            font-size: 14px;
        }

        .fm-login-page .card-wrapper {
            width: 360px;
        }

        .fm-login-page .card {
            border-color: transparent;
            box-shadow: 0 4px 8px rgba(0, 0, 0, .05);
        }

        .fm-login-page .card-title {
            margin-bottom: 1.5rem;
            font-size: 24px;
            font-weight: 400;
        }

        .fm-login-page .form-control {
            border-width: 2.3px;
        }

        .fm-login-page .btn.btn-block {
            padding: 12px 10px;
        }

        .fm-login-page .footer {
            margin: 20px 0;
            color: #888;
            text-align: center;
        }

        .message {
            padding: 4px 7px;
            border: 1px solid #ddd;
            background-color: #fff;
        }

        .message.ok {
            border-color: green;
            color: green;
        }

        .message.error {
            border-color: red;
            color: red;
        }

        .message.alert {
            border-color: orange;
            color: orange;
        }

        body.fm-login-page.theme-dark {
            background-color: #2f2a2a;
        }

        .theme-dark .form-control {
            color: #fff;
            background-color: #403e3e;
        }

        .h-100vh {
            min-height: 100vh;
        }

        @media screen and (max-width: 425px) {
            .fm-login-page .card-wrapper {
                width: 90%;
                margin: 0 auto;
                margin-top: 10%;
            }
        }
    </style>
</head>

<body class="fm-login-page <?php echo (FM_THEME == 'dark') ? 'theme-dark' : ''; ?>">
    <div id="wrapper" class="container-fluid">
        <section class="h-100">
            <div class="container h-100">
                <div class="row justify-content-md-center align-content-center h-100vh">
                    <div class="card-wrapper">
                        <div class="card fat" data-bs-theme="<?php echo FM_THEME; ?>">
                            <div class="card-body">
                                <?php if (!isset($_GET['forgot'])): ?>
                                    <!-- Login Form -->
                                    <form class="form-signin" action="" method="post" autocomplete="off">
                                        <div class="mb-3">
                                            <div class="text-center">
                                                <h1 class="card-title"><?php echo fm_enc(APP_TITLE); ?></h1>
                                            </div>
                                        </div>
                                        <hr />
                                        <div class="mb-3">
                                            <label for="fm_usr" class="pb-2"><?php echo lng('Username'); ?></label>
                                            <input type="text" class="form-control" id="fm_usr" name="fm_usr" required
                                                autofocus>
                                        </div>
                                        <div class="mb-3">
                                            <label for="fm_pwd" class="pb-2"><?php echo lng('Password'); ?></label>
                                            <input type="password" class="form-control" id="fm_pwd" name="fm_pwd" required>
                                        </div>
                                        <div class="mb-3">
                                            <?php fm_show_message(); ?>
                                        </div>
                                        <input type="hidden" name="token"
                                            value="<?php echo htmlspecialchars($_SESSION['token']); ?>" />
                                        <div class="mb-3">
                                            <button type="submit" class="btn btn-success btn-block w-100 mt-4"
                                                role="button">
                                                <?php echo lng('Login'); ?>
                                            </button>
                                        </div>
                                        <div class="text-center mt-2">
                                            <a href="?forgot=1"
                                                class="text-muted small"><?php echo lng('Forgot Password?') ?></a>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <!-- Forgot Password Form -->
                                    <form class="form-signin" action="?forgot=1" method="post" autocomplete="off">
                                        <div class="mb-3">
                                            <div class="text-center">
                                                <h1 class="card-title"><?php echo lng('Reset Password') ?></h1>
                                            </div>
                                        </div>
                                        <hr />
                                        <?php if ($forgot_msg): ?>
                                            <div class="alert alert-success"><?php echo htmlspecialchars($forgot_msg) ?></div>
                                        <?php elseif ($forgot_error): ?>
                                            <div class="alert alert-danger"><?php echo htmlspecialchars($forgot_error) ?></div>
                                        <?php endif; ?>
                                        <div class="mb-3">
                                            <label for="forgot_email" class="pb-2"><?php echo lng('Email') ?></label>
                                            <input type="email" class="form-control" id="forgot_email" name="forgot_email"
                                                required autofocus>
                                        </div>
                                        <input type="hidden" name="token"
                                            value="<?php echo htmlspecialchars($_SESSION['token']); ?>" />
                                        <div class="mb-3">
                                            <button type="submit" class="btn btn-primary btn-block w-100 mt-2"
                                                role="button">
                                                <?php echo lng('Send Reset Link') ?>
                                            </button>
                                        </div>
                                        <div class="text-center mt-2">
                                            <a href="?" class="text-muted small">&larr; <?php echo lng('Login') ?></a>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="footer text-center">
                            &mdash;&mdash; &copy;
                            <a href="https://github.com/leomullerluiz/web-file-manager" target="_blank"
                                class="text-decoration-none text-muted" data-version="<?php echo VERSION; ?>">Web File
                                Manager <?php echo VERSION; ?></a> &mdash;&mdash;<br>
                            Improved by <a href="https://ipse.ag/" target="_blank">IPSE
                                Marketing Estratégico</a> & <a href="https://leomullerluiz.com/" target="_blank">Leo
                                Müller</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php print_external('js-jquery'); ?>
    <?php print_external('js-bootstrap'); ?>
</body>

</html>