<?php

declare(strict_types=1);

namespace BeadTests\Session;

use Bead\Contracts\SessionHandler;
use Bead\Core\Application;
use Bead\Exceptions\Session\ExpiredSessionIdUsedException;
use Bead\Exceptions\Session\InvalidSessionHandlerException;
use Bead\Exceptions\Session\SessionException;
use Bead\Exceptions\Session\SessionExpiredException;
use Bead\Exceptions\Session\SessionNotFoundException;
use Bead\Session\Session;
use Bead\Testing\StaticXRay;
use Bead\Testing\XRay;
use BeadTests\Framework\CallTracker;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;
use RuntimeException;

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

    /** @var array stash the actual supported session classes so we put thme back when we're done messing with them */
    private array $sessionClasses;

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

        // temporarily suppress cookie management while setting up the test fixture
        $this->mockMethod(Session::class, "setCookie", null);
        $this->mockMethod(Session::class, "deleteCookie", null);
        $this->mockFunction("time", 1701072000);
        $this->session = new Session();
        $this->removeMethodMock(Session::class, "setCookie");
        $this->removeMethodMock(Session::class, "deleteCookie");

        $sessionClass = new StaticXRay(Session::class);
        $this->sessionClasses = $sessionClass->m_handlerClasses;
    }

    public function tearDown(): void
    {
        Mockery::close();
        $sessionClass = new StaticXRay(Session::class);
        $sessionClass->m_handlerClasses = $this->sessionClasses;
        unset($this->session, $this->handler, $this->app, $this->sessionClasses);
        parent::tearDown();
    }

    /** Ensure constructor calls createHandler() */
    public function testConstructor1(): void
    {
        $this->mockFunction("setcookie", null);
        $session = new Session();
        self::assertSame($this->handler, $session->handler());
    }

    /** Ensure constructor detects when the id expired within the grace period and uses the replacement. */
    public function testConstructor2(): void
    {
        $this->mockFunction("setcookie", null);
        $this->handler->shouldReceive("idHasExpired")->once()->andReturn(true);
        $this->handler->shouldReceive("idExpiredAt")->once()->andReturn(self::CurrentTimestamp - 1);
        $this->handler->shouldReceive("replacementId")->once()->andReturn(self::SessionId . "-replacement");
        $session = new Session();
        self::markTestAsExternallyVerified();
    }

    /** Ensure constructor detects when the id expired outside the grace period. */
    public function testConstructor3(): void
    {
        $this->mockFunction("setcookie", null);
        $this->handler->shouldReceive("idHasExpired")->once()->andReturn(true);
        $this->handler->shouldReceive("idExpiredAt")->once()->andReturn(self::CurrentTimestamp - Session::expiredSessionGracePeriod() - 1);
        $this->handler->shouldReceive("destroy")->once();
        self::expectException(ExpiredSessionIdUsedException::class);
        self::expectExceptionMessage("The provided session ID is not valid.");
        $session = new Session();
    }

    /** Ensure constructor detects when the session idle period has expired. */
    public function testConstructor4(): void
    {
        $this->mockFunction("setcookie", null);
        $this->handler->shouldReceive("idHasExpired")->once()->andReturn(false);
        $this->handler->shouldReceive("lastUsedAt")->once()->andReturn(self::CurrentTimestamp - Session::sessionIdleTimeoutPeriod() - 1);
        $this->handler->shouldReceive("destroy")->once();
        self::expectException(SessionExpiredException::class);
        self::expectExceptionMessage("The session with the provided ID has been unused for more than " . Session::sessionIdleTimeoutPeriod() . " seconds.");
        $session = new Session();
    }

    /** Ensure constructor detects when the session id is about to expire. */
    public function testConstructor5(): void
    {
        $this->mockFunction("setcookie", null);
        $this->handler->shouldReceive("idHasExpired")->once()->andReturn(false);
        $this->handler->shouldReceive("lastUsedAt")->once()->andReturn(self::CurrentTimestamp - Session::sessionIdleTimeoutPeriod() + 1);
        $this->handler->shouldReceive("idGeneratedAt")->once()->andReturn(self::CurrentTimestamp - Session::sessionIdRegenerationPeriod() - 1);
        $this->handler->shouldReceive("regenerateId")->once()->andReturn(self::SessionId . "-regenerated");
        $session = new Session();
        self::markTestAsExternallyVerified();
    }

    /** Ensure constructor initialises transient keys if they're not in the session data. */
    public function testConstructor6(): void
    {
        $this->mockFunction("setcookie", null);
        $this->handler->shouldReceive("get")->once()->with("__bead_transient_keys")->andReturn(null);
        $session = new XRay(new Session());
        self::assertEquals([], $session->m_transientKeys);
    }

    /** Ensure constructor sets the transient keys from the handler's data if set. */
    public function testConstructor7(): void
    {
        $this->mockFunction("setcookie", null);
        $this->handler->shouldReceive("get")->once()->with("__bead_transient_keys")->andReturn(["bead" => 1, "framework" => 2,]);
        $session = new XRay(new Session());
        self::assertEquals(["bead" => 1, "framework" => 2,], $session->m_transientKeys);
    }

    /** Ensure constructor throws if the handler has transient keys that are not strings. */
    public function testConstructor8(): void
    {
        $this->mockFunction("setcookie", null);
        $this->handler->shouldReceive("get")->once()->with("__bead_transient_keys")->andReturn(["bead" => 1, 1 => 2,]);
        self::expectException(SessionException::class);
        self::expectExceptionMessage("Session data is corrupt.");
        $session = new XRay(new Session());
    }

    /** Ensure constructor throws if the handler has transient keys that have non-int request counts.. */
    public function testConstructor9(): void
    {
        $this->mockFunction("setcookie", null);
        $this->handler->shouldReceive("get")->once()->with("__bead_transient_keys")->andReturn(["bead" => 1, "framework" => "library",]);
        self::expectException(SessionException::class);
        self::expectExceptionMessage("Session data is corrupt.");
        $session = new XRay(new Session());
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

    /** Ensure has() can detect a key that is present in the session. */
    public function testHas1(): void
    {
        $this->handler->shouldReceive("get")->with("bead")->once()->andReturn("framework");
        self::assertTrue($this->session->has("bead"));
    }

    /** Ensure has() can detect a key that is not present in the session. */
    public function testHas2(): void
    {
        $this->handler->shouldReceive("get")->with("bead")->once()->andReturn(null);
        self::assertFalse($this->session->has("bead"));
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

    /** Ensure extract() throws with non-string keys. */
    public function testExtract3(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Keys for session data must be strings.");
        $actual = $this->session->extract(["bead", 1, "framework",]);
    }

    /** Ensure extract() ignores keys that aren't set. */
    public function testExtract4(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn("framework");
        $this->handler->shouldReceive("get")->once()->with("framework")->andReturn("bead");
        $this->handler->shouldReceive("get")->once()->with("library")->andReturn(null);
        $this->handler->shouldReceive("remove")->once()->with("bead");
        $this->handler->shouldReceive("remove")->once()->with("framework");
        $actual = $this->session->extract(["bead", "framework", "library",]);
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

    /** Ensure transient data request counts can be refreshed. */
    public function testRefreshTransientData1(): void
    {
        $session = new XRay($this->session);
        $session->m_transientKeys = ["bead" => 1, "framework" => 0];
        $this->session->refreshTransientData();
        self::assertEqualsCanonicalizing(["framework" => 1, "bead" => 1,], $session->m_transientKeys);
    }

    /** Ensure calls to remove() for a single key are forwarded to the handler. */
    public function testRemove1(): void
    {
        $this->handler->shouldReceive("remove")->once()->with("bead");
        $this->session->remove("bead");
        self::markTestAsExternallyVerified();
    }

    /** Ensure calls to remove() for a multiple keys are forwarded to the handler. */
    public function testRemove2(): void
    {
        $this->handler->shouldReceive("remove")->once()->with("bead");
        $this->handler->shouldReceive("remove")->once()->with("framework");
        $this->session->remove(["bead", "framework",]);
        self::markTestAsExternallyVerified();
    }

    /** Ensure remove() throws with non-string kys. */
    public function testRemove3(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Keys for session data to remove must be strings.");
        $this->session->remove(["bead", 2, "framework",]);
    }

    /** Ensure calls to clear() are forwarded to the handler. */
    public function testClear1(): void
    {
        $this->handler->shouldReceive("clear")->once();
        $this->session->clear();
        self::markTestAsExternallyVerified();
    }

    /** Ensure commit() sets the transient keys and calls commit on the handler. */
    public function testCommit1(): void
    {
        $session = new XRay($this->session);
        $session->m_transientKeys = ["bead" => 1, "framework" => 1,];
        $this->handler->shouldReceive("set")->once()->with("__bead_transient_keys", ["bead" => 1, "framework" => 1,]);
        $this->session->commit();
        self::markTestAsExternallyVerified();
    }

    /** Ensure the call to regenerateId() is forwarded to the handler, and that the session cookie is set immediately */
    public function testRegenerateId1(): void
    {
        $called = 0;
        $expectedSessionIds = ["old-test-id", self::SessionId,];
        $expectedExpiries = [1701072000 - 3600, 0,];

        $this->mockFunction("setcookie", function (string $name, string $id, int $expires, string $path) use (&$called, &$expectedSessionIds, &$expectedExpiries): void {
            SessionTest::assertEquals("BeadSession", $name);
            SessionTest::assertEquals(array_shift($expectedSessionIds), $id);
            SessionTest::assertEquals(array_shift($expectedExpiries), $expires);
            SessionTest::assertEquals("/", $path);
            ++$called;
        });

        $this->handler->shouldReceive("regenerateId")->once()->andReturn(self::SessionId);
        $this->handler->shouldReceive("id")->ordered()->once()->andReturn("old-test-id");
        $this->handler->shouldReceive("id")->ordered()->once()->andReturn(self::SessionId);
        $this->session->regenerateId();
        self::assertEquals(2, $called);
    }

    public function testDestroy1(): void
    {
        $expectedSessionId = self::SessionId;
        $expectedExpiry = self::CurrentTimestamp - 3600;
        $called = false;

        $this->mockFunction("setcookie", function (string $name, string $id, int $expires, string $path) use ($expectedSessionId, $expectedExpiry, &$called): void {
            SessionTest::assertEquals("BeadSession", $name);
            SessionTest::assertEquals($expectedSessionId, $id);
            SessionTest::assertEquals($expectedExpiry, $expires);
            SessionTest::assertEquals("/", $path);
            $called = true;
        });

        $this->handler->shouldReceive("destroy")->once();
        $this->session->destroy();
        self::assertTrue($called);
    }

    /** Ensure createHandler() gets the handler from the app config and passes the provided ID to its constructor. */
    public function testCreateHandler1(): void
    {
        $this->removeMethodMock(Session::class, "createHandler");
        $sessionClass = new StaticXRay(Session::class);

        $testHandlerClass = (new class extends AbstractTestSessionHandler {
            public function __construct(?string $id = null)
            {
                $this->id = $id ?? "";
            }
        })::class;

        $sessionClass->m_handlerClasses = ["test" => $testHandlerClass,];
        $this->app->shouldReceive("config")->with("session.handler", "file")->andReturn("test");
        $handler = $sessionClass->createHandler("-test-" . self::SessionId . "-test");
        self::assertInstanceOf($testHandlerClass, $handler);
        self::assertEquals("-test-" . self::SessionId . "-test", $handler->id());
    }

    /** Ensure createHandler() throws with unrecognised session handler. */
    public function testCreateHandler2(): void
    {
        $this->removeMethodMock(Session::class, "createHandler");
        $this->app->shouldReceive("config")->with("session.handler", "file")->andReturn("test");
        self::expectException(InvalidSessionHandlerException::class);
        self::expectExceptionMessage("Session handler 'test' configured in session config file is not recognised.");
        $sessionClass = new StaticXRay(Session::class);
        $handler = $sessionClass->createHandler("-test-" . self::SessionId . "-test");
    }

    /** Ensure createHandler() throws with unrecognised session handler. */
    public function testCreateHandler3(): void
    {
        $this->removeMethodMock(Session::class, "createHandler");
        $sessionClass = new StaticXRay(Session::class);

        $testHandlerClass = (new class extends AbstractTestSessionHandler {
            public function __construct(?string $id = null)
            {
                if (null !== $id) {
                    throw new RuntimeException("Test exception.");
                }
            }
        })::class;

        $sessionClass->m_handlerClasses = ["test" => $testHandlerClass,];
        $this->app->shouldReceive("config")->with("session.handler", "file")->andReturn("test");
        self::expectException(SessionNotFoundException::class);
        self::expectExceptionMessageMatches("/^Exception creating test session handler (.*)\\.\$/");
        $sessionClass->createHandler("-test-" . self::SessionId . "-test");
    }

    /** Ensure we can push a single value to a session array. */
    public function testPush1(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn(["framework",]);
        $this->handler->shouldReceive("set")->once()->with("bead", ["framework", "app",]);
        $this->session->push("bead", "app");
        self::markTestAsExternallyVerified();
    }

    /** Ensure push() throws when the key is not set. */
    public function testPush2(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn(null);
        $this->handler->shouldReceive("set")->once()->with("bead", ["framework",]);
        $this->session->push("bead", "framework");
        self::markTestAsExternallyVerified();
    }

    /** Ensure push() throws when the key is not an array. */
    public function testPush3(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn("framework");
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("The session key 'bead' does not contain an array.");
        $this->session->push("bead", "framework");
    }

    /** Ensure push() throws when the key is not an array. */
    public function testPushAll1(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn(["framework",]);
        $this->handler->shouldReceive("set")->once()->with("bead", ["framework", "app", "library",]);
        $this->session->pushAll("bead", ["app", "library",]);
        self::markTestAsExternallyVerified();
    }

    /** Ensure push() creates an array when the key is not set. */
    public function testPushAll2(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn(null);
        $this->handler->shouldReceive("set")->once()->with("bead", ["framework", "app",]);
        $this->session->pushAll("bead", ["framework", "app",]);
        self::markTestAsExternallyVerified();
    }

    /** Ensure push() throws when the key is not an array. */
    public function testPushAll3(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn("framework");
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("The session key 'bead' does not contain an array.");
        $this->session->pushAll("bead", ["framework", "app",]);
    }

    /** Ensure pop() gets one item by default. */
    public function testPop1(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn(["framework", "app",]);
        $actual = $this->session->pop("bead");
        self::assertEquals("app", $actual);
    }

    /** Ensure pop() can get multiple items. */
    public function testPop2(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn(["framework", "app", "library",]);
        $actual = $this->session->pop("bead", 2);
        self::assertEquals(["library", "app",], $actual);
    }

    /** Ensure pop() returns null if 0 items are popped. */
    public function testPop3(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn(["framework", "app", "library",]);
        $this->handler->shouldNotReceive("set");
        $actual = $this->session->pop("bead", 0);
        self::assertNull($actual);
    }

    /** Ensure pop() gets and sets on the handler. */
    public function testPop4(): void
    {
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn(["framework", "app",]);
        $this->handler->shouldReceive("set")->once()->with("bead", ["framework",]);
        $this->session->pop("bead");
        self::markTestAsExternallyVerified();
    }

    /** Ensure pop() throws when the key is not set. */
    public function testPop5(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("The session key 'bead' does not contain an array.");
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn(null);
        $this->session->pop("bead");
    }

    /** Ensure pop() throws when the key is not an array. */
    public function testPop6(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("The session key 'bead' does not contain an array.");
        $this->handler->shouldReceive("get")->once()->with("bead")->andReturn("framework");
        $this->session->pop("bead");
    }
}
