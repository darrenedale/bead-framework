<?php

declare(strict_types=1);

namespace Bead\Contracts;

use Bead\Web\Request;

/** Interface for classes that can post-process Requests and their Responses. */
interface RequestPostprocessor
{
    /**
     * Post-process the Request and Response.
     *
     * @param Request $request The Request that was processed.
     * @param Response $response The Response generated by the app.
     *
     * @return Response|null A Response to replace the one generated by the app, or `null` if the app's response should
     * be left in place.
     */
    public function postprocessRequest(Request $request, Response $response): ?Response;
}
