<?php

declare(strict_types=1);

namespace BeadTests\Authentication;

use Bead\Authentication\PasswordCredentials;
use BeadTests\Framework\TestCase;

/** @covers \Bead\Authentication\PasswordCredentials */
class PasswordCredentialsTest extends TestCase
{
    /** Ensure the constructor sets the username and password correctly. */
    public function testConstructor1(): void
    {
        $actual = new PasswordCredentials("darren@example.org", "secret");
        self::assertSame("darren@example.org", $actual->username());
        self::assertSame("secret", $actual->password());
    }

    /** Ensure the username can be set. */
    public function testWithUsername1(): void
    {
        $actual = (new PasswordCredentials("darren@example.net", "secret"))
            ->withUsername("darren@example.org");

        self::assertSame("darren@example.org", $actual->username());
    }

    /** Ensure setting the username preserves immutability. */
    public function testWithUsername2(): void
    {
        $original = new PasswordCredentials("darren@example.com", "secret");
        $original->withUsername("darren@example.net");
        self::assertSame("darren@example.com", $original->username());
    }

    /** Ensure the password can be set. */
    public function testWithPassword1(): void
    {
        $actual = (new PasswordCredentials("darren@example.net", "secret"))
            ->withPassword("password1234");

        self::assertSame("password1234", $actual->password());
    }

    /** Ensure setting the password preserves immutability. */
    public function testWithPassword2(): void
    {
        $original = new PasswordCredentials("darren@example.com", "password-42");
        $original->withPassword("1secret");
        self::assertSame("password-42", $original->password());
    }
}
