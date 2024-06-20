<?php

namespace Bead\Web\Responses;

use Bead\Contracts\Web\Response;

/**
 * Base class for responses with default boilerplate implementations.
 *
 * Use this as the base for your response classes when their behaviour deviates from the default in only minor ways.
 */
abstract class AbstractResponse implements Response
{
    use CanSetStatusCode;
    use HasDefaultReasonPhrase;
    use CanSetContentType;
    use DoesntHaveHeaders;
    use NaivelySendsContent;
}
