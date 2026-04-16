<?php
/**
 * Web File Manager - Configuration
 * Based on Tiny File Manager V1.0.0
 */

// Raiz do projeto (pasta onde está o index.php)
define('FM_APP_ROOT', __DIR__);

// Pasta padrão para armazenar arquivos de clientes
define('FM_CLIENTS_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'clients_files');

// --- DATABASE CONFIGURATION ---
define('DB_HOST', 'localhost:3306');
define('DB_NAME', 'file_manager');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- APPLICATION CONFIGURATION ---
$CONFIG = '{"lang":"en","error_reporting":false,"show_hidden":false,"hide_Cols":false,"theme":"light"}';

define('VERSION', '1.0.0');
define('APP_TITLE', 'File Manager');

// Enable highlight.js on view page
$use_highlightjs = true;
$highlightjs_style = 'vs';

// Enable ace.js editor
$edit_files = true;

// Default timezone
$default_timezone = 'Etc/UTC';

// Root path for file manager (used as base for admin users)
$root_path = $_SERVER['DOCUMENT_ROOT'];

// Root url for links
$root_url = '';

// Server hostname
$http_host = $_SERVER['HTTP_HOST'];

// Input encoding for iconv
$iconv_input_encoding = 'UTF-8';

// Date format
$datetime_format = 'm/d/Y g:i A';

// Path display mode: 'full', 'relative', 'host'
$path_display_mode = 'full';

// Allowed file extensions for create/rename (empty = all)
$allowed_file_extensions = '';

// Allowed file extensions for upload (empty = all)
$allowed_upload_extensions = '';

// Favicon path
$favicon_path = '';

// Excluded files/folders from listing
$exclude_items = array();

// Online office viewer: 'google', 'microsoft', or false
$online_viewer = 'google';

// Sticky navbar
$sticky_navbar = true;

// Max upload size (~5GB)
$max_upload_size_bytes = 5000000000;

// Upload chunk size (~2MB)
$upload_chunk_size_bytes = 2000000;

// IP ruleset: 'OFF', 'AND', 'OR'
$ip_ruleset = 'OFF';
$ip_silent = true;
$ip_whitelist = array('127.0.0.1', '::1');
$ip_blacklist = array('0.0.0.0', '::');

// Global readonly mode
$global_readonly = false;

// Password reset settings
define('RESET_TOKEN_EXPIRY_HOURS', 24);
define('APP_URL', 'http://localhost/web-file-manager');

// Email settings (used for password reset)
define('MAIL_FROM', 'noreply@example.com');
define('MAIL_FROM_NAME', 'File Manager');

// External CDN resources
$external = array(
    'css-bootstrap' => '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">',
    'css-dropzone' => '<link href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.css" rel="stylesheet">',
    'css-font-awesome' => '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" crossorigin="anonymous">',
    'css-highlightjs' => '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/' . $highlightjs_style . '.min.css">',
    'js-ace' => '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.2/ace.js"></script>',
    'js-bootstrap' => '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>',
    'js-dropzone' => '<script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>',
    'js-jquery' => '<script src="https://code.jquery.com/jquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>',
    'js-jquery-datatables' => '<script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js" crossorigin="anonymous" defer></script>',
    'js-highlightjs' => '<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>',
    'pre-jsdelivr' => '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin/><link rel="dns-prefetch" href="https://cdn.jsdelivr.net"/>',
    'pre-cloudflare' => '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin/><link rel="dns-prefetch" href="https://cdnjs.cloudflare.com"/>'
);

// Session name
if (!defined('FM_SESSION_ID')) {
    define('FM_SESSION_ID', 'filemanager');
}

define('MAX_UPLOAD_SIZE', $max_upload_size_bytes);
define('UPLOAD_CHUNK_SIZE', $upload_chunk_size_bytes);
