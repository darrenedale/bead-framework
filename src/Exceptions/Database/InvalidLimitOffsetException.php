<?php

namespace Equit\Exceptions\Database;

use Throwable;

/**
 * Exception thrown when the limit offset of a query builder is not valid.
 */
class InvalidLimitOffsetException extends QueryBuilderException
{
    /** @var int The invalid limit. */
    private int $m_offset;

    /**
     * @param int $offset The invalid limit offset.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous throwable. Defaults to null.
     */
    public function __construct(int $offset, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_offset = $offset;
    }

    /**
     * Fetch the invalid offset.
     * @return int The offset.
     */
    public function getOffset(): int
    {
        return $this->m_offset;
    }
}
