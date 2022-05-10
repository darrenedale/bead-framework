<?php

namespace Equit\Util;

/**
 * Convenience class to time how long parts of a program take to run.
 *
 * Create an instance, give the process it is timing a name, and call start() when it starts and stop() when it's done.
 * duration() will contain the time the process took.
 *
 * You can add listeners to be notified when start() and stop() are called. This can be handy to add automatic logging, etc.
 * Timers can be set to start and stop automatically. If set, the constructor starts the timer and the destructor stops it.
 * Timers can be reset to re-use them.
 */
class ProcessTimer
{
    // Events that can be listened for
    public const EventStart = 0;
    public const EventStop = 1;
    public const EventReset = 2;

    /**
     * Only use the listener args if you're auto-starting - in all other use-cases it's preferable to use the
     * addListener method.
     */
    public function __construct(string $processName = "", bool $auto = false, array $startListeners = [], array $stopListeners = [], array $resetListeners = [])
    {
        $this->m_auto = $auto;
        $this->setProcessName($processName);

        $addListeners = function (int $event, array $listeners) {
            foreach ($listeners as $listener) {
                if (is_callable($listener)) {
                    $this->addListener($event, $listener);
                }
            }
        };

        $addListeners(self::EventStart, $startListeners);
        $addListeners(self::EventStop, $stopListeners);
        $addListeners(self::EventReset, $resetListeners);

        if ($auto) {
            $this->start();
        }
    }

    /**
     * If the timer is in auto mode, it will be stopped and all listeners for the stop event will be
     * called.
     */
    public function __destruct()
    {
        if ($this->m_auto) {
            $this->stop();
        }
    }

    /**
     * Start the process timer.
     *
     * @return bool `true` if it was started, `false` if it was already running or has already completed.
     */
    public function start(): bool
    {
        // get time first so that we are a accurate as possible
        $time = microtime(true);

        if ($this->isRunning() || $this->isFinished()) {
            return false;
        }

        $this->m_startTime = $time;
        $this->callListeners(self::EventStart, $this->startTime());
        return true;
    }

    /**
     * Stop the process timer.
     *
     * @return float The duration if the timer was successfully stopped, `null` if it was not running.
     */
    public function stop(): ?float
    {
        // get time first so that we are as accurate as possible
        $time = microtime(true);

        if (!$this->isRunning()) {
            return null;
        }

        $this->m_endTime = $time;
        $this->callListeners(self::EventStop, $this->startTime(), $this->endTime(), $this->duration());
        return $this->duration();
    }

    /**
     * Reset the timer.
     *
     * It is safe to reset the timer while it is running. All reset listeners will be called _after_ the timer has been
     * reset, before the method returns.
     */
    public function reset(): void
    {
        $this->m_startTime = null;
        $this->m_endTime = null;
        $this->callListeners(self::EventReset);
    }

    /**
     * Check whether the timer is currently running.
     *
     * Running means the timer has been started but not yet stopped.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return !is_null($this->startTime()) && is_null($this->endTime());
    }

    /**
     * Check whether the timer has finished.
     *
     * Finished means the timer has been started and stopped but not yet reset.
     *
     * @return bool
     */
    public function isFinished(): bool
    {
        return !is_null($this->startTime()) && !is_null($this->endTime());
    }

    /**
     * Fetch the timestamp when the timer was started.
     *
     * @return float|null The start time, or `null` if the timer has not been started or has been reset.
     */
    public function startTime(): ?float
    {
        return $this->m_startTime;
    }

    /**
     * Fetch the timestamp when the timer was stopped.
     *
     * @return float|null The end time, or `null` if the timer is currently running or has been reset.
     */
    public function endTime(): ?float
    {
        return $this->m_endTime;
    }

    /**
     * Fetch the duration.
     *
     * The duration is only available after the timer has been stopped and before it has been reset.
     *
     * @return float|null The duration, or `null` if the timer is currently running or has been reset.
     */
    public function duration(): ?float
    {
        if (!$this->isFinished()) {
            return null;
        }

        return $this->endTime() - $this->startTime();
    }

    /**
     * Set the process name.
     *
     * The process name is for reference only.
     *
     * @param $name string The process name.
     */
    public function setProcessName(string $name): void
    {
        $this->m_processName = $name;
    }

    /**
     * Fetch the process name.
     *
     * @return string
     */
    public function processName(): string
    {
        return $this->m_processName;
    }

    /**
     * Add a listener for an event.
     *
     * Three events are available, which should be self-explanatory:
     * - EventStart
     * - EventStop
     * - EventReset
     *
     * All events receive the process name as the first argument. EventStart also receives the start time; EventStop also receives
     * the start and end times and duration, in that order. All times and the duration are floats.
     */
    public function addListener(int $event, callable $fn): bool
    {
        switch ($event) {
            case self::EventStart:
            case self::EventStop:
            case self::EventReset:
                $this->m_listeners[$event][] = $fn;
                return true;

            default:
                return false;
        }
    }

    /**
     * Call the registered listeners for an event.
     *
     * All events are given the process name as the first argument automatically, you should only provide other arguments
     * that should be sent to the listeners.
     *
     * @param $event int The event type.
     * @param $args mixed The arguments to send to the listener.
     */
    private function callListeners(int $event, ...$args): void
    {
        foreach ($this->m_listeners[$event] as $fn) {
            $fn($this->processName(), ...$args);
        }
    }

    /** @var string The name of the process being timed. */
    private string $m_processName = "";

    /** @var float|null The timestamp the process started. */
    private ?float $m_startTime = null;

    /** @var float|null The timestamp the process started. */
    private ?float $m_endTime = null;

    /** @var bool Whether the timer is in auto-start-stop mode. */
    private bool $m_auto;

    /** @var array The event listeners. */
    private array $m_listeners = [
        self::EventStart => [],
        self::EventStop => [],
        self::EventReset => [],
    ];
}
