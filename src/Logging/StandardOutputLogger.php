<?php

namespace Bead\Logging;

use Bead\Contracts\Logger as LoggerContract;
use Psr\Log\AbstractLogger as PsrAbstractLogger;

class StandardOutputLogger extends PsrAbstractLogger implements LoggerContract
{
    use LogsToStream;

    /**
     * @return resource The PHP pre-defined stream STDOUT.
     */
    protected function stream(): mixed
    {
        return STDOUT;
    }
}
