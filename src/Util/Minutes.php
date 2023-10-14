<?php

namespace Bead\Util;

/**
 * An immutable number of minutes to express as seconds.
 */
class Minutes implements TimestampInterval
{
    public const SecondsPerMinute = 60;

    /** @var int The number of minutes. */
    private int $m_mins;

    /**
     * Initialise a new number of minutes to express as seconds.
     *
     * @param int $mins The number of minutes.
     */
    public function __construct(int $mins)
    {
        $this->m_mins = $mins;
    }

    /**
     * Fluently add minutes.
     *
     * @param int $mins The number of minutes to add.
     *
     * @return $this A clone of the Minutes object with the given minutes added.
     */
    public function plus(int $mins): self
    {
        $clone = clone $this;
        $clone->m_mins += $mins;
        return $clone;
    }

    /**
     * Fluently subtract minutes.
     *
     * @param int $mins The number of minutes to subtract.
     *
     * @return $this A clone of the Minutes object with the given minutes subtracted.
     */
    public function minus(int $mins): self
    {
        $clone = clone $this;
        $clone->m_mins -= $mins;
        return $clone;
    }

    /**
     * Fetch the number of minutes.
     *
     * @return int The number of minutes.
     */
    public function minutes(): int
    {
        return $this->m_mins;
    }

    /**
     * Fetch the number of seconds in the number of minutes.
     *
     * @return int The number of seconds.
     */
    public function inSeconds(): int
    {
        return $this->m_mins * self::SecondsPerMinute;
    }
}
