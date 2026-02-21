<?php

namespace Bead\Exceptions\Authentication;

/** Exception thrown when authentication attempts fail due to MFA (i.e. not the core credential). */
class MultiFactorAuthenticationException extends AuthenticationException
{
}
