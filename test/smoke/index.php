<!DOCTYPE html>
<html lang="en">
<head>
	<title>libequit Smoke Tests</title>
</head>
<body>
<h1>libequit Smoke Test Index</h1>
<?php

require_once "bootstrap.php";
require_once "libs/equit/includes/string.php";

function listEntryInIndex(string $entry): bool {
	if ("bootstrap.php" == $entry) {
		return false;
	}

	if (basename(__FILE__) == $entry) {
		return false;
	}

	if ("." == $entry[0]) {
		return false;
	}

	$hasVisibleExtension = function(string $fileName): bool {
		static $visibleExtensions = [ "php", "html", ];

		foreach($visibleExtensions as $ext) {
			$extLen = strlen($ext);

			if (strlen($fileName) > $extLen && ".$ext" === substr($fileName, 0 - $extLen - 1)) {
				return true;
			}
		}

		return false;
	};

	return $hasVisibleExtension($entry);
}

$dirHandle = dir("./");
$entries = [];

while(false !== ($entry = $dirHandle->read())) {
	if (listEntryInIndex($entry)) {
		$entries[] = $entry;
	}
}

sort($entries);

if(empty($entries)) {
	echo "<div class=\"dialogue\">No smoke test scripts available</div>";
}
else {
	echo "<ul class=\"index\">";

	foreach($entries as $entry) {
		$entry = html($entry);
		echo "<li><a href=\"$entry\">$entry</a></li>";
	}

	echo "</ul>";
}

?>
</body>
</html>
