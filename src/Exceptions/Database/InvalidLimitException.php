<?php

namespace Equit\Exceptions\Database;

use Throwable;

/**
 * Exception thrown when the limit size of a query builder is not valid.
 */
class InvalidLimitException extends QueryBuilderException
{
    /** @var int The invalid limit. */
    private int $m_limit;

    /**
     * @param int $limit The invalid limit.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous throwable. Defaults to null.
     */
    public function __construct(int $limit, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_limit = $limit;
    }

    /**
     * Fetch the invalid limit.
     * @return int The limit.
     */
    public function getLimit(): int
    {
        return $this->m_limit;
    }
}
