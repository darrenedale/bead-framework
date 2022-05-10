<?php

namespace Equit\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when the plugins path for the application cannot be used.
 */
class InvalidPluginsPathException extends Exception
{
	/** @var string The invalid path. */
	private string $m_path;

	/**
	 * @param string $path The invalid path.
	 * @param string $message The optional message, Defaults to an empty string.
	 * @param int $code The optional error code. Defaults to 0.
	 * @param \Throwable|null $previous The optional previous throwable. Defaults to null.
	 */
	public function __construct(string $path, string $message = "", int $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
		$this->m_path = $path;
	}

	/**
	 * Fetch the path from which plugins could not be loaded.
	 *
	 * @return string The path.
	 */
	public function getPath(): string
	{
		return $this->m_path;
	}
}