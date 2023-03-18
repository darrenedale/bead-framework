<?php

namespace Bead\Logging;

use Bead\Contracts\Logger as LoggerContract;
use Psr\Log\AbstractLogger as PsrAbstractLogger;

class StandardErrorLogger extends PsrAbstractLogger implements LoggerContract
{
    use LogsToStream;

    /**
     * @return resource The PHP pre-defined stream STDERR.
     */
    protected function stream(): mixed
    {
        return STDERR;
    }
}
