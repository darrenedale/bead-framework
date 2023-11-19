<?php

namespace Bead\Contracts;

use Throwable;

interface ErrorHandler
{
    /**
     * Handle a PHP error for the application.
     *
     * @param int $type The error type, one of the PHP `E_*` constants.
     * @param string $message The error message.
     * @param string $file The file in which the error occurred.
     * @param int $line The line on which the error occurred.
     */
    public function handleError(int $type, string $message, string $file, int $line): void;

    /**
     * Handle an exception for the application.
     *
     * @param Throwable $error The thrown exception.
     */
    public function handleException(Throwable $error): void;
}
