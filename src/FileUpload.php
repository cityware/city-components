<?php

namespace Cityware\Components;

/**
 * FileUpload
 * A complete class to upload files with php 5 or higher, but the best: very simple to use.
 *
 * @package FileUpload
 */
class FileUpload
{
    /**
     * Upload function name
     * Remember:
     * Default function: move_uploaded_file
     * Native options:
     * - move_uploaded_file (Default and best option)
     * - copy
     *
     * @var mixex
     */
    private $upload_function = "move_uploaded_file";

    /**
     * Array with the information obtained from the
     * variable $_FILES or $HTTP_POST_FILES.
     *
     * @var array
     */
    private $file_array = array();

    /**
     * If the file you are trying to upload already exists it will
     * be overwritten if you set the variable to true.
     *
     * @var boolean
     */
    private $overwrite_file = false;

    /**
     * Input element
     * Example:
     * <input type="file" name="file" />
     * Result:
     * FileUpload::$input = file
     *
     * @var string
     */
    private $input;

    /**
     * Path output
     *
     * @var string
     */
    private $destination_directory;

    /**
     * Output filename
     *
     * @var string
     */
    private $filename;

    /**
     * Max file size
     *
     * @var float
     */
    private $max_file_size = 0.0;

    /**
     * List of allowed mime types
     *
     * @var array
     */
    private $allowed_mime_types = array();

    /**
     * Callbacks
     *
     * @var array
     */
    private $callbacks = array("before" => null, "after" => null);

    /**
     * File object
     *
     * @var object
     */
    private $file;

    /**
     * Helping mime types
     *
     * @var array
     */
    private $mime_helping = array('text' => array('text/plain',),
        'image' => array(
            'image/jpeg',
            'image/jpg',
            'image/pjpeg',
            'image/png',
            'image/gif',
        ),
        'document' => array(
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-powerpoint',
            'application/vnd.ms-excel',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
        ),
        'video' => array(
            'video/3gpp',
            'video/3gpp',
            'video/x-msvideo',
            'video/avi',
            'video/mpeg4',
            'video/mp4',
            'video/mpeg',
            'video/mpg',
            'video/quicktime',
            'video/x-sgi-movie',
            'video/x-ms-wmv',
            'video/x-flv',
        ),
    );

    /**
     * Construct
     *
     * @return object
     */
    public function __construct()
    {
        $this->file = array(
            "status" => false, // True: success upload
            "mime" => "", // Empty string
            "filename" => "", // Empty string
            "original" => "", // Empty string
            "size" => 0, // 0 Bytes
            "sizeFormated" => "0B", // 0 Bytes
            "destination" => "./", // Default: ./
            "allowed_mime_types" => array(), // Allowed mime types
            "log" => array(), // Logs
            "error" => 0, // File error
        );

        // Change dir to current dir
        $this->destination_directory = dirname(__FILE__) . DIRECTORY_SEPARATOR;
        chdir($this->destination_directory);

        // Set file array
        if (isset($_FILES) AND is_array($_FILES)) {
            $this->file_array = $_FILES;
        } elseif (isset($HTTP_POST_FILES) AND is_array($HTTP_POST_FILES)) {
            $this->file_array = $HTTP_POST_FILES;
        }
    }

    /**
     * Set input.
     * If you have $_FILES["file"], you must use the key "file"
     * Example:
     * $object->setInput("file");
     *
     * @param  string  $input
     * @return boolean
     */
    public function setInput($input)
    {
        if (!empty($input) AND (is_string($input) or is_numeric($input) )) {
            $this->log("Capture set to %s", $input);
            $this->input = $input;

            return true;
        }

        return false;
    }

    /**
     * Set new filename
     * Example:
     * FileUpload::setFilename("new file.txt")
     * Remember:
     * Use %s to retrive file extension
     *
     * @param  string  $filename
     * @return boolean
     */
    public function setFilename($filename)
    {
        if ($this->isFilename($filename)) {
            $this->log("Filename set to %s", $filename);
            $this->filename = $filename;

            return true;
        }

        return false;
    }

    /**
     * Set automatic filename
     *
     * @param  string  $extension
     * @return boolean
     */
    public function setAutoFilename()
    {
        $this->log("Automatic filename is enabled");
        $this->filename = sha1(mt_rand(1, 9999) . uniqid());
        $this->filename .= time();
        $this->filename .= ".%s";
        $this->log("Filename set to %s", $this->filename);

        return true;
    }

    /**
     * Set file size limit
     *
     * @param  integer $file_size
     * @return boolean
     */
    public function setMaxFileSize($file_size)
    {
        $file_size = $this->sizeInBytes($file_size);
        if (is_numeric($file_size) AND $file_size > -1) {
            // Get php config
            $size = $this->sizeInBytes(ini_get('upload_max_filesize'));
            $this->log("PHP settings have set the maximum file upload size to %s(%d)", $this->sizeFormat($size), $size
            );

            // Calculate difference
            if ($size < $file_size) {
                $this->log(
                        "WARNING! The PHP configuration allows a maximum size of %s", $this->sizeFormat($size));

                return false;
            }

            $this->log("[INFO]Maximum allowed size set at %s(%d)", $this->sizeFormat($file_size), $file_size
            );

            $this->max_file_size = $file_size;

            return true;
        }

        return false;
    }

    /**
     * Set array mime types
     *
     * @param  array   $mimes
     * @return boolean
     */
    public function setAllowedMimeTypes(array $mimes)
    {
        if (count($mimes) > 0) {
            array_map(array($this, "setAllowMimeType"), $mimes);

            return true;
        }

        return false;
    }

    /**
     * Set input callback
     *
     * @param  mixed   $callback
     * @return boolean
     */
    public function setCallbackInput($callback)
    {
        if (is_callable($callback, false)) {
            $this->callbacks["input"] = $callback;

            return true;
        }

        return false;
    }

    /**
     * Set output callback
     *
     * @param  mixed   $callback
     * @return boolean
     */
    public function setCallbackOutput($callback)
    {
        if (is_callable($callback, false)) {
            $this->callbacks["output"] = $callback;

            return true;
        }

        return false;
    }

    /**
     * Append a mime type to allowed mime types
     *
     * @param  string  $mime
     * @return boolean
     */
    public function setAllowMimeType($mime)
    {
        if (!empty($mime) AND is_string($mime)) {
            if (preg_match("#^[-\w\+]+/[-\w\+]+$#", $mime)) {
                $this->log("IMPORTANT! Mime %s enabled", $mime);
                $this->allowed_mime_types[] = strtolower($mime);
                $this->file["allowed_mime_types"][] = strtolower($mime);

                return true;
            } else {
                return $this->setMimeHelping($mime);
            }
        }

        return false;
    }

    /**
     * Set allowed mime types from mime helping
     *
     * @return boolean
     */
    public function setMimeHelping($name)
    {
        if (!empty($mime) and is_string($name)) {
            if (array_key_exists($name, $this->mime_helping)) {
                return $this->setAllowedMimeTypes($this->mime_helping[$name]);
            }
        }

        return false;
    }

    /**
     * Set function to upload file
     * Examples:
     * 1.- FileUpload::setUploadFunction("move_uploaded_file");
     * 2.- FileUpload::setUploadFunction("copy");
     *
     * @param  string  $mime
     * @return boolean
     */
    public function setUploadFunction($function)
    {
        if (!empty($function) and (is_array($function) or is_string($function) )) {
            if (is_callable($function)) {
                $this->log(
                        "IMPORTANT! The function for uploading files is set to: %s", $function
                );
                $this->upload_function = $function;

                return true;
            }
        }

        return false;
    }

    /**
     * Clear allowed mime types cache
     *
     * @return boolean
     */
    public function clearAllowedMimeTypes()
    {
        $this->allowed_mime_types = array();
        $this->file["allowed_mime_types"] = array();

        return true;
    }

    /**
     * Set destination output
     *
     * @param  string  $destination_directory Destination path
     * @param  boolean $create_if_not_exist
     * @return boolean
     */
    public function setDestinationDirectory(
    $destination_directory, $create_if_not_exist = false
    ) {
        $destination_directory = realpath($destination_directory);
        if (substr($destination_directory, -1) != DIRECTORY_SEPARATOR) {
            $destination_directory .= DIRECTORY_SEPARATOR;
        }

        if ($this->isDirpath($destination_directory)) {
            if ($this->dirExists($destination_directory)) {
                $this->destination_directory = $destination_directory;
                if (substr($this->destination_directory, -1) != DIRECTORY_SEPARATOR) {
                    $this->destination_directory .= DIRECTORY_SEPARATOR;
                }
                chdir($destination_directory);

                return true;
            } elseif ($create_if_not_exist === true) {
                if (mkdir($destination_directory, $this->destination_permissions, true)) {
                    if ($this->dirExists($destination_directory)) {
                        $this->destination_directory = $destination_directory;
                        if (substr($this->destination_directory, -1) != DIRECTORY_SEPARATOR) {
                            $this->destination_directory .= DIRECTORY_SEPARATOR;
                        }
                        chdir($destination_directory);

                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check file exists
     *
     * @param  string  $file_destination
     * @return boolean
     */
    public function fileExists($file_destination)
    {
        if ($this->isFilename($file_destination)) {
            return (file_exists($file_destination) and is_file($file_destination));
        }

        return false;
    }

    /**
     * Check dir exists
     *
     * @param  string  $path
     * @return boolean
     */
    public function dirExists($path)
    {
        if ($this->isDirpath($path)) {
            return (file_exists($path) and is_dir($path));
        }

        return false;
    }

    /**
     * Check valid filename
     *
     * @param  string  $filename
     * @return boolean
     */
    public function isFilename($filename)
    {
        $filename = basename($filename);

        return (!empty($filename) and (is_string($filename) or is_numeric($filename)));
    }

    /**
     * Validate mime type with allowed mime types,
     * but if allowed mime types is empty, this method return true
     *
     * @param  string  $mime
     * @return boolean
     */
    public function checkMimeType($mime)
    {
        if (count($this->allowed_mime_types) == 0) {
            return true;
        }

        return in_array(strtolower($mime), $this->allowed_mime_types);
    }

    /**
     * Retrive status of upload
     *
     * @return boolean
     */
    public function getStatus()
    {
        return $this->file["status"];
    }

    /**
     * Check valid path
     *
     * @param  string  $filename
     * @return boolean
     */
    public function isDirpath($path)
    {
        if (!empty($path) and (is_string($path) or is_numeric($path) )) {
            if (DIRECTORY_SEPARATOR == "/") {
                return (preg_match('/^[^*?"<>|:]*$/', $path) == 1 );
            } else {
                return (preg_match("/^[^*?\"<>|:]*$/", substr($path, 2)) == 1);
            }
        }

        return false;
    }

    /**
     * Allow overwriting files
     *
     * @return boolean
     */
    public function allowOverwriting()
    {
        $this->log("Overwrite enabled");
        $this->allowOverwriting = true;

        return true;
    }

    /**
     * File info
     *
     * @return object
     */
    public function getInfo()
    {
        return (object) $this->file;
    }

    /**
     * Upload file
     *
     * @return boolean
     */
    public function save()
    {
        if (count($this->file_array) > 0) {
            $this->log("Capturing input %s", $this->input);
            if (array_key_exists($this->input, $this->file_array)) {
                // set original filename if not have a new name
                if (empty($this->filename)) {
                    $this->log(
                            "Using original filename %s", $this->file_array[$this->input]["name"]
                    );
                    $this->filename = $this->file_array[$this->input]["name"];
                }

                // Replace %s for extension in filename
                // Before: /[\w\d]*(.[\d\w]+)$/i
                // After: /^[\s[:alnum:]\-\_\.]*\.([\d\w]+)$/iu
                // Support unicode(utf-8) characters
                // Example: "русские.jpeg" is valid; "Zhōngguó.jpeg" is valid; "Tønsberg.jpeg" is valid
                $extension = preg_replace("/^[\p{L}\d\s\-\_\.\(\)]*\.([\d\w]+)$/iu", '$1', $this->file_array[$this->input]["name"]);
                $this->filename = sprintf($this->filename, $extension);

                // set file info
                $this->file["mime"] = $this->file_array[$this->input]["type"];
                $this->file["tmp"] = $this->file_array[$this->input]["tmp_name"];
                $this->file["original"] = $this->file_array[$this->input]["name"];
                $this->file["size"] = $this->file_array[$this->input]["size"];
                $this->file["sizeFormated"] = $this->sizeFormat($this->file["size"]);
                $this->file["destination"] = $this->destination_directory . $this->filename;
                $this->file["filename"] = $this->filename;
                $this->file["error"] = $this->file_array[$this->input]["error"];

                // Check if exists file
                if ($this->fileExists($this->destination_directory . $this->filename)) {
                    $this->log("%s file already exists", $this->filename);
                    // Check if overwrite file
                    if ($this->overwrite_file === false) {
                        $this->log(
                                "You don't allow overwriting. Show more about FileUpload::allowOverwriting"
                        );

                        return false;
                    }
                    $this->log("The %s file is overwritten", $this->filename);
                }

                // Execute input callback
                if (!empty($this->callbacks["input"])) {
                    $this->log("Running input callback");
                    call_user_func($this->callbacks["input"], (object) $this->file);
                }

                // Check mime type
                $this->log("Check mime type");
                if (!$this->checkMimeType($this->file["mime"])) {
                    $this->log("Mime type %s not allowed", $this->file["mime"]);

                    return false;
                }
                $this->log("Mime type %s allowed", $this->file["mime"]);
                // Check file size
                if ($this->max_file_size > 0) {
                    $this->log("Checking file size");

                    if ($this->max_file_size < $this->file["size"]) {
                        $this->log(
                                "The file exceeds the maximum size allowed(Max: %s; File: %s)", $this->sizeFormat($this->max_file_size), $this->sizeFormat($this->file["size"])
                        );

                        return false;
                    }
                }
                // Copy tmp file to destination and change status
                $this->log(
                        "Copy tmp file to destination %s", $this->destination_directory
                );
                $this->log(
                        "Using upload function: %s", $this->upload_function
                );

                $this->file["status"] = call_user_func_array(
                        $this->upload_function, array(
                    $this->file_array[$this->input]["tmp_name"],
                    $this->destination_directory . $this->filename
                        )
                );

                // Execute output callback
                if (!empty($this->callbacks["output"])) {
                    $this->log("Running output callback");
                    call_user_func($this->callbacks["output"], (object) $this->file);
                }

                return $this->file["status"];
            }
        }
    }

    /**
     * Create log
     *
     * @return boolean
     */
    public function log()
    {
        $params = func_get_args();
        $this->file["log"][] = call_user_func_array("sprintf", $params);

        return true;
    }

    /**
     * File size for humans.
     *
     * @param  integer $bytes
     * @param  integer $precision
     * @return string
     */
    public function sizeFormat($size, $precision = 2)
    {
        $base = log($size) / log(1024);
        $suffixes = array('B', 'K', 'M', 'G');

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    /**
     * Convert human file size to bytes
     *
     * @param  integer $size
     * @return string
     */
    public function sizeInBytes($size)
    {
        $unit = "B";
        $units = array("B" => 0, "K" => 1, "M" => 2, "G" => 3);
        $matches = array();
        preg_match("/(?<size>[\d\.]+)\s*(?<unit>b|k|m|g)?/i", $size, $matches);
        if (array_key_exists("unit", $matches)) {
            $unit = strtoupper($matches["unit"]);
        }

        return (floatval($matches["size"]) * pow(1024, $units[$unit]) );
    }

}
