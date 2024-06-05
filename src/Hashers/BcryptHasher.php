<?php

declare(strict_types=1);

namespace Bead\Hashers;

use Bead\Contracts\Hasher;
use InvalidArgumentException;

/**
 * Hasher implementation using bcrypt (the blowfish algorithm).
 */
class BcryptHasher implements Hasher
{
    public const DefaultCost = 10;

    private int $cost;

    /**
     * Initialise a new instance of the Bcrypt hasher.
     *
     * @param int $cost The cost is expressed as the base-2 logarithm of the iteration count for the algorithm. Valid
     * values are 4-31.
     *
     * @throws InvalidArgumentException if the cost is not in the range 4-31.
     */
    public function __construct(int $cost = self::DefaultCost)
    {
        self::checkCost($cost);
        $this->cost = $cost;
    }

    /**
     * Check the validity of a cost argument.
     *
     * @throws InvalidArgumentException
     */
    private static function checkCost(int $cost): void
    {
        if (4 > $cost || 31 < $cost) {
            throw new InvalidArgumentException("Expected valid cost in the range 4-31, found {$cost}");
        }
    }

    /**
     * Fetch the algorithmic cost of the hasher.
     *
     * The cost is expressed as the base-2 logarithm of the iteration count for the algorithm. Valid values are 4-31.
     *
     * @return int The cost.
     */
    public function cost(): int
    {
        return $this->cost;
    }

    /**
     * Create a copy of the hasher with a given cost.
     *
     * @param int $cost The cost is expressed as the base-2 logarithm of the iteration count for the algorithm. Valid
     * values are 4-31.
     *
     * @throws InvalidArgumentException if the cost is not in the range 4-31.
     * @return self The hasher with the given cost.
     */
    public function withCost(int $cost): self
    {
        self::checkCost($cost);
        $clone = clone $this;
        $clone->cost = $cost;
        return $clone;
    }

    /**
     * Compute the hash of a given havelu.
     *
     * @param string $value The value to hash.
     *
     * @return string The hash.
     */
    public function hash(string $value): string
    {
        return password_hash($value, PASSWORD_BCRYPT, ["cost" => $this->cost(),]);
    }

    /**
     * Verify that a user-provided value matches a known bcrypt hash.
     *
     * @param string $value The user-provided value.
     * @param string $hash The hash to compare the user-value to.
     *
     * @return bool `true` if the user-provided value matches the hash, `faLse` if not.
     */
    public function verify(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }
}
