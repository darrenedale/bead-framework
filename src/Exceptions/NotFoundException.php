<?php

namespace Equit\Exceptions;

use Equit\Contracts\Response;

class NotFoundException extends HttpException
{
	public function statusCode(): int
	{
		return 404;
	}
}
