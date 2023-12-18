<?php

declare(strict_types=1);

namespace Bead\Hashers;

use Bead\Contracts\Hasher;
use InvalidArgumentException;

/**
 * Hasher implementation using argon.
 */
class ArgonHasher implements Hasher
{
    public const DefaultMemoryCost = PASSWORD_ARGON2_DEFAULT_MEMORY_COST;

    public const DefaultTimeCost = PASSWORD_ARGON2_DEFAULT_TIME_COST;

    private int $memoryCost;

    private int $timeCost;

    /**
     * Initialise a new instance of the Argon hasher.
     *
     * @param int $memoryCost
     * @param int $timeCost
     *
     * @throws InvalidArgumentException
     */
    public function __construct(int $memoryCost = self::DefaultMemoryCost, int $timeCost = self::DefaultTimeCost)
    {
        $this->memoryCost = $memoryCost;
        $this->timeCost = $timeCost;
    }

    /**
     * Check the validity of a memory cost argument.
     *
     * @throws InvalidArgumentException
     */
    private static function checkMemoryCost(int $memoryCost): void
    {
        if (4 > $memoryCost || 31 < $memoryCost) {
            throw new InvalidArgumentException("Expected valid memory cost, found {$memoryCost}");
        }
    }

    /**
     * Check the validity of a time cost argument.
     *
     * @throws InvalidArgumentException
     */
    private static function checkTimeCost(int $timeCost): void
    {
        if (4 > $timeCost || 31 < $timeCost) {
            throw new InvalidArgumentException("Expected valid time cost, found {$timeCost}");
        }
    }

    /**
     * Fetch the memory cost of the hasher.
     *
     * @return int The memory cost.
     */
    public function memoryCost(): int
    {
        return $this->memoryCost;
    }

    /**
     * Create a copy of the hasher with a given memory cost.
     *
     * @param int $memoryCost The memory cost.
     *
     * @throws InvalidArgumentException
     * @return self The hasher with the given memory cost.
     */
    public function withMemoryCost(int $memoryCost): self
    {
        self::checkMemoryCost($memoryCost);
        $clone = clone $this;
        $clone->memoryCost = $memoryCost;
        return $clone;
    }

    /**
     * Fetch the time cost of the hasher.
     *
     * @return int The time cost.
     */
    public function timeCost(): int
    {
        return $this->timeCost;
    }

    /**
     * Create a copy of the hasher with a given time cost.
     *
     * @param int $timeCost The time cost.
     *
     * @throws InvalidArgumentException
     * @return self The hasher with the given time cost.
     */
    public function withTimeCost(int $timeCost): self
    {
        self::checkTimeCost($timeCost);
        $clone = clone $this;
        $clone->timeCost = $timeCost;
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
        return password_hash(
            $value,
            PASSWORD_ARGON2ID,
            [
                "time_cost" => $this->timeCost(),
                "memory_cost" => $this->memoryCost(),
            ]
        );
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
