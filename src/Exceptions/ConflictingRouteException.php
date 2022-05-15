<?php

namespace Equit\Exceptions;

use Throwable;

/**
 * Exception thrown by a Router instance when an attempt is made to register a path that is already registered.
 */
class ConflictingRouteException extends \Exception
{
	private string $m_path;

	/**
	 * Initialise a new DuplicateRouteException.
	 *
	 * @param string $path The path whose route is already taken.
	 * @param string $message The optional error message. Defaults to an empty string.
	 * @param int $code The optional error code. Defaults to 0.
	 * @param Throwable|null $previous The previous throwable, if any. Defaults to null.
	 */
	public function __construct(string $path, string $message = "", int $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
		$this->m_path = $path;
	}

	/**
	 * Fetch the path that already has a defined route.
	 *
	 * @return string The path.
	 */
	public function getPath(): string
	{
		return $this->m_path;
	}
}
