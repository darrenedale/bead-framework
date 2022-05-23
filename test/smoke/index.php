<!DOCTYPE html>
<html lang="en">
<head>
    <title>libequit Smoke Tests</title>
</head>
<body>
<h1>libequit Smoke Test Index</h1>
<?php

use Equit\Request;
use Equit\Router;

require_once __DIR__ . "/bootstrap.php";

class Controller
{
	public static function editEntry(Request $request, string $type, int $id): void
	{
		echo "Routed request {$request->pathInfo()} with type = \"{$type}\" id = {$id} to controller\n";
	}
}

class IndexController
{
	private static function hasVisibleExtension(string $fileName): bool
	{
		static $visibleExtensions = ["php", "html",];

		foreach ($visibleExtensions as $ext) {
			$extLen = strlen($ext);

			if (strlen($fileName) > $extLen && ".$ext" === substr($fileName, 0 - $extLen - 1)) {
				return true;
			}
		}

		return false;
	}

	private function listEntryInIndex(string $entry): bool
	{
		if ("bootstrap.php" == $entry) {
			return false;
		}

		if (basename(__FILE__) == $entry) {
			return false;
		}

		if ("." == $entry[0]) {
			return false;
		}

		return static::hasVisibleExtension($entry);
	}

	public function index(): void
	{
		$dirHandle = dir(__DIR__);
		$entries   = [];

		while (false !== ($entry = $dirHandle->read())) {
			if (static::listEntryInIndex($entry)) {
				$entries[] = $entry;
			}
		}

		sort($entries);

		if (empty($entries)) {
			echo "<div class=\"dialogue\">No smoke test scripts available</div>";
		} else {
			echo "<ul class=\"index\">";

			foreach ($entries as $entry) {
				$entry = html($entry);
				echo "<li><a href=\"$entry\">$entry</a></li>";
			}

			echo "</ul>";
		}
	}
}

$router = new Router();
$router->registerGet("/index-entry/{type}/{id}/edit", [Controller::class, "editEntry"]);
$router->registerGet("/article/{id}/edit", function (Request $request, int $id) {
	echo "Routed request {$request->pathInfo()} with id = {$id} to closure\n";
});
$router->registerGet("/info", function() {
	include_once(__DIR__ . "/info.php");
});
$router->registerGet("/", [IndexController::class, "index"]);

try {
	$router->route(Request::originalRequest());
}
catch (Exception $err) {
	echo "[{$err->getCode()}] {$err->getMessage()}\n";
    echo($err->getTraceAsString());
}

?>
</body>
</html>
