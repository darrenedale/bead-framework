<?php

declare(strict_types=1);

namespace Bead\Contracts;

use Bead\Web\Request;

/** Interface for classes that can pre-process Requests. */
interface RequestPreprocessor
{
    /**
     * Pre-process the Request.
     *
     * @param Request $request The Request to be pre-processed.
     *
     * @return Response|null A Response to send immediately, or `null` if the Request should proceed to the app.
     */
    public function preprocessRequest(Request $request): ?Response;
}
