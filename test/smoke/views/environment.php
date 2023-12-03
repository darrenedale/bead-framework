<?php

/**
 * @var Application $app
 * @var Environment $env
 * @var array $data
 */

use Bead\Contracts\Environment;
use Bead\Core\Application;
use Bead\View;

use function Bead\Helpers\Iterable\toArray;

?>

<?php View::layout("layouts.layout"); ?>

<?php View::section("main") ?>
    <p>Found <?= count(toArray($env->names())) ?> environment variables:</p>
    <ul>
        <?php foreach ($env->all() as $name => $value) : ?>
            <li><strong><?= html($name) ?></strong>: <pre><?= print_r($value, true) ?></pre></li>
        <?php endforeach; ?>
    </ul>
<?php View::endSection() ?>
