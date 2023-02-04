<?php

namespace Bead\Util;

/**
 * An immutable number of hours to express as seconds.
 */
class Hours implements TimestampInterval
{
	/** @var int The number of seconds in an hour. */
	public const SecondsPerHour = 60 * 60;

	/** @var int The number of hours. */
    private int $m_hours;

	/**
	 * Initialise a new number of hours to express as seconds.
	 *
	 * @param int $hours The number of hours.
	 */
    public function __construct(int $hours)
	{
        $this->m_hours = $hours;
    }

	/**
	 * Fluently add hours.
	 *
	 * @param int $hours The number of hours to add.
	 *
	 * @return $this A clone of the Hours object with the given hours added.
	 */
	public function plus(int $hours): self
	{
		$clone = clone $this;
		$clone->m_hours += $hours;
		return $clone;
	}

	/**
	 * Fluently subtract hours.
	 *
	 * @param int $hours The number of hours to subtract.
	 *
	 * @return $this A clone of the Hours object with the given hours subtracted.
	 */
	public function minus(int $hours): self
	{
		$clone = clone $this;
		$clone->m_hours -= $hours;
		return $clone;
	}

	/**
	 * Fetch the number of hours.
	 *
	 * @return int The number of hours.
	 */
	public function hours(): int
	{
		return $this->m_hours;
	}

	/**
	 * Fetch the number of seconds in the number of hours.
	 *
	 * @return int The number of seconds.
	 */
    public function inSeconds(): int
	{
        return $this->m_hours * self::SecondsPerHour;
    }
}
