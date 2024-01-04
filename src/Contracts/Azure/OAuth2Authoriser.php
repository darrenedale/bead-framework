<?php

declare(strict_types=1);

namespace Bead\Contracts\Azure;

use Bead\Exceptions\Azure\AuthorisationException;

interface OAuth2Authoriser
{
    public const ClientCredentialsGrantType = "client_credentials";

    public const ServiceBusResource = "https://servicebus.azure.net";

    /** @throws AuthorisationException on error */
    public function authorise(string $resource, string $grantType, ClientApplicationCredentials $credentials): Authorisation;
}
