<?php

declare(strict_types=1);

use Bead\Contracts\Hasher as HasherContract;
use Bead\Core\Application;
use Bead\Core\Binders\Hasher as HasherBinder;
use Bead\Hashers\ArgonHasher;
use Bead\Hashers\BcryptHasher;
use BeadTests\Framework\TestCase;
use Bead\Exceptions\InvalidConfigurationException;

final class HasherTest extends TestCase
{
    private HasherBinder $hasher;

    /** @var Application&MockInterface  */
    private Application $app;

    public function setUp(): void
    {
        $this->hasher = new HasherBinder();
        $this->app = Mockery::mock(Application::class);
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->hasher, $this->app);
        parent::tearDown();
    }

    private function setHashConfig(array|null $config): void
    {
        $this->app->shouldReceive("config")
            ->with("hash")
            ->andReturn($config);
    }

    /** Ensure we exit without binding any services if no config is set */
    public function testBindServices1(): void
    {
        $this->setHashConfig(null);
        $this->app->shouldNotReceive("bindService");
        $this->hasher->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure we exit without binding any services if an empty config is set */
    public function testBindServices2(): void
    {
        $this->setHashConfig([]);
        $this->app->shouldNotReceive("bindService");
        $this->hasher->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure we exit without binding any services if the config has no driver */
    public function testBindServices3(): void
    {
        $this->setHashConfig(["cost" => 15,]);
        $this->app->shouldNotReceive("bindService");
        $this->hasher->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure we can successfully bind using Bcrypt hasher. */
    public function testBindServices4(): void
    {
        $this->setHashConfig([
            "driver" => "bcrypt",
            "cost" => 15,
        ]);

        $matcher = Mockery::on(fn (mixed $instance): bool => $instance instanceof BcryptHasher && 15 === $instance->cost());

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(HasherContract::class, $matcher);

        $this->hasher->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure we can successfully bind using Argon hasher. */
    public function testBindServices5(): void
    {
        $this->setHashConfig([
            "driver" => "argon",
            "memory_cost" => 32768,
            "time_cost" => 15,
        ]);

        $matcher = Mockery::on(fn (mixed $instance): bool => $instance instanceof ArgonHasher && 15 === $instance->timeCost() && 32768 === $instance->memoryCost());

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(HasherContract::class, $matcher);

        $this->hasher->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure bindServices() throws if the driver is invalid. */
    public function testBindServices6(): void
    {
        $this->setHashConfig([
            "driver" => "something-invalid",
        ]);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid hash driver, found something-invalid");
        $this->hasher->bindServices($this->app);
    }
}
