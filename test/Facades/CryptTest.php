<?php

namespace BeadTests\Facades;

use Bead\Application;
use Bead\Contracts\Encryption\Crypter as CrypterContract;
use Bead\Encryption\SerializationMode;
use Bead\Facades\Crypt;
use BeadTests\Framework\TestCase;
use Mockery;

final class CryptTest extends TestCase
{
    /** @var Application&Mockery\MockInterface The test Application instance. */
    private Application $app;

    /** @var CrypterContract&Mockery\MockInterface The test crypter instance bound into the application. */
    private CrypterContract $crypter;

    public function setUp(): void
    {
        $this->crypter = Mockery::mock(CrypterContract::class);
        $this->app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $this->app);

        $this->app->shouldReceive("get")
            ->with(CrypterContract::class)
            ->andReturn($this->crypter)
            ->byDefault();
    }

    public function tearDown(): void
    {
        unset($this->app, $this->crypter);
        Mockery::close();
        parent::tearDown();
    }

    public static function dataForTestEncrypt(): iterable
    {
        yield "auto-serialization" => ["data", SerializationMode::Auto, "test-encrypted-data",];
        yield "no-serialization" => ["other-data", SerializationMode::Off, "test-encrypted-other-data",];
        yield "forced-serialization" => ["more-data", SerializationMode::On, "more-test-encrypted-data"];
    }

    /**
     * Ensure encrypt() on the facace calls the expected service method with the expected arguments and forwards the
     * method's return value.
     *
     * @dataProvider dataForTestEncrypt
     */
    public function testEncrypt(string $data, int $serializationMode, string $mockEncryptedData): void
    {
        $this->crypter->shouldReceive("encrypt")->with($data, $serializationMode)->andReturn($mockEncryptedData);
        $actual = Crypt::encrypt($data, $serializationMode);
        self::assertEquals($mockEncryptedData, $actual);
    }

    /**
     * Ensure decrypt() on the facace calls the expected service method with the expected argument and forwards the
     * method's return value.
     */
    public function testDecrypt(): void
    {
        $this->crypter->shouldReceive("decrypt")->with("some-data")->andReturn("some-ecrypted-data");
        $actual = Crypt::decrypt("some-data");
        self::assertEquals("some-ecrypted-data", $actual);
    }
}
