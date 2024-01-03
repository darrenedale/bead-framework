<?php

namespace Bead\Queues\Azure;

use Bead\Contracts\Azure\BearerToken as AzureBearerTokenContract;

class BearerToken extends AccessToken
{

    public function headers(): array
    {
        return ["Authorization" => "Bearer {$this->token()}",];
    }
}
