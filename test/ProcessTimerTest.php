<?php

/**
 * User: darren
 * Date: 05/10/19
 * Time: 23:00
 */

declare(strict_types = 1);

namespace Equit\Test;

use Equit\Test\Framework\TestCase;
use Equit\Util\ProcessTimer;

class ProcessTimerTest extends TestCase
{
    public function setUp(): void
    {
        $this->m_testTimer = new ProcessTimer();
    }

    public function providerTestProcessName(): array
    {
        return [
            [""],
            ["test1"],
            ["test2"],
            ["    "],
        ];
    }

    /**
     * @dataProvider providerTestProcessName
     *
     * @param string $name
     */
    public function testProcessName(string $name): void
    {
        $this->m_testTimer->setProcessName($name);
        $this->assertEquals($name, $this->m_testTimer->processName());
    }

    public function providerTestDuration(): array
    {
        return [
            [100],
            [200],
            [300],
            [400],
            [500],
            [600],
            [700],
            [800],
            [900],
            [1000],
        ];
    }

    /**
     * @dataProvider providerTestDuration
     *
     * @param int $duration The duration, in ms, to wait before stopping the timer.
     */
    public function testDuration(int $duration): void
    {
        $res = $this->m_testTimer->start();

        if (!$res) {
            $this->markTestSkipped("Timer failed to start");
        }

        // we receive 1/1000 sec, usleep expects 1/1000000 sec
        usleep($duration * 1000);
        $res = $this->m_testTimer->stop();

        if (false === $res) {
            $this->markTestSkipped("Timer failed to stop");
        }

        $this->assertBetween($duration - self::TestDurationTolerance, $duration + self::TestDurationTolerance, $this->m_testTimer->duration() * 1000, "Duration outside tolerance.");
    }

    public function testStart(): void
    {
        $res = $this->m_testTimer->start();
        $this->assertTrue($res, "failed to start timer");
        $res = $this->m_testTimer->start();
        $this->assertFalse($res, "should have received false when calling start() on a running timer");

        if (false === $this->m_testTimer->stop()) {
            $this->markTestSkipped("Timer could not be stopped");
        }

        $res = $this->m_testTimer->start();
        $this->assertFalse($res, "should have received false when calling start() on a finished timer");
        $this->m_testTimer->reset();

        $res = $this->m_testTimer->start();
        $this->assertTrue($res, "failed to start a timer that had been reset");
    }

    public function testEvents(): void
    {
        $started = (object)[
            "count" => 0,
            "times" => [],
        ];

        $stopped = (object)[
            "count" => 0,
            "times" => [],
            "durations" => [],
        ];

        $reset = (object)[
            "count" => 0,
        ];

        $onStart = function (string $processName, float $start) use ($started) {
            ++$started->count;
            $started->times[] = $start;
        };

        $onStop = function (string $processName, float $start, float $stop, float $duration) use ($stopped) {
            ++$stopped->count;
            $stopped->times[]     = $stop;
            $stopped->durations[] = $duration;
        };

        $onReset = function (string $processName) use ($reset) {
            ++$reset->count;
        };

        $this->m_testTimer->addListener(ProcessTimer::EventStart, $onStart);
        $this->m_testTimer->addListener(ProcessTimer::EventStop, $onStop);
        $this->m_testTimer->addListener(ProcessTimer::EventReset, $onReset);

        $res = $this->m_testTimer->start();

        if (!$res) {
            $this->markTestSkipped("failed to start timer");
        }

        $this->assertEquals(1, $started->count);
        $this->assertEquals(0, $stopped->count);
        $this->assertEquals(0, $reset->count);
        $this->assertEquals($this->m_testTimer->startTime(), $started->times[0]);

        $res = $this->m_testTimer->stop();

        if (false === $res) {
            $this->markTestSkipped("failed to start timer");
        }

        $this->assertEquals(1, $stopped->count);
        $this->assertEquals(1, $stopped->count);
        $this->assertEquals(0, $reset->count);
        $this->assertEquals($this->m_testTimer->endTime(), $stopped->times[0]);
        $this->assertEquals($this->m_testTimer->duration(), $res);
        $this->assertEquals($this->m_testTimer->duration(), $stopped->durations[0]);

        $this->m_testTimer->reset();

        $this->assertEquals(1, $stopped->count);
        $this->assertEquals(1, $stopped->count);
        $this->assertEquals(1, $reset->count);
    }

    /** testDuration() can't predict precisely what the actual duration will be, so we need a tolerance beyond which we
     * assume the test has failed.
     */
    private const TestDurationTolerance = 100;

    /** @var ProcessTimer The timer used for testing. */
    private ProcessTimer $m_testTimer;
}
