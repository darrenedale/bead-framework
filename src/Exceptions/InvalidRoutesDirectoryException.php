<?php

namespace Equit\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when the routes directory for the application cannot be used.
 */
class InvalidRoutesDirectoryException extends Exception
{
	/** @var string The invalid directory. */
	private string $m_dir;

	/**
	 * @param string $dir The invalid directory.
	 * @param string $message The optional message, Defaults to an empty string.
	 * @param int $code The optional error code. Defaults to 0.
	 * @param \Throwable|null $previous The optional previous throwable. Defaults to null.
	 */
	public function __construct(string $dir, string $message = "", int $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
		$this->m_dir = $dir;
	}

	/**
	 * Fetch the directory from which reoutes could not be loaded.
	 *
	 * @return string The directory.
	 */
	public function getDirectory(): string
	{
		return $this->m_dir;
	}
}