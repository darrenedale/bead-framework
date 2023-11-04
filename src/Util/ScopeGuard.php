<?php

namespace Bead\Util;

use Closure;

/**
 * Invoke a closure when the object goes out of scope.
 *
 * This is a utility class that can be used for cleanup in cases where a function is not guaranteed to return or where
 * there are mutliple exit points. Instances will invoke the provided closure(s) when they go out of scope, giving you
 * the chance to encapsulate your cleanup code in a single place (the closure) and ensure it's called when the current
 * scope (the function/method) is exited.
 *
 * This is particularly useful in tests where assertion failures abort tests potentially before the code has had a
 * chance to clean up resources (e.g. webdriver automation sessions). You can, of course, use setup and teardown methods
 * but this requires your test method's variables that require cleaning up to be class members. With many test methods
 * you class members could get confusing. Using a scope guard, you can keep your variables scoped to the method where
 * they belong while still ensuring the cleanup you might puth in a teardown method is performed.
 */
class ScopeGuard
{
    /** @var array The closures to invoke when the guard goes out of scope. */
    private array $m_closures = [];

    /** @var bool Whether the guard is currently enabled. */
    private bool $m_enabled = true;

    /**
     * Initialise a guard with a single closure.
     *
     * @param Closure $closure The closure to invoke when the object goes out of scope.
     */
    public function __construct(Closure $closure)
    {
        $this->addClosure($closure);
    }

    /**
     * The destructor.
     */
    public function __destruct()
    {
        $this->invoke();
    }

    /**
     * Add a closure to be invoked when the guard goes out of scope.
     *
     * @param Closure $closure The closure to add.
     */
    public function addClosure(Closure $closure): void
    {
        $this->m_closures[] = $closure;
    }

    /**
     * Fetch the closures that will be invoked when the guard goes out of scope.
     *
     * @return array The closures.
     */
    protected function closures(): array
    {
        return $this->m_closures;
    }

    /**
     * Invoke the closures, if the guard is enabled.
     */
    public function invoke(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        foreach ($this->closures() as $closure) {
            $closure();
        }
    }

    /**
     * Cancel the guard.
     *
     * Once cancelled, the closures will not be invoked when the guard goes out of scope. You can re-enable the guard
     * by subsequently calling enable().
     */
    public function cancel(): void
    {
        $this->m_enabled = false;
    }

    /**
     * Enable the guard.
     *
     * You can re-enable a cancelled guard by calling this method.
     */
    public function enable(): void
    {
        $this->m_enabled = true;
    }

    /**
     * Fetch whether the guard is enabled.
     *
     * @return bool `true` if the closures will be called when the guard goes out of scope, `false` otherwise.
     */
    public function isEnabled(): bool
    {
        return $this->m_enabled;
    }
}
