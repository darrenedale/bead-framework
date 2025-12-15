<?php

namespace BeadTests\Web;

use Bead\Web\Uri;
use Bead\Web\UriAuthority;
use BeadTests\Framework\TestCase;

class UriTest extends TestCase
{
    private Uri $m_uri;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m_uri = (new Uri("sftp", "example.org", "/home"))
            ->withUserInfo("darren", "password")
            ->withPort(22)
            ->withQuery("framework=bead")
            ->withFragment("uri");
    }

    /** Ensure the default constructed Uri is correct. */
    public function testConstructor1(): void
    {
        $uri = new Uri();
        self::assertSame("https", $uri->scheme());
        self::assertSame("localhost", $uri->host());
        self::assertSame("/", $uri->path());
    }

    /** Ensure a new Uri has the given scheme, host and path. */
    public function testConstructor2(): void
    {
        $uri = new Uri("sftp", "example.net", "/home");
        self::assertSame("sftp", $uri->scheme());
        self::assertSame("example.net", $uri->host());
        self::assertSame("/home", $uri->path());
    }

    /** Ensure the scheme is reported correctly. */
    public function testScheme1(): void
    {
        self::assertSame("sftp", $this->m_uri->scheme());
    }

    /** Ensure the scheme can be set. */
    public function testScheme2(): void
    {
        $uri = $this->m_uri->withScheme("http");
        self::assertSame("http", $uri->scheme());
    }

    /** Ensure setting the scheme preserves immutability. */
    public function testScheme3(): void
    {
        $this->m_uri->withScheme("http");
        self::assertSame("sftp", $this->m_uri->scheme());
    }

    /** Ensure the scheme is reported correctly. */
    public function testGetScheme1(): void
    {
        self::assertSame("sftp", $this->m_uri->getScheme());
    }

    /** Ensure the username is reported correctly. */
    public function testUsername1(): void
    {
        self::assertSame("darren", $this->m_uri->username());
    }

    /** Ensure the username can be set. */
    public function testUsername2(): void
    {
        $uri = $this->m_uri->withUsername("susan");
        self::assertSame("susan", $uri->username());
    }

    /** Ensure setting the username preserves immutability. */
    public function testUsername3(): void
    {
        $this->m_uri->withUsername("susan");
        self::assertSame("darren", $this->m_uri->username());
    }

    /** Ensure the password is reported correctly. */
    public function testPassword1(): void
    {
        self::assertSame("password", $this->m_uri->password());
    }

    /** Ensure the password can be set. */
    public function testPassword2(): void
    {
        $uri = $this->m_uri->withPassword("secret");
        self::assertSame("secret", $uri->password());
    }

    /** Ensure setting the password preserves immutability.. */
    public function testPassword3(): void
    {
        $this->m_uri->withPassword("secret");
        self::assertSame("password", $this->m_uri->password());
    }

    /** Ensure the password can be removed. */
    public function testPassword4(): void
    {
        $uri = $this->m_uri->withoutPassword();
        self::assertNull($uri->password());
    }

    /** Ensure removing the password preserves immutability. */
    public function testPassword5(): void
    {
        $this->m_uri->withoutPassword();
        self::assertSame("password", $this->m_uri->password());
    }

    /** Ensure the user info is reported correctly. */
    public function testUserInfo1(): void
    {
        self::assertSame("darren", $this->m_uri->userInfo()?->username());
        self::assertSame("password", $this->m_uri->userInfo()?->password());
    }

    /** Ensure the user info can be removed. */
    public function testUserInfo2(): void
    {
        $uri = $this->m_uri->withoutUserInfo();
        self::assertNull($uri->userInfo());
    }

    /** Ensure removing the user info preserves immutability. */
    public function testUserInfo3(): void
    {
        $this->m_uri->withoutUserInfo();
        self::assertSame("darren", $this->m_uri->userInfo()?->username());
        self::assertSame("password", $this->m_uri->userInfo()?->password());
    }

    /** Ensure the host is reported correctly. */
    public function testHost1(): void
    {
        self::assertSame("example.org", $this->m_uri->host());
    }

    /** Ensure the host can be set. */
    public function testHost2(): void
    {
        $uri = $this->m_uri->withHost("example.com");
        self::assertSame("example.com", $uri->host());
    }

    /** Ensure setting the host preserves immutability. */
    public function testHost3(): void
    {
        $this->m_uri->withHost("example.com");
        self::assertSame("example.org", $this->m_uri->host());
    }

    /** Ensure the port is reported correctly. */
    public function testPort1(): void
    {
        self::assertSame(22, $this->m_uri->port());
    }

    /** Ensure the port can be set. */
    public function testPort2(): void
    {
        $uri = $this->m_uri->withPort(509);
        self::assertSame(509, $uri->port());
    }

    /** Ensure setting the port preserves immutability. */
    public function testPort3(): void
    {
        $this->m_uri->withPort(509);
        self::assertSame(22, $this->m_uri->port());
    }

    /** Ensure the port can be removed. */
    public function testPort4(): void
    {
        $uri = $this->m_uri->withoutPort();
        self::assertNull($uri->port());
    }

    /** Ensure removing the port preserves immutability. */
    public function testPort5(): void
    {
        $this->m_uri->withoutPort();
        self::assertSame(22, $this->m_uri->port());
    }

    /** Ensure the path is reported correctly. */
    public function testPath1(): void
    {
        self::assertSame("/home", $this->m_uri->path());
    }

    /** Ensure the path can be set. */
    public function testPath2(): void
    {
        $uri = $this->m_uri->withPath("/elsewhere");
        self::assertSame("/elsewhere", $uri->path());
    }

    /** Ensure setting the path preserves immutability. */
    public function testPath3(): void
    {
        $this->m_uri->withPath("/elsewhere");
        self::assertSame("/home", $this->m_uri->path());
    }

    /** Ensure the query string is reported correctly. */
    public function testQuery1(): void
    {
        self::assertSame("framework=bead", $this->m_uri->query());
    }

    /** Ensure the query can be set. */
    public function testQuery2(): void
    {
        $uri = $this->m_uri->withQuery("foo=bar");
        self::assertSame("foo=bar", $uri->query());
    }

    /** Ensure setting the query preserves immutability. */
    public function testQuery3(): void
    {
        $this->m_uri->withQuery("foo=bar");
        self::assertSame("framework=bead", $this->m_uri->query());
    }

    /** Ensure the query can be removed. */
    public function testQuery4(): void
    {
        $uri = $this->m_uri->withoutQuery();
        self::assertSame("", $uri->query());
    }

    /** Ensure removing the query preserves immutability. */
    public function testQuery5(): void
    {
        $this->m_uri->withoutQuery();
        self::assertSame("framework=bead", $this->m_uri->query());
    }
}
