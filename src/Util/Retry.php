<?php

namespace Bead\Util;

use InvalidArgumentException;

/**
 * Call some code a given number of times, optionally until the result passes a callback.
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
 * If `$fileName` is `null`, an unused filename could not be found after 5 attempts; otherwise it's the first name that
 * didn't already exist.
 */
final class Retry
{
    /** @var int How many times to retry before giving up. */
    private int $m_maxRetries = 1;

    /** @var int|null How many attempts the last retry took, `null` if the current setup hasn't been attempted. */
    private ?int $m_attemptsTaken = null;

    /** @var bool Whether the last retry succeeded, `false` if the current setup hasn't been attempted. */
    private bool $m_succeeded = false;

    /** @var callable The callable encapsulating the code to be repeated. */
    private $m_retry;

    /** @var ?callable A closure to determine when the result is acceptable. */
    private $m_exitCondition = null;

    /**
     * Initialise a new retry with a callable.
     */
    public function __construct(callable $fn)
    {
		$this->setRetry($fn);
    }

    /**
     * Fluently set how many times to execute the code.
	 *
	 * This method must not be called from within the callable being retried.
     */
    public function times(int $times): self
    {
		$this->setMaxRetries($times);
        return $this;
    }

    /**
     * Fluently set a callback to determine whether the code needs to continue retrying.
     *
     * The callback will be called after each attempt. If it returns `true` no more attempts will be made and the result of the last
     * attempt will be returned.
	 *
	 * This method must not be called from within the callable being retried.
	 *
	 * @param callable $exitCondition The callable that determines whether the retry loop can exit.
     *
     * @return self The Retry instance for further method chaining.
     */
    public function until(callable $exitCondition): self
    {
		$this->setExitCondition($exitCondition);
        return $this;
    }


    /**
     * Start executing the code.
     *
     * @return mixed The value returned from the last attempt, or null if no attempts passed the exit callback. If no exit callback is set,
     * the value of the last attempt is returned.
     */
    public function __invoke(...$args)
    {
		$this->m_attemptsTaken = 0;

        for ($attempt = 0; $attempt < $this->m_maxRetries; ++$attempt) {
			++$this->m_attemptsTaken;
            $result = ($this->m_retry)(...$args);

            if (null !== $this->m_exitCondition && true === ($this->m_exitCondition)($result)) {
				$this->m_succeeded = true;
                return $result;
            }
        }

        return (null === $this->m_exitCondition) ? $result : null;
    }

	/**
	 * Set how many times to execute the code.
	 *
	 * This method must not be called from within the callable being retried.
	 *
	 * @param int $retries The maximum number of retries.
	 */
	public function setMaxRetries(int $retries): void
	{
		if (1 > $retries) {
			throw new InvalidArgumentException("Can't retry fewer than 1 time.");
		}

		$this->m_maxRetries = $retries;
		$this->m_attemptsTaken = null;
		$this->m_succeeded = false;
	}

	/**
	 * The maximum number of retries that will be attampted.
	 *
	 * @return int The max retries.
	 */
	public function maxRetries(): int
	{
		return $this->m_maxRetries;
	}

	/**
	 * Set the callable to retry.
	 *
	 * This method must not be called from within the callable being retried.
	 *
	 * @param $retry callable The callable to retry.
	 */
	public function setRetry(callable $retry): void
	{
		$this->m_attemptsTaken = null;
		$this->m_retry = $retry;
		$this->m_succeeded = false;
	}

	/**
	 * Fetch the callable to retry.
	 *
	 * @return callable The callable.
	 */
	public function retry(): callable
	{
		return $this->m_retry;
	}

	/**
	 * Set a callback to determine whether the code needs to continue retrying.
	 *
	 * The callback will be called after each attempt. If it returns `true` no more attempts will be made and the result of the last
	 * attempt will be returned. Set the exit condition to `null` to remove it, in which case the maximum number of
	 * retries will always be made when invoked.
	 *
	 * This method must not be called from within the callable being retried.
	 *
	 * @param callable|null $exitCondition The callable that determines whether the retry loop can exit.
	 */
	public function setExitCondition(?callable $exitCondition): void
	{
		$this->m_exitCondition = $exitCondition;
		$this->m_attemptsTaken = null;
		$this->m_succeeded = false;
	}

	/**
	 * Fetch the callback that checks whether the retry loop can exit.
	 *
	 * @return callable|null The callable, or `null` if there is no exit condition set.
	 */
	public function exitCondition(): ?callable
	{
		return $this->m_exitCondition;
	}

    /**
     * How many attempts did the last retry of the current setup take?
	 *
	 * This is reset if the max retries or callable is changed.
     */
    public function attemptsTaken(): ?int
    {
        return $this->m_attemptsTaken;
    }

    /**
     * Cehck whether the last time the retry was invoked was successful.
     *
     * @return `true` if the last retry succeeded (or no success vallback was set), `false` if the retry hasn't run or did not pass the success
     * callback.
     */
    public function succeeded(): bool
    {
        return $this->m_succeeded;
    }
}
