<?php
declare(strict_types=1);

namespace BeadTests\Util;

use Equit\Util\ScopeGuard;

/**
 * ScopeGuard test case.
 */
class ScopeGuardTest extends \BeadTests\Framework\TestCase
{
	/**
	 * Data provider for the constructor/addClosure tests.
	 *
	 * @return array The test data.
	 */
	public function closureTestData(): array
	{
		return [
			"valid" => [function() {},],
			"invalidString" => ["foo", \TypeError::class,],
			"invalidInt" => [5, \TypeError::class,],
			"invalidFloat" => [21.4362785, \TypeError::class,],
			"invalidNull" => [null, \TypeError::class,],
			"invalidBool" => [true, \TypeError::class,],
			"invalidInvokableLikeAnonymousObject" => [(object) ["__invoke" => function(){}], \TypeError::class,],
			"invalidIvokableLikeClass" => [new class() { public function __invoke(){}}, \TypeError::class,],
			"invalidCallableTuple" => [[$this, "testConstructor"] , \TypeError::class,],
		];
	}

	/**
	 * @dataProvider closureTestData
	 *
	 * @param mixed $closure The closure to pass to the constructor.
	 * @param string|null $exceptionClass The exception class expected, if any.
	 */
	public function testConstructor($closure, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$guard = new ScopeGuard($closure);

		$this->assertInstanceOf(ScopeGuard::class, $guard, "The ScopeGuard constructor did not create an instance of " . ScopeGuard::class . ".");
	}

	/**
	 * Test invoke() calls all the closures.
	 */
	public function testInvoke(): void
	{
		$called = false;

		$guard = new ScopeGuard(function() use (&$called) {
			$called = true;
		});

		$guard->invoke();
		$this->assertTrue($called, "The scope guard closure was not called by invoke().");

		$called1 = false;
		$called2 = false;

		$guard = new ScopeGuard(function() use (&$called1) {
			$called1 = true;
		});

		$guard->addClosure(function() use (&$called2) {
			$called2 = true;
		});

		$guard->invoke();
		$this->assertTrue($called1, "The scope guard's initial closure was not called by invoke().");
		$this->assertTrue($called2, "The scope guard's added closure was not called by invoke().");

		$called1 = false;
		$called2 = false;

		$cancelledGuard = new ScopeGuard(function() use (&$called1) {
			$called1 = true;
		});

		$cancelledGuard->addClosure(function() use (&$called2) {
			$called2 = true;
		});

		$cancelledGuard->cancel();
		$cancelledGuard->invoke();
		$this->assertFalse($called1, "The scope guard's initial closure was still called by invoke() after cancellation.");
		$this->assertFalse($called2, "The scope guard's added closure was still called by invoke() after cancellation.");
	}

	/**
	 * Test that the destructor invokes the closure.
	 */
	public function testDestructor(): void
	{
		$called = false;

		(function() use (&$called) {
			$guard = new ScopeGuard(function() use (&$called) {
				$called = true;
			});
		})();

		$this->assertTrue($called, "The scope guard closure was not called on destruction.");

		$called1 = false;
		$called2 = false;

		(function() use (&$called1, &$called2) {
			$guard = new ScopeGuard(function() use (&$called1) {
				$called1 = true;
			});

			$guard->addClosure(function() use (&$called2) {
				$called2 = true;
			});
		})();

		$this->assertTrue($called1, "The scope guard's initial closure was not called on destruction.");
		$this->assertTrue($called2, "The scope guard's added closure was not called on destruction.");
	}

	/**
	 * Test guards can be cancelled.
	 */
	public function testCancel(): void
	{
		// test destructor respets call to cancel()
		$notCalled = true;

		(function() use (&$notCalled) {
			$guard = new ScopeGuard(function() use (&$notCalled) {
				$notCalled = false;
			});

			$guard->cancel();
		})();

		$this->assertTrue($notCalled, "The scope guard closure was still called on destruction after cancellation.");

		$notCalled1 = true;
		$notCalled2 = true;

		(function() use (&$notCalled1, &$notCalled2) {
			$guard = new ScopeGuard(function() use (&$notCalled1) {
				$notCalled1 = false;
			});

			$guard->addClosure(function() use (&$notCalled2) {
				$notCalled2 = false;
			});

			$guard->cancel();
		})();

		$this->assertTrue($notCalled1, "The scope guard's initial closure was still called on destruction after cancellation.");
		$this->assertTrue($notCalled2, "The scope guard's added closure was still called on destruction after cancellation.");

		// test invoke() respets call to cancel()
		$notCalled = true;

		$guard = new ScopeGuard(function() use (&$notCalled) {
			$notCalled = false;
		});

		$guard->cancel();
		$guard->invoke();
		$this->assertTrue($notCalled, "The scope guard closure was still called on destruction after cancellation.");

		$notCalled1 = true;
		$notCalled2 = true;

		$guard = new ScopeGuard(function() use (&$notCalled1) {
			$notCalled1 = false;
		});

		$guard->addClosure(function() use (&$notCalled2) {
			$notCalled2 = false;
		});

		$guard->cancel();
		$guard->invoke();
		$this->assertTrue($notCalled1, "The scope guard's initial closure was still called on destruction after cancellation.");
		$this->assertTrue($notCalled2, "The scope guard's added closure was still called on destruction after cancellation.");
	}

	/**
	 * Test closures can be added to guards.
	 * @dataProvider closureTestData
	 */
	public function testAddClosure($closure, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$guard = new ScopeGuard(function(){});
		$guard->addClosure($closure);
		$closuresMethod = new \ReflectionMethod($guard, "closures");
		$closuresMethod->setAccessible(true);
		$this->assertEquals(2, count($closuresMethod->invoke($guard)), "Scope guard did not have two closures after call to addClosure().");
	}

	/**
	 * Test guards can be re-enabled.
	 */
	public function testEnable(): void
	{
		$called = false;

		(function() use (&$called) {
			$guard = new ScopeGuard(function() use (&$called) {
				$called = true;
			});

			$guard->cancel();
			$guard->enable();
		})();

		$this->assertTrue($called, "The scope guard closure was not called on destruction.");

		$called1 = false;
		$called2 = false;

		(function() use (&$called1, &$called2) {
			$guard = new ScopeGuard(function() use (&$called1) {
				$called1 = true;
			});

			$guard->addClosure(function() use (&$called2) {
				$called2 = true;
			});

			$guard->cancel();
			$guard->enable();
		})();

		$this->assertTrue($called1, "The scope guard's initial closure was not called on destruction.");
		$this->assertTrue($called2, "The scope guard's added closure was not called on destruction.");
	}
}
