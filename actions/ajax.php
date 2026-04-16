<?php
/**
 * AJAX Request Handlers
 * Handles: search, save, backup, settings, pwdhash, upload from URL
 */

if (!auth_is_logged_in()) {
    header('HTTP/1.0 401 Unauthorized');
    die("Unauthorized.");
}

if (!isset($_POST['ajax'], $_POST['token'])) {
    return;
}

if (!verifyToken($_POST['token'])) {
    header('HTTP/1.0 401 Unauthorized');
    die("Invalid Token.");
}

// Search
if (isset($_POST['type']) && $_POST['type'] == 'search') {
    $dir = $_POST['path'] == '.' ? '' : $_POST['path'];
    $response = scan(fm_clean_path($dir), $_POST['content']);
    echo json_encode($response);
    exit();
}

if (FM_READONLY) {
    exit();
}

// Save editor file
if (isset($_POST['type']) && $_POST['type'] == 'save') {
    $path = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $path .= '/' . FM_PATH;
    }
    if (!is_dir($path)) {
        fm_redirect(FM_SELF_URL . '?p=');
    }
    $file = $_GET['edit'];
    $file = fm_clean_path($file);
    $file = str_replace('/', '', $file);
    if ($file == '' || !is_file($path . '/' . $file)) {
        fm_set_msg(lng('File not found'), 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    header('X-XSS-Protection:0');
    $file_path = $path . '/' . $file;
    $writedata = $_POST['content'];
    $fd = fopen($file_path, 'w');
    $write_results = @fwrite($fd, $writedata);
    fclose($fd);
    if ($write_results === false) {
        header('HTTP/1.1 500 Internal Server Error');
        die('Could Not Write File! - Check Permissions / Ownership');
    }
    die(true);
}

// Backup file
if (isset($_POST['type']) && $_POST['type'] == 'backup' && !empty($_POST['file'])) {
    $fileName = fm_clean_path($_POST['file']);
    $fullPath = FM_ROOT_PATH . '/';
    if (!empty($_POST['path'])) {
        $relativeDirPath = fm_clean_path($_POST['path']);
        $fullPath .= "{$relativeDirPath}/";
    }
    $date = date('dMy-His');
    $newFileName = "{$fileName}-{$date}.bak";
    $fullyQualifiedFileName = $fullPath . $fileName;
    try {
        if (!file_exists($fullyQualifiedFileName)) {
            throw new Exception("File {$fileName} not found");
        }
        if (copy($fullyQualifiedFileName, $fullPath . $newFileName)) {
            echo "Backup {$newFileName} created";
        } else {
            throw new Exception("Could not copy file {$fileName}");
        }
    } catch (Exception $e) {
        echo $e->getMessage();
    }
    exit();
}

// Save Config/Settings
if (isset($_POST['type']) && $_POST['type'] == 'settings') {
    global $cfg, $lang, $report_errors, $show_hidden_files, $lang_list, $hide_Cols, $theme;
    $newLng = $_POST['js-language'];
    fm_get_translations([]);
    if (!array_key_exists($newLng, $lang_list)) {
        $newLng = 'en';
    }
    $erp = isset($_POST['js-error-report']) && $_POST['js-error-report'] == 'true' ? true : false;
    $shf = isset($_POST['js-show-hidden']) && $_POST['js-show-hidden'] == 'true' ? true : false;
    $hco = isset($_POST['js-hide-cols']) && $_POST['js-hide-cols'] == 'true' ? true : false;
    $te3 = $_POST['js-theme-3'];

    if ($cfg->data['lang'] != $newLng) {
        $cfg->data['lang'] = $newLng;
        $lang = $newLng;
    }
    if ($cfg->data['error_reporting'] != $erp) {
        $cfg->data['error_reporting'] = $erp;
        $report_errors = $erp;
    }
    if ($cfg->data['show_hidden'] != $shf) {
        $cfg->data['show_hidden'] = $shf;
        $show_hidden_files = $shf;
    }
    if ($cfg->data['hide_Cols'] != $hco) {
        $cfg->data['hide_Cols'] = $hco;
        $hide_Cols = $hco;
    }
    if ($cfg->data['theme'] != $te3) {
        $cfg->data['theme'] = $te3;
        $theme = $te3;
    }
    $cfg->save();
    echo true;
    exit();
}

// Generate password hash
if (isset($_POST['type']) && $_POST['type'] == 'pwdhash') {
    $res = isset($_POST['inputPassword2']) && !empty($_POST['inputPassword2'])
        ? password_hash($_POST['inputPassword2'], PASSWORD_DEFAULT) : '';
    echo $res;
    exit();
}

// Upload from URL
if (isset($_POST['type']) && $_POST['type'] == 'upload' && !empty($_REQUEST['uploadurl'])) {
    $path = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $path .= '/' . FM_PATH;
    }

    function event_callback($message)
    {
        echo json_encode($message);
    }

    function get_file_path()
    {
        global $path, $fileinfo;
        return $path . '/' . basename($fileinfo->name);
    }

    $url = !empty($_REQUEST['uploadurl']) && preg_match('|^http(s)?://.+$|', stripslashes($_REQUEST['uploadurl']))
        ? stripslashes($_REQUEST['uploadurl']) : null;

    $domain = parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT);
    $knownPorts = [22, 23, 25, 3306];

    if (preg_match('/^localhost$|^127(?:\.[0-9]+){0,2}\.[0-9]+$|^(?:0*\:)*?:?0*1$/i', $domain) || in_array($port, $knownPorts)) {
        $err = ['message' => 'URL is not allowed'];
        event_callback(['fail' => $err]);
        exit();
    }

    $temp_file = tempnam(sys_get_temp_dir(), 'upload-');
    $fileinfo = new stdClass();
    $fileinfo->name = trim(urldecode(basename($url)), ".\x00..\x20");

    $allowed = FM_UPLOAD_EXTENSION ? explode(',', FM_UPLOAD_EXTENSION) : false;
    $ext = strtolower(pathinfo($fileinfo->name, PATHINFO_EXTENSION));
    $isFileAllowed = ($allowed) ? in_array($ext, $allowed) : true;

    if (!$isFileAllowed) {
        event_callback(['fail' => ['message' => 'File extension is not allowed']]);
        exit();
    }

    $err = false;
    if (!$url) {
        $success = false;
    } else {
        $ctx = stream_context_create();
        @$success = copy($url, $temp_file, $ctx);
        if (!$success) {
            $err = error_get_last();
        }
    }

    if ($success) {
        $success = rename($temp_file, strtok(get_file_path(), '?'));
    }

    if ($success) {
        event_callback(['done' => $fileinfo]);
    } else {
        @unlink($temp_file);
        if (!$err) {
            $err = ['message' => 'Invalid url parameter'];
        }
        event_callback(['fail' => $err]);
    }
    exit();
}
