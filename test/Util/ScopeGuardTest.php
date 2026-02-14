<?php

declare(strict_types=1);

namespace BeadTests\Util;

use Bead\Util\ScopeGuard;
use BeadTests\Framework\TestCase;
use Equit\XRay\XRay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use TypeError;

#[CoversClass(ScopeGuard::class)]
class ScopeGuardTest extends TestCase
{
    /** Ensure the constructor accepts a valid closure. */
    public function testConstructor1(): void
    {
        $closure = static function () use (&$called): void {
        };

        $guard = new ScopeGuard($closure);
        $closures = (new XRay($guard))->closures();
        self::assertCount(1, $closures);
        self::assertSame($closure, $closures[0]);
    }

    /** Ensure invoke() calls all the closures. */
    public function testInvoke1(): void
    {
        $called1 = false;
        $called2 = false;

        $guard = new ScopeGuard(static function () use (&$called1) {
            $called1 = true;
        });

        $guard->addClosure(static function () use (&$called2) {
            $called2 = true;
        });

        $guard->invoke();
        self::assertTrue($called1, "The scope guard's initial closure was not called by invoke().");
        self::assertTrue($called2, "The scope guard's added closure was not called by invoke().");
    }

    /** Ensure invoke() doesn't call the closures when the guard has been cancelled. */
    public function testInvoke2(): void
    {
        $called1 = false;
        $called2 = false;

        $cancelledGuard = new ScopeGuard(function () use (&$called1) {
            $called1 = true;
        });

        $cancelledGuard->addClosure(function () use (&$called2) {
            $called2 = true;
        });

        $cancelledGuard->cancel();
        $cancelledGuard->invoke();
        self::assertFalse($called1, "The scope guard's initial closure was still called by invoke() after cancellation.");
        self::assertFalse($called2, "The scope guard's added closure was still called by invoke() after cancellation.");
    }

    /** Ensure the destructor invokes all the closures. */
    public function testDestructor1(): void
    {
        $called1 = false;
        $called2 = false;

        (function () use (&$called1, &$called2) {
            $guard = new ScopeGuard(function () use (&$called1) {
                $called1 = true;
            });

            $guard->addClosure(function () use (&$called2) {
                $called2 = true;
            });
        })();

        self::assertTrue($called1, "The scope guard's initial closure was not called on destruction.");
        self::assertTrue($called2, "The scope guard's added closure was not called on destruction.");
    }

    /** Ensure the destructor doesn't invoke the closures when the guard has been cancelled. */
    public function testDestructor2(): void
    {
        $notCalled1 = true;
        $notCalled2 = true;

        (function () use (&$notCalled1, &$notCalled2) {
            $guard = new ScopeGuard(function () use (&$notCalled1) {
                $notCalled1 = false;
            });

            $guard->addClosure(function () use (&$notCalled2) {
                $notCalled2 = false;
            });

            $guard->cancel();
        })();

        self::assertTrue($notCalled1, "The scope guard's initial closure was still called on destruction after cancellation.");
        self::assertTrue($notCalled2, "The scope guard's added closure was still called on destruction after cancellation.");
    }

    /** Ensure closures can be added to the guard. */
    public function testAddClosure1(): void
    {
        $closure1 = static function (): void {
        };

        $closure2 = static function (): void {
        };

        $guard = new ScopeGuard($closure1);
        $guard->addClosure($closure2);
        $closures = (new XRay($guard))->closures();
        self::assertCount(2, $closures, "Scope guard did not have two closures after call to addClosure().");
        self::assertSame($closure1, $closures[0]);
        self::assertSame($closure2, $closures[1]);
    }

    /** ensure the guard can be re-enabled. */
    public function testEnable1(): void
    {
        $called1 = false;
        $called2 = false;

        (function () use (&$called1, &$called2) {
            $guard = new ScopeGuard(static function () use (&$called1): void {
                $called1 = true;
            });

            $guard->addClosure(static function () use (&$called2): void {
                $called2 = true;
            });

            $guard->cancel();
            $guard->enable();
        })();

        self::assertTrue($called1, "The scope guard's initial closure was not called on destruction.");
        self::assertTrue($called2, "The scope guard's added closure was not called on destruction.");
    }
}
