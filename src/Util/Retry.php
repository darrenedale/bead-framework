<?php

namespace Equit\Util;

/**
 * Call some code a given number of times. optionally until the result passes a callback.
 *
 * This is useful for things like attempting to come up with a unique filename for a directory, where you need to retry
 * the same piece of code until a given condition is reached, up to a maximum number of times.
 *
 * Example:
 *
 * $fileName = ((new Retry(fn() => uniquid()))
 *                 ->times(5)
 *                 ->until(fn(string $name) => !file_exists($name)))
 *                 ();
 *
 * If $fileName is null, an unused filename could not be found after 5 attempts; otherwise it's the first name that didn't
 * already exist. 
 */
final class Retry
{
    /** @var int How many times to retry before giving up. */
    private int $repetitions = 1;

    /** @var int|null How many attempts the last retry took, `null` if the current setup hasn't been attempted. */
    private ?int $attemptsTaken = null;
 
    /** @var callable The callable encapsulating the code to be repeated. */
    private $fn;

    /** @var ?callable A closure to determine when the result is acceptable. */
    private $exitCondition = null;

    /**
     * Initialise a new retry with a callable.
     */
    public function __construct(callable $fn)
    {
        $this->fn = $fn;
    }

    /**
     * Fluently set how many times to execute the code.
     */
    public function times(int $times): self
    {
        assert(0 < $times);
        $this->repetitions = $times;
        $this->attemptsTaken = null;
        return $this;
    }

    /**
     * Fluently set a callback to deermine whether the code needs to continue retrying.
     *
     * The callback will be called after each attempt. If it returns `true` no more attempts will be made and the result of the last
     * attempt will be returned.
     *
     * @return self The Retry instance for further method chaining.
     */
    public function until(callable $exitCondition): self
    {
        $this->exitCondition = $exitCondition;
        return $this;
    }


    /**
     * Start executing the code.
     *
     * @return The value returned from the last attempt, or null if no attempts passed the exit callback. If no exit callback is set,
     * the value of the last attempt is returned.
     */
    public function __invoke(...$args): mixed
    {
        for ($this->attemptsTaken = 0; $this->attemptsTaken < $this->repetitions; ++$this->attemptsTaken) {
            $result = ($this->fn)(...$args);

            if (null !== $this->exitCondition && ($this->exitCondition)($result)) {
                echo "took {$this->attemptsTaken} retries\n";
                return $result;
            }
        }

        return (null === $this->exitCondition) ? $result : null;
    }

    /**
     * How many attempts did the last retry of the current setup take?
     */
    public function attempts(): ?int
    {
        return $this->attemptsTaken;
    }

    /**
     * Cehck whether the last retry succeeded.
     *
     * @return `true` if the last retry succeeded (or no success vallback was set), `false` if the retry hasn't run or did not pass the success
     * callback.
     */
    public function succeeded(): bool
    {
        return null !== $this->attemptsTaken && $this->attemptsTaken < $this->repetitions;
    }
}
