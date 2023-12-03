<?php

namespace BeadTests\Core;

use Bead\Contracts\Logger;
use Bead\Core\Application;
use Bead\Core\ErrorHandler;
use Bead\Exceptions\Http\NotFoundException;
use Bead\Exceptions\ViewNotFoundException;
use Bead\Responses\AbstractResponse;
use Bead\Testing\XRay;
use Bead\View;
use Bead\Web\Application as WebApplication;
use Bead\Web\Request;
use BeadTests\Framework\TestCase;
use Error;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

class ErrorHandlerTest extends TestCase
{
    private ErrorHandler $handler;

    public function setUp(): void
    {
        $this->handler = new ErrorHandler();
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->handler);
        parent::tearDown();
    }

    /** @return MockInterface&Application */
    private function mockApplication($class = Application::class): Application
    {
        $mock = Mockery::mock($class);
        $this->mockMethod(Application::class, "instance", $mock);
        return $mock;
    }

    /** Ensure shouldDisplay() returns true when the app is in debug mode. */
    public function testShouldDisplay1(): void
    {
        $app = $this->mockApplication();
        $app->shouldReceive("isInDebugMode")->andReturn(true);
        $handler = new XRay($this->handler);
        self::assertTrue($handler->shouldDisplay(new InvalidArgumentException()));
    }

    /** Ensure shouldDisplay() returns false when the app is not in debug mode. */
    public function testShouldDisplay2(): void
    {
        $app = $this->mockApplication();
        $app->shouldReceive("isInDebugMode")->andReturn(false);
        $handler = new XRay($this->handler);
        self::assertFalse($handler->shouldDisplay(new InvalidArgumentException()));
    }

    /** Ensure shouldDisplay() returns false when there is no app. */
    public function testShouldDisplay3(): void
    {
        $handler = new XRay($this->handler);
        self::assertFalse($handler->shouldDisplay(new InvalidArgumentException()));
    }

    /** Ensure we get the expected view name. */
    public function testExceptionDisplayViewName1(): void
    {
        $handler = new XRay($this->handler);
        self::assertEquals("errors.exception", $handler->exceptionDisplayViewName());
    }

    /** Ensure we get the expected view name. */
    public function testErrorPageViewName1(): void
    {
        $handler = new XRay($this->handler);
        self::assertEquals("errors.error", $handler->errorPageViewName());
    }

    /** Ensure report() logs the expected message. */
    public function testReport1(): void
    {
        $error = new InvalidArgumentException("Mock exception.");

        $log = Mockery::mock(Logger::class);

        // it would be foolhardy to set test expectations based on the exception line not changing, so we don't
        // assert to verify the line, just validate it
        $log->shouldReceive("critical")
            ->once()
            ->with("Exception in %1[%2]: Mock exception.", Mockery::on(function (mixed $args): bool {
                TestCase::assertIsArray($args);
                TestCase::assertIsString($args[0]);
                TestCase::assertEquals(__FILE__, $args[0]);
                TestCase::assertIsInt($args[1]);
                TestCase::assertGreaterThanOrEqual(0, $args[1]);
                return true;
            }));

        $app = $this->mockApplication();
        $app->shouldReceive("get")
            ->with(Logger::class)
            ->andReturn($log);

        $handler = new XRay($this->handler);
        $handler->report($error);
        self::markTestAsExternallyVerified();
    }

    /** Ensure errors get converted to the expected exceptions. */
    public function testHandleError1(): void
    {
        self::expectException(Error::class);
        self::expectExceptionMessage("PHP error in /a/mock/file.php@42: Mock error");
        $this->handler->handleError(E_ERROR, "Mock error", "/a/mock/file.php", 42);
    }

    /** Ensure handleException() calls report(). */
    public function testHandleException1(): void
    {
        $called = false;
        $log = Mockery::mock(Logger::class);

        // it would be foolhardy to set test expectations based on the exception line not changing, so we don't
        // assert to verify the line, just validate it
        $log->shouldReceive("critical")
            ->once()
            ->with("Exception in %1[%2]: Mock exception.", Mockery::on(function (mixed $args) use (&$called): bool {
                TestCase::assertIsArray($args);
                TestCase::assertIsString($args[0]);
                TestCase::assertEquals(__FILE__, $args[0]);
                TestCase::assertIsInt($args[1]);
                TestCase::assertGreaterThanOrEqual(0, $args[1]);
                $called = true;
                return true;
            }));

        $app = $this->mockApplication();

        $app->shouldReceive("get")
            ->with(Logger::class)
            ->andReturn($log);

        $app->shouldReceive("isInDebugMode")
            ->andReturn(false);

        $this->handler->handleException(new InvalidArgumentException("Mock exception."));
        self::assertTrue($called);
    }

    /** Ensure handleException() sends the exception as a response if it implements the Response contract. */
    public function testHandleException2(): void
    {
        $called = false;
        $request = Mockery::mock(Request::class);
        $error = new NotFoundException($request, "Mock exception");
        $log = Mockery::mock(Logger::class);

        // it would be foolhardy to set test expectations based on the exception line not changing, so we don't
        // assert to verify the line, just validate it
        $log->shouldReceive("critical")
            ->once()
            ->with("Exception in %1[%2]: Mock exception", Mockery::on(function (mixed $args) use (&$called): bool {
                TestCase::assertIsArray($args);
                TestCase::assertIsString($args[0]);
                TestCase::assertEquals(__FILE__, $args[0]);
                TestCase::assertIsInt($args[1]);
                TestCase::assertGreaterThanOrEqual(0, $args[1]);
                $called = true;
                return true;
            }));

        $app = $this->mockApplication(WebApplication::class);

        $app->shouldReceive("get")
            ->with(Logger::class)
            ->andReturn($log);

        $app->shouldReceive("sendResponse")->with($error);
        $app->shouldNotReceive("isInDebugMode");

        $this->handler->handleException($error);
        self::assertTrue($called);
    }

    /** Ensure handleException() sends the exception display view when in debug mode. */
    public function testHandleException3(): void
    {
        $reportCalled = false;
        $sendResponseCalled = false;
        $error = new InvalidArgumentException("Mock exception");
        $log = Mockery::mock(Logger::class);

        // it would be foolhardy to set test expectations based on the exception line not changing, so we don't
        // assert to verify the line, just validate it
        $log->shouldReceive("critical")
            ->once()
            ->with("Exception in %1[%2]: Mock exception", Mockery::on(function (mixed $args) use (&$reportCalled): bool {
                TestCase::assertIsArray($args);
                TestCase::assertIsString($args[0]);
                TestCase::assertEquals(__FILE__, $args[0]);
                TestCase::assertIsInt($args[1]);
                TestCase::assertGreaterThanOrEqual(0, $args[1]);
                $reportCalled = true;
                return true;
            }));

        $app = $this->mockApplication(WebApplication::class);

        $app->shouldReceive("get")
            ->with(Logger::class)
            ->andReturn($log);

        $app->shouldReceive("isInDebugMode")->andReturn(true);
        $app->shouldReceive("rootDir")->andReturn(__DIR__ . "/files");
        $app->shouldReceive("config")->with("view.directory", Mockery::any())->andReturn("views");

        $app->shouldReceive("sendResponse")->with(Mockery::on(function (mixed $response) use (&$sendResponseCalled): bool {
            TestCase::assertInstanceOf(View::class, $response);
            TestCase::assertEquals("errors.exception", $response->name());
            $sendResponseCalled = true;
            return true;
        }));

        $this->handler->handleException($error);
        self::assertTrue($reportCalled);
        self::assertTrue($sendResponseCalled);
    }

    /** Ensure handleException() sends the fallback response when in debug mode and the exception view fails. */
    public function testHandleException4(): void
    {
        $reportCalled = false;
        $sendResponseCalled = false;
        $error = new InvalidArgumentException("Mock exception");
        $log = Mockery::mock(Logger::class);

        // it would be foolhardy to set test expectations based on the exception line not changing, so we don't
        // assert to verify the line, just validate it
        $log->shouldReceive("critical")
            ->once()
            ->with("Exception in %1[%2]: Mock exception", Mockery::on(function (mixed $args) use (&$reportCalled): bool {
                TestCase::assertIsArray($args);
                TestCase::assertIsString($args[0]);
                TestCase::assertEquals(__FILE__, $args[0]);
                TestCase::assertIsInt($args[1]);
                TestCase::assertGreaterThanOrEqual(0, $args[1]);
                $reportCalled = true;
                return true;
            }));

        $app = $this->mockApplication(WebApplication::class);

        $app->shouldReceive("get")
            ->with(Logger::class)
            ->andReturn($log);

        $app->shouldReceive("isInDebugMode")->andReturn(true);
        $app->shouldReceive("rootDir")->andReturn(__DIR__ . "/files");
        $app->shouldReceive("config")->with("view.directory", Mockery::any())->andReturn("no-views-here");

        $app->shouldReceive("sendResponse")->with(Mockery::on(function (mixed $response) use (&$sendResponseCalled): bool {
            TestCase::assertNotInstanceOf(View::class, $response);
            TestCase::assertInstanceOf(AbstractResponse::class, $response);
            TestCase::assertEquals(500, $response->statusCode());
            TestCase::assertEquals("text/plain", $response->contentType());
            TestCase::assertEquals("Mock exception", $response->content());
            $sendResponseCalled = true;
            return true;
        }));

        $this->handler->handleException($error);
        self::assertTrue($reportCalled);
        self::assertTrue($sendResponseCalled);
    }

    /**
     * Ensure handleException() sends the response of last resort when the app is in debug mode, the exception view
     * fails and the fallback response fails.
     */
    public function testHandleException5(): void
    {
        $reportCalled = false;
        $sendResponseCalled = false;
        $bufferCallbackCalled = false;
        $error = new InvalidArgumentException("Mock exception");
        $log = Mockery::mock(Logger::class);

        // it would be foolhardy to set test expectations based on the exception line not changing, so we don't
        // assert to verify the line, just validate it
        $log->shouldReceive("critical")
            ->once()
            ->with("Exception in %1[%2]: Mock exception", Mockery::on(function (mixed $args) use (&$reportCalled): bool {
                TestCase::assertIsArray($args);
                TestCase::assertIsString($args[0]);
                TestCase::assertEquals(__FILE__, $args[0]);
                TestCase::assertIsInt($args[1]);
                TestCase::assertGreaterThanOrEqual(0, $args[1]);
                $reportCalled = true;
                return true;
            }));

        $app = $this->mockApplication(WebApplication::class);

        $app->shouldReceive("get")
            ->with(Logger::class)
            ->andReturn($log);

        $app->shouldReceive("isInDebugMode")->andReturn(true);
        $app->shouldReceive("rootDir")->andReturn(__DIR__ . "/files");
        $app->shouldReceive("config")->with("view.directory", Mockery::any())->andReturn("no-views-here");

        $app->shouldReceive("sendResponse")
            ->once()
            ->with(Mockery::on(function (mixed $response) use (&$sendResponseCalled): bool {
                TestCase::assertNotInstanceOf(View::class, $response);
                TestCase::assertInstanceOf(AbstractResponse::class, $response);
                TestCase::assertEquals(500, $response->statusCode());
                TestCase::assertEquals("text/plain", $response->contentType());
                TestCase::assertEquals("Mock exception", $response->content());
                $sendResponseCalled = true;
                return true;
            }))
            ->andThrow(new RuntimeException("Exception sending fallback response."));

        ob_start(function (string $content) use (&$bufferCallbackCalled): string {
            TestCase::assertEquals("Mock exception", $content);
            $bufferCallbackCalled = true;
            return "";
        });
        $this->handler->handleException($error);
        ob_end_flush();
        self::assertTrue($reportCalled);
        self::assertTrue($sendResponseCalled);
        self::assertTrue($bufferCallbackCalled);
    }

    /** Ensure handleException() sends error page when not in debug mode and exception is not a Response. */
    public function testHandleException6(): void
    {
        $reportCalled = false;
        $sendResponseCalled = false;
        $error = new InvalidArgumentException("Mock exception");
        $log = Mockery::mock(Logger::class);

        // it would be foolhardy to set test expectations based on the exception line not changing, so we don't
        // assert to verify the line, just validate it
        $log->shouldReceive("critical")
            ->once()
            ->with("Exception in %1[%2]: Mock exception", Mockery::on(function (mixed $args) use (&$reportCalled): bool {
                TestCase::assertIsArray($args);
                TestCase::assertIsString($args[0]);
                TestCase::assertEquals(__FILE__, $args[0]);
                TestCase::assertIsInt($args[1]);
                TestCase::assertGreaterThanOrEqual(0, $args[1]);
                $reportCalled = true;
                return true;
            }));

        $app = $this->mockApplication(WebApplication::class);

        $app->shouldReceive("get")
            ->with(Logger::class)
            ->andReturn($log);

        $app->shouldReceive("isInDebugMode")->andReturn(false);
        $app->shouldReceive("rootDir")->andReturn(__DIR__ . "/files");
        $app->shouldReceive("config")->with("view.directory", Mockery::any())->andReturn("views");

        $app->shouldReceive("sendResponse")->with(Mockery::on(function (mixed $response) use (&$sendResponseCalled): bool {
            TestCase::assertInstanceOf(View::class, $response);
            TestCase::assertEquals("errors.error", $response->name());
            $sendResponseCalled = true;
            return true;
        }));

        $this->handler->handleException($error);
        self::assertTrue($reportCalled);
        self::assertTrue($sendResponseCalled);
    }

    /**
     * Ensure handleException() sends the fallback error page when not in debug mode, the exception is not a Response
     * and the error page view fails.
     */
    public function testHandleException7(): void
    {
        $reportCalled = false;
        $sendFirstResponseCalled = false;
        $sendSecondResponseCalled = false;
        $error = new InvalidArgumentException("Mock exception");
        $log = Mockery::mock(Logger::class);

        // it would be foolhardy to set test expectations based on the exception line not changing, so we don't
        // assert to verify the line, just validate it
        $log->shouldReceive("critical")
            ->once()
            ->with("Exception in %1[%2]: Mock exception", Mockery::on(function (mixed $args) use (&$reportCalled): bool {
                TestCase::assertIsArray($args);
                TestCase::assertIsString($args[0]);
                TestCase::assertEquals(__FILE__, $args[0]);
                TestCase::assertIsInt($args[1]);
                TestCase::assertGreaterThanOrEqual(0, $args[1]);
                $reportCalled = true;
                return true;
            }));

        $app = $this->mockApplication(WebApplication::class);

        $app->shouldReceive("get")
            ->with(Logger::class)
            ->andReturn($log);

        $app->shouldReceive("isInDebugMode")->andReturn(false);
        $app->shouldReceive("rootDir")->andReturn(__DIR__ . "/files");
        $app->shouldReceive("config")->with("view.directory", Mockery::any())->andReturn("views");

        $app->shouldReceive("sendResponse")
            ->once()
            ->ordered()
            ->with(Mockery::on(function (mixed $response) use (&$sendFirstResponseCalled): bool {
                TestCase::assertInstanceOf(View::class, $response);
                TestCase::assertEquals("errors.error", $response->name());
                $sendFirstResponseCalled = true;
                return true;
            }))
            ->andThrow(new ViewNotFoundException("errors.error", "Exception sending error page."));

        $app->shouldReceive("sendResponse")
            ->once()
            ->ordered()
            ->with(Mockery::on(function (mixed $response) use (&$sendSecondResponseCalled): bool {
                TestCase::assertNotInstanceOf(View::class, $response);
                TestCase::assertInstanceOf(AbstractResponse::class, $response);
                TestCase::assertEquals("text/html", $response->contentType());
                TestCase::assertEquals(500, $response->statusCode());
                TestCase::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\">", $response->content());
                TestCase::assertStringContainsString("An application error has occurred. It has been reported and should be investigated and fixed in due course.", $response->content());
                TestCase::assertStringEndsWith("</html>", $response->content());
                $sendSecondResponseCalled = true;
                return true;
            }));

        $this->handler->handleException($error);
        self::assertTrue($reportCalled);
        self::assertTrue($sendFirstResponseCalled);
        self::assertTrue($sendSecondResponseCalled);
    }

    /**
     * Ensure handleException() sends the error page of last resort when not in debug mode, the exception is not a
     * Response, and both the error page view and the fallback error page responses fail.
     */
    public function testHandleException8(): void
    {
        $reportCalled = false;
        $sendFirstResponseCalled = false;
        $sendSecondResponseCalled = false;
        $bufferCallbackCalled = false;
        $error = new InvalidArgumentException("Mock exception");
        $log = Mockery::mock(Logger::class);

        // it would be foolhardy to set test expectations based on the exception line not changing, so we don't
        // assert to verify the line, just validate it
        $log->shouldReceive("critical")
            ->once()
            ->with("Exception in %1[%2]: Mock exception", Mockery::on(function (mixed $args) use (&$reportCalled): bool {
                TestCase::assertIsArray($args);
                TestCase::assertIsString($args[0]);
                TestCase::assertEquals(__FILE__, $args[0]);
                TestCase::assertIsInt($args[1]);
                TestCase::assertGreaterThanOrEqual(0, $args[1]);
                $reportCalled = true;
                return true;
            }));

        $app = $this->mockApplication(WebApplication::class);

        $app->shouldReceive("get")
            ->with(Logger::class)
            ->andReturn($log);

        $app->shouldReceive("isInDebugMode")->andReturn(false);
        $app->shouldReceive("rootDir")->andReturn(__DIR__ . "/files");
        $app->shouldReceive("config")->with("view.directory", Mockery::any())->andReturn("views");

        $app->shouldReceive("sendResponse")
            ->once()
            ->ordered()
            ->with(Mockery::on(function (mixed $response) use (&$sendFirstResponseCalled): bool {
                TestCase::assertInstanceOf(View::class, $response);
                TestCase::assertEquals("errors.error", $response->name());
                $sendFirstResponseCalled = true;
                return true;
            }))
            ->andThrow(new ViewNotFoundException("errors.error", "Exception sending error page."));

        $app->shouldReceive("sendResponse")
            ->once()
            ->ordered()
            ->with(Mockery::on(function (mixed $response) use (&$sendSecondResponseCalled): bool {
                TestCase::assertNotInstanceOf(View::class, $response);
                TestCase::assertInstanceOf(AbstractResponse::class, $response);
                TestCase::assertEquals("text/html", $response->contentType());
                TestCase::assertEquals(500, $response->statusCode());
                TestCase::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\">", $response->content());
                TestCase::assertStringContainsString("An application error has occurred. It has been reported and should be investigated and fixed in due course.", $response->content());
                TestCase::assertStringEndsWith("</html>", $response->content());
                $sendSecondResponseCalled = true;
                return true;
            }))
            ->andThrow(new RuntimeException("Exception sending fallback response."));

        ob_start(function (string $content) use (&$bufferCallbackCalled): string {
            TestCase::assertEquals("An application error has occurred. It has been reported and should be investigated and fixed in due course.", $content);
            $bufferCallbackCalled = true;
            return "";
        });
        $this->handler->handleException($error);
        ob_end_flush();
        self::assertTrue($reportCalled);
        self::assertTrue($sendFirstResponseCalled);
        self::assertTrue($sendSecondResponseCalled);
        self::assertTrue($bufferCallbackCalled);
    }

    /**
     * Ensure handleException() outputs the exception details to stderr for non-web applications.
     */
    public function testOutputToStream(): void
    {
        $bufferCallbackCalled = false;
        $handler = new XRay($this->handler);

        $this->mockApplication();
        $stream = fopen("php://temp", "w+");
        $handler->outputToStream(new RuntimeException("Mock exception", 100, null), $stream);
        fflush($stream);
        rewind($stream);

        $content = "";

        while (false !== ($line = (fgets($stream)))) {
            $content .= $line;
        }

        fclose($stream);
        TestCase::assertStringStartsWith("Exception `RuntimeException` in '" . __FILE__ . "'", $content);
        TestCase::assertStringContainsString("\n... from '", $content);
    }
}
