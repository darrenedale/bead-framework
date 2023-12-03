<?php

/** @var array<string, mixed> $data */
/** @var Application $app */

use Bead\View;
use Bead\Web\Application;

?>

<?php View::layout("layouts.layout"); ?>

<?php View::push("scripts"); ?>
<script type="text/javascript" src="/js/csrf.js"></script>
<?php View::endPush(); ?>

<?php View::section("main"); ?>

	<form action="/csrf" method="post" enctype="multipart/form-data">
        <?php View::csrf(); ?>
        <input type="text" name="text" value="<?= $text ?? "" ?>" placeholder="Text..." />
        <button type="submit">Submit</button>
        <button type="button" id="incorrect-csrf-button">Submit with Incorrect CSRF</button>
	</form>

<?php View::endSection(); ?>
