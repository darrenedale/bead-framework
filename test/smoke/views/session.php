<?php
use Bead\Facades\Session;
use Bead\View;
?>

<?php View::layout("layouts.layout"); ?>

<?php View::section("main") ?>
    <nav>
        Other pages:
        <a href="/prefixed-session">Session prefixing</a>
    </nav>

    <p>Old random number is <?= $previousRandom ?></p>
    <p>New random number is <?= $currentRandom ?></p>
    <p>Session created <?= $createdAt ?></p>
    <p>Session last used <?= $lastUsedAt ?></p>
    <p>Session IDs expire after <?= Session::sessionIdRegenerationPeriod() ?> seconds</p>
    <p>Session's current ID generated <?= $idGeneratedAt ?></p>
    <p>Session's current ID expires <?= $idExpiresAt ?></p>
    <p>Sessions expire after <?= Session::sessionIdleTimeoutPeriod() ?> seconds idle (currently at <?= $sessionExpiresAt ?>)</p>
    <p>Time is <?= $now ?></p>
    <p>Session ID is <?= html(Session::id()) ?>
    <p>Session ID has <?= (Session::get("session-id") === Session::id() ? "not" : "") ?> been regenerated</p>

    <a href="/session/set">Set the "some data" key</a>

    <h2>String keys</h2>
    <ul>
        <?php foreach ($data["session"] ?? [] as $key => $value): ?>
            <li><?= html($key) ?> = <?= html($value) ?></li>
        <?php endforeach; ?>
    </ul>

    <h2>Transient data</h2>
    <ul>
        <?php foreach (Session::get("__bead_transient_keys") as $key => $ttl): ?>
            <li>Key <strong><?= html($key) ?></strong> is transient with value <strong><?= Session::get($key) ?></strong> and has <?= html($ttl) ?> requests to live</li>
        <?php endforeach; ?>
    </ul>
    <a href="/session/transient/add">Add some transient data</a>

<?php View::endSection() ?>
