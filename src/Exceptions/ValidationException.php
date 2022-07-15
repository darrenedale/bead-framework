<?php

namespace Equit\Exceptions;

use Equit\Validation\Validator;
use Exception;
use Throwable;

/**
 * Exception class thrown when a validator fails.
 */
class ValidationException extends Exception
{
    private Validator $m_validator;

    /**
     * Initialise a new ValidationException.
     *
     * @param \Equit\Validation\Validator $validator The validator that threw the exception.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param \Throwable|null $previous An optional previous Throwable. Defaults to null.
     */
    public function __construct(Validator $validator, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_validator = $validator;
    }

    /**
     * Fetch the validator that triggered the validation exception.
     *
     * @return \Equit\Validation\Validator The validator.
     */
    public function validator(): Validator
    {
        return $this->m_validator;
    }
}
