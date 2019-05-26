<!DOCTYPE html>
<?php
	require_once "bootstrap.php";
	use Equit\Html\ListEdit;
?>
<html>
<head>
    <script type="module" src="scripts/listedit.js"></script>
<?php

foreach(ListEdit::runtimeScriptUrls() as $url) {
	echo "<script type=\"module\" src=\"/$url\"></script>";
}

?>
    <link rel="stylesheet" type="text/css" href="styles/listedit.css" />
</head>
<body>
<h1>ListEdit smoke test</h1>
<section class="test-content">
<?php

$listEdit = new ListEdit();
$listEdit->setPlaceholder("Type here to add to the list...");
echo $listEdit->html();

?>
</section>
</body>
</html>