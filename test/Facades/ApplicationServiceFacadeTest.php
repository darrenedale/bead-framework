<?php

declare(strict_types=1);

namespace BeadTests\Facades;

use Bead\Core\Application;
use BeadTests\Framework\TestCase;
use Error;
use LogicException;
use Mockery;
use Mockery\MockInterface;

final class ApplicationServiceFacadeTest extends TestCase
{
    /** @var Application&MockInterface $app*/
    private Application $app;

    public function setUp(): void
    {
        $service = new TestApplicationService();

        $this->app = Mockery::mock(Application::class);
        $this->app->shouldReceive("get")
            ->with(TestApplicationService::class)
            ->andReturn($service)
            ->byDefault();

        $this->mockMethod(Application::class, "instance", $this->app);
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->app);
        parent::tearDown();
    }

    public function testMethod1(): void
    {
        self::assertEquals(TestApplicationService::ExpectedReturnValue, TestApplicationServiceFacade::doSomething());
    }

    public function testInvalidMethod1(): void
    {
        self::expectException(Error::class);
        self::expectExceptionMessage("Call to undefined method " . TestApplicationService::class . "::invalidMethod()");
        TestApplicationServiceFacade::invalidMethod();
    }

    public function testServiceInvalid1(): void
    {
        $this->app->shouldReceive("get")
            ->with(TestApplicationService::class)
            ->andReturn($this);

        self::expectException(LogicException::class);
        self::expectExceptionMessage("Invalid service bound to " . TestApplicationService::class . " interface.");
        TestApplicationServiceFacade::doSomething();
    }

    public function testNoApplication1(): void
    {
        $this->mockMethod(Application::class, "instance", null);
        self::expectException(LogicException::class);
        self::expectExceptionMessage(TestApplicationServiceFacade::class . " facade used without Application container instance.");
        TestApplicationServiceFacade::doSomething();
    }
}
