<!DOCTYPE html>
<?php
	require_once "bootstrap.php";
	use Equit\Html\ListEdit;
?>
<html>
<head>
<?php

foreach(ListEdit::runtimeScriptUrls() as $url) {
	echo <<<HTML
	<script type = "module" src = "../../$url"></script>
HTML;
}

?>
</head>
<body>
<h1>ListEdit smoke test</h1>
<section class="test-content">
<?php

$listEdit = new ListEdit();
echo $listEdit->html();

?>
</section>
</body>
</html>
