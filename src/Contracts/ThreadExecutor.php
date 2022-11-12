<?php

namespace Equit\Contracts;

/**
 * Interface for classes that can execute threads.
 */
interface ThreadExecutor
{
    /**
     * Call a closure in a thread.
     *
     * @param callable $entryPoint The closure to call.
     * @param ...$args The arguments to pass to the closure.
     *
     * @return int A unique identifier for the thread.
     */
    public function exec(callable $entryPoint, ... $args): int;

    /**
     * Determine whether a thread is running.
     *
     * @param int $threadId The identifier of the thread to check.
     *
     * @return bool `true` if it's running, `false` otherwise.
     */
    public function isRunning(int $threadId): bool;
}
