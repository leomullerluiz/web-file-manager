<?php
/**
 * File Operations Handler
 * Handles: delete, create, copy/move, rename, download, upload, mass operations, chmod
 */

if (!auth_is_logged_in()) {
    fm_redirect(FM_SELF_URL);
}

// Delete file / folder
if (isset($_GET['del'], $_POST['token']) && !FM_READONLY) {
    $del = str_replace('/', '', fm_clean_path($_GET['del']));
    if ($del != '' && $del != '..' && $del != '.' && verifyToken($_POST['token'])) {
        $path = FM_ROOT_PATH;
        if (FM_PATH != '')
            $path .= '/' . FM_PATH;
        $is_dir = is_dir($path . '/' . $del);
        if (fm_rdelete($path . '/' . $del)) {
            $msg = $is_dir ? lng('Folder') . ' <b>%s</b> ' . lng('Deleted') : lng('File') . ' <b>%s</b> ' . lng('Deleted');
            fm_set_msg(sprintf($msg, fm_enc($del)));
        } else {
            $msg = $is_dir ? lng('Folder') . ' <b>%s</b> ' . lng('not deleted') : lng('File') . ' <b>%s</b> ' . lng('not deleted');
            fm_set_msg(sprintf($msg, fm_enc($del)), 'error');
        }
    } else {
        fm_set_msg(lng('Invalid file or folder name'), 'error');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Create new file/folder
if (isset($_POST['newfilename'], $_POST['newfile'], $_POST['token']) && !FM_READONLY) {
    $type = urldecode($_POST['newfile']);
    $new = str_replace('/', '', fm_clean_path(strip_tags($_POST['newfilename'])));
    if (fm_isvalid_filename($new) && $new != '' && $new != '..' && $new != '.' && verifyToken($_POST['token'])) {
        $path = FM_ROOT_PATH;
        if (FM_PATH != '')
            $path .= '/' . FM_PATH;
        if ($type == 'file') {
            if (!file_exists($path . '/' . $new)) {
                if (fm_is_valid_ext($new)) {
                    @fopen($path . '/' . $new, 'w') or die('Cannot open file: ' . $new);
                    fm_set_msg(sprintf(lng('File') . ' <b>%s</b> ' . lng('Created'), fm_enc($new)));
                } else {
                    fm_set_msg(lng('File extension is not allowed'), 'error');
                }
            } else {
                fm_set_msg(sprintf(lng('File') . ' <b>%s</b> ' . lng('already exists'), fm_enc($new)), 'alert');
            }
        } else {
            if (fm_mkdir($path . '/' . $new, false) === true) {
                fm_set_msg(sprintf(lng('Folder') . ' <b>%s</b> ' . lng('Created'), $new));
            } elseif (fm_mkdir($path . '/' . $new, false) === $path . '/' . $new) {
                fm_set_msg(sprintf(lng('Folder') . ' <b>%s</b> ' . lng('already exists'), fm_enc($new)), 'alert');
            } else {
                fm_set_msg(sprintf(lng('Folder') . ' <b>%s</b> ' . lng('not created'), fm_enc($new)), 'error');
            }
        }
    } else {
        fm_set_msg(lng('Invalid characters in file or folder name'), 'error');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Single copy/move
if (isset($_GET['copy'], $_GET['finish']) && !FM_READONLY) {
    $copy = fm_clean_path(urldecode($_GET['copy']));
    if ($copy == '') {
        fm_set_msg(lng('Source path not defined'), 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    $from = FM_ROOT_PATH . '/' . $copy;
    $dest = FM_ROOT_PATH;
    if (FM_PATH != '')
        $dest .= '/' . FM_PATH;
    $dest .= '/' . basename($from);
    $move = isset($_GET['move']);

    if ($from != $dest) {
        $msg_from = trim(FM_PATH . '/' . basename($from), '/');
        if ($move) {
            $rename = fm_rename($from, $dest);
            if ($rename) {
                fm_set_msg(sprintf(lng('Moved from') . ' <b>%s</b> ' . lng('to') . ' <b>%s</b>', fm_enc($copy), fm_enc($msg_from)));
            } elseif ($rename === null) {
                fm_set_msg(lng('File or folder with this path already exists'), 'alert');
            } else {
                fm_set_msg(sprintf(lng('Error while moving from') . ' <b>%s</b> ' . lng('to') . ' <b>%s</b>', fm_enc($copy), fm_enc($msg_from)), 'error');
            }
        } else {
            if (fm_rcopy($from, $dest)) {
                fm_set_msg(sprintf(lng('Copied from') . ' <b>%s</b> ' . lng('to') . ' <b>%s</b>', fm_enc($copy), fm_enc($msg_from)));
            } else {
                fm_set_msg(sprintf(lng('Error while copying from') . ' <b>%s</b> ' . lng('to') . ' <b>%s</b>', fm_enc($copy), fm_enc($msg_from)), 'error');
            }
        }
    } else {
        if (!$move) {
            $fn_parts = pathinfo($from);
            $extension_suffix = !is_dir($from) ? '.' . $fn_parts['extension'] : '';
            $fn_duplicate = $fn_parts['dirname'] . '/' . $fn_parts['filename'] . '-' . date('YmdHis') . $extension_suffix;
            $loop_count = 0;
            while (file_exists($fn_duplicate) && $loop_count < 1000) {
                $fn_parts = pathinfo($fn_duplicate);
                $fn_duplicate = $fn_parts['dirname'] . '/' . $fn_parts['filename'] . '-copy' . $extension_suffix;
                $loop_count++;
            }
            if (fm_rcopy($from, $fn_duplicate, false)) {
                fm_set_msg(sprintf('Copied from <b>%s</b> to <b>%s</b>', fm_enc($copy), fm_enc($fn_duplicate)));
            } else {
                fm_set_msg(sprintf('Error while copying from <b>%s</b> to <b>%s</b>', fm_enc($copy), fm_enc($fn_duplicate)), 'error');
            }
        } else {
            fm_set_msg(lng('Paths must be not equal'), 'alert');
        }
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Mass copy/move
if (isset($_POST['file'], $_POST['copy_to'], $_POST['finish'], $_POST['token']) && !FM_READONLY) {
    if (!verifyToken($_POST['token'])) {
        fm_set_msg(lng('Invalid Token.'), 'error');
        die('Invalid Token.');
    }
    $path = FM_ROOT_PATH;
    if (FM_PATH != '')
        $path .= '/' . FM_PATH;
    $copy_to_path = FM_ROOT_PATH;
    $copy_to = fm_clean_path($_POST['copy_to']);
    if ($copy_to != '')
        $copy_to_path .= '/' . $copy_to;
    if ($path == $copy_to_path) {
        fm_set_msg(lng('Paths must be not equal'), 'alert');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    if (!is_dir($copy_to_path) && !fm_mkdir($copy_to_path, true)) {
        fm_set_msg('Unable to create destination folder', 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    $move = isset($_POST['move']);
    $errors = 0;
    $files = $_POST['file'];
    if (is_array($files) && count($files)) {
        foreach ($files as $f) {
            if ($f != '') {
                $f = fm_clean_path($f);
                $from = $path . '/' . $f;
                $dest = $copy_to_path . '/' . $f;
                if ($move) {
                    if (fm_rename($from, $dest) === false)
                        $errors++;
                } else {
                    if (!fm_rcopy($from, $dest))
                        $errors++;
                }
            }
        }
        if ($errors == 0) {
            fm_set_msg($move ? 'Selected files and folders moved' : 'Selected files and folders copied');
        } else {
            fm_set_msg($move ? 'Error while moving items' : 'Error while copying items', 'error');
        }
    } else {
        fm_set_msg(lng('Nothing selected'), 'alert');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Rename
if (isset($_POST['rename_from'], $_POST['rename_to'], $_POST['token']) && !FM_READONLY) {
    if (!verifyToken($_POST['token'])) {
        fm_set_msg('Invalid Token.', 'error');
        die('Invalid Token.');
    }
    $old = str_replace('/', '', fm_clean_path(urldecode($_POST['rename_from'])));
    $new = str_replace('/', '', fm_clean_path(strip_tags(urldecode($_POST['rename_to']))));
    $path = FM_ROOT_PATH;
    if (FM_PATH != '')
        $path .= '/' . FM_PATH;
    if (fm_isvalid_filename($new) && $old != '' && $new != '') {
        if (fm_rename($path . '/' . $old, $path . '/' . $new)) {
            fm_set_msg(sprintf(lng('Renamed from') . ' <b>%s</b> ' . lng('to') . ' <b>%s</b>', fm_enc($old), fm_enc($new)));
        } else {
            fm_set_msg(sprintf(lng('Error while renaming from') . ' <b>%s</b> ' . lng('to') . ' <b>%s</b>', fm_enc($old), fm_enc($new)), 'error');
        }
    } else {
        fm_set_msg(lng('Invalid characters in file name'), 'error');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Download
if (isset($_GET['dl'], $_POST['token'])) {
    if (!verifyToken($_POST['token'])) {
        fm_set_msg('Invalid Token.', 'error');
        exit;
    }
    $dl = str_replace('/', '', fm_clean_path(urldecode($_GET['dl'])));
    $path = FM_ROOT_PATH;
    if (FM_PATH != '')
        $path .= '/' . FM_PATH;
    if ($dl != '' && is_file($path . '/' . $dl)) {
        if (session_status() === PHP_SESSION_ACTIVE)
            session_write_close();
        fm_download_file($path . '/' . $dl, $dl, 1024);
        exit;
    } else {
        fm_set_msg(lng('File not found'), 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
}

// Upload (multipart)
if (!empty($_FILES) && !FM_READONLY) {
    if (isset($_POST['token'])) {
        if (!verifyToken($_POST['token'])) {
            echo json_encode(['status' => 'error', 'info' => 'Invalid Token.']);
            exit();
        }
    } else {
        echo json_encode(['status' => 'error', 'info' => 'Token Missing.']);
        exit();
    }

    $chunkIndex = $_POST['dzchunkindex'];
    $chunkTotal = $_POST['dztotalchunkcount'];
    $fullPathInput = fm_clean_path($_REQUEST['fullpath']);
    $f = $_FILES;
    $path = FM_ROOT_PATH;
    if (FM_PATH != '')
        $path .= '/' . FM_PATH;

    $allowed = FM_UPLOAD_EXTENSION ? explode(',', FM_UPLOAD_EXTENSION) : false;
    $response = ['status' => 'error', 'info' => 'Oops! Try again'];

    $filename = $f['file']['name'];
    $tmp_name = $f['file']['tmp_name'];
    $ext = pathinfo($filename, PATHINFO_FILENAME) != '' ? strtolower(pathinfo($filename, PATHINFO_EXTENSION)) : '';
    $isFileAllowed = ($allowed) ? in_array($ext, $allowed) : true;

    if (!fm_isvalid_filename($filename) && !fm_isvalid_filename($fullPathInput)) {
        echo json_encode(['status' => 'error', 'info' => 'Invalid File name!']);
        exit();
    }

    $targetPath = $path . DIRECTORY_SEPARATOR;
    if (is_writable($targetPath)) {
        $fullPath = $path . '/' . $fullPathInput;
        $folder = substr($fullPath, 0, strrpos($fullPath, '/'));
        if (!is_dir($folder)) {
            $old = umask(0);
            mkdir($folder, 0777, true);
            umask($old);
        }

        if (empty($f['file']['error']) && !empty($tmp_name) && $tmp_name != 'none' && $isFileAllowed) {
            if ($chunkTotal) {
                $out = @fopen("{$fullPath}.part", $chunkIndex == 0 ? 'wb' : 'ab');
                if ($out) {
                    $in = @fopen($tmp_name, 'rb');
                    if ($in) {
                        stream_copy_to_stream($in, $out);
                        $response = ['status' => 'success', 'info' => 'file upload successful'];
                    }
                    @fclose($in);
                    @fclose($out);
                    @unlink($tmp_name);
                    if ($chunkIndex == $chunkTotal - 1) {
                        if (file_exists($fullPath)) {
                            $ext_1 = $ext ? '.' . $ext : '';
                            $fullPathTarget = $path . '/' . basename($fullPathInput, $ext_1) . '_' . date('ymdHis') . $ext_1;
                        } else {
                            $fullPathTarget = $fullPath;
                        }
                        rename("{$fullPath}.part", $fullPathTarget);
                    }
                    $response = ['status' => 'success', 'info' => 'file upload successful'];
                }
            } else if (move_uploaded_file($tmp_name, $fullPath)) {
                $response = file_exists($fullPath)
                    ? ['status' => 'success', 'info' => 'file upload successful']
                    : ['status' => 'error', 'info' => "Couldn't upload the requested file."];
            } else {
                $response = ['status' => 'error', 'info' => 'Error while uploading files.'];
            }
        }
    } else {
        $response = ['status' => 'error', 'info' => "The specified folder for upload isn't writeable."];
    }
    echo json_encode($response);
    exit();
}

// Mass delete
if (isset($_POST['group'], $_POST['delete'], $_POST['token']) && !FM_READONLY) {
    if (!verifyToken($_POST['token'])) {
        fm_set_msg(lng('Invalid Token.'), 'error');
        die('Invalid Token.');
    }
    $path = FM_ROOT_PATH;
    if (FM_PATH != '')
        $path .= '/' . FM_PATH;
    $errors = 0;
    $files = $_POST['file'];
    if (is_array($files) && count($files)) {
        foreach ($files as $f) {
            if ($f != '') {
                $new_path = $path . '/' . $f;
                if (!fm_rdelete($new_path))
                    $errors++;
            }
        }
        if ($errors == 0) {
            fm_set_msg(lng('Selected files and folder deleted'));
        } else {
            fm_set_msg(lng('Error while deleting items'), 'error');
        }
    } else {
        fm_set_msg(lng('Nothing selected'), 'alert');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Pack zip/tar
if (isset($_POST['group'], $_POST['token']) && (isset($_POST['zip']) || isset($_POST['tar'])) && !FM_READONLY) {
    if (!verifyToken($_POST['token'])) {
        fm_set_msg(lng('Invalid Token.'), 'error');
        die('Invalid Token.');
    }
    $path = FM_ROOT_PATH;
    if (FM_PATH != '')
        $path .= '/' . FM_PATH;
    $ext = isset($_POST['tar']) ? 'tar' : 'zip';
    if (($ext == 'zip' && !class_exists('ZipArchive')) || ($ext == 'tar' && !class_exists('PharData'))) {
        fm_set_msg(lng('Operations with archives are not available'), 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    $files = array_map('fm_clean_path', $_POST['file']);
    if (!empty($files)) {
        chdir($path);
        $zipname = (count($files) == 1)
            ? basename(reset($files)) . '_' . date('ymd_His') . '.' . $ext
            : 'archive_' . date('ymd_His') . '.' . $ext;
        if ($ext == 'zip') {
            $zipper = new FM_Zipper();
            $res = $zipper->create($zipname, $files);
        } else {
            $tar = new FM_Zipper_Tar();
            $res = $tar->create($zipname, $files);
        }
        if ($res) {
            fm_set_msg(sprintf(lng('Archive') . ' <b>%s</b> ' . lng('Created'), fm_enc($zipname)));
        } else {
            fm_set_msg(lng('Archive not created'), 'error');
        }
    } else {
        fm_set_msg(lng('Nothing selected'), 'alert');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Unpack zip/tar
if (isset($_POST['unzip'], $_POST['token']) && !FM_READONLY) {
    if (!verifyToken($_POST['token'])) {
        fm_set_msg(lng('Invalid Token.'), 'error');
        die('Invalid Token.');
    }
    $unzip = str_replace('/', '', fm_clean_path(urldecode($_POST['unzip'])));
    $path = FM_ROOT_PATH;
    if (FM_PATH != '')
        $path .= '/' . FM_PATH;
    $isValid = false;
    if ($unzip != '' && is_file($path . '/' . $unzip)) {
        $zip_path = $path . '/' . $unzip;
        $ext = pathinfo($zip_path, PATHINFO_EXTENSION);
        $isValid = true;
    } else {
        fm_set_msg(lng('File not found'), 'error');
    }
    if ($isValid) {
        $tofolder = '';
        if (isset($_POST['tofolder'])) {
            $tofolder = pathinfo($zip_path, PATHINFO_FILENAME);
            if (fm_mkdir($path . '/' . $tofolder, true))
                $path .= '/' . $tofolder;
        }
        if ($ext == 'zip') {
            $zipper = new FM_Zipper();
            $res = $zipper->unzip($zip_path, $path);
        } else {
            try {
                $gzipper = new PharData($zip_path);
                $res = @$gzipper->extractTo($path, null, true) ? true : false;
            } catch (Exception $e) {
                $res = true;
            }
        }
        fm_set_msg($res ? lng('Archive unpacked') : lng('Archive not unpacked'), $res ? 'ok' : 'error');
    } else {
        fm_set_msg(lng('File not found'), 'error');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Change permissions (not for Windows)
if (isset($_POST['chmod'], $_POST['token']) && !FM_READONLY && !FM_IS_WIN) {
    if (!verifyToken($_POST['token'])) {
        fm_set_msg(lng('Invalid Token.'), 'error');
        die('Invalid Token.');
    }
    $path = FM_ROOT_PATH;
    if (FM_PATH != '')
        $path .= '/' . FM_PATH;
    $file = str_replace('/', '', fm_clean_path($_POST['chmod']));
    if ($file == '' || (!is_file($path . '/' . $file) && !is_dir($path . '/' . $file))) {
        fm_set_msg(lng('File not found'), 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    $mode = 0;
    if (!empty($_POST['ur']))
        $mode |= 0400;
    if (!empty($_POST['uw']))
        $mode |= 0200;
    if (!empty($_POST['ux']))
        $mode |= 0100;
    if (!empty($_POST['gr']))
        $mode |= 0040;
    if (!empty($_POST['gw']))
        $mode |= 0020;
    if (!empty($_POST['gx']))
        $mode |= 0010;
    if (!empty($_POST['or']))
        $mode |= 0004;
    if (!empty($_POST['ow']))
        $mode |= 0002;
    if (!empty($_POST['ox']))
        $mode |= 0001;
    if (@chmod($path . '/' . $file, $mode)) {
        fm_set_msg(lng('Permissions changed'));
    } else {
        fm_set_msg(lng('Permissions not changed'), 'error');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}
