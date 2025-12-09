<?php

namespace Bead\Web\RequestProcessors;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Contracts\Web\Request as RequestContract;
use Bead\Contracts\Web\RequestPostprocessor;
use Bead\Contracts\Web\RequestPreprocessor;
use Bead\Contracts\Web\Response;
use Bead\Facades\Log;

/** Request pre- and post-processor to time how long requests take to process. */
class LogRequestDuration implements RequestPostprocessor, RequestPreprocessor
{
    private int $m_started = 0;

    /** How many decimal places to use to report how long it took to process the request. */
    protected function decimalPlaces(): int
    {
        return 5;
    }

    /** The level at which the request duration should be logged. */
    protected function logLevel(): int
    {
        return LoggerContract::DebugLevel;
    }

    /** Capture the start time of the request so we can calculate the duration. */
    public function preprocessRequest(RequestContract $request): ?Response
    {
        $this->m_started = hrtime(true);
        return null;
    }

    /** Log how long the request took to process. */
    public function postprocessRequest(RequestContract $request, Response $response): ?Response
    {
        $nanoSeconds = hrtime(true) - $this->m_started;
        $seconds = sprintf("%0.{$this->decimalPlaces()}f", $nanoSeconds / 1_000_000_000);

        Log::log($this->logLevel(), "Request {$request->path()} took {$nanoSeconds}ns ({$seconds}s)");
        return null;
    }
}
