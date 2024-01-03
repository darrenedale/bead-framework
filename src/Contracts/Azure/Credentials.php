<?php

declare(strict_types=1);

namespace Bead\Contracts\Azure;

use Bead\Exceptions\Azure\AuthorizationException;

interface Credentials
{
    public const ServiceBusResource = "https://servicebus.azure.net";

    public const ClientCredentialsGrantType = "client_credentials";

    /** @throws AuthorizationException */
    public function authorise(string $resource, string $grantType): Authorisation;
}
