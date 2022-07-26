<?php

namespace Equit\Responses;

/**
 * Trait for responses that allow the status code to be set.
 *
 * Use this to avoid boilerplate.
 */
trait CanSetStatusCode
{
    /** @var int The HTTP status code. */
	private int $m_statusCode;

    /**
     * Fetch the HTTP status code.
     *
     * @return int The code.
     */
	public function statusCode(): int
	{
		return $this->m_statusCode;
	}

    /**
     * Set the HTTP status code.
     *
     * @param int $code The code.
     */
	public function setStatusCode(int $code): void
	{
		$this->m_statusCode = $code;
	}
}
