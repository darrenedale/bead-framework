<?php

namespace Bead\Util;

use LogicException;

/**
 * Convenience class to time how long parts of a program take to run.
 *
 * Create an instance, give the process it is timing a name, and call `start()` when it starts and `stop()` when it's
 * done. `duration()` will contain the time the process took.
 *
 * You can add listeners to be notified when `start()`, `stop()` and `reset()` are called. This can be handy to add
 * automatic logging, etc. Stopwatches can be set to start and stop automatically. If set, the constructor starts the
 * stopwatch and the destructor stops it. Stopwatches can be reset to re-use them.
 */
class Stopwatch
{
    /** @var int Enumerator indicating that an event listener is for when the stopwatch is started. */
    public const EventStart = 0;

    /** @var int Enumerator indicating that an event listener is for when the stopwatch is stopped. */
    public const EventStop = 1;

    /** @var int Enumerator indicating that an event listener is for when the stopwatch is reset. */
    public const EventReset = 2;

    /** @var string The name of the process being timed. */
    private string $m_processName = "";

    /** @var float|null The timestamp the process started. */
    private ?float $m_startTime = null;

    /** @var float|null The timestamp the process started. */
    private ?float $m_endTime = null;

    /** @var bool Whether the stopwatch is in auto-start-stop mode. */
    private bool $m_auto;

    /** @var array The event listeners. */
    private array $m_listeners = [
        self::EventStart => [],
        self::EventStop => [],
        self::EventReset => [],
    ];

    /**
     * Initialise a new stopwatch.
     *
     * Only use the listener args if you're auto-starting - in all other use-cases it's preferable to use the
     * addListener() method.
     *
     * @param string $processName The name of whatever it is your code is doing while the stopwatch is running.
     * @param bool $auto Whether to start the stopwatch immediately.
     * @param callable[] $startListeners The listeners for the start event. Default is empty.
     * @param callable[] $stopListeners The listeners for the stop event. Default is empty.
     * @param callable[] $resetListeners The listeners for the reset event. Default is empty.
     */
    public function __construct(string $processName = "", bool $auto = false, array $startListeners = [], array $stopListeners = [], array $resetListeners = [])
    {
        $this->m_auto = $auto;
        $this->setProcessName($processName);

        foreach (
            [
                self::EventStart => $startListeners,
                self::EventStop => $stopListeners,
                self::EventReset => $resetListeners,
            ] as $event => $listeners
        ) {
            foreach ($listeners as $listener) {
                $this->addListener($event, $listener);
            }
        }

        if ($auto) {
            $this->start();
        }
    }

    /**
     * The Stopwatch desructor.
     *
     * If the stopwatch is in auto mode, it will be stopped and all listeners for the stop event will be called.
     */
    public function __destruct()
    {
        if ($this->m_auto) {
            $this->stop();
        }
    }

    /**
     * Start the stopwatch.
     *
     * @return bool `true` if it was started, `false` if it was already running or has already completed.
     */
    public function start(): bool
    {
        // get time first so that we are as accurate as possible
        $time = microtime(true);

        if ($this->isRunning() || $this->isFinished()) {
            return false;
        }

        $this->m_startTime = $time;
        $this->callListeners(self::EventStart, $this->startTime());
        return true;
    }

    /**
     * Stop the stopwatch.
     *
     * @return float The duration if the stopwatch was successfully stopped, `null` if it was not running.
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
     * Reset the stopwatch.
     *
     * It is safe to reset the stopwatch while it is running. All reset listeners will be called _after_ the stopwatch
     * has been reset, before the method returns.
     */
    public function reset(): void
    {
        $this->m_startTime = null;
        $this->m_endTime = null;
        $this->callListeners(self::EventReset);
    }

    /**
     * Check whether the stopwatch is currently running.
     *
     * Running means the stopwatch has been started but not yet stopped.
     *
     * @return bool `true` if the stopwatch is running, `false` otherwise.
     */
    public function isRunning(): bool
    {
        return !is_null($this->startTime()) && is_null($this->endTime());
    }

    /**
     * Check whether the stopwatch has finished.
     *
     * Finished means the stopwatch has been started and stopped but not yet reset.
     *
     * @return bool `true` if the stopwatch has finished, `false` otherwise.
     */
    public function isFinished(): bool
    {
        return !is_null($this->startTime()) && !is_null($this->endTime());
    }

    /**
     * Fetch the timestamp when the stopwatch was started.
     *
     * @return float|null The start time, or `null` if the stopwatch has not been started or has been reset.
     */
    public function startTime(): ?float
    {
        return $this->m_startTime;
    }

    /**
     * Fetch the timestamp when the stopwatch was stopped.
     *
     * @return float|null The end time, or `null` if the stopwatch is currently running or has been reset.
     */
    public function endTime(): ?float
    {
        return $this->m_endTime;
    }

    /**
     * Fetch the duration.
     *
     * The duration is only available after the stopwatch has been stopped and before it has been reset.
     *
     * @return float|null The duration, or `null` if the stopwatch is currently running or has been reset.
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
     * The process name is of no intrinsic significance - it's for reference only.
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
     * @return string The process name.
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
     * All events receive the process name as the first argument. EventStart also receives the start time; EventStop
     * also receives the start and end times and duration, in that order. All times and the duration are floats.
     *
     * @param int $event The event to listen for.
     * @param callable $listener The listener.
     */
    public function addListener(int $event, callable $listener): void
    {
        switch ($event) {
            case self::EventStart:
            case self::EventStop:
            case self::EventReset:
                $this->m_listeners[$event][] = $listener;
                break;

            default:
                throw new LogicException("Invalid event enumerator {$event}.");
        }
    }

    /**
     * Convenience method to add a listener for the stopwatch starting.
     *
     * @param callable $listener The listener.
     */
    public function addStartListener(callable $listener): void
    {
        $this->addListener(self::EventStart, $listener);
    }

    /**
     * Convenience method to add a listener for the stopwatch stopping.
     *
     * @param callable $listener The listener.
     */
    public function addStopListener(callable $listener): void
    {
        $this->addListener(self::EventStop, $listener);
    }

    /**
     * Convenience method to add a listener for the stopwatch being reset.
     *
     * @param callable $listener The listener.
     */
    public function addResetListener(callable $listener): void
    {
        $this->addListener(self::EventReset, $listener);
    }

    /**
     * Call the registered listeners for an event.
     *
     * All events are given the process name as the first argument automatically, you should only provide other
     * arguments that should be sent to the listeners.
     *
     * @param $event int The event type.
     * @param $args mixed The arguments to send to the listener.
     */
    protected function callListeners(int $event, ... $args): void
    {
        foreach ($this->m_listeners[$event] as $fn) {
            $fn($this->processName(), ...$args);
        }
    }
}
