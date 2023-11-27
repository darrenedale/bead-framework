<?php

declare(strict_types=1);

namespace BeadTests\Session;

use Bead\Contracts\SessionHandler;
use Bead\Core\Application;
use Bead\Session\Session;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

final class SessionTestTest extends TestCase
{
    // 2023-11-27 08:00:00
    private const CurrentTimestamp = 1701072000;

    private const SessionId = "abcdefghijklmnopqrstuvwxyzyxwvutsrqponmlkjihgfedcba";

    private Session $session;

    /** @var SessionHandler&MockInterface */
    private SessionHandler $handler;

    /** @var Application&MockInterface */
    private Application $app;

    public function setUp(): void
    {
        $this->handler = Mockery::mock(SessionHandler::class);
        $this->app = Mockery::mock(Application::class);

        $this->handler->shouldReceive("id")->andReturn(self::SessionId)->byDefault();
        $this->handler->shouldReceive("idHasExpired")->andReturn(false)->byDefault();
        $this->handler->shouldReceive("lastUsedAt")->andReturn(self::CurrentTimestamp - 10)->byDefault();
        $this->handler->shouldReceive("idGeneratedAt")->andReturn(self::CurrentTimestamp - 120)->byDefault();
        $this->handler->shouldReceive("get")->with("__bead_transient_keys")->andReturn([])->byDefault();
        $this->handler->shouldReceive("set")->with(Mockery::on(fn (mixed $key): bool => is_string($key)), Mockery::any())->andReturn(null)->byDefault();
        $this->handler->shouldReceive("commit")->byDefault();

        $this->app->shouldReceive("config")
            ->with(Mockery::on(fn (mixed $arg): bool => is_string($arg)), Mockery::any())
            ->andReturnUsing(fn (string $key, mixed $default = null): mixed => $default)
            ->byDefault();

        $this->mockMethod(Session::class, "createHandler", $this->handler);
        $this->mockMethod(Application::class, "instance", $this->app);
        $this->mockMethod(Session::class, "setCookie", null);
        $this->mockMethod(Session::class, "deleteCookie", null);
        $this->mockFunction("time", 1701072000);
        $this->session = new Session();
    }

    public function tearDonw(): void
    {
        Mockery::close();
        unset($this->handler, $this->app);
        parent::tearDown();
    }

    /** Ensure constructor calls createHandler() */
    public function testConstructor1(): void
    {
        $session = new Session();
        self::assertSame($this->handler, $session->handler());
    }
}
