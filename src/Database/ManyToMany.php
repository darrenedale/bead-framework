<?php

namespace Equit\Database;

use PDO;

/**
 * A model relation that links many models of two related types in arbitrary configurations through a pivot table.
 */
class ManyToMany extends Relation
{
    /** @var class-string The model class that contains the pivot records linking the local and related models. */
    protected string $pivotModel;

    /** @var string The property in the pivot table that links to the local model. */
    protected string $pivotLocalKey;

    /** @var string The property in the pivot table that links to the related model. */
    protected string $pivotRelatedKey;

    /** @var array|null The cahced related models. */
    protected ?array $relatedModels;

    /**
     * Initialise a new instance of the relation.
     *
     * @param Model $model The model that has the relation.
     * @param string $relatedModel The model class of the related models.
     * @param string $pivotModel The model class of the pivot table.
     * @param string $pivotLocalKey
     * @param string $pivotRelatedKey
     * @param string|null $localKey
     * @param string|null $relatedKey
     */
    public function __construct(Model $model, string $relatedModel, string $pivotModel, string $pivotLocalKey, string $pivotRelatedKey, ?string $localKey = null, ?string $relatedKey = null)
    {
        parent::__construct($model, $relatedModel, $relatedKey, $localKey);
        $this->pivotModel = $pivotModel;
        $this->pivotLocalKey = $pivotLocalKey;
        $this->pivotRelatedKey = $pivotRelatedKey;
    }

    /**
     * Fetch the class name of the model used to link the local and related models.
     *
     * @return string The model class name.
     */
    public function pivotModel(): string
    {
        return $this->pivotModel;
    }

    /**
     * Fetch the name of the column in the pivot table that links to the local model.
     *
     * @return string The column name.
     */
    public function pivotLocalKey(): string
    {
        return $this->pivotLocalKey;
    }

    /**
     * Fetch the name of the column in the pivot table that links to the related model.
     *
     * @return string The column name.
     */
    public function pivotRelatedKey(): string
    {
        return $this->pivotLocalKey;
    }

    /**
     * @inheritDoc
     */
    public function reload(): void
    {
        $relatedClass = $this->relatedModel();
        $pivotClass = $this->pivotModel();
        $stmt = $this->localModel()->connection()->prepare(" SELECT `r`.* FROM `" . $relatedClass::table() . "` AS `r`, `" . $pivotClass::table() . "` AS `p` WHERE `p`.`{$this->pivotRelatedKey()}` = `r`.`{$this->relatedKey()}` AND `p`.`{$this->pivotLocalKey()}` = ? ");
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute([$this->localModel()->{$this->localKey()}]);
        $this->relatedModels = $this->makeModelsFromQuery($stmt);
    }

    /**
     * @inheritDoc
     */
    public function relatedModels()
    {
        if (!isset($this->relatedModels)) {
            $this->reload();
        }

        return $this->relatedModels;
    }
}
