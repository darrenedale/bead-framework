<?php

declare(strict_types=1);

namespace Bead\Contracts\Encryption;

interface Decrypter
{
    public function decrypt(string $data): mixed;
}
