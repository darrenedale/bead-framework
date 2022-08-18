<?php

namespace Equit\Contracts;

interface ThreadExecutor
{
    public function exec(callable $entryPoint, ... $args): int;
    public function isRunning(int $threadId): bool;
}
