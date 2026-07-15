<?php

// The mbstring extension is a standard Laravel requirement; this minimal
// polyfill only lets the suite run in a stripped-down CI image without it.
if (! function_exists('mb_split')) {
    function mb_split($pattern, $string, $limit = -1)
    {
        return preg_split('/' . $pattern . '/u', $string, $limit <= 0 ? -1 : $limit);
    }
}

require __DIR__ . '/../vendor/autoload.php';
