<?php

declare(strict_types=1);

namespace Bead\Models;

use Bead\Database\Model;

/**
 * A database model representing an applied migration.
 *
 * If you've used the bead-app starter your app will have an \App\Models\Migration model class that derives from this
 * class, which the migrate command works with when applying or reverting migrations. You can customise that class and
 * the app's migrate command to change how migrations work.
 */
class Migration extends Model
{
    protected static string $table = "bead_migrations";

    protected static array $properties = [
        "migration" => "string",
        "description" => "string",
        "up_at" => "datetime",
    ];
}
