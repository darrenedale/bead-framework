<?php

namespace Equit\Threads;

use Equit\Contracts\ThreadExecutor;

/**
 * Fallback thread executor that just runs the callable in the current thread.
 *
 * This is only for use when no other means of running the callable in parallel is available.
 */
class SerialExecutor implements ThreadExecutor
{

    public function exec(callable $entryPoint, ...$args): int
    {
        $entryPoint(...$args);
        return 0;
    }

    public function isRunning(int $threadId): bool
    {
        return false;
    }
}