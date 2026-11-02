<?php
// Development router only. DirectAdmin serves production through its web server.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = realpath('/var/www/html');
$file = realpath($root . $path);
if ($file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
require $root . '/index.php';
