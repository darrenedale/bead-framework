<?php

declare(strict_types=1);

namespace Bead\Facades;

use Bead\Contracts\Email\Transport as TransportContract;
use Bead\Contracts\Email\Message as MessageContract;

/**
 * @method void send(MessageContract $message)
 */
class Mail extends ApplicationServiceFacade
{
    protected static string $serviceInterface = TransportContract::class;
}
