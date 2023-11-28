<?php

declare(strict_types=1);

namespace BeadTests\Session;

use Bead\Contracts\SessionHandler;
use Bead\Core\Application;
use Bead\Session\Session;
use Bead\Testing\XRay;
use BeadTests\Framework\CallTracker;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;

final class SessionTest extends TestCase
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

    /** Ensure destructor commits the session. */
    public function testDestructor1(): void
    {
        $called = false;

        $this->handler
            ->shouldReceive("commit")
            ->once()
            ->andReturnUsing(function () use (&$called): void {
                $called = true;
            });

        unset($this->session);
        self::assertTrue($called);
    }

    /** Ensure destructor removes expired transient keys. */
    public function testDestructor2(): void
    {
        $session = new XRay($this->session);
        $session->m_transientKeys = ["bead" => 0,];
        unset($session);
        $called = false;

        $this->handler
            ->shouldReceive("remove")
            ->with("bead")
            ->once()
            ->andReturnUsing(function () use (&$called): void {
                $called = true;
            });

        unset($this->session);
        self::assertTrue($called);
    }

    public function testSetCookie1(): void
    {
        $called = false;
        $expectedSessionId = self::SessionId;

        $this->mockFunction("setcookie", function (string $name, string $id, int $expires, string $path) use (&$called, $expectedSessionId): void {
            SessionTest::assertEquals("BeadSession", $name);
            SessionTest::assertEquals($expectedSessionId, $id);
            SessionTest::assertEquals(0, $expires);
            SessionTest::assertEquals("/", $path);
            $called = true;
        });

        $session = new XRay($this->session);
        $session->setCookie();
        self::assertTrue($called);
    }

    public function testDeleteCookie1(): void
    {
        $called = false;
        $expectedSessionId = self::SessionId;
        $expectedExpires = self::CurrentTimestamp - 3600;
        $this->mockFunction("time", self::CurrentTimestamp);

        $this->mockFunction("setcookie", function (string $name, string $id, int $expires, string $path) use (&$called, $expectedSessionId, $expectedExpires): void {
            SessionTest::assertEquals("BeadSession", $name);
            SessionTest::assertEquals($expectedSessionId, $id);
            SessionTest::assertEquals($expectedExpires, $expires);
            SessionTest::assertEquals("/", $path);
            $called = true;
        });

        $session = new XRay($this->session);
        $session->deleteCookie();
        self::assertTrue($called);
    }

    /** Ensure we get the idle timeout from the config. */
    public function testSessionIdleTimeoutPeriod1(): void
    {
        $this->app->shouldReceive("config")
            ->with("session.idle-timeout-period", Mockery::any())
            ->andReturn(450);

        self::assertEquals(450, Session::sessionIdleTimeoutPeriod());
    }

    /** Ensure we get the default timeout when no config value is set. */
    public function testSessionIdleTimeoutPeriod2(): void
    {
        $this->app->shouldReceive("config")
            ->with("session.idle-timeout-period", Session::DefaultSessionIdleTimeoutPeriod)
            ->andReturn(Session::DefaultSessionIdleTimeoutPeriod);

        self::assertEquals(Session::DefaultSessionIdleTimeoutPeriod, Session::sessionIdleTimeoutPeriod());
    }

    /** Ensure we get the idle timeout from the config. */
    public function testSessionIdRegeneratonPeriod1(): void
    {
        $this->app->shouldReceive("config")
            ->with("session.id-regeneration-period", Mockery::any())
            ->andReturn(450);

        self::assertEquals(450, Session::sessionIdRegenerationPeriod());
    }

    /** Ensure we get the default timeout when no config value is set. */
    public function testSessionIdRegeneratonPeriod2(): void
    {
        $this->app->shouldReceive("config")
            ->with("session.id-regeneration-period", Session::DefaultSessionRegenerationPeriod)
            ->andReturn(Session::DefaultSessionRegenerationPeriod);

        self::assertEquals(Session::DefaultSessionRegenerationPeriod, Session::sessionIdRegenerationPeriod());
    }

    /** Ensure we get the idle timeout from the config. */
    public function testExpiredSessionGracePeriod1(): void
    {
        $this->app->shouldReceive("config")
            ->with("session.expired.grace-period", Mockery::any())
            ->andReturn(450);

        self::assertEquals(450, Session::expiredSessionGracePeriod());
    }

    /** Ensure we get the default timeout when no config value is set. */
    public function testExpiredSessionGracePeriod2(): void
    {
        $this->app->shouldReceive("config")
            ->with("session.expired.grace-period", Session::DefaultExpiryGracePeriod)
            ->andReturn(Session::DefaultExpiryGracePeriod);

        self::assertEquals(Session::DefaultExpiryGracePeriod, Session::expiredSessionGracePeriod());
    }

    /** Ensure id() returns the ID from the handler. */
    public function testId1(): void
    {
        $this->handler->shouldReceive("id")->once()->andReturn(self::SessionId);
        self::assertSame(self::SessionId, $this->session->id());
    }

    /** Ensure we can retrieve the handler. */
    public function testHandler1(): void
    {
        self::assertSame($this->handler, $this->session->handler());
    }

    /** Ensure createdAt() returns the created date from the handler. */
    public function testCreatedAt1(): void
    {
        $this->handler->shouldReceive("createdAt")->once()->andReturn(self::CurrentTimestamp - 100);
        self::assertEquals(self::CurrentTimestamp - 100, $this->session->createdAt());
    }

    /** Ensure lastUsedAt() returns the last used date from the handler. */
    public function testLastUsedAt1(): void
    {
        $this->handler->shouldReceive("lastUsedAt")->once()->andReturn(self::CurrentTimestamp - 30);
        self::assertEquals(self::CurrentTimestamp - 30, $this->session->lastUsedAt());
    }

    /** Ensure get() gets the key from the handler. */
    public function testGet1(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn("framework");
        self::assertEquals("framework", $this->session->get("bead"));
    }

    /** Ensure get() uses the default if the handler doesn't have the key. */
    public function testGet2(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn(null);
        self::assertEquals("framework", $this->session->get("bead", "framework"));
    }

    /** Ensure get() returns null if the handler doesn't have the key and no default is provideed. */
    public function testGet3(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn(null);
        self::assertNull($this->session->get("bead"));
    }

    /** Ensure we can extract a single value from the session. */
    public function testExtract1(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn("framework");
        $this->handler->shouldReceive("remove")->once()->with("bead");
        $actual = $this->session->extract("bead");
        self::assertEquals("framework", $actual);
    }

    /** Ensure we can extract multiple values from the session. */
    public function testExtract2(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn("framework");
        $this->handler->shouldReceive("get")->once()->with("framework")->andReturn("bead");
        $this->handler->shouldReceive("remove")->once()->with("bead");
        $this->handler->shouldReceive("remove")->once()->with("framework");
        $actual = $this->session->extract(["bead", "framework",]);
        self::assertEqualsCanonicalizing(["framework", "bead",], $actual);
    }

    /** Ensure all() forwards to the handler. */
    public function testAll1(): void
    {
        $this->handler->shouldReceive("all")->once()->andReturn(["bead", "framework",]);
        self::assertEqualsCanonicalizing(["bead", "framework",], $this->session->all());
    }

    /** Ensure we can set a single key. */
    public function testSet1(): void
    {
        $this->handler->shouldReceive("set")->once()->with("bead", "framework");
        $this->session->set("bead", "framework");
        self::markTestAsExternallyVerified();
    }

    /** Ensure we can set a multiple keys. */
    public function testSet2(): void
    {
        $this->handler->shouldReceive("set")->once()->with("bead", "framework");
        $this->handler->shouldReceive("set")->once()->with("darren", "edale");
        $this->session->set(["bead" => "framework", "darren" => "edale",]);
        self::markTestAsExternallyVerified();
    }

    /** Ensure set throws with arrays that aren't dictionaries keyed by strings. */
    public function testSet3(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Keys for session data must be strings.");
        $this->session->set(["bead", "framework",]);
    }

    /** Ensure we can set a single key. */
    public function testTransientSet1(): void
    {
        $this->handler->shouldReceive("set")->once()->with("bead", "framework");
        $this->session->transientSet("bead", "framework");
        $session = new XRay($this->session);
        self::assertEquals(["bead" => 1,], $session->m_transientKeys);
    }

    /** Ensure we can set a multiple keys. */
    public function testTransientSet2(): void
    {
        $this->handler->shouldReceive("set")->once()->with("bead", "framework");
        $this->handler->shouldReceive("set")->once()->with("darren", "edale");
        $this->session->transientSet(["bead" => "framework", "darren" => "edale",]);
        $session = new XRay($this->session);
        self::assertEqualsCanonicalizing(["darren" => 1, "bead" => 1], $session->m_transientKeys);
    }

    /** Ensure transientSet() throws with arrays that aren't dictionaries keyed by strings. */
    public function testTransientSet3(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Keys for session data must be strings.");
        $this->session->transientSet(["bead", "framework",]);
    }

    /** Ensure the correct transient keys get pruned. */
    public function testPruneTransientData1(): void
    {
        $session = new XRay($this->session);
        $session->m_transientKeys = ["bead" => 1, "framework" => 0];
        $this->handler->shouldReceive("remove")->once()->with("framework");
        $this->handler->shouldNotReceive("remove")->with("bead");
        $this->session->pruneTransientData();
        self::assertEquals(["bead" => 0,], $session->m_transientKeys);
    }
}
