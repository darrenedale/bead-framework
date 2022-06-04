<?php

namespace Equit\Database;

use PDO;

/**
 * Endow a Model class with a forceDelete() method that forcibly deletes the model from the database.
 *
 * These properties are type-hinted for the benefit of the IDE. They are all provided by the Model class, which should
 * always be in the inheritance chain for classes that import this trait.
 *
 * @property PDO $connection
 * @property string $table
 * @property string $primaryKey
 */
trait CanForceDeletion
{
    /**
     * Force deletion of the model.
     *
     * This can be useful for models that utilise the SoftDeletes trait to enable a means through which actual deletion
     * can be forced.
     *
     * @return bool
     */
    public function forceDelete(): bool
    {
        return $this->connection
            ->prepare("DELETE FROM `" . static::$table . "` AS `t` WHERE `t`.`" . static::$primaryKey . "` = ? LIMIT 1")
            ->execute($this->{static::$primaryKey});
    }
}
