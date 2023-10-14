<?php

declare(strict_types=1);

namespace Bead\Contracts\Encryption;

use Bead\Encryption\SerializationMode;

interface Encrypter
{
	public function encrypt(mixed $data, int $serializationMode = SerializationMode::Auto): string;
}
