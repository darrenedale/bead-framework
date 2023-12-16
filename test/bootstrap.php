<?php

require_once __DIR__ . "/../vendor/autoload.php";

use BeadTests\Framework\TestCase;

if (!file_exists(TestCase::tempDir())) {
    mkdir(TestCase::tempDir(), 0777, true);
}
