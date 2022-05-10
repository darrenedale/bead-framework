<?php

namespace Equit\Contracts;

use Throwable;

interface ErrorHandler
{
    public function handleError(int $type, string $message, string $file, int $Line): void;
    public function handleException(Throwable $err): void;
}