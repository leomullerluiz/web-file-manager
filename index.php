<?php
/**
 * Web File Manager
 * Main entry point - orchestrates all modules
 */

// Debug: show all errors (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration and dependencies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/classes.php';
require_once __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/users.php';

// Security headers
header('Content-Type: text/html; charset=utf-8');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');

// Start session
session_name(FM_SESSION_ID);
session_start();

// Generate CSRF token
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

// -------------------------------------------------------
// Load FM_Config early so FM_THEME is available everywhere
// (login page, IP-denied page, etc.)
// -------------------------------------------------------
$cfg = new FM_Config();

$lang = isset($cfg->data['lang']) ? $cfg->data['lang'] : 'en';
$show_hidden_files = isset($cfg->data['show_hidden']) ? $cfg->data['show_hidden'] : false;
$report_errors = isset($cfg->data['error_reporting']) ? $cfg->data['error_reporting'] : false;
$hide_Cols = isset($cfg->data['hide_Cols']) ? $cfg->data['hide_Cols'] : false;
$theme = isset($cfg->data['theme']) ? $cfg->data['theme'] : 'light';
$readonly_users = isset($cfg->data['readonly_users']) ? $cfg->data['readonly_users'] : [];

defined('FM_THEME') || define('FM_THEME', $theme);

$lang_list = ['en' => 'English'];

date_default_timezone_set($default_timezone);

if ($report_errors) {
    @ini_set('error_reporting', E_ALL);
    @ini_set('display_errors', 1);
}

// Define FM_SELF_URL early (needed for login/logout redirects)
$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
defined('FM_SELF_URL') || define('FM_SELF_URL', ($is_https ? 'https' : 'http') . '://' . $http_host . $_SERVER['PHP_SELF']);

// IP restriction check
if ($ip_ruleset != 'OFF') {
    function getClientIP()
    {
        if (array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER))
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER))
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        if (array_key_exists('REMOTE_ADDR', $_SERVER))
            return $_SERVER['REMOTE_ADDR'];
        if (array_key_exists('HTTP_CLIENT_IP', $_SERVER))
            return $_SERVER['HTTP_CLIENT_IP'];
        return '';
    }
    $clientIp = getClientIP();
    $proceed = false;
    $whitelisted = in_array($clientIp, $ip_whitelist);
    $blacklisted = in_array($clientIp, $ip_blacklist);
    if ($ip_ruleset == 'AND') {
        $proceed = ($whitelisted && !$blacklisted);
    } elseif ($ip_ruleset == 'OR') {
        $proceed = ($whitelisted || !$blacklisted);
    }
    if (!$proceed) {
        trigger_error('User connection denied from: ' . $clientIp, E_USER_WARNING);
        if (!$ip_silent) {
            fm_set_msg(lng('Access denied. IP restriction applicable'), 'error');
            include __DIR__ . '/templates/login.php';
        }
        exit();
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    auth_logout();
    fm_redirect(FM_SELF_URL);
}

// --- Authentication ---
if (!auth_is_logged_in()) {
    // Handle login form POST
    if (isset($_POST['fm_usr'], $_POST['fm_pwd'], $_POST['token'])) {
        sleep(1); // Brute-force mitigation
        if (verifyToken($_POST['token'])) {
            if (auth_login($_POST['fm_usr'], $_POST['fm_pwd'])) {
                fm_set_msg(lng('You are logged in'));
                fm_redirect(FM_SELF_URL);
            } else {
                fm_set_msg(lng('Login failed. Invalid username or password'), 'error');
                fm_redirect(FM_SELF_URL);
            }
        } else {
            fm_set_msg(lng('Invalid security token'), 'error');
            fm_redirect(FM_SELF_URL);
        }
    }

    // Show login page
    include __DIR__ . '/templates/login.php';
    exit();
}

// --- Authenticated: set up root path ---
$root_path = auth_get_root_path();

// Handle directory switch for client users
if (isset($_GET['switch_dir']) && !auth_is_admin()) {
    auth_switch_directory((int) $_GET['switch_dir']);
    $root_path = auth_get_root_path();
    fm_redirect(FM_SELF_URL . '?p=');
}

// Clean and validate root path
$root_path = rtrim(str_replace('\\', '/', $root_path), '\\/');
if (!@is_dir($root_path)) {
    echo '<h1>' . lng('Root path') . ' "' . fm_enc($root_path) . '" ' . lng('not found!') . '</h1>';
    exit;
}

// Always redirect to ?p= if no path set (skip for admin routes)
if (!isset($_GET['p']) && !isset($_GET['admin']) && empty($_FILES)) {
    fm_redirect(FM_SELF_URL . '?p=');
}

// Get and clean current path
$p = isset($_GET['p']) ? $_GET['p'] : (isset($_POST['p']) ? $_POST['p'] : '');
$p = fm_clean_path($p);

// Client path enforcement - ensure client cannot navigate outside their directory
if (!auth_is_admin()) {
    $abs_path = realpath($root_path . ($p ? '/' . $p : ''));
    $abs_root = realpath($root_path);
    if ($abs_path === false || strpos($abs_path, $abs_root) !== 0) {
        $p = '';
    }
}

// For AJAX save requests
$input = file_get_contents('php://input');
$_POST = (strpos($input, 'ajax') !== false && strpos($input, 'save') !== false) ? json_decode($input, true) : $_POST;

// Define constants
defined('FM_ROOT_PATH') || define('FM_ROOT_PATH', $root_path);
defined('FM_LANG') || define('FM_LANG', $lang);
defined('FM_FILE_EXTENSION') || define('FM_FILE_EXTENSION', $allowed_file_extensions);
defined('FM_UPLOAD_EXTENSION') || define('FM_UPLOAD_EXTENSION', $allowed_upload_extensions);
defined('FM_EXCLUDE_ITEMS') || define('FM_EXCLUDE_ITEMS', (version_compare(PHP_VERSION, '7.0.0', '<') ? serialize($exclude_items) : $exclude_items));
defined('FM_DOC_VIEWER') || define('FM_DOC_VIEWER', $online_viewer);
define('FM_READONLY', $global_readonly || (!auth_is_admin() && !empty($readonly_users) && in_array(auth_get_username(), $readonly_users)));
define('FM_IS_WIN', DIRECTORY_SEPARATOR == '\\');

// abs path for site
$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
defined('FM_ROOT_URL') || define('FM_ROOT_URL', ($is_https ? 'https' : 'http') . '://' . $http_host . (!empty($root_url) ? '/' . $root_url : ''));
defined('FM_SELF_URL') || define('FM_SELF_URL', ($is_https ? 'https' : 'http') . '://' . $http_host . $_SERVER['PHP_SELF']);

define('FM_PATH', $p);
define('FM_USE_AUTH', true);
define('FM_EDIT_FILE', $edit_files);
defined('FM_ICONV_INPUT_ENC') || define('FM_ICONV_INPUT_ENC', $iconv_input_encoding);
defined('FM_USE_HIGHLIGHTJS') || define('FM_USE_HIGHLIGHTJS', $use_highlightjs);
defined('FM_HIGHLIGHTJS_STYLE') || define('FM_HIGHLIGHTJS_STYLE', $highlightjs_style);
defined('FM_DATETIME_FORMAT') || define('FM_DATETIME_FORMAT', $datetime_format);
defined('FM_SHOW_HIDDEN') || define('FM_SHOW_HIDDEN', $show_hidden_files);

// --- AJAX Handlers ---
if (isset($_POST['ajax'], $_POST['token'])) {
    include __DIR__ . '/actions/ajax.php';
    exit();
}

// --- Admin Routes ---
if (auth_is_admin() && isset($_GET['admin'])) {
    $admin_action = $_GET['admin'];

    // Handle POST actions BEFORE any output (avoids "headers already sent")
    if (isset($_POST['token']) && verifyToken($_POST['token'])) {

        // Delete user
        if (isset($_POST['delete_user'])) {
            $result = users_delete((int) $_POST['delete_user']);
            fm_set_msg(isset($result['success']) ? lng('User deleted') : $result['error'], isset($result['success']) ? 'ok' : 'error');
            fm_redirect(FM_SELF_URL . '?admin=users');
        }

        // Send password reset link
        if (isset($_POST['reset_user'])) {
            $u = users_get_by_id((int) $_POST['reset_user']);
            if ($u) {
                auth_send_reset_email($u['id'], $u['email'], $u['username']);
                fm_set_msg(lng('Reset link sent'));
            }
            fm_redirect(FM_SELF_URL . '?admin=users');
        }

        // Create / update user (from user_form)
        if (isset($_POST['save_user'])) {
            $edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'client';
            $password = trim($_POST['password'] ?? '');
            // directories: user submits only the folder name; we prepend FM_CLIENTS_DIR
            $dirs_raw = preg_replace('/[\/\\\\]+/', '', trim($_POST['directories'] ?? '')); // strip any slashes
            $directories = [];
            if ($role === 'client' && $dirs_raw !== '') {
                $directories = [FM_CLIENTS_DIR . DIRECTORY_SEPARATOR . $dirs_raw];
            }

            $form_errors = [];
            if (empty($username))
                $form_errors[] = 'Username is required.';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                $form_errors[] = 'Valid email is required.';
            if (!$edit_id && empty($password))
                $form_errors[] = 'Password is required for new users.';
            if (!empty($password) && strlen($password) < 6)
                $form_errors[] = 'Password must be at least 6 characters.';
            if ($role === 'client' && empty($dirs_raw))
                $form_errors[] = 'O nome da pasta do cliente é obrigatório.';

            if (empty($form_errors)) {
                if ($edit_id) {
                    $result = users_update($edit_id, $username, $email, $role, $password ?: null, $directories);
                } else {
                    $result = users_create($username, $password, $email, $role, $directories);
                }

                if (isset($result['success'])) {
                    fm_set_msg($edit_id ? lng('User updated successfully') : lng('User created successfully'));
                    fm_redirect(FM_SELF_URL . '?admin=users');
                } else {
                    // Validation failed — pass errors to template via globals
                    $form_errors[] = $result['error'] ?? 'Unknown error occurred.';
                }
            }

            // Store errors + submitted data for the template to display
            $user_form_errors = $form_errors;
            $user_form_data = compact('username', 'email', 'role', 'dirs_raw');
        }
    }

    // Admin template pages
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/nav.php';
    fm_show_message();

    if ($admin_action === 'users') {
        include __DIR__ . '/templates/admin/users.php';
    } elseif ($admin_action === 'user_form') {
        include __DIR__ . '/templates/admin/user_form.php';
    }

    include __DIR__ . '/templates/footer.php';
    exit();
}

// --- File Action Routes (POST/GET that cause redirects) ---
include __DIR__ . '/actions/file_actions.php';

// --- Page setup ---
$path = FM_ROOT_PATH;
if (FM_PATH != '')
    $path .= '/' . FM_PATH;

if (!is_dir($path)) {
    fm_redirect(FM_SELF_URL . '?p=');
}

$parent = fm_get_parent_path(FM_PATH);

$objects = is_readable($path) ? scandir($path) : [];
$folders = [];
$files = [];
$current_path = array_slice(explode('/', $path), -1)[0];
if (is_array($objects) && fm_is_exclude_items($current_path, $path)) {
    foreach ($objects as $file) {
        if ($file == '.' || $file == '..')
            continue;
        if (!FM_SHOW_HIDDEN && substr($file, 0, 1) === '.')
            continue;
        $new_path = $path . '/' . $file;
        if (@is_file($new_path) && fm_is_exclude_items($file, $new_path)) {
            $files[] = $file;
        } elseif (@is_dir($new_path) && fm_is_exclude_items($file, $new_path)) {
            $folders[] = $file;
        }
    }
}
if (!empty($files))
    natcasesort($files);
if (!empty($folders))
    natcasesort($folders);

// --- Page rendering ---

// Upload form
if (isset($_GET['upload']) && !FM_READONLY) {
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/nav.php';
    function getUploadExt()
    {
        $extArr = explode(',', FM_UPLOAD_EXTENSION);
        if (FM_UPLOAD_EXTENSION && $extArr) {
            array_walk($extArr, function (&$x) {
                $x = ".$x";
            });
            return implode(',', $extArr);
        }
        return '';
    }
    print_external('css-dropzone');
    ?>
    <div class="path">
        <div class="card mb-2 fm-upload-wrapper" data-bs-theme="<?php echo FM_THEME; ?>">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" href="#fileUploader" data-target="#fileUploader">
                            <i class="fa fa-arrow-circle-o-up"></i> <?php echo lng('UploadingFiles') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#urlUploader" data-target="#urlUploader">
                            <i class="fa fa-link"></i> <?php echo lng('Upload from URL') ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <p class="card-text">
                    <a href="?p=<?php echo FM_PATH ?>" class="float-right"><i class="fa fa-chevron-circle-left go-back"></i>
                        <?php echo lng('Back') ?></a>
                    <strong><?php echo lng('DestinationFolder') ?></strong>: <?php echo fm_enc(fm_convert_win(FM_PATH)) ?>
                </p>
                <form action="<?php echo htmlspecialchars(FM_SELF_URL) . '?p=' . fm_enc(FM_PATH) ?>"
                    class="dropzone card-tabs-container" id="fileUploader" enctype="multipart/form-data">
                    <input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
                    <input type="hidden" name="fullpath" id="fullpath" value="<?php echo fm_enc(FM_PATH) ?>">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                    <div class="fallback"><input name="file" type="file" multiple /></div>
                </form>
                <div class="upload-url-wrapper card-tabs-container hidden" id="urlUploader">
                    <form id="js-form-url-upload" class="row row-cols-lg-auto g-3 align-items-center"
                        onsubmit="return upload_from_url(this);" method="POST" action="">
                        <input type="hidden" name="type" value="upload" aria-label="hidden" aria-hidden="true">
                        <input type="url" placeholder="URL" name="uploadurl" required class="form-control"
                            style="width:80%">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <button type="submit" class="btn btn-primary ms-3"><?php echo lng('Upload') ?></button>
                        <div class="lds-facebook">
                            <div></div>
                            <div></div>
                            <div></div>
                        </div>
                    </form>
                    <div id="js-url-upload__list" class="col-9 mt-3"></div>
                </div>
            </div>
        </div>
    </div>
    <?php print_external('js-dropzone'); ?>
    <script>
        Dropzone.options.fileUploader = {
            chunking: true, chunkSize: <?php echo UPLOAD_CHUNK_SIZE; ?>,
            forceChunking: true, retryChunks: true, retryChunksLimit: 3,
            parallelUploads: 1, parallelChunkUploads: false, timeout: 120000,
            maxFilesize: "<?php echo MAX_UPLOAD_SIZE; ?>",
            acceptedFiles: "<?php echo getUploadExt() ?>",
            init: function () {
                this.on("sending", function (file, xhr, formData) {
                    let _path = (file.fullPath) ? file.fullPath : file.name;
                    document.getElementById("fullpath").value = _path;
                    xhr.ontimeout = function () { toast('Error: Server Timeout'); };
                }).on("success", function (res) {
                    try {
                        let _response = JSON.parse(res.xhr.response);
                        if (_response.status == "error") { toast(_response.info); }
                    } catch (e) { toast("Error: Invalid JSON response"); }
                }).on("error", function (file, response) { toast(response); });
            }
        };
    </script>
    <?php
    include __DIR__ . '/templates/footer.php';
    exit();
}

// Copy form (POST)
if (isset($_POST['copy']) && !FM_READONLY) {
    $copy_files = isset($_POST['file']) ? $_POST['file'] : null;
    if (!is_array($copy_files) || empty($copy_files)) {
        fm_set_msg(lng('Nothing selected'), 'alert');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/nav.php';
    ?>
    <div class="path">
        <div class="card" data-bs-theme="<?php echo FM_THEME; ?>">
            <div class="card-header">
                <h6><?php echo lng('Copying') ?></h6>
            </div>
            <div class="card-body">
                <form action="" method="post">
                    <input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
                    <input type="hidden" name="finish" value="1">
                    <?php foreach ($copy_files as $cf)
                        echo '<input type="hidden" name="file[]" value="' . fm_enc($cf) . '">' . PHP_EOL; ?>
                    <p class="break-word"><strong><?php echo lng('Files') ?></strong>:
                        <b><?php echo implode('</b>, <b>', $copy_files) ?></b>
                    </p>
                    <p class="break-word">
                        <strong><?php echo lng('SourceFolder') ?></strong>:
                        <?php echo fm_enc(fm_convert_win(FM_ROOT_PATH . '/' . FM_PATH)) ?><br>
                        <label for="inp_copy_to"><strong><?php echo lng('DestinationFolder') ?></strong>:</label>
                        <?php echo FM_ROOT_PATH ?>/<input type="text" name="copy_to" id="inp_copy_to"
                            value="<?php echo fm_enc(FM_PATH) ?>">
                    </p>
                    <p class="custom-checkbox custom-control">
                        <input type="checkbox" name="move" value="1" id="js-move-files" class="custom-control-input">
                        <label for="js-move-files" class="custom-control-label ms-2"><?php echo lng('Move') ?></label>
                    </p>
                    <p>
                        <a href="?p=<?php echo urlencode(FM_PATH) ?>" class="btn btn-outline-danger"><i
                                class="fa fa-times-circle"></i> <?php echo lng('Cancel') ?></a>&nbsp;
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <button type="submit" class="btn btn-success"><i class="fa fa-check-circle"></i>
                            <?php echo lng('Copy') ?></button>
                    </p>
                </form>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/templates/footer.php';
    exit();
}

// Copy form (GET - folder select)
if (isset($_GET['copy']) && !isset($_GET['finish']) && !FM_READONLY) {
    $copy = fm_clean_path($_GET['copy']);
    if ($copy == '' || !file_exists(FM_ROOT_PATH . '/' . $copy)) {
        fm_set_msg(lng('File not found'), 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/nav.php';
    ?>
    <div class="path">
        <p><b>Copying</b></p>
        <p class="break-word">
            <strong>Source path:</strong> <?php echo fm_enc(fm_convert_win(FM_ROOT_PATH . '/' . $copy)) ?><br>
            <strong>Destination folder:</strong> <?php echo fm_enc(fm_convert_win(FM_ROOT_PATH . '/' . FM_PATH)) ?>
        </p>
        <p>
            <a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;copy=<?php echo urlencode($copy) ?>&amp;finish=1"><i
                    class="fa fa-check-circle"></i> Copy</a> &nbsp;
            <a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;copy=<?php echo urlencode($copy) ?>&amp;finish=1&amp;move=1"><i
                    class="fa fa-check-circle"></i> Move</a> &nbsp;
            <a href="?p=<?php echo urlencode(FM_PATH) ?>" class="text-danger"><i class="fa fa-times-circle"></i> Cancel</a>
        </p>
        <p><i><?php echo lng('Select folder') ?></i></p>
        <ul class="folders break-word">
            <?php if ($parent !== false): ?>
                <li><a href="?p=<?php echo urlencode($parent) ?>&amp;copy=<?php echo urlencode($copy) ?>"><i
                            class="fa fa-chevron-circle-left"></i> ..</a></li>
            <?php endif; ?>
            <?php foreach ($folders as $f): ?>
                <li>
                    <a href="?p=<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) ?>&amp;copy=<?php echo urlencode($copy) ?>">
                        <i class="fa fa-folder-o"></i> <?php echo fm_convert_win($f) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    include __DIR__ . '/templates/footer.php';
    exit();
}

// Settings page
if (isset($_GET['settings']) && !FM_READONLY) {
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/nav.php';
    global $lang, $lang_list;
    ?>
    <div class="col-md-8 offset-md-2 pt-3">
        <div class="card mb-2" data-bs-theme="<?php echo FM_THEME; ?>">
            <h6 class="card-header d-flex justify-content-between">
                <span><i class="fa fa-cog"></i> <?php echo lng('Settings') ?></span>
                <a href="?p=<?php echo FM_PATH ?>" class="text-danger"><i class="fa fa-times-circle-o"></i>
                    <?php echo lng('Cancel') ?></a>
            </h6>
            <div class="card-body">
                <form id="js-settings-form" action="" method="post" data-type="ajax" onsubmit="return save_settings(this)">
                    <input type="hidden" name="type" value="settings" aria-label="hidden" aria-hidden="true">
                    <div class="form-group row">
                        <label for="js-language" class="col-sm-3 col-form-label"><?php echo lng('Language') ?></label>
                        <div class="col-sm-5">
                            <select class="form-select" id="js-language" name="js-language">
                                <?php
                                function getSelected($l)
                                {
                                    global $lang;
                                    return ($lang == $l) ? 'selected' : '';
                                }
                                foreach ($lang_list as $k => $v)
                                    echo "<option value='$k' " . getSelected($k) . ">$v</option>";
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 mb-3 row">
                        <label for="js-error-report"
                            class="col-sm-3 col-form-label"><?php echo lng('ErrorReporting') ?></label>
                        <div class="col-sm-9">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="js-error-report"
                                    name="js-error-report" value="true" <?php echo $report_errors ? 'checked' : ''; ?> />
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="js-show-hidden"
                            class="col-sm-3 col-form-label"><?php echo lng('ShowHiddenFiles') ?></label>
                        <div class="col-sm-9">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="js-show-hidden"
                                    name="js-show-hidden" value="true" <?php echo $show_hidden_files ? 'checked' : ''; ?> />
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="js-hide-cols" class="col-sm-3 col-form-label"><?php echo lng('HideColumns') ?></label>
                        <div class="col-sm-9">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="js-hide-cols"
                                    name="js-hide-cols" value="true" <?php echo $hide_Cols ? 'checked' : ''; ?> />
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="js-3-1" class="col-sm-3 col-form-label"><?php echo lng('Theme') ?></label>
                        <div class="col-sm-5">
                            <select class="form-select w-100 text-capitalize" id="js-3-0" name="js-theme-3">
                                <option value="light" <?php if ($theme == 'light')
                                    echo 'selected'; ?>>
                                    <?php echo lng('light') ?>
                                </option>
                                <option value="dark" <?php if ($theme == 'dark')
                                    echo 'selected'; ?>><?php echo lng('dark') ?>
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <div class="col-sm-10">
                            <button type="submit" class="btn btn-success"><i class="fa fa-check-circle"></i>
                                <?php echo lng('Save'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/templates/footer.php';
    exit();
}

// Help page
if (isset($_GET['help'])) {
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/nav.php';
    ?>
    <div class="col-md-8 offset-md-2 pt-3">
        <div class="card mb-2" data-bs-theme="<?php echo FM_THEME; ?>">
            <h6 class="card-header d-flex justify-content-between">
                <span><i class="fa fa-exclamation-circle"></i> <?php echo lng('Help') ?></span>
                <a href="?p=<?php echo FM_PATH ?>" class="text-danger"><i class="fa fa-times-circle-o"></i>
                    <?php echo lng('Cancel') ?></a>
            </h6>
            <div class="card-body">
                <div class="row">
                    <div class="col-xs-12 col-sm-6">
                        <h3><a href="https://github.com/leomullerluiz/web-file-manager" target="_blank"
                                class="app-v-title">Web File Manager <?php echo VERSION; ?></a></h3>
                        <p>Author: Leonan Müller</p>
                        <p>Mail Us: <a href="mailto:leomullerluiz@gmail.com">leomullerluiz [at] gmail [dot] com</a></p>
                    </div>
                    <div class="col-xs-12 col-sm-6">
                        <div class="card">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><a href="https://github.com/leomullerluiz/web-file-manager/wiki"
                                        target="_blank"><i class="fa fa-question-circle"></i>
                                        <?php echo lng('Help Documents') ?></a></li>
                                <li class="list-group-item"><a
                                        href="https://github.com/leomullerluiz/web-file-manager/issues" target="_blank"><i
                                            class="fa fa-bug"></i> <?php echo lng('Report Issue') ?></a></li>
                                <?php if (!FM_READONLY): ?>
                                    <li class="list-group-item"><a href="javascript:show_new_pwd();"><i class="fa fa-lock"></i>
                                            <?php echo lng('Generate new password hash') ?></a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="row js-new-pwd hidden mt-2">
                    <div class="col-12">
                        <form class="form-inline" onsubmit="return new_password_hash(this)" method="POST" action="">
                            <input type="hidden" name="type" value="pwdhash">
                            <div class="form-group mb-2">
                                <label><?php echo lng('Generate new password hash') ?></label>
                            </div>
                            <div class="form-group mx-sm-3 mb-2">
                                <input type="text" class="form-control btn-sm" id="inputPassword2" name="inputPassword2"
                                    placeholder="<?php echo lng('Password') ?>" required>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm mb-2"><?php echo lng('Generate') ?></button>
                        </form>
                        <textarea class="form-control" rows="2" readonly id="js-pwd-result"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/templates/footer.php';
    exit();
}

// File viewer
if (isset($_GET['view'])) {
    $file = str_replace('/', '', fm_clean_path($_GET['view'], false));
    if ($file == '' || !is_file($path . '/' . $file) || !fm_is_exclude_items($file, $path . '/' . $file)) {
        fm_set_msg(lng('File not found'), 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    $editFile = '';
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/nav.php';

    $file_url = FM_ROOT_URL . fm_convert_win((FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $file);
    $file_path = $path . '/' . $file;
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime_type = fm_get_mime_type($file_path);
    $filesize_raw = fm_get_size($file_path);
    $filesize = fm_get_filesize($filesize_raw);

    $is_zip = $is_image = $is_audio = $is_video = $is_text = $is_onlineViewer = false;
    $view_title = 'File';
    $filenames = false;
    $content = '';
    $online_viewer = strtolower(FM_DOC_VIEWER);

    if ($online_viewer && $online_viewer !== 'false' && in_array($ext, fm_get_onlineViewer_exts())) {
        $is_onlineViewer = true;
    } elseif ($ext == 'zip' || $ext == 'tar') {
        $is_zip = true;
        $view_title = 'Archive';
        $filenames = fm_get_zif_info($file_path, $ext);
    } elseif (in_array($ext, fm_get_image_exts())) {
        $is_image = true;
        $view_title = 'Image';
    } elseif (in_array($ext, fm_get_audio_exts())) {
        $is_audio = true;
        $view_title = 'Audio';
    } elseif (in_array($ext, fm_get_video_exts())) {
        $is_video = true;
        $view_title = 'Video';
    } elseif (in_array($ext, fm_get_text_exts()) || substr($mime_type, 0, 4) == 'text' || in_array($mime_type, fm_get_text_mimes())) {
        $is_text = true;
        $content = file_get_contents($file_path);
    }
    ?>
    <div class="row">
        <div class="col-12">
            <ul class="list-group w-50 my-3" data-bs-theme="<?php echo FM_THEME; ?>">
                <li class="list-group-item active"><strong><?php echo lng($view_title) ?>:</strong>
                    <?php echo fm_enc(fm_convert_win($file)) ?></li>
                <?php $display_path = fm_get_display_path($file_path); ?>
                <li class="list-group-item"><strong><?php echo $display_path['label']; ?>:</strong>
                    <?php echo $display_path['path']; ?></li>
                <li class="list-group-item"><strong><?php echo lng('Date Modified') ?>:</strong>
                    <?php echo date(FM_DATETIME_FORMAT, filemtime($file_path)); ?></li>
                <li class="list-group-item"><strong><?php echo lng('File size') ?>:</strong>
                    <?php echo ($filesize_raw <= 1000) ? "$filesize_raw bytes" : $filesize; ?></li>
                <li class="list-group-item"><strong><?php echo lng('MIME-type') ?>:</strong> <?php echo $mime_type ?></li>
                <?php if ($is_text):
                    $is_utf8 = fm_is_utf8($content);
                    echo '<li class="list-group-item"><strong>' . lng('Charset') . ':</strong> ' . ($is_utf8 ? 'utf-8' : '8 bit') . '</li>';
                endif; ?>
                <?php if ($is_image):
                    $image_size = getimagesize($file_path);
                    echo '<li class="list-group-item"><strong>' . lng('Image size') . ':</strong> ' . (isset($image_size[0]) ? $image_size[0] : '0') . ' x ' . (isset($image_size[1]) ? $image_size[1] : '0') . '</li>';
                endif; ?>
            </ul>
            <div class="btn-group btn-group-sm flex-wrap">
                <form method="post" class="d-inline mb-0 btn btn-outline-primary"
                    action="?p=<?php echo urlencode(FM_PATH) ?>&amp;dl=<?php echo urlencode($file) ?>">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                    <button type="submit" class="btn btn-link btn-sm text-decoration-none fw-bold p-0"><i
                            class="fa fa-cloud-download"></i> <?php echo lng('Download') ?></button> &nbsp;
                </form>
                <?php if (!FM_READONLY): ?>
                    <a class="fw-bold btn btn-outline-primary"
                        href="?p=<?php echo urlencode(FM_PATH) ?>&amp;del=<?php echo urlencode($file) ?>"
                        onclick="confirmDailog(event, 1209, '<?php echo lng('Delete') . ' ' . lng('File'); ?>','<?php echo urlencode($file); ?>', this.href);">
                        <i class="fa fa-trash"></i> Delete
                    </a>
                <?php endif; ?>
                <a class="fw-bold btn btn-outline-primary" href="<?php echo fm_enc($file_url) ?>" target="_blank"><i
                        class="fa fa-external-link-square"></i> <?php echo lng('Open') ?></a>
                <?php if ($is_text && !FM_READONLY): ?>
                    <a class="fw-bold btn btn-outline-primary"
                        href="?p=<?php echo urlencode(trim(FM_PATH)) ?>&amp;edit=<?php echo urlencode($file) ?>"><i
                            class="fa fa-pencil-square"></i> <?php echo lng('Edit') ?></a>
                    <a class="fw-bold btn btn-outline-primary"
                        href="?p=<?php echo urlencode(trim(FM_PATH)) ?>&amp;edit=<?php echo urlencode($file) ?>&env=ace"><i
                            class="fa fa-pencil-square"></i> <?php echo lng('AdvancedEditor') ?></a>
                <?php endif; ?>
                <a class="fw-bold btn btn-outline-primary" href="?p=<?php echo urlencode(FM_PATH) ?>"><i
                        class="fa fa-chevron-circle-left go-back"></i> <?php echo lng('Back') ?></a>
            </div>
            <div class="row mt-3">
                <?php
                if ($is_onlineViewer) {
                    if ($online_viewer == 'google')
                        echo '<iframe src="https://docs.google.com/viewer?embedded=true&hl=en&url=' . fm_enc($file_url) . '" frameborder="no" style="width:100%;min-height:460px"></iframe>';
                    elseif ($online_viewer == 'microsoft')
                        echo '<iframe src="https://view.officeapps.live.com/op/embed.aspx?src=' . fm_enc($file_url) . '" frameborder="no" style="width:100%;min-height:460px"></iframe>';
                } elseif ($is_zip && $filenames !== false) {
                    echo '<code class="maxheight">';
                    foreach ($filenames as $fn) {
                        echo $fn['folder'] ? '<b>' . fm_enc($fn['name']) . '</b><br>' : $fn['name'] . ' (' . fm_get_filesize($fn['filesize']) . ')<br>';
                    }
                    echo '</code>';
                } elseif ($is_image && in_array($ext, ['gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico', 'svg', 'webp', 'avif'])) {
                    echo '<p><input type="checkbox" id="preview-img-zoomCheck"><label for="preview-img-zoomCheck"><img src="' . fm_enc($file_url) . '" alt="image" class="preview-img"></label></p>';
                } elseif ($is_audio) {
                    echo '<p><audio src="' . fm_enc($file_url) . '" controls preload="metadata"></audio></p>';
                } elseif ($is_video) {
                    echo '<div class="preview-video"><video src="' . fm_enc($file_url) . '" width="640" height="360" controls preload="metadata"></video></div>';
                } elseif ($is_text) {
                    $is_utf8 = fm_is_utf8($content);
                    if (function_exists('iconv') && !$is_utf8)
                        $content = iconv(FM_ICONV_INPUT_ENC, 'UTF-8//IGNORE', $content);
                    if (FM_USE_HIGHLIGHTJS) {
                        $hljs_classes = ['shtml' => 'xml', 'htaccess' => 'apache', 'phtml' => 'php', 'lock' => 'json', 'svg' => 'xml'];
                        $hljs_class = isset($hljs_classes[$ext]) ? 'lang-' . $hljs_classes[$ext] : 'lang-' . $ext;
                        if (empty($ext) || in_array(strtolower($file), fm_get_text_names()) || preg_match('#\.min\.(css|js)$#i', $file))
                            $hljs_class = 'nohighlight';
                        echo '<pre class="with-hljs"><code class="' . $hljs_class . '">' . fm_enc($content) . '</code></pre>';
                    } elseif (in_array($ext, ['php', 'php4', 'php5', 'phtml', 'phps'])) {
                        echo highlight_string($content, true);
                    } else {
                        echo '<pre>' . fm_enc($content) . '</pre>';
                    }
                }
                ?>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/templates/footer.php';
    exit();
}

// File editor
if (isset($_GET['edit']) && !FM_READONLY) {
    $file = str_replace('/', '', fm_clean_path($_GET['edit'], false));
    if ($file == '' || !is_file($path . '/' . $file) || !fm_is_exclude_items($file, $path . '/' . $file)) {
        fm_set_msg(lng('File not found'), 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    $editFile = ' : <i><b>' . $file . '</b></i>';
    header('X-XSS-Protection:0');
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/nav.php';

    $file_url = FM_ROOT_URL . fm_convert_win((FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $file);
    $file_path = $path . '/' . $file;
    $isNormalEditor = !(isset($_GET['env']) && $_GET['env'] == 'ace');

    if (isset($_POST['savedata'])) {
        $writedata = $_POST['savedata'];
        $fd = fopen($file_path, 'w');
        @fwrite($fd, $writedata);
        fclose($fd);
        fm_set_msg(lng('File Saved Successfully'));
    }

    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime_type = fm_get_mime_type($file_path);
    $is_text = in_array($ext, fm_get_text_exts()) || substr($mime_type, 0, 4) == 'text' || in_array($mime_type, fm_get_text_mimes());
    $content = $is_text ? file_get_contents($file_path) : '';
    ?>
    <div class="path">
        <div class="row">
            <div class="col-xs-12 col-sm-5 col-lg-6 pt-1">
                <?php if (!$isNormalEditor): ?>
                    <div class="btn-toolbar js-ace-toolbar">
                        <div class="btn-group">
                            <button data-cmd="none" data-option="fullscreen" class="btn btn-sm btn-outline-secondary"
                                title="<?php echo lng('Fullscreen') ?>"><i class="fa fa-expand"></i></button>
                            <button data-cmd="find" class="btn btn-sm btn-outline-secondary"
                                title="<?php echo lng('Search') ?>"><i class="fa fa-search"></i></button>
                            <button data-cmd="undo" class="btn btn-sm btn-outline-secondary"
                                title="<?php echo lng('Undo') ?>"><i class="fa fa-undo"></i></button>
                            <button data-cmd="redo" class="btn btn-sm btn-outline-secondary"
                                title="<?php echo lng('Redo') ?>"><i class="fa fa-repeat"></i></button>
                            <button data-cmd="none" data-option="wrap" class="btn btn-sm btn-outline-secondary"
                                title="<?php echo lng('Word Wrap') ?>"><i class="fa fa-text-width"></i></button>
                            <select id="js-ace-mode" data-type="mode"
                                class="btn-outline-secondary border-start-0 d-none d-md-block">
                                <option>-- <?php echo lng('Select Mode') ?> --</option>
                            </select>
                            <select id="js-ace-theme" data-type="theme"
                                class="btn-outline-secondary border-start-0 d-none d-lg-block">
                                <option>-- <?php echo lng('Select Theme') ?> --</option>
                            </select>
                            <select id="js-ace-fontSize" data-type="fontSize"
                                class="btn-outline-secondary border-start-0 d-none d-lg-block">
                                <option>-- <?php echo lng('Select Font Size') ?> --</option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="edit-file-actions col-xs-12 col-sm-7 col-lg-6 text-end pt-1">
                <div class="btn-group">
                    <a class="btn btn-sm btn-outline-primary"
                        href="?p=<?php echo urlencode(trim(FM_PATH)) ?>&amp;view=<?php echo urlencode($file) ?>"><i
                            class="fa fa-reply-all"></i> <?php echo lng('Back') ?></a>
                    <a class="btn btn-sm btn-outline-primary" href="javascript:void(0);"
                        onclick="backup('<?php echo urlencode(trim(FM_PATH)) ?>','<?php echo urlencode($file) ?>')"><i
                            class="fa fa-database"></i> <?php echo lng('BackUp') ?></a>
                    <?php if ($is_text): ?>
                        <?php if ($isNormalEditor): ?>
                            <a class="btn btn-sm btn-outline-primary"
                                href="?p=<?php echo urlencode(trim(FM_PATH)) ?>&amp;edit=<?php echo urlencode($file) ?>&amp;env=ace"><i
                                    class="fa fa-pencil-square-o"></i> <?php echo lng('AdvancedEditor') ?></a>
                            <button type="button" class="btn btn-sm btn-success" data-url="<?php echo fm_enc($file_url) ?>"
                                onclick="edit_save(this,'nrl')"><i class="fa fa-floppy-o"></i> Save</button>
                        <?php else: ?>
                            <a class="btn btn-sm btn-outline-primary"
                                href="?p=<?php echo urlencode(trim(FM_PATH)) ?>&amp;edit=<?php echo urlencode($file) ?>"><i
                                    class="fa fa-text-height"></i> <?php echo lng('NormalEditor') ?></a>
                            <button type="button" class="btn btn-sm btn-success" data-url="<?php echo fm_enc($file_url) ?>"
                                onclick="edit_save(this,'ace')"><i class="fa fa-floppy-o"></i> <?php echo lng('Save') ?></button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        if ($is_text && $isNormalEditor) {
            echo '<textarea class="mt-2" id="normal-editor" rows="33" cols="120" style="width:99.5%;">' . htmlspecialchars($content) . '</textarea>';
        } elseif ($is_text) {
            echo '<div id="editor" contenteditable="true">' . htmlspecialchars($content) . '</div>';
        } else {
            fm_set_msg(lng('FILE EXTENSION IS NOT SUPPORTED'), 'error');
        }
        ?>
    </div>
    <?php
    include __DIR__ . '/templates/footer.php';
    exit();
}

// Chmod page (not for Windows)
if (isset($_GET['chmod']) && !FM_READONLY && !FM_IS_WIN) {
    $file = str_replace('/', '', fm_clean_path($_GET['chmod']));
    if ($file == '' || (!is_file($path . '/' . $file) && !is_dir($path . '/' . $file))) {
        fm_set_msg(lng('File not found'), 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    $editFile = '';
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/nav.php';
    $file_path = $path . '/' . $file;
    $mode = fileperms($path . '/' . $file);
    ?>
    <div class="path">
        <div class="card mb-2" data-bs-theme="<?php echo FM_THEME; ?>">
            <h6 class="card-header"><?php echo lng('ChangePermissions') ?></h6>
            <div class="card-body">
                <?php $display_path = fm_get_display_path($file_path); ?>
                <p><?php echo $display_path['label']; ?>: <?php echo $display_path['path']; ?></p>
                <form action="" method="post">
                    <input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
                    <input type="hidden" name="chmod" value="<?php echo fm_enc($file) ?>">
                    <table class="table compact-table" data-bs-theme="<?php echo FM_THEME; ?>">
                        <tr>
                            <td></td>
                            <td><b><?php echo lng('Owner') ?></b></td>
                            <td><b><?php echo lng('Group') ?></b></td>
                            <td><b><?php echo lng('Other') ?></b></td>
                        </tr>
                        <tr>
                            <td style="text-align:right"><b><?php echo lng('Read') ?></b></td>
                            <td><label><input type="checkbox" name="ur" value="1" <?php echo ($mode & 00400) ? 'checked' : '' ?>></label></td>
                            <td><label><input type="checkbox" name="gr" value="1" <?php echo ($mode & 00040) ? 'checked' : '' ?>></label></td>
                            <td><label><input type="checkbox" name="or" value="1" <?php echo ($mode & 00004) ? 'checked' : '' ?>></label></td>
                        </tr>
                        <tr>
                            <td style="text-align:right"><b><?php echo lng('Write') ?></b></td>
                            <td><label><input type="checkbox" name="uw" value="1" <?php echo ($mode & 00200) ? 'checked' : '' ?>></label></td>
                            <td><label><input type="checkbox" name="gw" value="1" <?php echo ($mode & 00020) ? 'checked' : '' ?>></label></td>
                            <td><label><input type="checkbox" name="ow" value="1" <?php echo ($mode & 00002) ? 'checked' : '' ?>></label></td>
                        </tr>
                        <tr>
                            <td style="text-align:right"><b><?php echo lng('Execute') ?></b></td>
                            <td><label><input type="checkbox" name="ux" value="1" <?php echo ($mode & 00100) ? 'checked' : '' ?>></label></td>
                            <td><label><input type="checkbox" name="gx" value="1" <?php echo ($mode & 00010) ? 'checked' : '' ?>></label></td>
                            <td><label><input type="checkbox" name="ox" value="1" <?php echo ($mode & 00001) ? 'checked' : '' ?>></label></td>
                        </tr>
                    </table>
                    <p>
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <a href="?p=<?php echo urlencode(FM_PATH) ?>" class="btn btn-outline-primary"><i
                                class="fa fa-times-circle"></i> <?php echo lng('Cancel') ?></a>&nbsp;
                        <button type="submit" class="btn btn-success"><i class="fa fa-check-circle"></i>
                            <?php echo lng('Change') ?></button>
                    </p>
                </form>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/templates/footer.php';
    exit();
}

// --- MAIN FILE LISTING ---
$editFile = '';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/nav.php';
fm_show_message();

$num_files = count($files);
$num_folders = count($folders);
$all_files_size = 0;
?>
<form action="" method="post" class="pt-3">
    <input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
    <input type="hidden" name="group" value="1">
    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
    <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm" id="main-table"
            data-bs-theme="<?php echo FM_THEME; ?>">
            <thead class="thead-white">
                <tr>
                    <?php if (!FM_READONLY): ?>
                        <th style="width:3%" class="custom-checkbox-header">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="js-select-all-items"
                                    onclick="checkbox_toggle()">
                                <label class="custom-control-label" for="js-select-all-items"></label>
                            </div>
                        </th>
                    <?php endif; ?>
                    <th><?php echo lng('Name') ?></th>
                    <th><?php echo lng('Size') ?></th>
                    <th><?php echo lng('Modified') ?></th>
                    <?php if (!FM_IS_WIN && !$hide_Cols): ?>
                        <th><?php echo lng('Perms') ?></th>
                        <th><?php echo lng('Owner') ?></th>
                    <?php endif; ?>
                    <th><?php echo lng('Actions') ?></th>
                </tr>
            </thead>
            <?php

            $ii = 3399;
            foreach ($folders as $f):
                $is_link = is_link($path . '/' . $f);
                $img = $is_link ? 'icon-link_folder' : 'fa fa-folder-o';
                $modif_raw = filemtime($path . '/' . $f);
                $modif = date(FM_DATETIME_FORMAT, $modif_raw);
                $date_sorting = strtotime(date('F d Y H:i:s.', $modif_raw));
                $perms = substr(decoct(fileperms($path . '/' . $f)), -4);
                $owner = ['name' => '?'];
                $group = ['name' => '?'];
                if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) {
                    try {
                        $oid = fileowner($path . '/' . $f);
                        if ($oid != 0) {
                            $oi = posix_getpwuid($oid);
                            if ($oi)
                                $owner = $oi;
                        }
                        $gi = posix_getgrgid(filegroup($path . '/' . $f));
                        if ($gi)
                            $group = $gi;
                    } catch (Exception $e) {
                        error_log("exception:" . $e->getMessage());
                    }
                }
                ?>
                <tr>
                    <?php if (!FM_READONLY): ?>
                        <td class="custom-checkbox-td">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="<?php echo $ii ?>" name="file[]"
                                    value="<?php echo fm_enc($f) ?>">
                                <label class="custom-control-label" for="<?php echo $ii ?>"></label>
                            </div>
                        </td>
                    <?php endif; ?>
                    <td data-sort=<?php echo fm_convert_win(fm_enc($f)) ?>>
                        <div class="filename">
                            <a href="?p=<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) ?>"><i
                                    class="<?php echo $img ?>"></i> <?php echo fm_convert_win(fm_enc($f)) ?></a>
                            <?php echo ($is_link ? ' &rarr; <i>' . readlink($path . '/' . $f) . '</i>' : '') ?>
                        </div>
                    </td>
                    <td data-order="a-<?php echo str_pad('', 18, '0', STR_PAD_LEFT); ?>"><?php echo lng('Folder') ?></td>
                    <td data-order="a-<?php echo $date_sorting; ?>"><?php echo $modif ?></td>
                    <?php if (!FM_IS_WIN && !$hide_Cols): ?>
                        <td><?php if (!FM_READONLY): ?><a
                                    href="?p=<?php echo urlencode(FM_PATH) ?>&amp;chmod=<?php echo urlencode($f) ?>"><?php echo $perms ?></a><?php else:
                            echo $perms;
                        endif; ?>
                        </td>
                        <td><?php echo $owner['name'] . ':' . $group['name'] ?></td>
                    <?php endif; ?>
                    <td class="inline-actions">
                        <?php if (!FM_READONLY): ?>
                            <a title="<?php echo lng('Delete') ?>"
                                href="?p=<?php echo urlencode(FM_PATH) ?>&amp;del=<?php echo urlencode($f) ?>"
                                onclick="confirmDailog(event, '1028','<?php echo lng('Delete') . ' ' . lng('Folder'); ?>','<?php echo urlencode($f) ?>', this.href);">
                                <i class="fa fa-trash-o" aria-hidden="true"></i></a>
                            <a title="<?php echo lng('Rename') ?>" href="#"
                                onclick="rename('<?php echo fm_enc(addslashes(FM_PATH)) ?>', '<?php echo fm_enc(addslashes($f)) ?>');return false;"><i
                                    class="fa fa-pencil-square-o" aria-hidden="true"></i></a>
                            <a title="<?php echo lng('CopyTo') ?>..."
                                href="?p=&amp;copy=<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) ?>"><i
                                    class="fa fa-files-o" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <a title="<?php echo lng('DirectLink') ?>"
                            href="<?php echo fm_enc(FM_ROOT_URL . (FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $f . '/') ?>"
                            target="_blank"><i class="fa fa-link" aria-hidden="true"></i></a>
                    </td>
                </tr>
                <?php flush();
                $ii++;
            endforeach;

            $ik = 8002;
            foreach ($files as $f):
                $is_link = is_link($path . '/' . $f);
                $img = $is_link ? 'fa fa-file-text-o' : fm_get_file_icon_class($path . '/' . $f);
                $modif_raw = filemtime($path . '/' . $f);
                $modif = date(FM_DATETIME_FORMAT, $modif_raw);
                $date_sorting = strtotime(date('F d Y H:i:s.', $modif_raw));
                $filesize_raw = fm_get_size($path . '/' . $f);
                $filesize = fm_get_filesize($filesize_raw);
                $filelink = '?p=' . urlencode(FM_PATH) . '&amp;view=' . urlencode($f);
                $all_files_size += $filesize_raw;
                $perms = substr(decoct(fileperms($path . '/' . $f)), -4);
                $owner = ['name' => '?'];
                $group = ['name' => '?'];
                if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) {
                    try {
                        $oid = fileowner($path . '/' . $f);
                        if ($oid != 0) {
                            $oi = posix_getpwuid($oid);
                            if ($oi)
                                $owner = $oi;
                        }
                        $gi = posix_getgrgid(filegroup($path . '/' . $f));
                        if ($gi)
                            $group = $gi;
                    } catch (Exception $e) {
                        error_log("exception:" . $e->getMessage());
                    }
                }
                $isImg = in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico', 'svg', 'webp', 'avif']);
                ?>
                <tr>
                    <?php if (!FM_READONLY): ?>
                        <td class="custom-checkbox-td">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="<?php echo $ik ?>" name="file[]"
                                    value="<?php echo fm_enc($f) ?>">
                                <label class="custom-control-label" for="<?php echo $ik ?>"></label>
                            </div>
                        </td>
                    <?php endif; ?>
                    <td data-sort=<?php echo fm_enc($f) ?>>
                        <div class="filename">
                            <?php if ($isImg):
                                $imagePreview = fm_enc(FM_ROOT_URL . (FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $f); ?>
                                <a href="<?php echo $filelink ?>" data-preview-image="<?php echo $imagePreview ?>"
                                    title="<?php echo fm_enc($f) ?>">
                                <?php else: ?>
                                    <a href="<?php echo $filelink ?>" title="<?php echo $f ?>">
                                    <?php endif; ?>
                                    <i class="<?php echo $img ?>"></i> <?php echo fm_convert_win(fm_enc($f)) ?>
                                </a>
                                <?php echo ($is_link ? ' &rarr; <i>' . readlink($path . '/' . $f) . '</i>' : '') ?>
                        </div>
                    </td>
                    <td data-order="b-<?php echo str_pad($filesize_raw, 18, '0', STR_PAD_LEFT); ?>"><span
                            title="<?php printf('%s bytes', $filesize_raw) ?>"><?php echo $filesize; ?></span></td>
                    <td data-order="b-<?php echo $date_sorting; ?>"><?php echo $modif ?></td>
                    <?php if (!FM_IS_WIN && !$hide_Cols): ?>
                        <td><?php if (!FM_READONLY): ?><a
                                    href="?p=<?php echo urlencode(FM_PATH) ?>&amp;chmod=<?php echo urlencode($f) ?>"><?php echo $perms ?></a><?php else:
                            echo $perms;
                        endif; ?>
                        </td>
                        <td><?php echo fm_enc($owner['name'] . ':' . $group['name']) ?></td>
                    <?php endif; ?>
                    <td class="inline-actions">
                        <?php if (!FM_READONLY): ?>
                            <a title="<?php echo lng('Delete') ?>"
                                href="?p=<?php echo urlencode(FM_PATH) ?>&amp;del=<?php echo urlencode($f) ?>"
                                onclick="confirmDailog(event, 1209, '<?php echo lng('Delete') . ' ' . lng('File'); ?>','<?php echo urlencode($f); ?>', this.href);">
                                <i class="fa fa-trash-o"></i></a>
                            <a title="<?php echo lng('Rename') ?>" href="#"
                                onclick="rename('<?php echo fm_enc(addslashes(FM_PATH)) ?>', '<?php echo fm_enc(addslashes($f)) ?>');return false;"><i
                                    class="fa fa-pencil-square-o"></i></a>
                            <a title="<?php echo lng('CopyTo') ?>..."
                                href="?p=<?php echo urlencode(FM_PATH) ?>&amp;copy=<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) ?>"><i
                                    class="fa fa-files-o"></i></a>
                        <?php endif; ?>
                        <a title="<?php echo lng('DirectLink') ?>"
                            href="<?php echo fm_enc(FM_ROOT_URL . (FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $f) ?>"
                            target="_blank"><i class="fa fa-link"></i></a>
                        <a title="<?php echo lng('Download') ?>"
                            href="?p=<?php echo urlencode(FM_PATH) ?>&amp;dl=<?php echo urlencode($f) ?>"
                            onclick="confirmDailog(event, 1211, '<?php echo lng('Download'); ?>','<?php echo urlencode($f); ?>', this.href);">
                            <i class="fa fa-download"></i></a>
                    </td>
                </tr>
                <?php flush();
                $ik++;
            endforeach;

            if (empty($folders) && empty($files)): ?>
                <tfoot>
                    <tr>
                        <?php if (!FM_READONLY): ?>
                            <td></td><?php endif; ?>
                        <td colspan="<?php echo (!FM_IS_WIN && !$hide_Cols) ? '6' : '4' ?>">
                            <em><?php echo lng('Folder is empty') ?></em>
                        </td>
                    </tr>
                </tfoot>
            <?php else: ?>
                <tfoot>
                    <tr>
                        <td class="gray fs-7"
                            colspan="<?php echo (!FM_IS_WIN && !$hide_Cols) ? (FM_READONLY ? '6' : '7') : (FM_READONLY ? '4' : '5') ?>">
                            <?php echo lng('FullSize') . ': <span class="badge text-bg-light">' . fm_get_filesize($all_files_size) . '</span>' ?>
                            <?php echo lng('File') . ': <span class="badge text-bg-light">' . $num_files . '</span>' ?>
                            <?php echo lng('Folder') . ': <span class="badge text-bg-light">' . $num_folders . '</span>' ?>
                        </td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <div class="row">
        <?php if (!FM_READONLY): ?>
            <div class="col-xs-12 col-sm-9">
                <div class="btn-group flex-wrap" role="toolbar">
                    <a href="#/select-all" class="btn btn-small btn-outline-primary btn-2"
                        onclick="select_all();return false;"><i class="fa fa-check-square"></i>
                        <?php echo lng('SelectAll') ?></a>
                    <a href="#/unselect-all" class="btn btn-small btn-outline-primary btn-2"
                        onclick="unselect_all();return false;"><i class="fa fa-window-close"></i>
                        <?php echo lng('UnSelectAll') ?></a>
                    <a href="#/invert-all" class="btn btn-small btn-outline-primary btn-2"
                        onclick="invert_all();return false;"><i class="fa fa-th-list"></i>
                        <?php echo lng('InvertSelection') ?></a>
                    <input type="submit" class="hidden" name="delete" id="a-delete" value="Delete"
                        onclick="return confirm('<?php echo lng('Delete selected files and folders?'); ?>')">
                    <a href="javascript:document.getElementById('a-delete').click();"
                        class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-trash"></i>
                        <?php echo lng('Delete') ?></a>
                    <input type="submit" class="hidden" name="zip" id="a-zip" value="zip"
                        onclick="return confirm('<?php echo lng('Create archive?'); ?>')">
                    <a href="javascript:document.getElementById('a-zip').click();"
                        class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-file-archive-o"></i>
                        <?php echo lng('Zip') ?></a>
                    <input type="submit" class="hidden" name="tar" id="a-tar" value="tar"
                        onclick="return confirm('<?php echo lng('Create archive?'); ?>')">
                    <a href="javascript:document.getElementById('a-tar').click();"
                        class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-file-archive-o"></i>
                        <?php echo lng('Tar') ?></a>
                    <input type="submit" class="hidden" name="copy" id="a-copy" value="Copy">
                    <a href="javascript:document.getElementById('a-copy').click();"
                        class="btn btn-small btn-outline-primary btn-2"><i class="fa fa-files-o"></i>
                        <?php echo lng('Copy') ?></a>
                </div>
            </div>
            <div class="col-3 d-none d-sm-block"><a href="https://github.com/leomullerluiz/web-file-manager" target="_blank"
                    class="float-right text-muted">Web File Manager <?php echo VERSION; ?></a></div>
        <?php else: ?>
            <div class="col-12"><a href="https://github.com/leomullerluiz/web-file-manager" target="_blank"
                    class="float-right text-muted">Web File Manager <?php echo VERSION; ?></a></div>
        <?php endif; ?>
    </div>
</form>

<?php include __DIR__ . '/templates/footer.php'; ?>