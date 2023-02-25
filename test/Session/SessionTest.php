<?php

declare(strict_types=1);

namespace BeadTests\Session;

use Bead\Application;
use Bead\Contracts\Session\Handler;
use Bead\Exceptions\Session\ExpiredSessionIdUsedException;
use Bead\Exceptions\Session\SessionExpiredException;
use Bead\Session\Session;
use Bead\Testing\XRay;
use Bead\WebApplication;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;

class SessionTest extends TestCase
{
	private const CurrentTime = 1677267395;

	// 30s ago
	private const SessionLastUsed = 1677267365;

	// 20m ago
	private const SessionIdGeneratedAtExpiring = 1677266195;

	// 35m ago
	private const SessionLastUsedExpired = 1677265295;

	// 1m ago
	private const SessionIdGeneratedAt = 1677267335;

	// 2m ago
	private const SessionIdExpiredAtGrace = 1677267275;

	// 4m ago
	private const SessionIdExpiredAtNoGrace = 1677267155;

	// 1h ago
	private const SessionCreated = 1677263795;

	public const SessionId = "test-session-id";

	public const ReplacementSessionId = "test-regenerated-session-id";

	/** @var Handler&MockInterface The mock session handler to use in tests. */
	private Handler $handler;

	/** @var WebApplication&MockInterface The application to use in tests.  */
	private WebApplication $app;

	public function setUp(): void
	{
		$this->handler = Mockery::mock(Handler::class);
		$this->handler->shouldReceive("id")->byDefault()->andReturn(self::SessionId);
		$this->handler->shouldReceive("idHasExpired")->byDefault()->andReturn(false);
		$this->handler->shouldReceive("lastUsedAt")->byDefault()->andReturn(self::SessionLastUsed);
		$this->handler->shouldReceive("idGeneratedAt")->byDefault()->andReturn(self::SessionIdGeneratedAt);
		$this->handler->shouldReceive("createdAt")->byDefault()->andReturn(self::SessionCreated);
		$this->handler->shouldReceive("commit")->byDefault();

		$this->app = Mockery::mock(WebApplication::class);

		$this->app->shouldReceive("config")
			->zeroOrMoreTimes()
			->byDefault()
			->with("session.expired.grace-period", Session::DefaultExpiryGracePeriod)
			->andReturn(Session::DefaultExpiryGracePeriod);

		$this->app->shouldReceive("config")
			->zeroOrMoreTimes()
			->byDefault()
			->with("session.idle-timeout-period", Session::DefaultSessionIdleTimeoutPeriod)
			->andReturn(Session::DefaultSessionIdleTimeoutPeriod);

		$this->app->shouldReceive("config")
			->zeroOrMoreTimes()
			->byDefault()
			->with("session.id-regeneration-period", Session::DefaultSessionRegenerationPeriod)
			->andReturn(Session::DefaultSessionRegenerationPeriod);

		self::mockFunction("setcookie", true);
		self::mockFunction("time", self::CurrentTime);
		self::mockMethod(Application::class, "instance", $this->app);
	}

	public function tearDown(): void
	{
		parent::tearDown();
		Mockery::close();
		unset($this->handler, $this->app);
	}

	public function testConstructor(): void
	{
		$this->handler->shouldReceive("commit");

		self::mockFunction("setcookie", function(string $name, string $id): bool {
			TestCase::assertEquals(Session::CookieName, $name);
			TestCase::assertEquals(SessionTest::SessionId, $id);
			return true;
		});

		$session = new Session($this->handler);
		self::assertSame($this->handler, $session->handler());
	}

	/** Ensure ExpiredSessionIdUsedException is thrown when expired session id used outside grace period. */
	public function testConstructorWithExpiredId(): void
	{
		$this->handler->shouldReceive("idHasExpired")->andReturn(true);
		$this->handler->shouldReceive("idExpiredAt")->andReturn(self::SessionIdExpiredAtNoGrace);
		$this->handler->shouldReceive("destroy");

		self::mockFunction("setcookie", function(): bool {
			TestCase::fail("Constructor should not set the session cookie.");
		});

		self::expectException(ExpiredSessionIdUsedException::class);
		self::expectExceptionMessage("The provided session ID is not valid.");
		new Session($this->handler);
	}

	/** Ensure handler's id is swapped for regenerated id when old id is used within grace period */
	public function testConstructorWithExpiredIdWithinGrace(): void
	{
		$this->handler->shouldReceive("idHasExpired")->andReturn(true);
		$this->handler->shouldReceive("idExpiredAt")->andReturn(self::SessionIdExpiredAtGrace);
		$this->handler->shouldReceive("replacementId")->andReturn(self::ReplacementSessionId);
		$this->handler->shouldReceive("id")->andReturn(self::ReplacementSessionId);
		$this->handler->shouldReceive("load")->with(self::ReplacementSessionId);
		$this->handler->shouldReceive("commit");

		self::mockFunction("setcookie", function(string $name, string $id): bool {
			TestCase::assertEquals(Session::CookieName, $name);
			TestCase::assertEquals(SessionTest::ReplacementSessionId, $id);
			return true;
		});

		$session = new Session($this->handler);
		self::assertSame($this->handler, $session->handler());
	}

	/** Ensure ExpiredSessionIdUsedException is thrown when expired session used */
	public function testConstructorWithExpiredSession(): void
	{
		$this->handler->shouldReceive("lastUsedAt")->andReturn(self::SessionLastUsedExpired);
		$this->handler->shouldReceive("destroy");
		$this->handler->shouldReceive("id")->andReturn(self::SessionId);

		self::mockFunction("setcookie", function(): bool {
			TestCase::fail("Constructor should not set the session cookie.");
		});

		self::expectException(SessionExpiredException::class);
		self::expectExceptionMessage("The session with the provided ID has been unused for more than 1800 seconds.");
		new Session($this->handler);
	}

	/** Ensure session ID is generated when it's close to expiry. */
	public function testConstructorWithExpiringSession(): void
	{
		$this->handler->shouldReceive("idGeneratedAt")->andReturn(self::SessionIdGeneratedAtExpiring);
		$this->handler->shouldReceive("regenerateId")->andReturn(self::ReplacementSessionId);
		$this->handler->shouldReceive("id")->andReturn(self::ReplacementSessionId);
		$this->handler->shouldReceive("commit");

		self::mockFunction("setcookie", function(string $name, string $id): bool {
			TestCase::assertEquals(Session::CookieName, $name);
			TestCase::assertEquals(SessionTest::ReplacementSessionId, $id);
			return true;
		});

		new Session($this->handler);
		$session = new Session($this->handler);
		self::assertSame($this->handler, $session->handler());
	}

	/** Ensure the session idle timeout period is fetched from the config file. */
	public function testSessionIdleTimeoutPeriod(): void
	{
		$this->app->shouldReceive("config")
			->with("session.idle-timeout-period", Session::DefaultSessionIdleTimeoutPeriod)
			->andReturn(10);

		self::assertEquals(10, Session::sessionIdleTimeoutPeriod());
	}

	/** Ensure the session ID regeneration period is fetched from the config file. */
	public function testSessionIdRegenerationPeriod(): void
	{
		$this->app->shouldReceive("config")
			->with("session.id-regeneration-period", Session::DefaultSessionRegenerationPeriod)
			->andReturn(5);

		self::assertEquals(5, Session::sessionIdRegenerationPeriod());
	}

	/** Ensure the expired session grace period is fetched from the config file. */
	public function testExpiredSessionGracePeriod(): void
	{
		$this->app->shouldReceive("config")
			->with("session.expired.grace-period", Session::DefaultExpiryGracePeriod)
			->andReturn(15);

		self::assertEquals(15, Session::expiredSessionGracePeriod());
	}

	/** Ensure id() returns the id from the handler. */
	public function testId(): void
	{
		self::assertEquals(self::SessionId, (new Session($this->handler))->id());
	}

	/** Ensure the handler can be retrieved. */
	public function testHandler(): void
	{
		self::assertSame($this->handler, (new Session($this->handler))->handler());
	}

	/** Ensure createdAt() returns the timestamp from the handler. */
	public function testCreatedAt(): void
	{
		self::assertEquals(self::SessionCreated, (new Session($this->handler))->createdAt());
	}

	/** Ensure lastUsedAt() returns the timestamp from the handler. */
	public function testLastUsedAt(): void
	{
		self::assertEquals(self::SessionLastUsed, (new Session($this->handler))->lastUsedAt());
	}

	/** Test data for testHas() and testGet() */
	private function dataForSessionKeyValues(): iterable
	{
		yield "intZero" => [0];
		yield "intNonZero" => [1];
		yield "intNegative" => [-1];
		yield "floatZero" => [0.0];
		yield "floatNonZero" => [0.1];
		yield "floatNegative" => [-0.1];
		yield "emptyString" => [""];
		yield "nonEmptyString" => ["value"];
		yield "whitespaceString" => [" "];
		yield "emptyArray" => [[]];
		yield "nonEmptyArray" => [[0]];
		yield "emptyObject" => [(object) []];
		yield "nonEmptyObject" => [(object) ["key" => 0]];
	}
	
	/**
	 * Ensure has() returns true for keys the handler has.
	 * 
	 * @dataProvider dataForSessionKeyValues
	 */
	public function testHas(mixed $value): void
	{
		$this->handler->shouldReceive("get")->once()->with("test-key")->andReturn($value);
		self::assertTrue((new Session($this->handler))->has("test-key"));
	}

	/** Ensure has() returns false for keys the handler does not have. */
	public function testHasNot(): void
	{
		$this->handler->shouldReceive("get")->once()->with("test-key")->andReturn(null);
		self::assertFalse((new Session($this->handler))->has("test-key"));
	}

	/**
	 * Ensure get() returns the expected value for keys the handler has.
	 *
	 * @dataProvider dataForSessionKeyValues
	 */
	public function testGet(mixed $value): void
	{
		$this->handler->shouldReceive("get")->once()->with("test-key")->andReturn($value);
		self::assertSame($value, (new Session($this->handler))->get("test-key"));
	}

	/** Ensure get() returns null for keys the handler doesn"t have. */
	public function testGetWithMissingKey(): void
	{
		$this->handler->shouldReceive("get")->once()->with("test-key")->andReturn(null);
		self::assertNull((new Session($this->handler))->get("test-key"));
	}

	/**
	 * Ensure get() returns the provided default for keys the handler doesn't have.
	 *
	 * @dataProvider dataForSessionKeyValues
	 */
	public function testGetWithMissingKeyAndDefault(mixed $defaultValue): void
	{
		$this->handler->shouldReceive("get")->once()->with("test-key")->andReturn(null);
		self::assertSame($defaultValue, (new Session($this->handler))->get("test-key", $defaultValue));
	}

	/** Ensure all() returns all the keys from the handler. */
	public function testAll(): void
	{
		$expected = ["test-key" => "test-value"];
		$this->handler->shouldReceive("all")->andReturn($expected);
		self::assertEquals($expected, (new Session($this->handler))->all());
	}

	/**
	 * Ensure we can set single keys.
	 *
	 * @dataProvider dataForSessionKeyValues
	 */
	public function testSet(mixed $value): void
	{
		$this->handler->shouldReceive("set")->with("test-key", $value);
		(new Session($this->handler))->set("test-key", $value);
		self::assertMockeryHandlesTestExpectations();
	}

	/** Ensure we can set multiple keys by passing an associative array. */
	public function testSetArray(): void
	{
		$this->handler->shouldReceive("set")->with("test-key-1", "value-1")->once();
		$this->handler->shouldReceive("set")->with("test-key-2", 0)->once();
		(new Session($this->handler))->set(["test-key-1" => "value-1", "test-key-2" => 0]);
		self::assertMockeryHandlesTestExpectations();
	}

	/** Ensure set() rejects arrays with non-string keys. */
	public function testSetThrows(): void
	{
		self::expectException(InvalidArgumentException::class);
		self::expectExceptionMessage("Keys for session data must be strings.");
		(new Session($this->handler))->set(["test-key-1" => "value", 2 => 0, "test-key-2" => 2]);
	}

	/**
	 * Ensure we can transiently set single keys.
	 *
	 * @dataProvider dataForSessionKeyValues
	 */
	public function testTransientSet(mixed $value): void
	{
		$this->handler->shouldReceive("set")->with("test-key", $value);
		$session = new XRay(new Session($this->handler));
		$session->transientSet("test-key", $value);
		self::assertIsArray($session->m_transientKeys);
		self::assertArrayHasKey("test-key", $session->m_transientKeys);
		self::assertEquals(1, $session->m_transientKeys["test-key"]);
	}

	/** Ensure we can set transiently multiple keys by passing an associative array. */
	public function testTransientSetArray(): void
	{
		$this->handler->shouldReceive("set")->with("test-key-1", "value-1")->once();
		$this->handler->shouldReceive("set")->with("test-key-2", 0)->once();
		$session = new XRay(new Session($this->handler));
		$session->transientSet(["test-key-1" => "value-1", "test-key-2" => 0]);
		self::assertIsArray($session->m_transientKeys);
		self::assertEquals(["test-key-1" => 1, "test-key-2" => 1], $session->m_transientKeys);
	}

	/** Ensure transientSet() rejects arrays with non-string keys. */
	public function testTransientSetThrows(): void
	{
		self::expectException(InvalidArgumentException::class);
		self::expectExceptionMessage("Keys for session data must be strings.");
		(new Session($this->handler))->transientSet(["test-key-1" => "value", 2 => 0, "test-key-2" => 2]);
	}

	/** Ensure calling destroy() calls the handler's destroy() method. */
	public function testDestroy(): void
	{
		$this->handler->shouldReceive("destroy")->once();
		(new Session($this->handler))->destroy();
		self::assertMockeryHandlesTestExpectations();
	}

	/** Ensure calling commit() calls the handler's commit() method. */
	public function testCommit(): void
	{
		// NOTE the constructor calls commit(), so we get that out of the way before setting our specific expectations
		// for this test
		$session = new Session($this->handler);
		$this->handler->shouldReceive("commit");
		$session->commit();
		self::assertMockeryHandlesTestExpectations();
	}

	/** Ensure calling clear() calls the handler's clear() method. */
	public function testClear(): void
	{
		$this->handler->shouldReceive("clear");
		(new Session($this->handler))->clear();
		self::assertMockeryHandlesTestExpectations();
	}

	/** Ensure regenerateId() asks the handler to regenerate the ID. */
	public function testRegenerateId(): void
	{
		$session = new Session($this->handler);
		$this->handler->shouldReceive("regenerateId")->andReturn(self::ReplacementSessionId);
		$this->handler->shouldReceive("id")->andReturn(self::ReplacementSessionId);

		self::mockFunction("setcookie", function(string $name, string $id): bool {
			TestCase::assertEquals(Session::CookieName, $name);
			TestCase::assertEquals(SessionTest::ReplacementSessionId, $id);
			return true;
		});

		$session->regenerateId();
	}

	/** Ensure removing a single key calls the handler's remove() method once. */
	public function testRemove(): void
	{
		$this->handler->shouldReceive("remove")->with("test-key")->once();
		(new Session($this->handler))->remove("test-key");
		self::assertMockeryHandlesTestExpectations();
	}

	/** Ensure removing multiple key calls the handler's remove() method with the appropriate keys. */
	public function testRemoveMany(): void
	{
		$this->handler->shouldReceive("remove")->with("test-key-1")->once();
		$this->handler->shouldReceive("remove")->with("test-key-2")->once();
		(new Session($this->handler))->remove(["test-key-1", "test-key-2"]);
		self::assertMockeryHandlesTestExpectations();
	}

	/** Ensure remove() throws with an array with non-string keys. */
	public function testRemoveThrows(): void
	{
		$this->handler->shouldNotReceive("remove");
		self::expectException(InvalidArgumentException::class);
		self::expectExceptionMessage("Keys for session data to remove must be strings");
		(new Session($this->handler))->remove(["test-key-1", 2, "test-key-2"]);
	}

	// TODO testExtract()
	// TODO testPruneTransientData()
	// TODO testRefreshTransientData()
	// TODO testPush()
	// TODO testPushAll()
	// TODO testPop()
}
