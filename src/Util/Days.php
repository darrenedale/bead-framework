<?php

namespace Bead\Util;

/**
 * An immutable number of days to express as seconds.
 */
class Days implements TimestampInterval
{
    /** @var int The number of seconds in a day. */
    public const SecondsPerDay = 60 * 60 * 24;

    /** @var int The number of days. */
    private int $m_days;

    /**
     * Initialise a new number of days to express as seconds.
     *
     * @param int $days The number of days.
     */
    public function __construct(int $days)
    {
        $this->m_days = $days;
    }

    /**
     * Fluently add days.
     *
     * @param int $days The number of days to add.
     *
     * @return $this A clone of the Days object with the given days added.
     */
    public function plus(int $days): self
    {
        $clone = clone $this;
        $clone->m_days += $days;
        return $clone;
    }

    /**
     * Fluently subtract days.
     *
     * @param int $days The number of days to subtract.
     *
     * @return $this A clone of the Days object with the given days subtracted.
     */
    public function minus(int $days): self
    {
        $clone = clone $this;
        $clone->m_days -= $days;
        return $clone;
    }

    /**
     * Fetch the number of days.
     *
     * @return int The number of days.
     */
    public function days(): int
    {
        return $this->m_days;
    }

    /**
     * Fetch the number of seconds in the number of days.
     *
     * @return int The number of seconds.
     */
    public function inSeconds(): int
    {
        return $this->m_days * self::SecondsPerDay;
    }
}
