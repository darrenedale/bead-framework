<?php

namespace BeadTests\Core;

use Bead\Contracts\Logger;
use Bead\Core\Application;
use Bead\Core\ErrorHandler;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use Error;
use Exception;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;

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
    private function mockApplication(): Application
    {
        $mock = Mockery::mock(Application::class);
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
        // it's quite a pitfall to set test expectations based on this line number not changing, so we don't actually
        // assert to verify the exception line, just to validate it
        $error = new InvalidArgumentException("Mock exception.");

        $log = Mockery::mock(Logger::class);

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
        self::expectExceptionMessage("PHP error /a/mock/file.php@42: Mock error");
        $this->handler->handleError(E_ERROR, "Mock error", "/a/mock/file.php", 42);
    }
}
