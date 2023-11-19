<?php

/**
 * psalm appears to only use the PSR-4 autoloading from composer, not the explicitly included files, so we use this
 * custom autoloader for static analysis
 */
require_once __DIR__ . "/../src/Helpers/Iterable.php";
require_once __DIR__ . "/../src/Helpers/Str.php";
require_once __DIR__ . "/../src/Helpers/I18n.php";
require_once __DIR__ . "/../src/Polyfill/array.php";
require_once __DIR__ . "/../src/Polyfill/string.php";
