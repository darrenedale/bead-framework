<?php

require_once __DIR__ . "/includes/constants.php";

spl_autoload_register(function(string $className) {
	static $baseDir = null;

	$path = explode(NAMESPACE_SEPARATOR, $className);
	$className = array_pop($path);

	// only autoload from Equit namespace
	if(empty($path) || "Equit" != $path[0]) {
		return;
	}

	if(!isset($baseDir)) {
		$baseDir = realpath(__DIR__ );
	}

	// trim equit namespace from the path - baseDir is where Equit root namespace is located
	array_shift($path);

	// all path components are lower case, except file name
	array_walk($path, function(string &$str) {
		$str = mb_strtolower($str, "UTF-8");
	});

	if(empty($path)) {
		$path = "";
	}
	else {
		$path = implode(DIRECTORY_SEPARATOR, $path) . DIRECTORY_SEPARATOR;
	}

	/** @noinspection PhpIncludeInspection */
	@include($baseDir . DIRECTORY_SEPARATOR . $path . $className . ".php");
});
