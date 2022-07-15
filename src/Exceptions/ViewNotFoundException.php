<?php

namespace Equit\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when an attempt is made to instantiate a view that does not exist.
 */
class ViewNotFoundException extends Exception
{
	/** @var string The name of the missing view. */
	private string $m_name;

	/**
	 * Initialise a new instance of the exception.
	 *
	 * @param string $name The name of the missing view.
	 * @param string $message The optional error message. Defaults to an empty string.
	 * @param int $code The optional error code. Defaults to 0.
	 * @param \Throwable|null $previous THe optional previous Throwable. Defaults to null.
	 */
	public function __construct(string $name, string $message = "", int $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
		$this->m_name = $name;
	}

	/**
	 * Fetch the name of the view that could not be found.
	 *
	 * @return string The name.
	 */
	public function getName(): string
	{
		return $this->m_name;
	}
}
