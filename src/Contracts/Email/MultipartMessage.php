<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

use Bead\Contracts\Email\Message;

interface MultipartMessage extends Message
{
    /** @return Part[] */
    public function parts(): array;
}
