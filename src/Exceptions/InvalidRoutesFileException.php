<?php

namespace Bead\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when a file in the routes directory for the application cannot be read.
 */
class InvalidRoutesFileException extends Exception
{
	/** @var string The invalid file name. */
	private string $m_fileName;

	/**
	 * @param string $fileName The invalid routes file.
	 * @param string $message The optional message, Defaults to an empty string.
	 * @param int $code The optional error code. Defaults to 0.
	 * @param Throwable|null $previous The optional previous throwable. Defaults to null.
	 */
	public function __construct(string $fileName, string $message = "", int $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
		$this->m_fileName = $fileName;
	}

	/**
	 * Fetch the file from which routes could not be loaded.
	 *
	 * @return string The file name.
	 */
	public function getFileName(): string
	{
		return $this->m_fileName;
	}
}