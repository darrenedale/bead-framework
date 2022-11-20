<?php

namespace Bead\Exceptions;

use Bead\Request;
use Exception;
use Throwable;

/**
 * Exception thrown when a Router instance is unable to route a request.
 */
class UnroutableRequestException extends Exception
{
	private Request $m_request;

	/**
	 * Initialise a new UnroutableRequestException.
	 *
	 * @param \Bead\Request $request The request that could not be routed.
	 * @param string $message The optional error message. Defaults to an empty string.
	 * @param int $code The optional error code. Defaults to 0.
	 * @param Throwable|null $previous The previous throwable, if any. Defaults to null.
	 */
	public function __construct(Request $request, string $message = "", int $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
		$this->m_request = $request;
	}

	/**
	 * The request that could not be routed.
	 *
	 * @return \Bead\Request The request.
	 */
	public function getRequest(): Request
	{
		return $this->m_request;
	}
}
