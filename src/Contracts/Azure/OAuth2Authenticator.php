<?php

declare(strict_types=1);

namespace Bead\Contracts\Azure;

use Bead\Exceptions\Azure\AuthenticationException;

interface OAuth2Authenticator
{
    public const ServiceBusResource = "https://servicebus.azure.net";

    public const ClientCredentialsGrantType = "client_credentials";

    public function grantType(): string;

    public function resource(): string;

    /** @throws AuthenticationException on error */
    public function authenticateUsing(Credentials $credentials): AccessToken;
}
