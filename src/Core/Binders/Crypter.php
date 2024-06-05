<?php

declare(strict_types=1);

namespace Bead\Core\Binders;

use Bead\Contracts\Binder;
use Bead\Contracts\Encryption\Crypter as CrypterContract;
use Bead\Contracts\Encryption\Decrypter as DecrypterContract;
use Bead\Contracts\Encryption\Encrypter as EncrypterContract;
use Bead\Core\Application;
use Bead\Encryption\OpenSsl\Crypter as OpenSslCrypter;
use Bead\Encryption\Sodium\Crypter as SodiumCrypter;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\ServiceAlreadyBoundException;

/** Bind cryptographic services into the service container. */
class Crypter implements Binder
{
    /**
     * Create the Crypter instance to bind to the contracts.
     *
     * @param array $config The crypto configuration.
     * @return CrypterContract
     */
    protected static function createCrypter(array $config): CrypterContract
    {
        return match ($config["driver"]) {
            "openssl" => new OpenSslCrypter($config["algorithm"] ?? "", $config["key"] ?? ""),
            "sodium" => new SodiumCrypter($config["key"] ?? ""),
            default => throw new InvalidConfigurationException("crypto.driver", "Expected valid crypto driver, found {$config["driver"]}"),
        };
    }

    /**
     * @param Application $app
     * @throws InvalidConfigurationException if the language is misconfigured.
     * @throws ServiceAlreadyBoundException if a Crypter, Decrypter or Encrypter is already bound.
     */
    public function bindServices(Application $app): void
    {
        $cryptConfig = $app->config("crypto");

        if (!isset($cryptConfig["driver"])) {
            return;
        }

        $crypter = static::createCrypter($cryptConfig);

        $app->bindService(DecrypterContract::class, $crypter);
        $app->bindService(EncrypterContract::class, $crypter);
        $app->bindService(CrypterContract::class, $crypter);
    }
}
