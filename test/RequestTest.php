<?php

declare(strict_types=1);

namespace BeadTests;

use Bead\Request;
use BeadTests\Framework\TestCase;
use ReflectionProperty;

class RequestTest extends TestCase
{
    public function setUp(): void
    {
        $_SERVER["REQUEST_METHOD"] = "GET";
    }

    public function tearDown(): void
    {
        // ensure the request doesn't persist between tests.
        $property = new ReflectionProperty(Request::class, "s_originalRequest");
        $property->setAccessible(true);
        $property->setValue(null);
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
        echo $_SERVER["REQUEST_URI"] . chr(10);
        self::assertEquals("/", Request::originalRequest()->path());
    }
}
