<?php

namespace Bead\Web\RequestProcessors;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Contracts\RequestPostprocessor;
use Bead\Contracts\RequestPreprocessor;
use Bead\Contracts\Response;
use Bead\Facades\Log;
use Bead\Web\Request;

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
        return LoggerContract::InformationLevel;
    }

    /** Capture the start time of the request so we can calculate the duration. */
    public function preprocessRequest(Request $request): ?Response
    {
        $this->m_started = hrtime(true);
        return null;
    }

    /** Log how long the request took to process. */
    public function postprocessRequest(Request $request, Response $response): ?Response
    {
        $nanoSeconds = hrtime(true) - $this->m_started;
        $seconds = sprintf("%0.{$this->decimalPlaces()}f", $nanoSeconds / 1_000_000_000);

        Log::log($this->logLevel(), "Request {$request->url()} from {$request->remoteIp4()} took {$nanoSeconds}ns ({$seconds}s)");
        return null;
    }
}
