<?php
declare(strict_types=1);

namespace Bead\Exceptions;

use BeadTests\Exceptions\Throwable;

/**
 * Thrown when something in a config file doesn't make sense.
 */
class InvalidConfigurationException extends \RuntimeException
{
    /** @var string The configuration key that is not valid. */
    private string $key;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $key The configuration key that's causing the issue.
     * @param string $message The issue. Optional, but strongly recommended.
     * @param int $code Optional code. Default is 0.
     * @param Throwable|null $previous The previous exception, if any. Default is `null`.
     */
    public function __construct(string $key, string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->key = $key;
    }

    /** @return string The configuration key causing problems. */
    public function getKey(): string
    {
        return $this->key;
    }
}
