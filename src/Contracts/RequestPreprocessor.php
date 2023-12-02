<?php

declare(strict_types=1);

namespace Bead\Contracts;

use Bead\Web\Request;

interface RequestPreprocessor
{
    public function preprocessRequest(Request $request): ?Response;
}
