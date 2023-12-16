<?php

declare(strict_types=1);

/** @var array<string, mixed> $data */
/** @var \Bead\WebApplication $app */
/** @var array|null $dispatchMessages */

use Bead\View;


?>

<?php View::layout("layouts.layout"); ?>

<?php View::section("main") ?>

    <div class="email-view">

        <?php if (isset($dispatchMessages) && 0 < count($dispatchMessages)): ?>
            <?php View::component("components.inline-dialogue") ?>
            <div class="details">
                <?php if (1 === count($dispatchMessages)): ?>
                    <?= html($dispatchMessages[0]) ?>
                <?php else: ?>
                    <ul>
                    <?php foreach ($dispatchMessages as $message): ?>
                        <li><?= html($message) ?></li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php View::endComponent(); ?>
        <?php endif; ?>

        <form method="post" action="/email/send">
            <?php View::csrf(); ?>
            <div class="flex-h">
                <label for="from">From</label>
                <input type="email" id="from" name="from" value="<?= ($from ?? null) ?>"/>
            </div>
            <div class="flex-h">
                <label for="to">To</label>
                <input type="email" id="to" name="to" value="<?= ($to ?? null) ?>"/>
            </div>
            <div class="flex-h">
                <label for="cc">Cc</label>
                <input type="email" id="cc" name="cc" value="<?= ($cc ?? null) ?>" />
            </div>
            <div class="flex-h">
                <label for="bcc">Bcc</label>
                <input type="email" id="bcc" name="bcc" value="<?= ($bcc ?? null) ?>" />
            </div>
            <div class="flex-h">
                <input type="text" id="subject" name="subject" placeholder="Message subject..." value="<?= ($subject ?? null) ?>" />
            </div>
            <textarea name="content" placeholder="Message body..."><?= html($content ?? (string) null) ?></textarea>
            <div class="flex-h align-end">
                <button type="submit">Send</button>
            </div>
        </form>

    </div>

<?php View::endSection() ?>
