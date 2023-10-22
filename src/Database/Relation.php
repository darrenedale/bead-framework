<?php

namespace Bead\Database;

use PDOStatement;
use ReflectionException;
use ReflectionMethod;

/**
 * Abstract base class for relations between models.
 */
abstract class Relation
{
    /** @var Model The model that owns the relation. */
    private Model $model;

    /** @var class-string The class name of the related model. */
    private string $relatedModel;

    /** @var string The property on the local model that links to the related model(s). */
    private string $localKey;

    /** @var string The property on the related model(s) that link to the owning model. */
    private string $relatedKey;

    /**
     * Initialise the base class for a model relation.
     *
     * @param Model $model The model that has related models.
     * @param class-string $relatedModel The class name of the related models.
     * @param string $relatedKey The property on the related models that link to the owning model.
     * @param string $localKey The property on the model that links to the related models.
     */
    public function __construct(Model $model, string $relatedModel, string $relatedKey, string $localKey)
    {
        $this->model = $model;
        $this->relatedModel = $relatedModel;
        $this->relatedKey = $relatedKey;
        $this->localKey = $localKey;
    }

    /**
     * The model instance that has related other models.
     *
     * @return Model The model instance.
     */
    public function localModel(): Model
    {
        return $this->model;
    }

    /**
     * The property on the local model that provides the key to link to the related model(s).
     *
     * @return string The property name.
     */
    public function localKey(): string
    {
        return $this->localKey;
    }

    /**
     * The property class of the related model.
     *
     * @return string The model class name.
     */
    public function relatedModel(): string
    {
        return $this->relatedModel;
    }

    /**
     * The property on the related model(s) that provides the key to link to the local model.
     *
     * @return string
     */
    public function relatedKey(): string
    {
        return $this->relatedKey;
    }

    /**
     * Given an executed PDO statement, create a set of related model instances.
     *
     * @param PDOStatement $stmt The PDO statement.
     *
     * @return array<Model> The created model.
     * @throws ReflectionException If the related model class is not actually a model class.
     */
    protected function makeModelsFromQuery(PDOStatement $stmt): array
    {
        $method = new ReflectionMethod($this->relatedModel, "makeModelsFromQuery");
        $method->setAccessible(true);
        return $method->invoke(null, $stmt);
    }

    /**
     * Reload the related models from the database.
     *
     * Relations should, for performance and data integrity reasons, read the models once from the database and return
     * the same set each time relatedModels() is called. This method can be called to force a reload of the related
     * models from the database.
     */
    abstract public function reload(): void;

    /**
     * Returns the models for the relation.
     *
     * If the relation is a *-to-one relation (e.g. belongs-to) a single model instance should be returned (or null if
     * the relation is not set). If the relation is a *-to-many (e.g. has) an array of models should be returned, even
     * if there are none or one.
     *
     * @return Model|array|null
     */
    abstract public function relatedModels();
}
