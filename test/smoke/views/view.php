<?php

/** @var array<string, mixed> $data */
/** @var \Bead\WebApplication $app */

use Bead\View;

?>

<?php View::layout("layouts.layout"); ?>

<?php View::push("scripts"); ?>
    <script type="module" src="/js/script1.js"></script>
    <script type="module" src="/js/script2.js"></script>
<?php View::endPush(); ?>

<?php View::push("styles"); ?>
    <link rel="stylesheet" type="text/css" href="/css/style1.css" />
    <link rel="stylesheet" type="text/css" href="/css/style2.css" />
<?php View::endPush(); ?>

<?php View::push("scripts"); ?>
    <script type="module" src="/js/script3.js"></script>
<?php View::endPush(); ?>

<?php View::section("main"); ?>
    <p>This is the section content. The view was given &ldquo;<?= html($foo) ?>&rdquo; as its data.</p>

    <?php View::component("components.details") ?>
        <?php View::slot("summary"); ?>
            <span style="font-weight: bold">Summary</span>
        <?php View::endSlot(); ?>

        <div class="details">
            This is the details.
        </div>
    <?php View::endComponent(); ?>
<?php View::endSection(); ?>

<?php View::section("footer"); ?>
    <h1>View data:</h1>
    <div>
        <pre>
            <?= html(trim(print_r($data, true))) ?>
        </pre>
    </div>
<?php View::endSection(); ?>
