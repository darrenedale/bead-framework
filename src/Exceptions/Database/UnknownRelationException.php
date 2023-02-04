<?php

namespace Bead\Exceptions\Database;

use Throwable;

/**
 * Exception thrown when a named relation does not exist on a given model.
 */
class UnknownRelationException extends ModelException
{
    /** @var string The named relation. */
    private string $m_relation;

    /** @var string The model class. */
    private string $m_model;

    /**
     * @param string $model The model class on which the unknown relation was accessed.
     * @param string $relation The name of the unknown relation.
     * @param string $message The optional message, Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous throwable. Defaults to null.
     */
    public function __construct(string $model, string $relation, $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_model = $model;
        $this->m_relation = $relation;
    }

    /**
     * Fetch the model class on which the unknown relation was accessed.
     * @return string The model class.
     */
    public function getModel(): string
    {
        return $this->m_model;
    }

    /**
     * Fetch the name of the unknown relation.
     * @return string The relation.
     */
    public function getRelation(): string
    {
        return $this->m_relation;
    }
}
