<div class="dialogue inline-dialogue inline <?= $classes ?? "" ?>" id="<?= $id ?? "" ?>">
    <?php if (is_string($title ?? null)): ?>
        <div class="title"><?= html($title) ?></div>
    <?php endif; ?>
    <div class="content">
        <?= $slot ?>
    </div>
</div>
