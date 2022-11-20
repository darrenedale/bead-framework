<?php

use Bead\View;

?>
<!DOCTYPE html>
<html lang="en">
	<head>
        <title><?= Equit\Helpers\String\html($pageTitle) ?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <meta name="keywords" content="PHP framework MVC view bead" />
        <meta name="description" content="A simple test page for the view component of the Bead framework." />
        <meta name="theme-color" content="#0e366a" />
        <meta name="bead-version" content="0.9.3" />
        <link rel="stylesheet" href="/css/page.css" type="text/css" />
        <?php View::stack("scripts"); ?>
        <?php View::stack("styles"); ?>
	</head>
	<body>
        <header id="page-header">
            <img class="logo" src="/images/logo.svg" alt="logo "/>
            <h1><?= Equit\Helpers\String\html($pageTitle) ?></h1>
        </header>

        <!-- MAIN CONTAINER -->
        <main>
            <nav class="main-nav">
                <?php View::include("includes.navbar"); ?>
            </nav>

            <section class="main-content">
                <?php foreach ($messages ?? [] as $message): ?>
                    <div class="message"><?= html($message) ?></div>
                <?php endforeach; ?>

                <?php View::yieldSection("main"); ?>
            </section>
        </main>

        <footer>
            <?php View::yieldSection("footer"); ?>
        </footer>
	</body>
</html>