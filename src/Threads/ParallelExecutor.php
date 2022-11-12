<?php

namespace Equit\Threads;

use Equit\Contracts\ThreadExecutor;
use RuntimeException;
use parallel\Runtime;

/**
 * Thread executor using the parallel extension.
 */
class ParallelExecutor implements ThreadExecutor
{
    /** @var Runtime The parrallel runtime that will be used to manage the threads. */
    private Runtime $m_runtime;

    /** @var array The futures spawned to run the threads. */
    private array $m_futures;

    /** @var int The ID to use for the next thread executed. */
    private int $m_nextId;

    /**
     * Initialise a new executor instance.
     */
    public function __construct()
    {
        assert(extension_loaded("parallel"), new RuntimeException("The parallel extension is required for the ParallelExecutor thread backend."));
        $this->m_runtime = new Runtime();
        $this->m_futures = [];
        $this->m_nextId = 0;
    }

    /**
     * Clean up all threads spawned by the executor.
     */
    public function __destruct()
    {
        foreach ($this->m_futures as $future) {
            $future->cancel();
        }
    }

    /**
     * Fetch the runtime executing the threads.
     *
     * @return Runtime The runtime.
     */
    protected function runtime(): Runtime
    {
        return $this->m_runtime;
    }

    /**
     * Execute a function in a thread.
     *
     * @param callable $entryPoint The function to call.
     * @param array ...$args The arguments to pass to the function.
     */
    public function exec(callable $entryPoint, ...$args): int
    {
        $future = $this->runtime()->run($entryPoint, $args);
        $id = $this->m_nextId;
        $this->m_futures[$id] = $future;
        ++$this->m_nextId;
        return $id;
    }

    /**
     * Check whether a thread is currently running.
     *
     * @param int $threadId The ID of the thread to check.
     *
     * @return `true` if the thread is still running, `false` if not.
     */
    public function isRunning(int $threadId): bool
    {
        if (!array_key_exists($threadId, $this->m_futures)) {
            return false;
        }

        if ($this->m_futures[$threadId]->done()) {
            unset($this->m_futures[$threadId]);
            return false;
        }

        return true;
    }
}