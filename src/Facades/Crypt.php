<?php

declare(strict_types=1);

namespace Bead\Facades;

use Bead\Contracts\Encryption\Crypter as CrypterContract;
use Bead\Encryption\SerializationMode;

/**
 * Facade for easy access to the Application container's Encrypter and Decrypter.
 *
 * @method static string encrypt(mixed $data, int $serializationMode = SerializationMode::Auto)
 * @method static mixed decrypt(string $data)
 */
class Crypt extends ApplicationServiceFacade
{
    protected static string $serviceInterface = CrypterContract::class;
}
