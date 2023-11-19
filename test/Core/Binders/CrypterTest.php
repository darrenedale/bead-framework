<?php

namespace BeadTests\Core\Binders;

use Bead\Core\Application;
use Bead\Contracts\Encryption\Crypter as CrypterContract;
use Bead\Contracts\Encryption\Decrypter as DecrypterContract;
use Bead\Contracts\Encryption\Encrypter as EncrypterContract;
use Bead\Core\Binders\Crypter as CrypterBinder;
use Bead\Encryption\OpenSsl\Crypter as OpenSslCrypter;
use Bead\Encryption\Sodium\Crypter as SodiumCrypter;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

/** Test the bundled encryption services binder. */
final class CrypterTest extends TestCase
{
    private CrypterBinder $crypter;

    /** @var Application&MockInterface  */
    private Application $app;

    public function setUp(): void
    {
        $this->crypter = new CrypterBinder();
        $this->app = Mockery::mock(Application::class);
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->crypter, $this->app);
        parent::tearDown();
    }

    private function setCryptoConfig(array|null $config): void
    {
        $this->app->shouldReceive("config")
            ->with("crypto")
            ->andReturn($config);
    }

    /** Ensure we exit without binding any services if no config is set */
    public function testBindServices1(): void
    {
        $this->setCryptoConfig(null);
        $this->app->shouldNotReceive("bindService");
        $this->crypter->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure we exit without binding any services if an empty config is set */
    public function testBindServices2(): void
    {
        $this->setCryptoConfig([]);
        $this->app->shouldNotReceive("bindService");
        $this->crypter->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure we exit without binding any services if the config has no driver */
    public function testBindServices3(): void
    {
        $this->setCryptoConfig([
            "algorithm" => "aes-256-gcm",
            "key" => "-some-insecure-key-insecure-some",
        ]);
        $this->app->shouldNotReceive("bindService");
        $this->crypter->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure we can successfully bind using OpenSSL encryption. */
    public function testBindServices4(): void
    {
        $this->setCryptoConfig([
                "driver" => "openssl",
                "algorithm" => "aes-256-gcm",
                "key" => "-some-insecure-key-insecure-some",
            ]);

        $matcher = Mockery::on(fn (mixed $instance): bool => $instance instanceof OpenSslCrypter && "aes-256-gcm" === $instance->algorithm() && "-some-insecure-key-insecure-some" === (new XRay($instance))->key);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(CrypterContract::class, $matcher);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(DecrypterContract::class, $matcher);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(EncrypterContract::class, $matcher);

        $this->crypter->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure we can successfully bind using Sodium encryption. */
    public function testBindServices5(): void
    {
        $this->setCryptoConfig([
                "driver" => "sodium",
                "key" => "-some-insecure-key-insecure-some",
            ]);

        $matcher = Mockery::on(fn (mixed $instance): bool => $instance instanceof SodiumCrypter && "-some-insecure-key-insecure-some" === (new XRay($instance))->key);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(CrypterContract::class, $matcher);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(DecrypterContract::class, $matcher);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(EncrypterContract::class, $matcher);

        $this->crypter->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure bindServices() throws if the driver is invalid. */
    public function testBindServices6(): void
    {
        $this->setCryptoConfig([
            "driver" => "something-invalid",
        ]);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid crypto driver, found something-invalid");
        $this->crypter->bindServices($this->app);
    }
}
