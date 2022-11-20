<?php

/** @var array<string, mixed> $data */
/** @var \Bead\WebApplication $app */

use Bead\View;

?>

<?php View::layout("layouts.layout"); ?>

<?php View::section("main"); ?>

	<form action="/csrf" method="post" enctype="multipart/form-data">
        <?php View::csrf(); ?>
        <input type="text" name="text" value="<?= $text ?? "" ?>" placeholder="Text..." />
        <button type="submit">Submit</button>
	</form>

<?php View::endSection(); ?>
