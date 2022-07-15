<?php

namespace Equit\Database;

use PDO;

/**
 * A model relation that links many local models to a single related model.
 *
 * This is often a "belongs-to" relation.
 */
class ManyToOne extends Relation
{
    /** @var Model|null The related model. */
    protected ?Model $relatedModel;

    /**
     * @var bool Whether the related model has been fetched from the db.
     *
     * Since the model in the relation can be null, we need a flag to avoid querying the db every time the model is
     * requested from the relation if the related model is legitimately null.
     */
    private bool $fetched = false;

    /**
     * @inheritDoc
     */
    public function reload(): void
    {
		$key = $this->localModel()->{$this->localKey()};

		if (isset($key)) {
			$this->relatedModel = $this->relatedModel()::fetch($key);
		} else {
			$this->relatedModel = null;
		}

        $this->fetched = true;
    }

    /**
     * @inheritDoc
     */
    public function relatedModels(): ?Model
    {
        if (!$this->fetched) {
            $this->reload();
        }

        return $this->relatedModel;
    }
}
