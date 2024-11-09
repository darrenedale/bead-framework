<?php

namespace Bead\Database;

use PDO;

/**
 * @template T of Model
 * @template U of Model
 * @template-extends Relation<T,U>
 *
 * Model relation that links many related models to a single local model.
 *
 * This is often a "has" relation.
 */
class OneToMany extends Relation
{
    /** @var U[]|null The related models. */
    protected ?array $relatedModels;

    /**
     * @inheritdoc
     *
     * @psalm-suppress MissingThrowsDocblock we know that query() won't throw with the default operator.
     */
    public function reload(): void
    {
        $this->relatedModels = $this->relatedModel()::query($this->relatedKey(), $this->localModel()->{$this->localKey()});
    }

    /** @inheritdoc  */
    public function relatedModels(): array
    {
        if (!isset($this->relatedModels)) {
            $this->reload();
        }

        return $this->relatedModels;
    }
}
