<?php
declare(strict_types=1);

namespace Bead\Contracts\Database;

interface Migration
{
    public function description(): string;

    public function up(Connection $db): void;

    public function down(Connection $db): void;
}
