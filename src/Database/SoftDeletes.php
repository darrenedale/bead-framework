<?php

namespace Equit\Database;

use DateTime;
use PDO;

/**
 * Trait for Model classes that use soft-deletes.
 *
 * This provides a standard implementation of the SoftDeletableModel contract that you can include in your model classes
 * so that they support soft-deletes. In many cases importing the trait will be sufficient.
 */
trait SoftDeletes
{
    /** @var string The default column containing the deleted timestamps. */
    protected static string $deletedTimestampColumn = "deleted_at";

    /** @var bool Whether or not deleted models are currently set to be included in queries. */
    private static bool $includeSoftDeletedModels = false;

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
     * The name of the column that contains the deleted timestamp for soft-deleted records.
     *
     * @return string The column name.
     */
    public static function deletedTimestampPropertyName(): string
    {
        return static::$deletedTimestampColumn;
    }

    /**
     * Set the model type to include soft-deleted records in future queries.
     */
    public static function includeDeletedModels(): void
    {
        static::$includeSoftDeletedModels = true;
    }

    /**
     * Set the model type to exclude soft-deleted records from future queries.
     *
     * This is the default state for models that utilise this trait.
     */
    public static function excludeDeletedModels(): void
    {
        static::$includeSoftDeletedModels = true;
    }

    /**
     * Determine whether queries will include soft-deleted models in returned results.
     * @return bool `true` if soft-deleted models will be included, `false` otherwise.
     */
    public static function deletedModelsIncluded(): bool
    {
        return static::$includeSoftDeletedModels;
    }

    /**
     * Ensure soft-deleted records are excluded from queries unless the model is configured otherwise.
     *
     * @return string[]
     */
    protected static function fixedWhereExpressions(string $tableAlias = null): array
    {
        if (static::deletedModelsIncluded()) {
            return [];
        }

        return ["`" . ($tableAlias ?? static::table()) . "`.`" . static::deletedTimestampPropertyName() . "` IS NULL",];
    }

    /**
     * Override the implementation of delete() from Model with one that soft-deletes the model instead.
     *
     * @return bool `true` if the model was soft-deleted, `false` otherwise.
     */
    public function delete(): bool
    {
        $this->{static::deletedTimestampPropertyName()} = new DateTime();

        return $this->connection()
            ->prepare("UPDATE `" . static::table() . "` AS `t` SET `t`.`" . static::deletedTimestampPropertyName() . "` = ? WHERE `t`.`" . static::primaryKey() . "` = ?")
            ->execute([$this->{static::deletedTimestampPropertyName()}->format("Y-m-d H:i:s"),  $this->{static::primaryKey()},]);
    }

    /**
     * Restore the soft-deleted model.
     *
     * If the model was not soft-deleted before the call, the method returns `true`.
     *
     * @return bool `true` if the model was restored, `false` otherwise.
     */
    public function restore(): bool
    {
        $this->{static::deletedTimestampPropertyName()} = null;

        return $this->connection()
            ->prepare("UPDATE `" . static::table() . "` AS `t` SET `t`.`" . static::deletedTimestampPropertyName() . "` = NULL WHERE `t`.`" . static::primaryKey() . "` = ?")
            ->execute([$this->{static::primaryKey()},]);
    }

    /**
     * Check whether the model has been deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return !is_null($this->{static::deletedTimestampPropertyName()});
    }
}
