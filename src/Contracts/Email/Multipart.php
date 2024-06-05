<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

use Bead\Contracts\Email\Part as PartContract;

/**
 * Interface for messages and parts that are composed of multiple parts.
 */
interface Multipart
{
    /** @return PartContract[] The parts that belong to the parent. */
    public function parts(): array;
}
