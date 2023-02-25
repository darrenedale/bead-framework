<?php

declare(strict_types=1);

namespace Bead\Contracts\Session;

interface HandlerFactory
{
	/**
	 * Fetch an instance of a session handler, optionally initialised with a given id.
	 *
	 * @param string $name The handler type.
	 * @param ?string $id The ID of the session.
	 *
	 * @return Handler
	 * @throws InvalidSessionHandlerException if the configured handler is not available.
	 */
	public function handler(string $name, ?string $id): Handler;
}
