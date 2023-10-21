<?php

declare(strict_types=1);

namespace BeadTests\Util;

use Bead\Util\Stopwatch;
use BeadTests\Framework\TestCase;
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
    private Stopwatch $testStopwatch;

    public function setUp(): void
    {
        $this->testStopwatch = new Stopwatch();
    }

    public function tearDown(): void
    {
        if ($this->testStopwatch->isRunning()) {
            $this->testStopwatch->stop();
        }

        unset($this->testStopwatch);
    }

    /**
     * Data provider for testProcessName()
     *
     * @return iterable The test data.
     */
    public function dataForTestProcessName(): iterable
    {
        yield "extremeEmpty" => [""];
        yield "typicalTest1" => ["test1"];
        yield "typicalTest2" => ["test2"];
        yield "extremeWhitespace" => ["    "];
    }

    /**
     * Ensure we can set the process name in the constructor.
     *
     * @dataProvider dataForTestProcessName
     */
    public function testConstructorWithProcessName(string $processName): void
    {
        $stopwatch = new Stopwatch($processName);
        self::assertEquals($processName, $stopwatch->processName());
    }

    /** Ensure we can set start listeners in the constructor. */
    public function testConstructorWithStartListener(): void
    {
        $called = false;
        $onStart = function () use (&$called): void {
            $called = true;
        };

        $stopwatch = new Stopwatch(startListeners: [$onStart]);
        $stopwatch->start();
        self::assertTrue($called);
        $stopwatch->stop();
    }

    /** Ensure we can set stop listeners in the constructor. */
    public function testConstructorWithStopListener(): void
    {
        $called = false;
        $onStop = function () use (&$called): void {
            $called = true;
        };

        $stopwatch = new Stopwatch(stopListeners: [$onStop]);
        $stopwatch->start();
        $stopwatch->stop();
        self::assertTrue($called);
    }

    /** Ensure we can set reset listeners in the constructor. */
    public function testConstructorWithResetListener(): void
    {
        $called = false;
        $onStop = function () use (&$called): void {
            $called = true;
        };

        $stopwatch = new Stopwatch(resetListeners: [$onStop]);
        $stopwatch->start();
        $stopwatch->stop();
        $stopwatch->reset();
        self::assertTrue($called);
    }

    /** Ensure we can set reset listeners in the constructor. */
    public function testConstructorWithAutostart(): void
    {
        $stopwatch = new Stopwatch(auto: true);
        self::assertTrue($stopwatch->isRunning());
        $stopwatch->stop();
    }

    /**
     * @dataProvider dataForTestProcessName
     *
     * @param string $name The name to test with.
     */
    public function testProcessName(string $name): void
    {
        $this->testStopwatch->setProcessName($name);
        self::assertEquals($name, $this->testStopwatch->processName());
    }

    /**
     * Data provider for testDuration()
     *
     * @return iterable The test data.
     */
    public function dataForTestDuration(): iterable
    {
        yield "typical100" => [100];
        yield "typical300" => [300];
        yield "typical500" => [500];
        yield "typical700" => [700];
        yield "typical900" => [900];
    }

    /**
     * @dataProvider dataForTestDuration
     *
     * @param int $duration The duration, in ms, to wait before stopping the timer.
     */
    public function testDuration(int $duration): void
    {
        $res = $this->testStopwatch->start();

        if (!$res) {
            $this->markTestSkipped("Timer failed to start");
        }

        // we receive 1/1000 sec, usleep expects 1/1000000 sec
        usleep($duration * 1000);
        $res = $this->testStopwatch->stop();

        if (false === $res) {
            $this->markTestSkipped("Timer failed to stop");
        }

        self::assertEqualsWithDelta($duration, $this->testStopwatch->duration() * 1000, self::TestDurationTolerance, "Duration outside tolerance.");
    }

    /** Ensure duration returns null if the stopwatch is still running. */
    public function testDurationWileRunning(): void
    {
        $this->testStopwatch->start();
        self::assertNull($this->testStopwatch->duration());
        $this->testStopwatch->stop();
    }

    /** Test the start() method. */
    public function testStart(): void
    {
        $res = $this->testStopwatch->start();
        self::assertTrue($res, "failed to start timer");
        $res = $this->testStopwatch->start();
        self::assertFalse($res, "should have received false when calling start() on a running timer");

        if (false === $this->testStopwatch->stop()) {
            $this->markTestSkipped("Timer could not be stopped");
        }

        $res = $this->testStopwatch->start();
        self::assertFalse($res, "should have received false when calling start() on a finished timer");
        $this->testStopwatch->reset();

        $res = $this->testStopwatch->start();
        self::assertTrue($res, "failed to start a timer that had been reset");
    }

    /** Ensure stop returns an appropriate duration. */
    public function testStop(): void
    {
        $this->testStopwatch->start();

        if (!$this->testStopwatch->isRunning()) {
            self::markTestSkipped("Failed to start stopwatch.");
        }

        usleep(500000);
        $actual = $this->testStopwatch->stop();
        self::assertIsFloat($actual);
        self::assertEqualsWithDelta(500, $actual * 1000, self::TestDurationTolerance);
        self::assertFalse($this->testStopwatch->isRunning());
    }

    /** Ensure stop() returns null if the stopwatch is not currently running. */
    public function testStopWhileNotRunning(): void
    {
        $this->testStopwatch->start();

        if (!$this->testStopwatch->isRunning()) {
            $this->markTestSkipped("Failed to stop stopwatch.");
        }

        usleep(250000);
        $this->testStopwatch->stop();

        if ($this->testStopwatch->isRunning()) {
            $this->markTestSkipped("Failed to stop stopwatch.");
        }

        self::assertNull($this->testStopwatch->stop());
    }

    /** Dummy method for use when testing addListener(). */
    public static function dummyListener(): void
    {
    }

    /**
     * Data provider for testAddListener().
     *
     * @return iterable The test data.
     */
    public function dataForTestAddListener(): iterable
    {
        yield "typicalStartClosure" => [
            Stopwatch::EventStart,
            function () {
            },
        ];

        yield "typicalStopClosure" => [
            Stopwatch::EventStart,
            function () {
            },
        ];

        yield "typicalResetClosure" => [
            Stopwatch::EventStart,
            function () {
            },
        ];

        yield "typicalAnonymousCallableStartListener" => [
            Stopwatch::EventStart,
            new class
            {
                public function __invoke(): void
                {
                }
            },
        ];

        yield "typicalAnonymousCallableStopListener" => [
            Stopwatch::EventStop,
            new class
            {
                public function __invoke(): void
                {
                }
            },
        ];

        yield "typicalAnonymousCallableResetListener" => [
            Stopwatch::EventReset, new class
            {
                public function __invoke(): void
                {
                }
            },
        ];

        yield "typicalMethodStartListener" => [
            Stopwatch::EventReset,
            [
                new class
                {
                    public function listener(): void
                    {
                    }
                },
                "listener",
            ],
        ];

        yield "typicalMethodStopListener" => [
            Stopwatch::EventReset,
            [
                new class
                {
                    public function listener(): void
                    {
                    }
                },
                "listener",
            ],
        ];

        yield "typicalMethodResetListener" => [
            Stopwatch::EventReset,
            [
                new class
                {
                    public function listener(): void
                    {
                    }
                },
                "listener",
            ],
        ];

        yield "typicalStaticMethodStartListener" => [Stopwatch::EventReset, [self::class, "dummyListener",],];
        yield "typicalStaticMethodStopListener" => [Stopwatch::EventReset, [self::class, "dummyListener",],];
        yield "typicalStaticMethodResetListener" => [Stopwatch::EventReset, [self::class, "dummyListener",],];
    }

    /**
     * @dataProvider dataForTestAddListener
     *
     * @param mixed $event The event for which to add the listener.
     * @param mixed $listener The listener to add.
     */
    public function testAddListener($event, $listener): void
    {
        $this->testStopwatch->addListener($event, $listener);
        $listenersProperty = new ReflectionProperty(Stopwatch::class, "m_listeners");
        $listenersProperty->setAccessible(true);
        self::assertEquals($listenersProperty->getValue($this->testStopwatch)[$event][0], $listener, "The array of listeners for event {$event} did not consist of the test listener.");
    }

    /** Ensure addListener() rejects invalid events. */
    public function testAddListenerThrows(): void
    {
        $listener = fn () => null;
        $this->expectException(LogicException::class);
        $this->testStopwatch->addListener(999, $listener);
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

        $this->testStopwatch->addListener(Stopwatch::EventStart, $onStart);
        $this->testStopwatch->addListener(Stopwatch::EventStop, $onStop);
        $this->testStopwatch->addListener(Stopwatch::EventReset, $onReset);

        $res = $this->testStopwatch->start();

        if (!$res) {
            $this->markTestSkipped("failed to start timer");
        }

        self::assertEquals(1, $started["count"]);
        self::assertEquals(0, $stopped["count"]);
        self::assertEquals(0, $reset["count"]);
        self::assertEquals($this->testStopwatch->startTime(), $started["times"][0]);

        $res = $this->testStopwatch->stop();

        if (false === $res) {
            $this->markTestSkipped("Failed to start stopwatch.");
        }

        self::assertEquals(1, $stopped["count"]);
        self::assertEquals(1, $stopped["count"]);
        self::assertEquals(0, $reset["count"]);
        self::assertEquals($this->testStopwatch->endTime(), $stopped["times"][0]);
        self::assertEquals($this->testStopwatch->duration(), $res);
        self::assertEquals($this->testStopwatch->duration(), $stopped["durations"][0]);

        $this->testStopwatch->reset();

        self::assertEquals(1, $stopped["count"]);
        self::assertEquals(1, $stopped["count"]);
        self::assertEquals(1, $reset["count"]);
    }

    /** Ensure we can add a start listener. */
    public function testAddStartListener(): void
    {
        $started = [
            "count" => 0,
            "times" => [],
        ];

        $onStart = function (string $processName, float $start) use (&$started) {
            ++$started["count"];
            $started["times"][] = $start;
        };

        $this->testStopwatch->addStartListener($onStart);
        $res = $this->testStopwatch->start();

        if (!$res) {
            $this->markTestSkipped("Failed to start stopwatch.");
        }

        self::assertEquals(1, $started["count"]);
        self::assertEquals($this->testStopwatch->startTime(), $started["times"][0]);

        $this->testStopwatch->stop();
    }

    /** Ensure we can add a stop listener. */
    public function testAddStopListener(): void
    {
        $stopped = [
            "count" => 0,
            "times" => [],
        ];

        $onStop = function (string $processName, float $start, float $end, float $duration) use (&$stopped) {
            ++$stopped["count"];
            $stopped["times"][] = [
                "start" => $start,
                "end" => $end,
                "duration" => $duration,
            ];
        };

        $this->testStopwatch->addStopListener($onStop);
        $res = $this->testStopwatch->start();

        if (!$res) {
            $this->markTestSkipped("Failed to start stopwatch.");
        }

        usleep(250000);

        if (null === $this->testStopwatch->stop()) {
            $this->markTestSkipped("Failed to stop stopwatch.");
        }

        self::assertEquals(1, $stopped["count"]);
        self::assertEquals($this->testStopwatch->startTime(), $stopped["times"][0]["start"]);
        self::assertEquals($this->testStopwatch->duration(), $stopped["times"][0]["duration"]);
        self::assertEquals($this->testStopwatch->endTime(), $stopped["times"][0]["end"]);
    }

    /** Ensure we can add a reset listener. */
    public function testAddResetListener(): void
    {
        $resetCount = 0;

        $onReset = function (string $processName) use (&$resetCount) {
            ++$resetCount;
        };

        $this->testStopwatch->addResetListener($onReset);
        $res = $this->testStopwatch->start();

        if (!$res) {
            $this->markTestSkipped("Failed to start stopwatch.");
        }

        usleep(250000);

        if (null === $this->testStopwatch->stop()) {
            $this->markTestSkipped("Failed to stop stopwatch.");
        }

        $this->testStopwatch->reset();
        self::assertEquals(1, $resetCount);
    }
}
