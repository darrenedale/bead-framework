<!DOCTYPE html>
<?php
	require_once "bootstrap.php";
?>
<html>
<head>
<?php
use Equit\Html\ListEdit;

foreach(ListEdit::runtimeScriptUrls() as $url) {
	echo "<script type=\"module\" src=\"../../$url\"></script>";
}

?>
</head>
<body>
<h1>ListEdit smoke test</h1>
<section class="test-content">
<?php

//echo (class_exists("Equit\Html\ListEdit") ? "class exists" : "class does not exist");
//$listEdit = new ListEdit();
//echo $listEdit->html();

?>
</section>
</body>
</html>
