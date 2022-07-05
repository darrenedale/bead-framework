<?php

namespace Equit\Html;

use TypeError;

/**
 * A &lt;details&gt; page element.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
 */
class Details extends Element
{
    use HasChildElements;

    /** @var string | \Equit\Html\Summary The summary. */
    private $m_summary = "";

    /**
     * Set the summary for the details element.
     *
     * @param string|\Equit\Html\Summary $summary The summary.
     */
    public function setSummary($summary): void
    {
        if (!is_string($summary) && !($summary instanceof Summary)) {
            throw new TypeError("Summary for Details element must be a Summary object or a string.");
        }

        $this->m_summary = $summary;
    }

    /**
     * @return \Equit\Html\Summary|string
     */
    public function summary()
    {
        return $this->m_summary;
    }

    /**
     * Emit the start tag for the &lt;details&gt; element.
     *
     * @return string The HTML for the start tag.
     */
    protected function emitDetailsStart(): string
    {
        return "<details{$this->emitAttributes()}>";
    }

    /**
     * Emit the end tag for the &lt;details&gt; element.
     *
     * @return string The HTML for the end tag.
     */
    protected function emitDetailsEnd(): string
    {
        return "</details>";
    }

    /**
     * Emit the summary for the &lt;details&gt; element.
     *
     * @return string The HTML for the &lt;summary&gt; element.
     */
    protected function emitSummary(): string
    {
        $summary = $this->summary();

        if (is_string($summary)) {
            $summaryElement = new Summary();
            $summaryElement->addChildElement(new HtmlLiteral(html($summary)));
            $summary = $summaryElement;
        }

        return $summary->html();
    }

    /**
     * The HTML for the &lt;details&gt; element.
     * @inheritDoc
     */
    public function html(): string
    {
        return "{$this->emitDetailsStart()}{$this->emitSummary()}{$this->emitChildElements()}{$this->emitDetailsEnd()}";
    }
}
