<?php

namespace Bead\Exceptions;

use Bead\Validation\Rule;
use Throwable;

/**
 * Exception thrown when a validation rule cannot do its job.
 */
class ValidationRuleException extends \Exception
{
    /** @var Rule The rule that threw the exception. */
    private Rule $m_rule;

    /**
     * Initialise a new instance of the exception.
     *
     * @param Rule $rule The rule that's throwing the exception.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previously-thrown Throwbale. Defaults to null.
     */
    public function __construct(Rule $rule, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_rule = $rule;
    }

    /**
     * Fetch the rule that threw the exception.
     *
     * @return Rule The rule.
     */
    public function getRule(): Rule
    {
        return $this->m_rule;
    }
}
