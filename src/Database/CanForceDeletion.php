<?php

namespace Equit\Database;

use PDO;

/**
 * Endow a Model class with a forceDelete() method that forcibly deletes the model from the database.
 */
trait CanForceDeletion
{
    /**
     * Fetch the connection to use when soft-deleting/restoring the model.
     *
     * This is a constraint to ensure the trait can only successfully be applied to Model (or Model-like) classes.
     *
     * @return PDO The connection to use when soft-deleting/restoring the model.
     */
    public abstract function connection(): PDO;

    /**
     * Fetch the table to use when soft-deleting/restoring the model.
     *
     * This is a constraint to ensure the trait can only successfully be applied to Model (or Model-like) classes.
     *
     * @return string The database table.
     */
    public abstract static function table(): string;

    /**
     * Fetch the primary key column to use when soft-deleting/restoring the model.
     *
     * This is a constraint to ensure the trait can only successfully be applied to Model (or Model-like) classes.
     *
     * @return string The primary key column.
     */
    public abstract static function primaryKey(): string;

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
        return $this->connection()
            ->prepare("DELETE FROM `" . static::table() . "` AS `t` WHERE `t`.`" . static::primaryKey() . "` = ? LIMIT 1")
            ->execute($this->{static::primaryKey()});
    }
}
