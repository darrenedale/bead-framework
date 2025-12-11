<?php

declare(strict_types=1);

namespace BeadTests\Web;

use Bead\Web\UriAuthority;
use Bead\Web\UriUserInfo;
use BeadTests\Framework\TestCase;

class UriAuthorityTest extends TestCase
{
    private UriAuthority $m_uriAuthority;

    protected function setUp(): void
    {
        $this->m_uriAuthority = new UriAuthority("www.example.net", 8010, new UriUserInfo("darren", "password"));
    }

    public function tearDown(): void
    {
        unset($this->m_uriAuthority);
    }

    /** Ensure the host is reported correctly. */
    public function testHost1(): void
    {
        self::assertSame("www.example.net", $this->m_uriAuthority->host());
    }

    /** Ensure we can set the host. */
    public function testHost2(): void
    {
        $authority = $this->m_uriAuthority->withHost("www.example.com");
        self::assertSame("www.example.com", $authority->host());
    }

    /** Ensure setting the host preserves immutability. */
    public function testHost3(): void
    {
        $this->m_uriAuthority->withHost("www.example.com");
        self::assertSame("www.example.net", $this->m_uriAuthority->host());
    }

    /** Ensure the port is reported correctly. */
    public function testPort1(): void
    {
        self::assertSame(8010, $this->m_uriAuthority->port());
    }

    /** Ensure we can set the port. */
    public function testPort2(): void
    {
        $authority = $this->m_uriAuthority->withPort(9001);
        self::assertSame(9001, $authority->port());
    }

    /** Ensure setting the port preserves immutability. */
    public function testPort3(): void
    {
        $this->m_uriAuthority->withPort(9001);
        self::assertSame(8010, $this->m_uriAuthority->port());
    }

    /** Ensure we can remove the port. */
    public function testPort4(): void
    {
        $authority = $this->m_uriAuthority->withoutPort();
        self::assertNull($authority->port());
    }

    /** Ensure removing the port preserves immutability. */
    public function testPort5(): void
    {
        $this->m_uriAuthority->withoutPort();
        self::assertSame(8010, $this->m_uriAuthority->port());
    }

    /** Ensure the user info is reported correctly. */
    public function testUserInfo1(): void
    {
        self::assertSame("darren", $this->m_uriAuthority->userInfo()->username());
        self::assertSame("password", $this->m_uriAuthority->userInfo()->password());
    }

    /** Ensure we can set the user info. */
    public function testUserInfo2(): void
    {
        $authority = $this->m_uriAuthority->withUserInfo(new UriUserInfo("susan", "secret"));
        self::assertSame("susan", $authority->userInfo()->username());
        self::assertSame("secret", $authority->userInfo()->password());
    }

    /** Ensure setting the user info preserves immutability. */
    public function testUserInfo3(): void
    {
        $this->m_uriAuthority->withUserInfo(new UriUserInfo("susan", "secret"));
        self::assertSame("darren", $this->m_uriAuthority->userInfo()->username());
        self::assertSame("password", $this->m_uriAuthority->userInfo()->password());
    }

    /** Ensure we can remove the user info. */
    public function testUserInfo4(): void
    {
        $authority = $this->m_uriAuthority->withoutUserInfo();
        self::assertNull($authority->userInfo());
    }

    /** Ensure removing the user info preserves immutability. */
    public function testUserInfo5(): void
    {
        $this->m_uriAuthority->withoutUserInfo();
        self::assertSame("darren", $this->m_uriAuthority->userInfo()->username());
        self::assertSame("password", $this->m_uriAuthority->userInfo()->password());
    }

    /** Ensure the user name is reported correctly. */
    public function testUsername1(): void
    {
        self::assertSame("darren", $this->m_uriAuthority->username());
    }

    /** Ensure the user name can be set. */
    public function testUsername2(): void
    {
        $authority = $this->m_uriAuthority->withUsername("susan");
        self::assertSame("susan", $authority->username());
    }

    /** Ensure setting the user name preserves immutability. */
    public function testUsername3(): void
    {
        $this->m_uriAuthority->withUsername("susan");
        self::assertSame("darren", $this->m_uriAuthority->username());
    }

    /** Ensure we can add a username to a UriAuthority without a user info part. */
    public function testUsername4(): void
    {
        $authority = $this->m_uriAuthority->withoutUserInfo();
        self::assertNull($authority->userInfo());
        $authority = $authority->withUsername("susan");
        self::assertSame("susan", $authority->username());
    }

    /** Ensure the password is reported correctly. */
    public function testPassword1(): void
    {
        self::assertSame("password", $this->m_uriAuthority->password());
    }

    /** Ensure we can set the password. */
    public function testPassword2(): void
    {
        $authority = $this->m_uriAuthority->withPassword("secret");
        self::assertSame("secret", $authority->password());
    }

    /** Ensure setting the password preserves immutability. */
    public function testPassword3(): void
    {
        $this->m_uriAuthority->withPassword("secret");
        self::assertSame("password", $this->m_uriAuthority->password());
    }

    /** Ensure we can remove the password. */
    public function testPassword4(): void
    {
        $authority = $this->m_uriAuthority->withoutPassword();
        self::assertNull($authority->password());
    }

    /** Ensure removing the password preserves immutability. */
    public function testPassword5(): void
    {
        $this->m_uriAuthority->withoutPassword();
        self::assertSame("password", $this->m_uriAuthority->password());
    }

    /** Ensure we can add a password to a UriAuthority without a user info part. */
    public function testPassword6(): void
    {
        $authority = $this->m_uriAuthority->withoutUserInfo();
        self::assertNull($authority->userInfo());
        $authority = $authority->withPassword("secret");
        self::assertSame("secret", $authority->password());
    }

    /** Ensure we can set the username and password at the same time. */
    public function testWithUsernameAndPassword1(): void
    {
        $authority = $this->m_uriAuthority->withUsernameAndPassword("susan", "secret");
        self::assertSame("susan", $authority->username());
        self::assertSame("secret", $authority->password());
    }

    /** Ensure an authority gets stringified correctly. */
    public function testToString1(): void
    {
        self::assertSame("darren:password@www.example.net:8010", $this->m_uriAuthority->__toString());
    }

    /** Ensure an authority with no user info gets stringified correctly. */
    public function testToString2(): void
    {
        $authority = $this->m_uriAuthority->withoutUserInfo();
        self::assertSame("www.example.net:8010", $authority->__toString());
    }

    /** Ensure an authority with no port gets stringified correctly. */
    public function testToString3(): void
    {
        $authority = $this->m_uriAuthority->withoutPort();
        self::assertSame("darren:password@www.example.net", $authority->__toString());
    }

    /** Ensure an authority with no user info or port gets stringified correctly. */
    public function testToString4(): void
    {
        $authority = $this->m_uriAuthority->withoutUserInfo()->withoutPort();
        self::assertSame("www.example.net", $authority->__toString());
    }

    /** Ensure an authority with no password gets stringified correctly. */
    public function testToString5(): void
    {
        $authority = $this->m_uriAuthority->withoutPassword();
        self::assertSame("darren@www.example.net:8010", $authority->__toString());
    }

    /** Ensure an authority with no password or port gets stringified correctly. */
    public function testToString6(): void
    {
        $authority = $this->m_uriAuthority->withoutPassword()->withoutPort();
        self::assertSame("darren@www.example.net", $authority->__toString());
    }
}
