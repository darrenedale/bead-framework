<?php

namespace Bead\Util;

use RuntimeException;

/**
 * Ensure a process always takes at least a certain amount of time.
 *
 * Starts a timer, and when join() is called ensures that it doesn't return until at least the requested number of
 * microseconds has passed. Use this to ensure that processes which would ordinarily take variable amounts of time based
 * on the path through the code, take roughly constant time regardless of the path taken through the code. This helps to
 * mitigate timing attacks.
 *
 * This is only usable on 64-bit platforms that provide a high-resolution timer.
 */
class ConstantTime
{
    /** @var int When the ConstantTime started measuring. */
    private int $startTimestamp;

    /** @var int The requested constant-time duration, in microseconds. */
    private int $requestedMicroseconds;

    /**
     * Start the process that's required to take a constant time of a given number of microseconds.
     *
     * @throws RuntimeException if the PHP's int type on the current hardware platform is less than 64-bits wide or a
     * high-resolution timer is not available.
     */
    public function __construct(int $requestedMicroseconds)
    {
        if (8 > PHP_INT_SIZE) {
            throw new RuntimeException("The ConstantTime class only functions on 64-bit (or larger) platforms");
        }

        $timestamp = hrtime(true);

        if (false === $timestamp) {
            throw new RuntimeException("High resolution time is not available");
        }

        $this->startTimestamp = (int) ceil($timestamp / 1000);
        $this->requestedMicroseconds = $requestedMicroseconds;
    }

    /** The destructor invokes join(). */
    public function __destruct()
    {
        $this->join();
    }

    /**
     * Unless the ConstantTime was cancelled or previously joined, sleep for a sufficient duration to ensure the
     * requested number of microseconds has passed since inception.
     *
     * Usually you won't want to call this manually, just let the destructor do it when the ConstantTime goes out of
     * scope in the function/method that implements your constant-time process.
     */
    public function join(): void
    {
        $sleepDuration = $this->requestedMicroseconds - ((int) ceil(hrtime(true) / 1000) - $this->startTimestamp);

        if (0 < $sleepDuration) {
            usleep($sleepDuration);
        }

        $this->requestedMicroseconds = 0;
    }

    /** Cancel the constant time. */
    public function cancel(): void
    {
        $this->requestedMicroseconds = 0;
    }
}
