<?php


date_default_timezone_set('Asia/Jakarta');
// Root path for file manager
// use absolute path of directory i.e: '/var/www/folder' or $_SERVER['DOCUMENT_ROOT'].'/folder'
$root_path = $_SERVER['DOCUMENT_ROOT'];
$datetime_format = 'd.m.y H:i';
// Server hostname. Can set manually if wrong
// $_SERVER['HTTP_HOST'].'/folder'
$http_host = $_SERVER['HTTP_HOST'];

// input encoding for iconv
$iconv_input_encoding = 'UTF-8';

// e.g. array('myfile.html', 'personal-folder', '*.php', ...)
$exclude_items = array();

$root_url = '';

$is_https = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)
    || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';

// clean $root_url
$root_url = fm_clean_path($root_url);

// abs path for site
defined('FM_ROOT_URL') || define('FM_ROOT_URL', ($is_https ? 'https' : 'http') . '://' . $http_host . (!empty($root_url) ? '/' . $root_url : ''));
defined('FM_SELF_URL') || define('FM_SELF_URL', ($is_https ? 'https' : 'http') . '://' . $http_host . $_SERVER['PHP_SELF']);

defined('FM_READONLY') || define('FM_READONLY', true);
defined('FM_SHOW_HIDDEN') || define('FM_SHOW_HIDDEN', true);
defined('FM_ROOT_PATH') || define('FM_ROOT_PATH', $root_path);
defined('FM_EXCLUDE_ITEMS') || define('FM_EXCLUDE_ITEMS', (version_compare(PHP_VERSION, '7.0.0', '<') ? serialize($exclude_items) : $exclude_items));
define('FM_IS_WIN', DIRECTORY_SEPARATOR == '\\');



// always use ?p=
if (!isset($_GET['p']) && empty($_FILES)) {
    fm_redirect(FM_SELF_URL . '?p=');
}

// get path
$p = isset($_GET['p']) ? $_GET['p'] : (isset($_POST['p']) ? $_POST['p'] : '');

// clean path
$p = fm_clean_path($p);


// instead globals vars
define('FM_PATH', $p);
defined('FM_ICONV_INPUT_ENC') || define('FM_ICONV_INPUT_ENC', $iconv_input_encoding);
defined('FM_DATETIME_FORMAT') || define('FM_DATETIME_FORMAT', $datetime_format);


// get current path
$path = FM_ROOT_PATH;

if (FM_PATH != '') {
    $path .= '/' . FM_PATH;
}

// check path
if (!is_dir($path)) {
    fm_redirect(FM_SELF_URL . '?p=');
}

// get parent folder
$parent = fm_get_parent_path(FM_PATH);

$objects = is_readable($path) ? scandir($path) : array();
$folders = array();
$files = array();
$current_path = array_slice(explode("/", $path), -1)[0];
// && fm_is_exclude_items($current_path)
if (is_array($objects)) {
    foreach ($objects as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        // if (!FM_SHOW_HIDDEN && substr($file, 0, 1) === '.') {
        //     continue;
        // }
        $new_path = $path . '/' . $file;
        //  && fm_is_exclude_items($file)
        if (@is_file($new_path)) {
            $files[] = $file;
            // && fm_is_exclude_items($file)
        } elseif (@is_dir($new_path) && $file != '.' && $file != '..') {
            $folders[] = $file;
        }
    }
}



function scan($dir = '', $filter = '')
{
    $path = FM_ROOT_PATH . '/' . $dir;
    if ($path) {
        $ite = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $rii = new RegexIterator($ite, "/(" . $filter . ")/i");

        $files = array();
        foreach ($rii as $file) {
            if (!$file->isDir()) {
                $fileName = $file->getFilename();
                $location = str_replace(FM_ROOT_PATH, '', $file->getPath());
                $files[] = array(
                    "name" => $fileName,
                    "type" => "file",
                    "path" => $location,
                );
            }
        }
        return $files;
    }
}



/**
 * HTTP Redirect
 * @param string $url
 * @param int $code
 */
function fm_redirect($url, $code = 302)
{
    header('Location: ' . $url, true, $code);
    exit;
}

/**
 * Path traversal prevention and clean the url
 * It replaces (consecutive) occurrences of / and \\ with whatever is in DIRECTORY_SEPARATOR, and processes /. and /.. fine.
 * @param $path
 * @return string
 */
function get_absolute_path($path)
{
    $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
    $absolutes = array();
    foreach ($parts as $part) {
        if ('.' == $part) continue;
        if ('..' == $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }
    return implode(DIRECTORY_SEPARATOR, $absolutes);
}

/**
 * Clean path
 * @param string $path
 * @return string
 */
function fm_clean_path($path, $trim = true)
{
    $path = $trim ? trim($path) : $path;
    $path = trim($path, '\\/');
    $path = str_replace(array('../', '..\\'), '', $path);
    $path =  get_absolute_path($path);
    if ($path == '..') {
        $path = '';
    }
    return str_replace('\\', '/', $path);
}

/**
 * Get parent path
 * @param string $path
 * @return bool|string
 */
function fm_get_parent_path($path)
{
    $path = fm_clean_path($path);
    if ($path != '') {
        $array = explode('/', $path);
        if (count($array) > 1) {
            $array = array_slice($array, 0, -1);
            return implode('/', $array);
        }
        return '';
    }
    return false;
}

/**
 * Check file is in exclude list
 * @param string $file
 * @return bool
 */
function fm_is_exclude_items($file)
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (isset($exclude_items) and sizeof($exclude_items)) {
        unset($exclude_items);
    }

    $exclude_items = FM_EXCLUDE_ITEMS;
    if (version_compare(PHP_VERSION, '7.0.0', '<')) {
        $exclude_items = unserialize($exclude_items);
    }
    if (!in_array($file, $exclude_items) && !in_array("*.$ext", $exclude_items)) {
        return true;
    }
    return false;
}

/**
 * get language translations from json file
 * @param int $tr
 * @return array
 */
function fm_get_translations($tr)
{
    try {
        $content = @file_get_contents('translation.json');
        if ($content !== FALSE) {
            $lng = json_decode($content, TRUE);
            global $lang_list;
            foreach ($lng["language"] as $key => $value) {
                $code = $value["code"];
                $lang_list[$code] = $value["name"];
                if ($tr)
                    $tr[$code] = $value["translation"];
            }
            return $tr;
        }
    } catch (Exception $e) {
        echo $e;
    }
}

/**
 * @param $file
 * Recover all file sizes larger than > 2GB.
 * Works on php 32bits and 64bits and supports linux
 * @return int|string
 */
function fm_get_size($file)
{
    static $iswin;
    static $isdarwin;
    if (!isset($iswin)) {
        $iswin = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN');
    }
    if (!isset($isdarwin)) {
        $isdarwin = (strtoupper(substr(PHP_OS, 0)) == "DARWIN");
    }

    static $exec_works;
    if (!isset($exec_works)) {
        $exec_works = (function_exists('exec') && !ini_get('safe_mode') && @exec('echo EXEC') == 'EXEC');
    }

    // try a shell command
    if ($exec_works) {
        $arg = escapeshellarg($file);
        $cmd = ($iswin) ? "for %F in (\"$file\") do @echo %~zF" : ($isdarwin ? "stat -f%z $arg" : "stat -c%s $arg");
        @exec($cmd, $output);
        if (is_array($output) && ctype_digit($size = trim(implode("\n", $output)))) {
            return $size;
        }
    }

    // try the Windows COM interface
    if ($iswin && class_exists("COM")) {
        try {
            $fsobj = new COM('Scripting.FileSystemObject');
            $f = $fsobj->GetFile(realpath($file));
            $size = $f->Size;
        } catch (Exception $e) {
            $size = null;
        }
        if (ctype_digit($size)) {
            return $size;
        }
    }

    // if all else fails
    return filesize($file);
}

/**
 * Get nice filesize
 * @param int $size
 * @return string
 */
function fm_get_filesize($size)
{
    $size = (float) $size;
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $power = ($size > 0) ? floor(log($size, 1024)) : 0;
    $power = ($power > (count($units) - 1)) ? (count($units) - 1) : $power;
    return sprintf('%s %s', round($size / pow(1024, $power), 2), $units[$power]);
}

function fm_enc($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function fm_convert_win($filename)
{
    if (FM_IS_WIN && function_exists('iconv')) {
        $filename = iconv(FM_ICONV_INPUT_ENC, 'UTF-8//IGNORE', $filename);
    }
    return $filename;
}

function fm_download_file($fileLocation, $fileName, $chunkSize  = 1024)
{
    if (connection_status() != 0)
        return (false);
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);

    $contentType = fm_get_file_mimes($extension);

    if (is_array($contentType)) {
        $contentType = implode(' ', $contentType);
    }

    $size = filesize($fileLocation);

    if ($size == 0) {
        fm_set_msg(lng('Zero byte file! Aborting download'), 'error');
        $FM_PATH = FM_PATH;
        fm_redirect(FM_SELF_URL . '?p=' . urlencode($FM_PATH));

        return (false);
    }

    @ini_set('magic_quotes_runtime', 0);
    $fp = fopen("$fileLocation", "rb");

    if ($fp === false) {
        fm_set_msg(lng('Cannot open file! Aborting download'), 'error');
        $FM_PATH = FM_PATH;
        fm_redirect(FM_SELF_URL . '?p=' . urlencode($FM_PATH));

        return (false);
    }

    header("Cache-Control: public");
    header("Content-Transfer-Encoding: binary\n");
    header("Content-Type: $contentType");

    $contentDisposition = 'attachment';


    if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
        $fileName = preg_replace('/\./', '%2e', $fileName, substr_count($fileName, '.') - 1);
        header("Content-Disposition: $contentDisposition;filename=\"$fileName\"");
    } else {
        header("Content-Disposition: $contentDisposition;filename=\"$fileName\"");
    }

    header("Accept-Ranges: bytes");
    $range = 0;

    if (isset($_SERVER['HTTP_RANGE'])) {
        list($a, $range) = explode("=", $_SERVER['HTTP_RANGE']);
        str_replace($range, "-", $range);
        $size2 = $size - 1;
        $new_length = $size - $range;
        header("HTTP/1.1 206 Partial Content");
        header("Content-Length: $new_length");
        header("Content-Range: bytes $range$size2/$size");
    } else {
        $size2 = $size - 1;
        header("Content-Range: bytes 0-$size2/$size");
        header("Content-Length: " . $size);
    }

    fseek($fp, $range);

    while (!@feof($fp) and (connection_status() == 0)) {
        set_time_limit(0);
        print(@fread($fp, 1024 * $chunkSize));
        flush();
        @ob_flush();
        // sleep(1);
    }
    fclose($fp);

    return ((connection_status() == 0) and !connection_aborted());
}

function fm_get_file_mimes($extension)
{
    $fileTypes['swf'] = 'application/x-shockwave-flash';
    $fileTypes['pdf'] = 'application/pdf';
    $fileTypes['exe'] = 'application/octet-stream';
    $fileTypes['zip'] = 'application/zip';
    $fileTypes['doc'] = 'application/msword';
    $fileTypes['xls'] = 'application/vnd.ms-excel';
    $fileTypes['ppt'] = 'application/vnd.ms-powerpoint';
    $fileTypes['gif'] = 'image/gif';
    $fileTypes['png'] = 'image/png';
    $fileTypes['jpeg'] = 'image/jpg';
    $fileTypes['jpg'] = 'image/jpg';
    $fileTypes['webp'] = 'image/webp';
    $fileTypes['avif'] = 'image/avif';
    $fileTypes['rar'] = 'application/rar';

    $fileTypes['ra'] = 'audio/x-pn-realaudio';
    $fileTypes['ram'] = 'audio/x-pn-realaudio';
    $fileTypes['ogg'] = 'audio/x-pn-realaudio';

    $fileTypes['wav'] = 'video/x-msvideo';
    $fileTypes['wmv'] = 'video/x-msvideo';
    $fileTypes['avi'] = 'video/x-msvideo';
    $fileTypes['asf'] = 'video/x-msvideo';
    $fileTypes['divx'] = 'video/x-msvideo';

    $fileTypes['mp3'] = 'audio/mpeg';
    $fileTypes['mp4'] = 'audio/mpeg';
    $fileTypes['mpeg'] = 'video/mpeg';
    $fileTypes['mpg'] = 'video/mpeg';
    $fileTypes['mpe'] = 'video/mpeg';
    $fileTypes['mov'] = 'video/quicktime';
    $fileTypes['swf'] = 'video/quicktime';
    $fileTypes['3gp'] = 'video/quicktime';
    $fileTypes['m4a'] = 'video/quicktime';
    $fileTypes['aac'] = 'video/quicktime';
    $fileTypes['m3u'] = 'video/quicktime';

    $fileTypes['php'] = ['application/x-php'];
    $fileTypes['html'] = ['text/html'];
    $fileTypes['txt'] = ['text/plain'];
    //Unknown mime-types should be 'application/octet-stream'
    if (empty($fileTypes[$extension])) {
        $fileTypes[$extension] = ['application/octet-stream'];
    }
    return $fileTypes[$extension];
}


//search : get list of files from the current folder
if (isset($_POST['type']) && $_POST['type'] == "search") {
    $dir = $_POST['path'] == "." ? '' : $_POST['path'];
    $response = scan(fm_clean_path($dir), $_POST['content']);
    echo json_encode($response);
    exit();
}


// Download
if (isset($_GET['dl'])) {

    $dl = $_GET['dl'];
    $dl = fm_clean_path($dl);
    $dl = str_replace('/', '', $dl);
    $path = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $path .= '/' . FM_PATH;
    }
    if ($dl != '' && is_file($path . '/' . $dl)) {
        fm_download_file($path . '/' . $dl, $dl, 1024);
        exit;
    } else {
        fm_set_msg(lng('File not found'), 'error');
        $FM_PATH = FM_PATH;
        fm_redirect(FM_SELF_URL . '?p=' . urlencode($FM_PATH));
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FILE MANAGER SERVER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <style>
        .lds-facebook {
            display: none;
            position: relative;
            width: 64px;
            height: 64px
        }

        .lds-facebook div,
        .lds-facebook.show-me {
            display: inline-block
        }

        .lds-facebook div {
            position: absolute;
            left: 6px;
            width: 13px;
            background: #007bff;
            animation: lds-facebook 1.2s cubic-bezier(0, .5, .5, 1) infinite
        }

        .lds-facebook div:nth-child(1) {
            left: 6px;
            animation-delay: -.24s
        }

        .lds-facebook div:nth-child(2) {
            left: 26px;
            animation-delay: -.12s
        }

        .lds-facebook div:nth-child(3) {
            left: 45px;
            animation-delay: 0s
        }

        @keyframes lds-facebook {
            0% {
                top: 6px;
                height: 51px
            }

            100%,
            50% {
                top: 19px;
                height: 26px
            }
        }
    </style>
</head>

<body>
    <nav class="navbar bg-body-tertiary shadow-sm mb-3">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="server.png" alt="Logo" width="30" height="24" class="d-inline-block align-text-top">
                <b class="?p=">FILE MANAGER SERVER</b>

                <?php
                $plink = fm_clean_path(FM_PATH);
                $root_url = "<a href='?p='>HOME</a>";
                $sep = '<i class="bread-crumb"> / </i>';
                if ($plink != '') {
                    $exploded = explode('/', $plink);
                    $count = count($exploded);
                    $array = array();
                    $parentlink = '';
                    for ($i = 0; $i < $count; $i++) {
                        $parentlink = trim($parentlink . '/' . $exploded[$i], '/');
                        $parent_enc = urlencode($parentlink);
                        $array[] = "<a href='?p={$parent_enc}'>" . fm_enc(fm_convert_win($exploded[$i])) . "</a>";
                    }
                    $root_url .= $sep . implode($sep, $array);
                }
                // $editFile
                echo '<div class="">' . $root_url  . '</div>';
                ?>
            </a>
            <form class="d-flex" role="search">

                <div class="input-group me-2 input-group-sm">
                    <input type="search" id="search-datatables" class="form-control form-control-sm" placeholder="Search in this folder">
                    <span class="input-group-text" id="basic-addon2">
                        <div class="dropdown dropdown-sm">
                            <button class="btn btn-sm  dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-lg-star">
                                <li><button type="button" class="dropdown-item" href="<?php echo $path2 = $plink ? $plink : '.'; ?>" id="js-search-modal">Advanced Search</button></li>
                            </ul>
                        </div>
                    </span>
                </div>
            </form>
        </div>
    </nav>
    <div class="container">

        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-body shadow-sm">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Size</th>
                                    <th>Modified</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($parent !== false) : ?>
                                    <tr>
                                        <td class="border-0" data-sort><a href="?p=<?php echo urlencode($parent) ?>"><i class="fa fa-chevron-circle-left go-back"></i> ..</a></td>
                                        <td class="border-0"></td>
                                        <td class="border-0"></td>
                                        <td class="border-0"></td>
                                    </tr>

                                <?php endif; ?>
                                <?php foreach ($folders as $f) : ?>
                                    <?php

                                    $is_link = is_link($path . '/' . $f);
                                    $modif_raw = filemtime($path . '/' . $f);
                                    $modif = date(FM_DATETIME_FORMAT, $modif_raw);
                                    $date_sorting = strtotime(date("F d Y H:i:s.", $modif_raw));

                                    ?>
                                    <tr>
                                        <td>
                                            <a href="?p=<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) ?>"><?= $f; ?></a>
                                        </td>
                                        <td>Folder</td>
                                        <td><?= $modif; ?></td>
                                        <td></td>
                                    </tr>

                                <?php endforeach; ?>

                                <?php foreach ($files as $f) : ?>
                                    <?php

                                    $is_link = is_link($path . '/' . $f);
                                    $modif_raw = filemtime($path . '/' . $f);
                                    $modif = date(FM_DATETIME_FORMAT, $modif_raw);
                                    $date_sorting = strtotime(date("F d Y H:i:s.", $modif_raw));

                                    $filesize_raw = fm_get_size($path . '/' . $f);
                                    $filesize = fm_get_filesize($filesize_raw);

                                    ?>
                                    <tr>
                                        <td>
                                            <a href="?p=<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) ?>"><?= $f; ?></a>
                                        </td>
                                        <td><?= $filesize; ?></td>
                                        <td><?= $modif; ?></td>
                                        <td>
                                            <a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;dl=<?php echo urlencode($f) ?>">Download</a>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalSearch" tabindex="-1" aria-labelledby="modalSearchLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="modalSearchLabel">Advanced Search</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col">
                            <div class="input-group input-group-sm mb-3">
                                <input type="text" class="form-control" id="advanced-search">
                                <span class="input-group-text" id="search-addon3">Search</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="lds-facebook">
                                <div></div>
                                <div></div>
                                <div></div>
                            </div>
                            <ul id="search-wrapper">
                                <p class="m-2">Search file in folder and subfolders...</p>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        const datatables = $('.table').DataTable({
            dom: 'lrtip',
        });

        $('#search-datatables').keyup(function() {
            datatables.search($(this).val()).draw();
        })

        const myModal = new bootstrap.Modal('#modalSearch', {
            keyboard: false
        })

        $('#js-search-modal').on('click', function() {
            myModal.show();
        })


        $(document).ready(function() {

            $("input#advanced-search").on('keyup', function(e) {
                if (e.keyCode === 13) {
                    fm_search();
                }
            });
            $('#search-addon3').on('click', function() {
                fm_search();
            });
        })


        //Search template
        function search_template(data) {
            var response = "";
            $.each(data, function(key, val) {
                // response += `<li><a href="?p=${val.path}&view=${val.name}">${val.path}/${val.name}</a></li>`;
                response += `<li><a href="?p=${val.path}">${val.path}/${val.name}</a></li>`;
            });
            return response;
        }

        //search
        function fm_search() {
            var searchTxt = $("input#advanced-search").val(),
                searchWrapper = $("ul#search-wrapper"),
                path = $("#js-search-modal").attr("href"),
                _html = "",
                $loader = $("div.lds-facebook");
            if (!!searchTxt && searchTxt.length > 2 && path) {
                var data = {
                    ajax: true,
                    content: searchTxt,
                    path: path,
                    type: 'search',
                    token: window.csrf
                };
                $.ajax({
                    type: "POST",
                    url: window.location,
                    data: data,
                    beforeSend: function() {
                        searchWrapper.html('');
                        $loader.addClass('show-me');
                    },
                    success: function(data) {
                        $loader.removeClass('show-me');
                        data = JSON.parse(data);
                        if (data && data.length) {
                            _html = search_template(data);
                            searchWrapper.html(_html);
                        } else {
                            searchWrapper.html('<p class="m-2">No result found!<p>');
                        }
                    },
                    error: function(xhr) {
                        $loader.removeClass('show-me');
                        searchWrapper.html('<p class="m-2">ERROR: Try again later!</p>');
                    },
                    failure: function(mes) {
                        $loader.removeClass('show-me');
                        searchWrapper.html('<p class="m-2">ERROR: Try again later!</p>');
                    }
                });
            } else {
                searchWrapper.html("OOPS: minimum 3 characters required!");
            }
        }
    </script>
</body>

</html>