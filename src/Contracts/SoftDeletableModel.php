<?php

namespace Equit\Contracts;

/**
 * Contract for models that can be soft-deleted.
 */
interface SoftDeletableModel
{
    /**
     * Indicates whether the model has been soft-deleted.
     *
     * @return bool `true` if the model is soft-deleted, `false` otherwise.
     */
    public function isDeleted(): bool;

    /**
     * The name of the model property that contains the soft-deletion timestamp.
     *
     * @return string The property name.
     */
    public static function deletedTimestampPropertyName(): string;

    /**
     * Whether deleted models are currently being included in model queries.
     *
     * The default state should usually be that soft-deleted models are not included.
     *
     * @return bool `true` if soft-deleted models will be included in query results, `false` otherwise.
     */
    public static function deletedModelsIncluded(): bool;

    /**
     * Set the model class to include soft-deleted models in query results.
     */
    public static function includeDeletedModels(): void;

    /**
     * Set the model class to exclude soft-deleted models from query results.
     */
    public static function excludeDeletedModels(): void;
}
