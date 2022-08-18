<?php

/** @var array<string, mixed> $data */
/** @var WebApplication $app */
/** @var Throwable $error */

use Equit\View;
use Equit\WebApplication;

?>

<?php View::layout("layouts.layout"); ?>

<?php View::section("main"); ?>
<details>
    <summary><?= html(get_class($error)) ?></summary>
    <?= html($error->getMessage()) ?>
</details>
<?php View::endSection(); ?>

