<?php

namespace BeadTests\Web;

use Bead\Web\UriUserInfo;
use BeadTests\Framework\TestCase;

class UriUserInfoTest extends TestCase
{
    private UriUserInfo $m_userInfo;

    public function setUp(): void
    {
        parent::setUp();
        $this->m_userInfo = new UriUserInfo("darren", "password");
    }

    public function tearDown(): void
    {
        unset($this->m_userInfo);
        parent::tearDown();
    }

    /** Ensure we can fetch the username. */
    public function testUsername1(): void
    {
        self::assertSame("darren", $this->m_userInfo->username());
    }

    /** Ensure we can set the username. */
    public function testWithUsername1(): void
    {
        $userInfo = $this->m_userInfo->withUsername("susan");
        self::assertSame("susan", $userInfo->username());
    }

    /** Ensure setting the username preserves immutability. */
    public function testWithUsername2(): void
    {
        $this->m_userInfo->withUsername("susan");
        self::assertSame("darren", $this->m_userInfo->username());
    }

    /** Ensure we can fetch the password. */
    public function testPassword1(): void
    {
        self::assertSame("password", $this->m_userInfo->password());
    }

    /** Ensure we can set the password. */
    public function testWithPassword1(): void
    {
        $userInfo = $this->m_userInfo->withPassword("secret");
        self::assertSame("secret", $userInfo->password());
    }

    /** Ensure setting the password preserves immutability. */
    public function testWithPassword2(): void
    {
        $this->m_userInfo->withPassword("secret");
        self::assertSame("password", $this->m_userInfo->password());
    }

    /** Ensure we can remove the password. */
    public function testWithoutPassword1(): void
    {
        $userInfo = $this->m_userInfo->withoutPassword();
        self::assertNull($userInfo->password());
    }

    /** Ensure removing the password preserves immutability. */
    public function testWithutPassword2(): void
    {
        $this->m_userInfo->withoutPassword();
        self::assertSame("password", $this->m_userInfo->password());
    }

    /** Ensure we get the correct string when the user info contains a password. */
    public function testToString1(): void
    {
        self::assertSame("darren:password", $this->m_userInfo->__toString());
    }

    /** Ensure we get the correct string when the user info has no password. */
    public function testToString2(): void
    {
        $userInfo = $this->m_userInfo->withoutPassword();
        self::assertSame("darren", $userInfo->__toString());
    }
}
