<?php

namespace Equit\Database;

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
        $relatedClass = $this->relatedModel();
        $stmt = $this->localModel()->connection()->prepare("SELECT * FROM `" . $relatedClass::table() . "` WHERE `{$this->relatedKey()}` = ?");
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute([$this->localModel()->{$this->localKey()}]);
        $this->relatedModels = $this->makeModelsFromQuery($stmt);
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