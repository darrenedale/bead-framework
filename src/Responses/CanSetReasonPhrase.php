<?php

namespace Bead\Responses;

/**
 * Trait for responses that allow the reason phrase to be set.
 *
 * Use this to avoid boilerplate.
 */
trait CanSetReasonPhrase
{
    /** @var string The HTTP reason phrase. */
    private string $m_reasonPhrase;

    /**
     * Fetch the HTTP reason phrase.
     *
     * @return string The reason phrase.
     */
    public function reaspnPhrase(): string
    {
        return $this->m_reasonPhrase;
    }

    /**
     * Set the HTTP reason phrase.
     *
     * @param string $reasonPhrase The reason phrase.
     */
    public function setReasonPhrase(string $reasonPhrase): void
    {
        $this->m_reasonPhrase = $reasonPhrase;
    }
}
