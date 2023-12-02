<?php

namespace BeadTests\smoke\app\Web\Preprocessors;

use Bead\Contracts\RequestPreprocessor;
use Bead\Contracts\Response;
use Bead\Web\Request;

class AddRequestTimestamp implements RequestPreprocessor
{
    public function preprocessRequest(Request $request): ?Response
    {
        $request->setPostData("bead-request-timestamp", (string) time());
        return null;
    }
}