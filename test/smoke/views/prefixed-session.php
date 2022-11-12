<?php
use Equit\Facades\Session;
use Equit\View;
?>

<?php View::layout("layouts.layout"); ?>

<?php View::section("main") ?>
    <p>
        The session data should contain the keys <code>foo.bar</code>, <code>foo.baz</code> and <code>foo.quux</code>.
        The keys <code>foo.fizz</code> and <code>foo.buzz</code> were added, then extracted using a prefix. These should
        be displayed below the session data on this page. The <code>foo.flox</code> key was added using array access
        semantics via a prefixed accessor.
    </p>
    <pre>
        <?= html(print_r(Session::all(), true)) ?>
    </pre>

    <pre>
        <?= html(print_r($extracted, true)) ?>
    </pre>
<?php View::endSection() ?>
