<?php

declare(strict_types=1);

namespace Bead\Contracts\Azure;

use Bead\Exceptions\Azure\AuthorisationException;

interface Credentials
{
    public const ServiceBusResource = "https://servicebus.azure.net";

    public const ClientCredentialsGrantType = "client_credentials";

    /** @throws AuthorisationException */
    public function authorise(string $resource, string $grantType): Authorisation;
}
