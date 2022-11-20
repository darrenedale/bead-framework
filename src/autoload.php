<?php

include_once __DIR__ . "/includes/string.php";
include_once __DIR__ . "/includes/i18n.php";
include_once __DIR__ . "/includes/array.php";
include_once __DIR__ . "/includes/traversable.php";

spl_autoload_register(function (string $className) {
    static $baseDir = null;
    $path = explode("\\", $className);
    $className = array_pop($path);

    // only autoload from Bead namespace
    if (empty($path) || "Bead" !== $path[0]) {
        return;
    }

    if (!isset($baseDir)) {
        $baseDir = realpath(__DIR__);
    }

    // trim Bead namespace from the path - baseDir is where Bead root namespace is located
    array_shift($path);

    if (empty($path)) {
        $path = "";
    } else {
        $path = implode("/", $path) . "/";
    }

    include("{$baseDir}/{$path}{$className}.php");
});
