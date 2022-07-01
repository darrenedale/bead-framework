<?php

namespace Equit\Exceptions;

use Equit\Contracts\Response;

/**
 * Exception-response to use when a user has attempted something they're not authorised to do.
 */
class NotAuthorisedException extends HttpException
{
	/**
	 * The HTTP status code.
	 * @return int 403
	 */
	public function statusCode(): int
	{
		return 403;
	}
}
