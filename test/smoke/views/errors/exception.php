<?php

/** @var array<string, mixed> $data */
/** @var \Bead\WebApplication $app */
/** @var \Throwable $error */

use Bead\View;

?>

<?php View::layout("layouts.layout"); ?>

<?php View::section("main"); ?>

<h2>Exception of type <?= get_class($error) ?> thrown</h2>
<div>
    <?= html($error->getMessage()) ?>
</div>

<?php View::endSection(); ?>
