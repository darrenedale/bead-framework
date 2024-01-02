<?php

declare(strict_types=1);

namespace Bead\Contracts\Azure;

interface Credentials
{
    public function tenantId(): string;
    public function clientId(): string;
    public function secret(): string;
}
