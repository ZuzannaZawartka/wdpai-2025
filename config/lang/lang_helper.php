<?php
function get_lang($section) {
    $file = __DIR__ . "/$section.php";
    if (file_exists($file)) {
        return require $file;
    }
    return [];
}