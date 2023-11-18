<?php

declare(strict_types=1);

namespace BeadTests;

use Bead\Request;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class RequestTest extends TestCase
{
    private Request $request;

    public function setUp(): void
    {
        $_SERVER["REQUEST_METHOD"] = "GET";
        $reflector =new ReflectionClass(Request::class);
        $this->request = $reflector->newInstanceWithoutConstructor();
        $reflector = $reflector->getConstructor();
        self::assertInstanceOf(ReflectionMethod::class, $reflector);
        $reflector->setAccessible(true);
        $reflector->invoke($this->request);
    }

    public function tearDown(): void
    {
        // ensure the request doesn't persist between tests.
        $property = new ReflectionProperty(Request::class, "s_originalRequest");
        $property->setAccessible(true);
        $property->setValue(null);
        unset($this->request);
    }
    
    /** Ensure ipv4 is empty by default. */
    public function testRemoteIpV41(): void
    {
        self::assertEquals("", $this->request->remoteIp4());
    }

    public static function dataForTestSetRemoteIpV41(): iterable
    {
        yield "broadcast" => ["0.0.0.0",];
        yield "typical localhost" => ["127.0.0.1",];
        yield "atypical localhost 1" => ["127.0.0.99",];
        yield "atypical localhost 2" => ["127.0.99.0",];
        yield "atypical localhost 3" => ["127.99.0.0",];
        yield "private 192.168.0.0/16 1" => ["192.168.0.0",];
        yield "private 192.168.0.0/16 2" => ["192.168.0.1",];
        yield "private 192.168.0.0/16 3" => ["192.168.1.0",];
        yield "private 192.168.0.0/16 4" => ["192.168.1.1",];
        yield "private 192.168.0.0/16 5" => ["192.168.0.255",];
        yield "private 192.168.0.0/16 6" => ["192.168.255.0",];
        yield "private 192.168.0.0/16 7" => ["192.168.255.255",];
        yield "private 192.168.0.0/16 8" => ["192.168.165.32",];
        yield "private 192.168.0.0/16 9" => ["192.168.12.191",];
        yield "private 10.0.0.0/8 1" => ["10.0.0.0",];
        yield "private 10.0.0.0/8 2" => ["10.0.0.1",];
        yield "private 10.0.0.0/8 3" => ["10.0.1.0",];
        yield "private 10.0.0.0/8 4" => ["10.0.1.1",];
        yield "private 10.0.0.0/8 5" => ["10.1.0.0",];
        yield "private 10.0.0.0/8 6" => ["10.1.0.1",];
        yield "private 10.0.0.0/8 7" => ["10.1.1.0",];
        yield "private 10.0.0.0/8 8" => ["10.1.1.1",];
        yield "private 10.0.0.0/8 9" => ["10.0.0.255",];
        yield "private 10.0.0.0/8 10" => ["10.0.255.0",];
        yield "private 10.0.0.0/8 11" => ["10.0.255.255",];
        yield "private 10.0.0.0/8 12" => ["10.255.0.0",];
        yield "private 10.0.0.0/8 13" => ["10.255.0.255",];
        yield "private 10.0.0.0/8 14" => ["10.255.255.0",];
        yield "private 10.0.0.0/8 15" => ["10.255.255.255",];
        yield "private 10.0.0.0/8 16" => ["10.99.108.14",];
        yield "private 10.0.0.0/8 17" => ["10.54.42.7",];
        yield "private 172.16.0.0/12 1" => ["172.16.0.0",];
        yield "private 172.16.0.0/12 2" => ["172.16.0.1",];
        yield "private 172.16.0.0/12 3" => ["172.16.1.0",];
        yield "private 172.16.0.0/12 4" => ["172.16.1.1",];
        yield "private 172.16.0.0/12 5" => ["172.16.0.255",];
        yield "private 172.16.0.0/12 6" => ["172.16.255.0",];
        yield "private 172.16.0.0/12 7" => ["172.16.255.255",];
        yield "private 172.16.0.0/12 8" => ["172.31.0.0",];
        yield "private 172.16.0.0/12 9" => ["172.31.0.255",];
        yield "private 172.16.0.0/12 10" => ["172.31.255.0",];
        yield "private 172.16.0.0/12 11" => ["172.31.255.255",];
        yield "private 172.16.0.0/12 12" => ["172.28.10.19",];
        yield "private 172.16.0.0/12 13" => ["172.17.24.18",];
        yield "public UK" => ["86.4.47.14",];
    }

    /** @dataProvider dataForTestSetRemoteIpV41 */
    public function testSetRemoteIpV41(string $ip): void
    {
        $this->request->setRemoteIp4($ip);
        self::assertEquals($ip, $this->request->remoteIp4());
    }

    public static function dataForTestSetRemoteIpV42(): iterable
    {
        yield "empty" => ["",];
        yield "whitespace" => ["   ",];
        yield "whitespace before" => [" 192.168.1.1",];
        yield "whitespace after" => ["192.168.1.1 ",];
        yield "whitespace inside" => ["192. 168.1.1",];
        yield "excess segments" => ["192.168.1.1.1",];
        yield "insufficient segments" => ["192.168.1",];
        yield "msb too large" => ["256.168.1.1",];
        yield "msb2 too large" => ["192.256.1.1",];
        yield "lsb2 too large" => ["192.168.256.1",];
        yield "lsb too large" => ["192.168.1.256",];
        yield "invalid characters" => ["a.b.c.d",];
        yield "broadcast leading 0s" => ["000.000.000.000",];
        yield "private leading 0s" => ["010.001.001.001",];
        yield "nonsense" => ["bead framework",];
    }

    /** @dataProvider dataForTestSetRemoteIpV42 */
    public function testSetRemoteIpV42(string $ip): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid IPv4 dotted-decimal address, found \"{$ip}\"");
        $this->request->setRemoteIp4($ip);
    }
    
    /** Ensure ipV6 is empty by default. */
    public function testRemoteIpV61(): void
    {
        self::assertEquals("", $this->request->remoteIp6());
    }

    public static function dataForTestSetRemoteIpV61(): iterable
    {
        yield "broadcast full" => ["0000:0000:0000:0000:0000:0000:0000:0000",];
        yield "broadcast leading 0s suppressed" => ["0:0:0:0:0:0:0:0",];
        yield "broadcast compressed" => ["::",];
        yield "localhost full" => ["0000:0000:0000:0000:0000:0000:0000:0002",];
        yield "localhost leading 0s suppressed" => ["0:0:0:0:0:0:0:1",];
        yield "localhost compressed" => ["::1",];
        yield "full" => ["2001:0db8:85a3:0000:0000:8a2e:0370:7334",];
        yield "leading 0s suppressed" => ["2001:db8:85a3:0:0:8a2e:370:7334",];
        yield "0 run compressed" => ["2001:db8:85a3::8a2e:370:7334",];
    }

    /** @dataProvider dataForTestSetRemoteIpV61 */
    public function testSetRemoteIpV61(string $ip): void
    {
        $this->request->setRemoteIp6($ip);
        self::assertEquals($ip, $this->request->remoteIp6());
    }

    public static function dataForTestSetRemoteIpV62(): iterable
    {
        yield "empty" => ["",];
        yield "whitespace" => ["   ",];
        yield "whitespace before" => [" ::",];
        yield "whitespace after" => [":: ",];
        yield "whitespace inside" => [": :",];
        yield "excess segments" => ["2001:db8:85a3:0:0:8a2e:370:7334:0",];
        yield "insufficient segments" => ["2001:db8:85a3:0:0:8a2e:370",];
        yield "invalid characters" => ["200g:db8:85a3:0:0:8a2e:370:7334",];
        yield "nonsense" => ["bead framework",];
    }

    /** @dataProvider dataForTestSetRemoteIpV62 */
    public function testSetRemoteIpV62(string $ip): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid IPv6 address, found \"{$ip}\"");
        $this->request->setRemoteIp6($ip);
    }

    public static function dataForTestOriginalRequest1(): iterable
    {
        yield "simple URI" => ["http://bead.example.com/home", "/home",];
        yield "URI with port" => ["http://bead.example.com:8080/home", "/home",];
        yield "URI with file" => ["http://bead.example.com:8080/home/index.php", "/home",];
        yield "URI with query string" => ["http://bead.example.com:8080/home/?bead=framework", "/home",];
        yield "URI with file, query string" => ["http://bead.example.com:8080/home/index.php?bead=framework", "/home",];
        yield "URI with fragment" => ["http://bead.example.com:8080/home/#bead", "/home",];
        yield "URI with file, fragment" => ["http://bead.example.com:8080/home/index.php#bead", "/home",];
        yield "URI with file, query string, fragment" => ["http://bead.example.com:8080/home/index.php#bead?bead=framework", "/home",];
        yield "URI with username, port" => ["http://bead@bead.example.com:8080/home", "/home",];
        yield "URI with username, file" => ["http://bead@bead.example.com:8080/home/index.php", "/home",];
        yield "URI with username, query string" => ["http://bead@bead.example.com:8080/home/?bead=framework", "/home",];
        yield "URI with username, file and query string" => ["http://bead@bead.example.com:8080/home/index.php?bead=framework", "/home",];
        yield "URI with username, fragment" => ["http://bead@bead.example.com:8080/home/#bead", "/home",];
        yield "URI with username, file and fragment" => ["http://bead@bead.example.com:8080/home/index.php#bead", "/home",];
        yield "URI with username, file, query string and fragment" => ["http://bead@bead.example.com:8080/home/index.php#bead?bead=framework", "/home",];
        yield "URI with username, password, port" => ["http://bead:framework@bead.example.com:8080/home", "/home",];
        yield "URI with username, password, file" => ["http://bead:framework@bead.example.com:8080/home/index.php", "/home",];
        yield "URI with username, password, query string" => ["http://bead:framework@bead.example.com:8080/home/?bead=framework", "/home",];
        yield "URI with username, password, file and query string" => ["http://bead:framework@bead.example.com:8080/home/index.php?bead=framework", "/home",];
        yield "URI with username, password, fragment" => ["http://bead:framework@bead.example.com:8080/home/#bead", "/home",];
        yield "URI with username, password, file and fragment" => ["http://bead:framework@bead.example.com:8080/home/index.php#bead", "/home",];
        yield "URI with username, password, file, query string and fragment" => ["http://bead:framework@bead.example.com:8080/home/index.php#bead?bead=framework", "/home",];
    }

    /**
     * Ensure the path is extracted from the request URI
     *
     * @dataProvider dataForTestOriginalRequest1
     */
    public function testOriginalRequest1(string $uri, string $expectedPath): void
    {
        $_SERVER["REQUEST_URI"] = $expectedPath;
        self::assertEquals($expectedPath, Request::originalRequest()->path());
    }

    /** Ensure a request URI without a path gets "/" as the path. */
    public function testOriginalRequest2(): void
    {
        $_SERVER["REQUEST_URI"] = "http://bead.example.com";
        self::assertEquals("/", Request::originalRequest()->path());
    }

    /** Ensure IPv4 is successfully read from $_SERVER */
    public function testOriginalRequest3(): void
    {
        $_SERVER["REQUEST_URI"] = "http://bead.example.com";
        $_SERVER["REMOTE_ADDR"] = "172.16.1.81";
        self::assertEquals("172.16.1.81", Request::originalRequest()->remoteIp4());
        self::assertEquals("", Request::originalRequest()->remoteIp6());
    }

    /** Ensure IPv6 is successfully read from $_SERVER */
    public function testOriginalRequest4(): void
    {
        $_SERVER["REQUEST_URI"] = "http://bead.example.com";
        $_SERVER["REMOTE_ADDR"] = "2001:db8:85a3::8a2e:370:7334";
        self::assertEquals("", Request::originalRequest()->remoteIp4());
        self::assertEquals("2001:db8:85a3::8a2e:370:7334", Request::originalRequest()->remoteIp6());
    }
}
