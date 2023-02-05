<?php

namespace Bead\Contracts\Database;

interface Connection
{
    public function prepare(string $sql): Statement;

    public function lastInsertId(): string|bool|null;
}
