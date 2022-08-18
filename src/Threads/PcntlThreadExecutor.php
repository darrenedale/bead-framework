<?php

namespace Equit\Threads;

use Equit\Contracts\ThreadExecutor;
use LogicException;
use RuntimeException;

class PcntlThreadExecutor implements ThreadExecutor
{
    private array $m_pids = [];

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
                print_r($this->m_pids);
                return $pid;
        }
    }

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