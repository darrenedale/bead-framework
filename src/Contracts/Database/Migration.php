<?php

declare(strict_types=1);

namespace Bead\Contracts\Database;

use Bead\Database\Connection;

/** Database migrations must all implement this interface. */
interface Migration
{
    /** A description of what the migration does. */
    public function description(): string;

    /** Apply the migration's changes to the db. */
    public function up(Connection $connection): void;

    /** Revert the migration's changes from the db. */
    public function down(Connection $connection): void;
}
