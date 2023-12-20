<?php

declare(strict_types=1);

use Bead\Hashers\ArgonHasher;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;

final class ArgonHasherTest extends TestCase
{
    private ArgonHasher $hasher;

    public function setUp(): void
    {
        /** @psalm-suppress Default construction won't throw. */
        $this->hasher = new ArgonHasher();
    }

    public function tearDonw(): void
    {
        unset($this->hasher);
        parent::tearDown();
    }

    /** Ensure we get the expected default-constructed hasher. */
    public function testConstructor1(): void
    {
        self::assertEquals(ArgonHasher::DefaultMemoryCost, $this->hasher->memoryCost());
        self::assertEquals(ArgonHasher::DefaultTimeCost, $this->hasher->timeCost());
    }

    /** Ensure we can set the memory cost in the constructor. */
    public function testConstructor2(): void
    {
        $hasher = new ArgonHasher(32768);
        self::assertEquals(32768, $hasher->memoryCost());
        self::assertEquals(ArgonHasher::DefaultTimeCost, $hasher->timeCost());
    }

    /** Ensure we can set the time cost in the constructor. */
    public function testConstructor3(): void
    {
        $hasher = new ArgonHasher(timeCost: 10);
        self::assertEquals(ArgonHasher::DefaultMemoryCost, $hasher->memoryCost());
        self::assertEquals(10, $hasher->timeCost());
    }

    public static function dataForTestConstructor4(): iterable
    {
        yield "zero" => [0,];
        yield "minus one" => [-1,];
        yield "PHP_INT_MIN" => [PHP_INT_MIN,];
    }

    /**
     * Ensure constructor throws with invalid memory cost.
     *
     * @dataProvider dataForTestConstructor4
     */
    public function testConstructor4(int $cost): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid memory cost, found {$cost}");
        $hasher = new ArgonHasher($cost);
    }

    /**
     * Ensure constructor throws with invalid time cost.
     *
     * @dataProvider dataForTestConstructor4
     */
    public function testConstructor5(int $cost): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid time cost, found {$cost}");
        $hasher = new ArgonHasher(timeCost: $cost);
    }

    /** Ensure we can immutably set the memory cost. */
    public function testWithTimeCost1(): void
    {
        $hasher = $this->hasher->withMemoryCost(32768);
        self::assertNotSame($this->hasher, $hasher);
        self::assertEquals(ArgonHasher::DefaultMemoryCost, $this->hasher->memoryCost());
        self::assertEquals(32768, $hasher->memoryCost());
    }

    /**
     * Ensure withTimeCost() throws with invalid time costs.
     *
     * @dataProvider dataForTestConstructor4
     */
    public function testWithTimeCost2(int $cost): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid time cost, found {$cost}");
        $hasher = $this->hasher->withTimeCost($cost);
    }

    /** Ensure we can immutably set the time cost. */
    public function testWithMemoryCost1(): void
    {
        $hasher = $this->hasher->withTimeCost(10);
        self::assertNotSame($this->hasher, $hasher);
        self::assertEquals(ArgonHasher::DefaultTimeCost, $this->hasher->timeCost());
        self::assertEquals(10, $hasher->timeCost());
    }

    /**
     * Ensure withTimeCost() throws with invalid time costs.
     *
     * @dataProvider dataForTestConstructor4
     */
    public function testWithMemoryCost2(int $cost): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid memory cost, found {$cost}");
        $hasher = $this->hasher->withMemoryCost($cost);
    }

    /** Ensure hash() calls password_hash() with the expected arguments. */
    public function testHash1(): void
    {
        $mock = function(string $value, string $algorithm, array $options): string {
            TestCase::assertEquals("user-entered-password", $value);
            TestCase::assertEquals(PASSWORD_ARGON2ID, $algorithm);
            TestCase::assertEqualsCanonicalizing(
                [
                    "time_cost" => 10,
                    "memory_cost" => 32768,
                ],
                $options
            );

            return "the-hashed-user-entered-password";
        };

        $this->mockFunction("password_hash", $mock);
        $hasher = $this->hasher->withMemoryCost(32768)->withTimeCost(10);
        self::assertEquals("the-hashed-user-entered-password", $hasher->hash("user-entered-password"));
    }

    /** Ensure verify() calls password_verify() with the expected arguments. */
    public function testVerify1(): void
    {
        $mock = function(string $value, string $hash): bool {
            TestCase::assertEquals("user-entered-password", $value);
            TestCase::assertEquals("the-hashed-stored-password", $hash);
            return true;
        };

        $this->mockFunction("password_verify", $mock);
        $this->hasher->verify("user-entered-password", "the-hashed-stored-password");
    }

    /** Ensure verify() returns false when password_verify() returns false. */
    public function testVerify2(): void
    {
        $this->mockFunction("password_verify", false);
        self::assertFalse($this->hasher->verify("user-entered-password", "the-hashed-stored-password"));
    }

    /** Ensure verify() returns true when password_verify() returns true. */
    public function testVerify3(): void
    {
        $this->mockFunction("password_verify", true);
        self::assertTrue($this->hasher->verify("user-entered-password", "the-hashed-stored-password"));
    }

    /** Ensure a known matching pair passes verification. */
    public function testVerify4(): void
    {
        self::assertTrue($this->hasher->verify("bead-framework", "\$argon2id\$v=19\$m=65536,t=4,p=1\$UWFvY3FZai5TYmVZejhRZg\$Gp5jtsszHekXgfFJ3h6werXvxJxDF7wuRhQAtEi6YFE"));
    }

    /** Ensure a known non-matching pair fails verification. */
    public function testVerify5(): void
    {
        self::assertFalse($this->hasher->verify("framework-bead", "\$argon2id\$v=19\$m=65536,t=4,p=1\$UWFvY3FZai5TYmVZejhRZg\$Gp5jtsszHekXgfFJ3h6werXvxJxDF7wuRhQAtEi6YFE"));
    }
}
