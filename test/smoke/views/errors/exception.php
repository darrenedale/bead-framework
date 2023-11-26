<?php

declare(strict_types=1);

/**
 * @var WebApplication $app
 * @var array $data
 * @var Throwable $error
 */

use Bead\Core\WebApplication;
use Bead\View;

?>

<?php View::layout("layouts.layout"); ?>

<?php View::push("styles"); ?>
<link rel="stylesheet" type="text/css" href="/css/exception.css" />
<?php View::endPush(); ?>

<?php View::section("main"); ?>

<!-- your HTML goes here -->
<h1>Exception</h1>

<h2><?= html($error::class) ?> <?= 0 !== $error->getCode() ? " [{$error->getCode()}]" : "" ?></h2>
<p><?= html($error->getMessage()) ?></p>
<h3>Location</h3>
<div>
    <p><strong><?= html(substr($error->getFile(), strlen(realpath($app->rootDir() . "/../..")))) ?></strong>:<?= $error->getLine() ?></p>
</div>

<h3>Stack</h3>
<ul class="stack">
<?php foreach ($error->getTrace() as $frame): ?>
    <?php // in a real app you'd just use $app->rootDir() to trim the path ?>
    <li>
        <strong><?= html(substr($frame["file"], strlen(realpath($app->rootDir() . "/../..")))) ?></strong>:<?= $frame["line"] ?> called
        <?=
            html(match($frame["type"] ?? "") {
                "::" => "{$frame["class"]}::{$frame["function"]}",
                "->" => "{$frame["class"]}->{$frame["function"]}",
                default => $frame["function"],
            })
        ?>()
    </li>
<?php endforeach ?>
</ul>

<?php View::endSection() ?>
