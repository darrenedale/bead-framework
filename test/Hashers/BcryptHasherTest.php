<?php

declare(strict_types=1);

namespace BeadTests\Hashers;

use Bead\Hashers\BcryptHasher;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;

final class BcryptHasherTest extends TestCase
{
    private BcryptHasher $hasher;

    public function setUp(): void
    {
        /** @psalm-suppress Default construction won't throw. */
        $this->hasher = new BcryptHasher();
    }

    public function tearDonw(): void
    {
        unset($this->hasher);
        parent::tearDown();
    }

    /** Ensure we get the expected default-constructed hasher. */
    public function testConstructor1(): void
    {
        self::assertEquals(BcryptHasher::DefaultCost, $this->hasher->cost());
    }

    /** Ensure we can set the cost in the constructor. */
    public function testConstructor2(): void
    {
        $hasher = new BcryptHasher(5);
        self::assertEquals(5, $hasher->cost());
    }

    public static function dataForTestConstructor3(): iterable
    {
        yield "zero" => [0,];
        yield "minus one" => [-1,];
        yield "three" => [3,];
        yield "eleven" => [32,];
        yield "PHP_INT_MIN" => [PHP_INT_MIN,];
        yield "PHP_INT_MAX" => [PHP_INT_MAX,];
    }

    /**
     * Ensure constructor throws with invalid cost.
     *
     * @dataProvider dataForTestConstructor3
     */
    public function testConstructor4(int $cost): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid cost in the range 4-31, found {$cost}");
        $hasher = new BcryptHasher($cost);
    }

    /** Ensure we can immutably set the cost. */
    public function testWithCost1(): void
    {
        $hasher = $this->hasher->withCost(15);
        self::assertNotSame($this->hasher, $hasher);
        self::assertEquals(BcryptHasher::DefaultCost, $this->hasher->cost());
        self::assertEquals(15, $hasher->cost());
    }

    /**
     * Ensure withCost() throws with invalid costs.
     *
     * @dataProvider dataForTestConstructor3
     */
    public function testWithCost2(int $cost): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid cost in the range 4-31, found {$cost}");
        $hasher = $this->hasher->withCost($cost);
    }

    /** Ensure hash() calls password_hash() with the expected arguments. */
    public function testHash1(): void
    {
        $mock = function (string $value, string $algorithm, array $options): string {
            TestCase::assertEquals("user-entered-password", $value);
            TestCase::assertEquals(PASSWORD_BCRYPT, $algorithm);
            TestCase::assertEqualsCanonicalizing(["cost" => 15,], $options);
            return "the-hashed-user-entered-password";
        };

        $this->mockFunction("password_hash", $mock);
        $hasher = $this->hasher->withCost(15);
        self::assertEquals("the-hashed-user-entered-password", $hasher->hash("user-entered-password"));
    }

    /** Ensure verify() calls password_verify() with the expected arguments. */
    public function testVerify1(): void
    {
        $mock = function (string $value, string $hash): bool {
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
}
