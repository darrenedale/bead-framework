<?php

namespace Equit\Threads;

use Equit\Contracts\ThreadExecutor;
use LogicException;
use RuntimeException;

/**
 * Execute a thread using the fork() syscall.
 */
class PcntlExecutor implements ThreadExecutor
{
    /** @var array The PIDs of the forked threads. */
    private array $m_pids;

    /** Initialise a new executor instance. */
    public function __construct()
    {
        assert(extension_loaded("pcntl"), new RuntimeException("The pcntl extension is required for the PcntlExecutor thread backend."));
        $this->m_pids = [];
    }

    /**
     * Fork a new process for a thread.
     *
     * @param callable $entryPoint The entry point for the thread.
     * @param ...$args The arguments to pass to the entry point.
     *
     * @return int The PID of the spawned process.
     */
    public function exec(callable $entryPoint, ...$args): int
    {
        $pid = pcntl_fork();

        switch ($pid) {
            case -1:
                throw new RuntimeException("Failed to fork process in " . self::class);

            case 0:
                // this is the fork
                $entryPoint(...$args);
                exit(0);

            default:
                // this is the original
                $this->m_pids[] = $pid;
                return $pid;
        }
    }

    /**
     * Check whether a thread is running.
     *
     * @param int $threadId The ID of the thread's PID.
     *
     * @return bool `true` if the thread is running, `false` otherwise.
     */
    public function isRunning(int $threadId): bool
    {
        $idx = array_search($threadId, $this->m_pids);

        if (false === $idx) {
            throw new LogicException("Thread {$threadId} was not started by this executor.");
        }

        // $result is 0 if running, the PID if terminated.
        $result = pcntl_waitpid($threadId, $status, WNOHANG);
        $running = (0 === $result);

        if (!$running) {
            array_splice($this->m_pids, $idx, 1);
        }

        return $running;
    }
}
