<?php

namespace BeadTests\Exceptions\Http;

use Bead\Contracts\Web\Request as RequestContract;
use Bead\Core\Application;
use BeadTests\Framework\TestCase;
use Bead\Exceptions\Http\HttpException;
use Equit\XRay\StaticXRay;
use Mockery;
use RuntimeException;

/** @covers \Bead\Exceptions\Http\HttpException */
class HttpExceptionTest extends TestCase
{
    /** @var RequestContract&Mockery\MockInterface */
    private RequestContract $request;

    protected function setUp(): void
    {
        $this->request = Mockery::mock(RequestContract::class);
    }

    public function tearDown(): void
    {
        unset($this->request);
        parent::tearDown();
    }

    /** Ensure the default message is an empty string. */
    public function testConstructor1(): void
    {
        $exception = new class ($this->request) extends HttpException
        {
            public function statusCode(): int
            {
                return 200;
            }
        };

        self::assertSame("", $exception->getMessage());
    }

    /** Ensure the default code is 0. */
    public function testConstructor2(): void
    {
        $exception = new class ($this->request) extends HttpException
        {
            public function statusCode(): int
            {
                return 200;
            }
        };

        self::assertSame(0, $exception->getCode());
    }

    /** Ensure the default previous Throwable is null. */
    public function testConstructor3(): void
    {
        $exception = new class ($this->request) extends HttpException
        {
            public function statusCode(): int
            {
                return 200;
            }
        };

        self::assertNull($exception->getPrevious());
    }

    /** Ensure the constructor sets the provided Request. */
    public function testConstructor4(): void
    {
        $exception = new class ($this->request) extends HttpException
        {
            public function statusCode(): int
            {
                return 200;
            }
        };

        self::assertSame($this->request, $exception->getRequest());
    }

    /** Ensure the constructor uses the provided message. */
    public function testConstructor5(): void
    {
        $exception = new class ($this->request, "Test message") extends HttpException
        {
            public function statusCode(): int
            {
                return 200;
            }
        };

        self::assertSame("Test message", $exception->getMessage());
    }

    /** Ensure the constructor uses the provided code. */
    public function testConstructor6(): void
    {
        $exception = new class ($this->request, code: 42) extends HttpException
        {
            public function statusCode(): int
            {
                return 200;
            }
        };

        self::assertSame(42, $exception->getCode());
    }

    /** Ensure the constructor uses the provided previous Throwable. */
    public function testConstructor7(): void
    {
        $previous = new RuntimeException();

        $exception = new class ($this->request, previous: $previous) extends HttpException
        {
            public function statusCode(): int
            {
                return 200;
            }
        };

        self::assertSame($previous, $exception->getPrevious());
    }

    /** Ensure the content-type is text/html. */
    public function testContentType1(): void
    {
        $exception = new class ($this->request) extends HttpException
        {
            public function statusCode(): int
            {
                return 200;
            }
        };

        self::assertSame("text/html", $exception->contentType());
    }

    /** Ensure the exception renders the appropriate view. */
    public function testContent1(): void
    {
        $app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $app);

        $app->expects("config")
            ->once()
            ->with("app.http.error.view.path")
            ->andReturn("errors");

        $app->expects("config")
            ->once()
            ->with("view.directory", "views")
            ->andReturn("views");

        $app->expects("rootDir")
            ->once()
            ->withNoArgs()
            ->andReturn(__DIR__);

        $exception = new class ($this->request) extends HttpException
        {
            public function statusCode(): int
            {
                return 404;
            }
        };

        self::assertSame(file_get_contents(__DIR__ . "/views/errors/404.php"), $exception->content());
    }

    /** Ensure the exception falls back on the built-in content when the view doesn't exist. */
    public function testContent2(): void
    {
        $app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $app);

        $app->expects("config")
            ->once()
            ->with("app.http.error.view.path")
            ->andReturn("errors");

        $app->expects("config")
            ->once()
            ->with("view.directory", "views")
            ->andReturn("views");

        $app->expects("rootDir")
            ->once()
            ->withNoArgs()
            ->andReturn(__DIR__);

        $app->expects("isInDebugMode")
            ->once()
            ->withNoArgs()
            ->andReturn(true);

        $exception = new class ($this->request, "Error message") extends HttpException
        {
            public function statusCode(): int
            {
                return 500;
            }
        };

        self::assertSame(
            <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<title>HTTP Error 500</title>
</head>
<body>
<h2>HTTP Error 500 <em>Internal Server Error</em></h2>
<p>Error message</p>
</body>
</html>
HTML,
            $exception->content(),
        );
    }

    /** Ensure the exception falls back on the built-in content when no view path is defined. */
    public function testContent3(): void
    {
        $app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $app);

        $app->expects("config")
            ->once()
            ->with("app.http.error.view.path")
            ->andReturn(null);

        $app->expects("isInDebugMode")
            ->once()
            ->withNoArgs()
            ->andReturn(true);

        $exception = new class ($this->request, "Error message") extends HttpException
        {
            public function statusCode(): int
            {
                return 500;
            }
        };

        self::assertSame(
            <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<title>HTTP Error 500</title>
</head>
<body>
<h2>HTTP Error 500 <em>Internal Server Error</em></h2>
<p>Error message</p>
</body>
</html>
HTML,
            $exception->content(),
        );
    }

    /** Ensure the content doesn't output an empty paragraph when the exception has no message. */
    public function testContent4(): void
    {
        $app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $app);

        $app->expects("config")
            ->once()
            ->with("app.http.error.view.path")
            ->andReturn("errors");

        $app->expects("config")
            ->once()
            ->with("view.directory", "views")
            ->andReturn("views");

        $app->expects("rootDir")
            ->once()
            ->withNoArgs()
            ->andReturn(__DIR__);

        $app->expects("isInDebugMode")
            ->once()
            ->withNoArgs()
            ->andReturn(true);

        $exception = new class ($this->request) extends HttpException
        {
            public function statusCode(): int
            {
                return 500;
            }
        };

        self::assertSame(
            <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<title>HTTP Error 500</title>
</head>
<body>
<h2>HTTP Error 500 <em>Internal Server Error</em></h2>

</body>
</html>
HTML,
            $exception->content(),
        );
    }

    /** Ensure the content doesn't output an the exception message when not in debug mode. */
    public function testContent5(): void
    {
        $app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $app);

        $app->expects("config")
            ->once()
            ->with("app.http.error.view.path")
            ->andReturn("errors");

        $app->expects("config")
            ->once()
            ->with("view.directory", "views")
            ->andReturn("views");

        $app->expects("rootDir")
            ->once()
            ->withNoArgs()
            ->andReturn(__DIR__);

        $app->expects("isInDebugMode")
            ->once()
            ->withNoArgs()
            ->andReturn(false);

        $exception = new class ($this->request, "Private exception message") extends HttpException
        {
            public function statusCode(): int
            {
                return 500;
            }
        };

        self::assertSame(
            <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<title>HTTP Error 500</title>
</head>
<body>
<h2>HTTP Error 500 <em>Internal Server Error</em></h2>

</body>
</html>
HTML,
            $exception->content(),
        );
    }
}
