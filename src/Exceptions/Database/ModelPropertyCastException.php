<?php

namespace Equit\Exceptions\Database;

use Throwable;

/**
 * Exception thrown when the value for a model property could not be cast either to or from the database column type.
 */
class ModelPropertyCastException extends ModelException
{
    /** @var string The name of the model class. */
    private string $m_model;

    /** @var string The property that could not be cast. */
    private string $m_property;

    /** @var mixed The value that could not be cast. */
    private $m_value;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $model The model class whose property value could not be cast.
     * @param string $property The property whose value could not be cast.
     * @param mixed $value The value that could not be cast.
     * @param string $message The optional message, Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous throwable. Defaults to null.
     */
    public function __construct(string $model, string $property, $value, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_model = $model;
        $this->m_property = $property;
        $this->m_value = $value;
    }

    /**
     * Fetch the model that could not have its property cast.
     * @return string The model class name.
     */
    public function getModel(): string
    {
        return $this->m_model;
    }

    /**
     * Fetch the property that could not be cast.
     * @return string The property name.
     */
    public function getProperty(): string
    {
        return $this->m_property;
    }

    /**
     * Fetch the value that could not be cast.
     * @return mixed The value.
     */
    public function getValue()
    {
        return $this->m_value;
    }
}
