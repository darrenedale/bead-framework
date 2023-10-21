<?php

namespace Bead\Database;

use PDO;

/**
 * Model relation that links many related models to a single local model.
 *
 * This is often a "has" relation.
 */
class OneToMany extends Relation
{
    /** @var array|null The related models. */
    protected ?array $relatedModels;

    /**
     * @inheritDoc
     */
    public function reload(): void
    {
        $this->relatedModels = $this->relatedModel()::query($this->relatedKey(), $this->localModel()->{$this->localKey()});
    }

    /**
     * @inheritDoc
     */
    public function relatedModels(): array
    {
        if (!isset($this->relatedModels)) {
            $this->reload();
        }

        return $this->relatedModels;
    }
}
