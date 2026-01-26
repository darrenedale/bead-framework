<?php

namespace Bead\Authentication;

/** Enumeration of non-failure authentication outcomes. */
enum AuthenticationResultCode
{
    /** Successful authentication. */
    case Authenticated;

    /** Authentication may succeed with additional factors (e.g. MFA). */
    case AdditionalFactorRequired;
}
