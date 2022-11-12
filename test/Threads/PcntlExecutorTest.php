<?php

declare(strict_types=1);

namespace Equit\Test\Threads;

use Equit\Test\Framework\TestCase;
use Equit\Threads\PcntlExecutor;
use LogicException;
use RuntimeException;

final class PcntlExecutorTest extends TestCase
{
    private const SharedMemoryKey = 0xBEAD;
    private PcntlExecutor $m_executor;
    private $m_sharedMemory;

    public function setUp(): void
    {
        try {
            $this->m_executor = new PcntlExecutor();
            $this->m_sharedMemory = shmop_open(self::SharedMemoryKey, "c", 0600, 1);
        } catch (RuntimeException) {
        }
    }

    public function tearDown(): void
    {
        shmop_delete($this->m_sharedMemory);
        unset($this->m_executor, $this->m_sharedMemory);
    }

    private function waitForThread(int $id): void
    {
        while($this->m_executor->isRunning($id)) {
            usleep (100000);
        }
    }

    private function entryPoint(bool &$result): void
    {
        sleep(1);
        $this->writeSharedMemory("1");
    }

    private function skipIfNecessary(): void
    {
        if (!isset($this->m_executor)) {
            $this->markTestSkipped("No executor set - pcntl extension may not be installed.");
        }
    }

    public function writeSharedMemory(string $value): void
    {
        $memory = shmop_open(self::SharedMemoryKey, "w", 0, 0);
        shmop_write($memory, $value, 0);
        shmop_close($memory);
    }

    public function readSharedMemory(): string
    {
        $memory = shmop_open(self::SharedMemoryKey, "a", 0, 0);
        return shmop_read($memory, 0, 1);
        shmop_close($memory);
    }

    public function testExecWithClosure(): void
    {
        $this->skipIfNecessary();
        $this->writeSharedMemory("0");
        $closure = function() use (&$result)
        {
            sleep(1);
            $this->writeSharedMemory("1");
        };

        $id = $this->m_executor->exec($closure);
        $this->assertEquals("0", $this->readSharedMemory());
        $this->assertTrue($this->m_executor->isRunning($id));
        $this->waitForThread($id);
        $this->assertEquals("1", $this->readSharedMemory());
    }

    public function testExecWithClosureAndArgs(): void
    {
        $this->skipIfNecessary();
        $this->writeSharedMemory("0");
        $closure = function(int $a, int $b) use (&$result)
        {
            sleep(1);
            $this->writeSharedMemory(chr($a * $b));
        };

        $id = $this->m_executor->exec($closure, 5, 6);
        $this->assertEquals("0", $this->readSharedMemory());
        $this->assertTrue($this->m_executor->isRunning($id));
        $this->waitForThread($id);
        $this->assertEquals(chr(30), $this->readSharedMemory());
    }

    public function testExecWithInvokable(): void
    {
        $this->skipIfNecessary();
        $this->writeSharedMemory("0");
        $testInstance = $this;

        $invokable = new class($testInstance)
        {
            private PcntlExecutorTest $m_testInstance;

            public function __construct(PcntlExecutorTest $testInstance)
            {
                $this->m_testInstance = $testInstance;
            }

            public function __invoke(): void
            {
                sleep(1);
                $this->m_testInstance->writeSharedMemory("1");
            }
        };

        $id = $this->m_executor->exec($invokable);
        $this->assertEquals("0", $this->readSharedMemory());
        $this->assertTrue($this->m_executor->isRunning($id));
        $this->waitForThread($id);
        $this->assertEquals("1", $this->readSharedMemory());
    }

    public function testExecWithInvokableAndArgs(): void
    {
        $this->skipIfNecessary();
        $this->writeSharedMemory("0");
        $testInstance = $this;

        $invokable = new class()
        {
            public function __invoke(PcntlExecutorTest $testInstance): void
            {
                sleep(1);
                $testInstance->writeSharedMemory("1");
            }
        };

        $id = $this->m_executor->exec($invokable, $this);
        $this->assertEquals("0", $this->readSharedMemory());
        $this->assertTrue($this->m_executor->isRunning($id));
        $this->waitForThread($id);
        $this->assertEquals("1", $this->readSharedMemory());
    }

    public function testIsRunning(): void
    {
        $closure = function()
        {
            usleep(100000);
        };

        $id = $this->m_executor->exec($closure);
        $this->assertTrue($this->m_executor->isRunning($id));
        usleep(200000);
        $this->assertFalse($this->m_executor->isRunning($id));
    }

    public function testIsRunningThrows(): void
    {
        $closure = function()
        {
            usleep(100000);
        };

        $id = $this->m_executor->exec($closure);
        $this->assertTrue($this->m_executor->isRunning($id));
        $this->expectException(LogicException::class);
        $this->m_executor->isRunning($id + 1);
    }
}
