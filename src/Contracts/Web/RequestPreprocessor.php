<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

use Bead\Contracts\Web\Request as RequestContract;

/** Interface for classes that can pre-process Requests. */
interface RequestPreprocessor
{
    /**
     * Pre-process the Request.
     *
     * @param RequestContract $request The Request to be pre-processed.
     *
     * @return Response|null A Response to send immediately, or `null` if the Request should proceed to the app.
     */
    public function preprocessRequest(RequestContract $request): ?Response;
}
