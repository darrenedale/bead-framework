<?php

/** @var int $timestamp The timestamp from the request pre-processor */
/** @var array<string, mixed> $data */
/** @var \Bead\Web\Application $app */

use Bead\View;

?>

<?php View::layout("layouts.layout"); ?>

<?php View::section("main"); ?>
<div>
    <p>Request was pre-processed at <?= $timestamp ?> (<?= DateTime::createFromFormat("U", (string) $timestamp, new DateTimeZone("UTC")) ->format("Y-m-d H:i:s") ?>).</p>
</div>
<?php View::endSection(); ?>
