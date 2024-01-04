<?php

declare(strict_types=1);

namespace Bead\Exceptions\Azure;

use RuntimeException;

/** Thrown Azure response to a REST request with a message indicating lack of authorisation. */
class AuthorisationException extends RuntimeException
{
}
