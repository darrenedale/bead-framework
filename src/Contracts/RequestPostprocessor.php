<?php

declare(strict_types=1);

namespace Bead\Contracts;

use Bead\Web\Request;

interface RequestPostprocessor
{
    public function postprocessRequest(Request $request, Response $response): ?Response;
}
