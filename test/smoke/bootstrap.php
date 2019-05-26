<?php

if(!defined("NAMESPACE_SEPARATOR")) {
	define("NAMESPACE_SEPARATOR", "\\");
}

set_include_path("../../" . PATH_SEPARATOR . get_include_path());

spl_autoload_register(function(string $className) {
	static $baseDir = null;

	$path = explode(NAMESPACE_SEPARATOR, $className);
	$className = array_pop($path);

	// only autoload from Equit namespace
	if(empty($path) || "Equit" != $path[0]) {
		return;
	}

	if(!isset($baseDir)) {
		$baseDir = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "..");
	}

	// we already know we're in the equit base path, so remove that part of the namespace
	array_shift($path);

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
