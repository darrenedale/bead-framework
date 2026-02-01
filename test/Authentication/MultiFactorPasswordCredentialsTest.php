<?php

declare(strict_types=1);

namespace BeadTests\Authentication;

use Bead\Authentication\MultiFactorPasswordCredentials;
use BeadTests\Framework\TestCase;

/** @covers \Bead\Authentication\MultiFactorPasswordCredentials */
class MultiFactorPasswordCredentialsTest extends TestCase
{
    /** Provides second factor arguments that result in the credentials reporting that there is no second factor. */
    public static function providerNoSecondFactor(): iterable
    {
        yield "no method" => [null, "123456"];
        yield "no password" => ["totp", null];
        yield "no method or password" => [null, null];
    }

    /** Ensure the constructor sets the username, password and second factor correctly. */
    public function testConstructor1(): void
    {
        $actual = new MultiFactorPasswordCredentials("darren@example.org", "secret", "totp", "123456");
        self::assertSame("darren@example.org", $actual->username());
        self::assertSame("secret", $actual->password());
        self::assertSame("totp", $actual->secondFactorMethod());
        self::assertSame("123456", $actual->secondFactorPassword());
    }

    /**
     * Ensure hasMultiFactorCredentials correctly reports when a second factor is not present.
     * @dataProvider providerNoSecondFactor
     */
    public function testHasMultiFactorCredentials1(?string $method, ?string $password): void
    {
        $actual = new MultiFactorPasswordCredentials("darren@example.net", "secret", $method, $password);
        self::assertFalse($actual->hasMultiFactorCredentials());
    }

    /** Ensure hasMultiFactorCredentials correctly reports when a second factor is present. */
    public function testHasMultiFactorCredentials2(): void
    {
        $actual = new MultiFactorPasswordCredentials("darren@example.net", "secret", "totp", "123456");
        self::assertTrue($actual->hasMultiFactorCredentials());
    }

    /** Ensure the second factor method can be set. */
    public function testWithSecondFactorMethod1(): void
    {
        $actual = (new MultiFactorPasswordCredentials("darren@example.net", "secret", null, null))
            ->withSecondFactorMethod("totp");

        self::assertSame("totp", $actual->secondFactorMethod());
    }

    /** Ensure setting the second factor method preserves immutability. */
    public function testWithSecondFactorMethod2(): void
    {
        $original = new MultiFactorPasswordCredentials("darren@example.com", "secret", "totp", "123456");
        $original->withSecondFactorMethod("fido2");
        self::assertSame("totp", $original->secondFactorMethod());
    }

    /** Ensure the password can be set. */
    public function testWithSecondFactorPassword1(): void
    {
        $actual = (new MultiFactorPasswordCredentials("darren@example.net", "secret", "totp", "123456"))
            ->withSecondFactorPassword("987654");

        self::assertSame("987654", $actual->secondFactorPassword());
    }

    /** Ensure setting the password preserves immutability. */
    public function testWithSecondFactor2(): void
    {
        $original = new MultiFactorPasswordCredentials("darren@example.com", "password-42", "totp", "123456");
        $original->withSecondFactorPassword("987654");
        self::assertSame("123456", $original->secondFactorPassword());
    }
}
