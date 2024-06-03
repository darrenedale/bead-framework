<?php

declare(strict_types=1);

namespace BeadTests\Framework\Constraints;

use PHPUnit\Framework\Constraint\Constraint;

/**
 * Constraint to ensure that a value is a readable stream resource whose content matches a string.
 */
class StreamContentEquals extends Constraint
{
    /** @var string The content the stream must contain. */
    private string $content;

    /**
     * Initialise an instance of the constraint with some content to match against.
     *
     * @param string $content The content the stream must have.
     */
    public function __construct(string $content)
    {
        $this->content = $content;
    }

    /**
     * Check whether a value satisfies the constraint.
     *
     * @param mixed $other The value to test against the constraint.
     */
    public function matches(mixed $other): bool
    {
        if (!is_resource($other)) {
            return false;
        }

        if ("stream" !== get_resource_type($other)) {
            return false;
        }

        if (0 !== fseek($other, 0, SEEK_SET)) {
            return false;
        }

        $pos = 0;

        while (!feof($other)) {
            $otherContent = fread($other, 1024);

            if (false === $otherContent) {
                return false;
            }

            $bytes = strlen($otherContent);

            if (substr($this->content, $pos, $bytes) !== $otherContent) {
                return false;
            }

            $pos += $bytes;

            if ($pos >= strlen($this->content)) {
                break;
            }
        }

        return feof($other);
    }

    /** Fetch a description of the constraint. */
    public function toString(): string
    {
        return "is a readable stream whose content is \"{$this->content}\"";
    }
}
