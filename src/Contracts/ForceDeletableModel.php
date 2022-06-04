<?php

namespace Equit\Contracts;

/**
 * Contract for models that can be force-deleted.
 *
 * This is most useful in tandem with SoftDeletableModel classes to provide a consistent means through which actual
 * deletion can also take place.
 */
interface ForceDeletableModel
{
    /**
     * Delete the model from the database, regardless of whether it is a soft-deletable model.
     *
     * @return bool `true` if the model was actually deleted, `false` otherwise.
     */
    public function forceDelete(): bool;
}
