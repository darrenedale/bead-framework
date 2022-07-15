<?php

namespace Equit\Html;

/**
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
 */
class Summary extends Element
{
    use HasChildElements;

    /**
     * Generate the opening HTML tag for the summary.
     *
     * This is a helper method for use when generating the HTML. It could be useful for subclasses to call so that they
     * don't need to replicate the common HTML for the start of the summary element and need only implement their
     * custom content.
     *
     * The start is generated as a &gt;summary&lt; element with the ID and classes specified by the creator, if any have
     * been provided.
     *
     * @return string The opening HTML.
     */
    protected function emitSummaryStart(): string
    {
        return "<summary{$this->emitAttributes()}>";
    }

    /**
     * Generate the closing HTML for the summary.
     *
     * This is a helper method for use when generating the HTML. It could be useful for subclasses to call so that they
     * don't need to replicate the common HTML for the end of the summary element and need only implement their custom
     * content.
     *
     * The end is generated as a closing &lt;/summary&gt; tag.
     *
     * @return string The closing HTML.
     */
    protected function emitSummaryEnd(): string {
        return "</summary>";
    }

    /**
     * @inheritDoc
     */
    public function html(): string
    {
        return "{$this->emitSummaryStart()}{$this->emitChildElements()}{$this->emitSummaryEnd()}";
    }
}