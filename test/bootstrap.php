<?php

if(!defined("NAMESPACE_SEPARATOR")) {
	define("NAMESPACE_SEPARATOR", "\\");
}

// SPL class autoloader that looks for classes in the Equit framework installation directory. classes are expected to be
// in namespace-mapped subdirs (lower-case dir names), in files named with the class name suffixed with ".php"
spl_autoload_register(function($class) {
	static $baseDir = null;

	if(!isset($baseDir)) {
		$baseDir = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "..");
	}

	$path = explode(NAMESPACE_SEPARATOR, $class);
	$class = array_pop($path);

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
	@include($baseDir . DIRECTORY_SEPARATOR . $path . $class . ".php");
});

