<?php
/**
 * Archive & Config Classes
 */

class FM_Zipper
{
    private $zip;

    public function __construct()
    {
        $this->zip = new ZipArchive();
    }

    public function create($filename, $files)
    {
        $res = $this->zip->open($filename, ZipArchive::CREATE);
        if ($res !== true)
            return false;
        if (is_array($files)) {
            foreach ($files as $f) {
                $f = fm_clean_path($f);
                if (!$this->addFileOrDir($f)) {
                    $this->zip->close();
                    return false;
                }
            }
            $this->zip->close();
            return true;
        } else {
            if ($this->addFileOrDir($files)) {
                $this->zip->close();
                return true;
            }
            return false;
        }
    }

    public function unzip($filename, $path)
    {
        $res = $this->zip->open($filename);
        if ($res !== true)
            return false;
        if ($this->zip->extractTo($path)) {
            $this->zip->close();
            return true;
        }
        return false;
    }

    private function addFileOrDir($filename)
    {
        if (is_file($filename))
            return $this->zip->addFile($filename);
        elseif (is_dir($filename))
            return $this->addDir($filename);
        return false;
    }

    private function addDir($path)
    {
        if (!$this->zip->addEmptyDir($path))
            return false;
        $objects = scandir($path);
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (is_dir($path . '/' . $file)) {
                        if (!$this->addDir($path . '/' . $file))
                            return false;
                    } elseif (is_file($path . '/' . $file)) {
                        if (!$this->zip->addFile($path . '/' . $file))
                            return false;
                    }
                }
            }
            return true;
        }
        return false;
    }
}

class FM_Zipper_Tar
{
    private $tar;

    public function __construct()
    {
        $this->tar = null;
    }

    public function create($filename, $files)
    {
        $this->tar = new PharData($filename);
        if (is_array($files)) {
            foreach ($files as $f) {
                $f = fm_clean_path($f);
                if (!$this->addFileOrDir($f))
                    return false;
            }
            return true;
        } else {
            return $this->addFileOrDir($files);
        }
    }

    public function unzip($filename, $path)
    {
        $res = $this->tar->open($filename);
        if ($res !== true)
            return false;
        return $this->tar->extractTo($path) ? true : false;
    }

    private function addFileOrDir($filename)
    {
        if (is_file($filename)) {
            try {
                $this->tar->addFile($filename);
                return true;
            } catch (Exception $e) {
                return false;
            }
        } elseif (is_dir($filename)) {
            return $this->addDir($filename);
        }
        return false;
    }

    private function addDir($path)
    {
        $objects = scandir($path);
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (is_dir($path . '/' . $file)) {
                        if (!$this->addDir($path . '/' . $file))
                            return false;
                    } elseif (is_file($path . '/' . $file)) {
                        try {
                            $this->tar->addFile($path . '/' . $file);
                        } catch (Exception $e) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }
}

class FM_Config
{
    var $data;

    function __construct()
    {
        global $CONFIG;
        $this->data = array(
            'lang' => 'en',
            'error_reporting' => true,
            'show_hidden' => true
        );
        $data = false;
        if (strlen($CONFIG)) {
            $data = fm_object_to_array(json_decode($CONFIG));
        }
        if (is_array($data) && count($data))
            $this->data = $data;
        else
            $this->save();
    }

    function save()
    {
        $settings_file = __DIR__ . '/../settings.json';
        $json = json_encode($this->data, JSON_PRETTY_PRINT);
        @file_put_contents($settings_file, $json);
    }
}
