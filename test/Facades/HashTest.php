<?php

declare(strict_types=1);

use Bead\Contracts\Hasher as HasherContract;
use Bead\Core\Application;
use Bead\Facades\Hash;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

final class HashTest extends TestCase
{
    /** @var Application&MockInterface The test Application instance. */
    private Application $app;

    /** @var HasherContract&MockInterface The test hasher instance bound into the application. */
    private HasherContract $hasher;

    public function setUp(): void
    {
        $this->hasher = Mockery::mock(HasherContract::class);
        $this->app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $this->app);

        $this->app->shouldReceive("get")
            ->with(HasherContract::class)
            ->andReturn($this->hasher)
            ->byDefault();
    }

    public function tearDonw(): void
    {
        unset($this->app, $this->hasher);
        Mockery::close();
        parent::tearDown();
    }

    /** Ensure the hash the instance returns is returned by hash(). */
    public function testHash1(): void
    {
        $this->hasher->shouldReceive("hash")
            ->once()
            ->with("user-provided-value")
            ->andReturn("hashed-user-provided-value");

        self::assertEquals("hashed-user-provided-value", Hash::hash("user-provided-value"));
    }

    /** Ensure false is returned from verify() when the instance returns true. */
    public function testVerify1(): void
    {
        $this->hasher->shouldReceive("verify")
            ->once()
            ->with("user-provided-value", "stored-hashed-value")
            ->andReturn(true);

        self::assertTrue(Hash::verify("user-provided-value", "stored-hashed-value"));
    }

    /** Ensure false is returned from verify() when the instance returns false. */
    public function testVerify2(): void
    {
        $this->hasher->shouldReceive("verify")
            ->once()
            ->with("user-provided-value", "stored-hashed-value")
            ->andReturn(false);

        self::assertFalse(Hash::verify("user-provided-value", "stored-hashed-value"));
    }
}
