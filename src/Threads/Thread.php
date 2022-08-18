<?php

namespace Equit\Threads;

use Equit\Contracts\ThreadExecutor;
use LogicException;

class Thread
{
    private ThreadExecutor $m_executor;
    private ?int $m_threadId = null;

    public function __construct(?ThreadExecutor $executor = null)
    {
        if (!isset($executor)) {
            if (extension_loaded("pcntl")) {
                $executor = new PcntlThreadExecutor();
            } else {
                $executor = new SerialThreadExecutor();
            }
        }

        $this->m_executor = $executor;
    }

    public function start(callable $entryPoint, ...$args): void
    {
        if ($this->isRunning()) {
            throw new LogicException("Can't start a thread when it's already running.");
        }

        $this->m_threadId = $this->m_executor->exec($entryPoint, $args);
    }

    public function isRunning(): bool
    {
        if (!isset($this->m_threadId)) {
            return false;
        }

        if (!$this->m_executor->isRunning($this->m_threadId)) {
            $this->m_threadId = null;
            return false;
        }

        return true;
    }

    /**
     * Wait (a given maximum number of ms) for a thread to finish.
     *
     * @param int|null $timeout The maximum time, in ms, to wait.
     *
     * @return bool `true` if the thread finished, `false` if it's still running.
     */
    public function wait(?int $timeout = null): bool
    {
        if (isset($timeout)) {
            $timeout = ($timeout * 1000) + microtime(true);
        }

        while ($this->isRunning() && (!isset($timeout) || microtime(true) < $timeout)) {
            usleep(1000);
        }

        return !$this->isRunning();
    }

    /**
     * Wait (up to a given duration) for one or more of a set of threads to finish.
     *
     * @param Thread[] $threads The threads to wait for.
     * @param int|null $timeout The optional timeout aftger which to return even if none of the threads has finished.
     *
     * @return Thread[] The finished threads, or an empty array if none have finished inside the timeout.
     */
    public static function waitForOneOf(array $threads, ?int $timeout = null): array
    {
        if (isset($timeout)) {
            $timeout = ($timeout * 1000) + microtime(true);
        }

        /** @var Thread[] $finishedThreads */
        $finishedThreads = [];

        while (empty($finishedThreads) && (!isset($timeout) || microtime(true) < $timeout)) {
            foreach ($threads as $thread) {
                if (!$thread->isRunning()) {
                    $finishedThreads[] = $thread;
                }
            }
            usleep(1000);
        }

        return $finishedThreads;
    }
}
