<?php

namespace Equit\Test\Framework;

/**
 * Class to facilitate tracking of method calls in test doubles.
 */
class CallTracker
{
	/** @var int The number of calls. */
	private int $m_callCount = 0;

	/**
	 * Fetch the current call count.
	 *
	 * @return int The number of calls.
	 */
	public function callCount(): int
	{
		return $this->m_callCount;
	}

	/**
	 * Increment the call count.
	 */
	public function increment(): void
	{
		++$this->m_callCount;
	}

	/**
	 * Reset the call count.
	 */
	public function reset(): void
	{
		$this->m_callCount = 0;
	}
}
