<?php

namespace Equit\Exceptions;

use Equit\View;
use Exception;
use Throwable;

/**
 * Exception thrown when a view can't be successfully rendered.
 */
class ViewRenderingException extends Exception
{
	/** @var \Equit\View The view that could not be rendered. */
	private View $m_view;

	/**
	 * Intialise a new instance of the exception.
	 *
	 * @param \Equit\View $view The view that could not be rendered.
	 * @param string $message The optional error messgae. Defaults to an empty string.
	 * @param int $code The optional error code. Defaults to 0.
	 * @param \Throwable|null $previous The optional previous Throwable, if any. Defaults to null.
	 */
	public function __construct(View $view, string $message = "", int $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
		$this->m_view = $view;
	}

	/**
	 * Fetch the view that could not be rendered.
	 * @return \Equit\View The view.
	 */
	public function getView(): View
	{
		return $this->m_view;
	}
}