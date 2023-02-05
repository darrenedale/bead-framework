<?php

declare(strict_types=1);

namespace Bead\Contracts\Database;

interface Statement extends Traversable
{
    public function execute(): mixed;
}
