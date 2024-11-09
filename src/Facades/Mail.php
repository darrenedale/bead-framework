<?php

declare(strict_types=1);

namespace Bead\Facades;

use Bead\Contracts\Email\Transport as TransportContract;
use Bead\Contracts\Email\Message as MessageContract;

/**
 * Facade for easy access to the Application container's mail transport.
 *
 * @mixin TransportContract
 * @psalm-seal-methods
 * @method static void send(MessageContract $message)
 */
class Mail extends ApplicationServiceFacade
{
    protected static string $serviceInterface = TransportContract::class;
}
