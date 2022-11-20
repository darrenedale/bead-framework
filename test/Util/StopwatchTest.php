<?php
declare(strict_types = 1);

namespace BeadTests\Util;

use BeadTests\Framework\TestCase;
use Bead\Util\Stopwatch;
use Generator;
use LogicException;
use ReflectionProperty;
use TypeError;

/**
 * Test for the Stopwatch utility class.
 */
class StopwatchTest extends TestCase
{
    /**
     * `testDuration()` can't predict precisely what the actual duration will be, so we need a tolerance beyond which we
     * assume the test has failed.
     */
    private const TestDurationTolerance = 100;

    /** @var Stopwatch The stopwatch used for testing. */
    private Stopwatch $m_testStopwatch;
    
    /**
     * Set up the test fixture.
     */
    public function setUp(): void
    {
        $this->m_testStopwatch = new Stopwatch();
    }

    /**
     * Data provider for testProcessName()
     *
     * @return Generator The test data.
     */
    public function dataForTestProcessName(): Generator
    {
        yield from [
            "extremeEmpty" => [""],
            "typicalTest1" => ["test1"],
            "typicalTest2" => ["test2"],
            "extremeWhitespace" => ["    "],
            "invalidNull" => [null, TypeError::class],
            "invalidBool" => [true, TypeError::class],
            "invalidInt" => [5, TypeError::class],
            "invalidFloat" => [23.469, TypeError::class],
            "invalidStringable" => [new class() {
                public function __toString(): string
                {
                    return "foo";
                }
            }, TypeError::class],
            "invalidAssociativeArray" => [["__toString" => function(): string
                {
                    return "foo";
                }
            ], TypeError::class],
            "invalidIndexedArray" => [["foo",], TypeError::class],
        ];

        // 100 random valid names
        for ($idx = 0; $idx < 100; ++$idx) {
            yield "randomValidName{$idx}" => [self::randomString(mt_rand(2,10)),];
        }
    }

    /**
     * @dataProvider dataForTestProcessName
     *
     * @param mixed $name The name to test with.
     */
    public function testProcessName($name, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $this->m_testStopwatch->setProcessName($name);
        $this->assertEquals($name, $this->m_testStopwatch->processName());
    }

    /**
     * Data provider for testDuration()
     *
     * @return Generator The test data.
     */
    public function dataForTestDuration(): Generator
    {
        yield from [
            "typical100" => [100],
            "typical200" => [200],
            "typical300" => [300],
            "typical400" => [400],
            "typical500" => [500],
            "typical600" => [600],
            "typical700" => [700],
            "typical800" => [800],
            "typical900" => [900],
            "typical1000" => [1000],
        ];

        for ($idx = 0; $idx < 25; ++$idx) {
            yield "randomTypical{$idx}" => [mt_rand(5, 50)];
        }
    }

    /**
     * @dataProvider dataForTestDuration
     *
     * @param int $duration The duration, in ms, to wait before stopping the timer.
     */
    public function testDuration(int $duration): void
    {
        $res = $this->m_testStopwatch->start();

        if (!$res) {
            $this->markTestSkipped("Timer failed to start");
        }

        // we receive 1/1000 sec, usleep expects 1/1000000 sec
        usleep($duration * 1000);
        $res = $this->m_testStopwatch->stop();

        if (false === $res) {
            $this->markTestSkipped("Timer failed to stop");
        }

        $this->assertEqualsWithDelta($duration,$this->m_testStopwatch->duration() * 1000, self::TestDurationTolerance, "Duration outside tolerance.");
    }

    /**
     * Test the start() method.
     */
    public function testStart(): void
    {
        $res = $this->m_testStopwatch->start();
        $this->assertTrue($res, "failed to start timer");
        $res = $this->m_testStopwatch->start();
        $this->assertFalse($res, "should have received false when calling start() on a running timer");

        if (false === $this->m_testStopwatch->stop()) {
            $this->markTestSkipped("Timer could not be stopped");
        }

        $res = $this->m_testStopwatch->start();
        $this->assertFalse($res, "should have received false when calling start() on a finished timer");
        $this->m_testStopwatch->reset();

        $res = $this->m_testStopwatch->start();
        $this->assertTrue($res, "failed to start a timer that had been reset");
    }

    /**
     * Dummy method for use when testing addListener().
     */
    public static function dummyListener(): void
    {}
    
    /**
     * Data provider for testAddListener().
     *
     * @return array[] The test data.
     */
    public function dataForTestAddListener(): array
    {
        return [
            "typicalStartClosure" => [Stopwatch::EventStart, function() {},],
            "typicalStopClosure" => [Stopwatch::EventStart, function() {},],
            "typicalResetClosure" => [Stopwatch::EventStart, function() {},],
            "typicalAnonymousCallableStartListener" => [Stopwatch::EventStart, new class() {
                public function __invoke(): void {}
            },],
            "typicalAnonymousCallableStopListener" => [Stopwatch::EventStop, new class() {
                public function __invoke(): void {}
            },],
            "typicalAnonymousCallableResetListener" => [Stopwatch::EventReset, new class() {
                public function __invoke(): void {}
            },],

            "typicalMethodStartListener" => [Stopwatch::EventReset, [new class() {
                public function listener(): void {}
            }, "listener"],],

            "typicalMethodStopListener" => [Stopwatch::EventReset, [new class() {
                public function listener(): void {}
            }, "listener"],],

            "typicalMethodResetListener" => [Stopwatch::EventReset, [new class() {
                public function listener(): void {}
            }, "listener"],],

            "typicalStaticMethodStartListener" => [Stopwatch::EventReset, [self::class, "dummyListener"],],
            "typicalStaticMethodStopListener" => [Stopwatch::EventReset, [self::class, "dummyListener"],],
            "typicalStaticMethodResetListener" => [Stopwatch::EventReset, [self::class, "dummyListener"],],

            "invalidUnknownEvent" => [9999, function() {}, LogicException::class,],
            "invalidNullStartListener" => [Stopwatch::EventStart, null, TypeError::class,],
            "invalidNullStopListener" => [Stopwatch::EventStop, null, TypeError::class,],
            "invalidNullResetListener" => [Stopwatch::EventReset, null, TypeError::class,],
            "invalidBoolEvent" => [true, function() {}, TypeError::class,],
            "invalidBoolStartListener" => [Stopwatch::EventStart, true, TypeError::class,],
            "invalidBoolStopListener" => [Stopwatch::EventStop, true, TypeError::class,],
            "invalidBoolResetListener" => [Stopwatch::EventReset, true, TypeError::class,],
            "invalidIntStartListener" => [Stopwatch::EventStart, 12, TypeError::class,],
            "invalidIntStopListener" => [Stopwatch::EventStop, 12, TypeError::class,],
            "invalidIntResetListener" => [Stopwatch::EventReset, 12, TypeError::class,],
            "invalidFloatEvent" => [56.2376, function() {}, TypeError::class,],
            "invalidFloatStartListener" => [Stopwatch::EventStart, 56.2376, TypeError::class,],
            "invalidFloatStopListener" => [Stopwatch::EventStop, 56.2376, TypeError::class,],
            "invalidFloatResetListener" => [Stopwatch::EventReset, 56.2376, TypeError::class,],
            "invalidEmptyStringEvent" => ["", function() {}, TypeError::class,],
            "invalidEmptyStringStartListener" => [Stopwatch::EventStart, null, TypeError::class,],
            "invalidEmptyStringStopListener" => [Stopwatch::EventStop, "", TypeError::class,],
            "invalidEmptyStringResetListener" => [Stopwatch::EventReset, "", TypeError::class,],
            "invalidAnonymousClassEvent" => [new class() {}, function() {}, TypeError::class,],
            "invalidUnknownEventAndNullListener" => [9999, null, TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestAddListener
     *
     * @param mixed $event The event for which to add the listener.
     * @param mixed $listener The listener to add.
     * @param string|null $exceptionClass The exception expected, if any.
     *
     * @return void
     */
    public function testAddListener($event, $listener, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $this->m_testStopwatch->addListener($event, $listener);
        $listenersProperty = new ReflectionProperty(Stopwatch::class, "m_listeners");
        $listenersProperty->setAccessible(true);
        $this->assertEquals($listenersProperty->getValue($this->m_testStopwatch)[$event][0], $listener, "The array of listeners for event {$event} did not consist of the test listener.");
    }

    /**
     * Basic test for start, stop and reset events.
     */
    public function testEvents(): void
    {
        $started = [
            "count" => 0,
            "times" => [],
        ];

        $stopped = [
            "count" => 0,
            "times" => [],
            "durations" => [],
        ];

        $reset = [
            "count" => 0,
        ];

        $onStart = function (string $processName, float $start) use (&$started) {
            ++$started["count"];
            $started["times"][] = $start;
        };

        $onStop = function (string $processName, float $start, float $stop, float $duration) use (&$stopped) {
            ++$stopped["count"];
            $stopped["times"][] = $stop;
            $stopped["durations"][] = $duration;
        };

        $onReset = function (string $processName) use (&$reset) {
            ++$reset["count"];
        };

        $this->m_testStopwatch->addListener(Stopwatch::EventStart, $onStart);
        $this->m_testStopwatch->addListener(Stopwatch::EventStop, $onStop);
        $this->m_testStopwatch->addListener(Stopwatch::EventReset, $onReset);

        $res = $this->m_testStopwatch->start();

        if (!$res) {
            $this->markTestSkipped("failed to start timer");
        }

        $this->assertEquals(1, $started["count"]);
        $this->assertEquals(0, $stopped["count"]);
        $this->assertEquals(0, $reset["count"]);
        $this->assertEquals($this->m_testStopwatch->startTime(), $started["times"][0]);

        $res = $this->m_testStopwatch->stop();

        if (false === $res) {
            $this->markTestSkipped("failed to start timer");
        }

        $this->assertEquals(1, $stopped["count"]);
        $this->assertEquals(1, $stopped["count"]);
        $this->assertEquals(0, $reset["count"]);
        $this->assertEquals($this->m_testStopwatch->endTime(), $stopped["times"][0]);
        $this->assertEquals($this->m_testStopwatch->duration(), $res);
        $this->assertEquals($this->m_testStopwatch->duration(), $stopped["durations"][0]);

        $this->m_testStopwatch->reset();

        $this->assertEquals(1, $stopped["count"]);
        $this->assertEquals(1, $stopped["count"]);
        $this->assertEquals(1, $reset["count"]);
    }
}
