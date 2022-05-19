<?php

namespace Equit\Responses;

use Equit\Contracts\Response;

abstract class AbstractResponse implements Response
{
	use CanSetStatusCode;
	use CanSetContentType;
	use DoesntHaveHeaders;
	use NaivelySendsContent;
}
