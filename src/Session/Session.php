<?php

namespace Equit\Session;

use Equit\Exceptions\ExpiredSessionIdUsedException;
use Equit\Exceptions\SessionExpiredException;
use Equit\Exceptions\SessionNotFoundException;
use Equit\WebApplication;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use TypeError;
use function Equit\Traversable\all;

/**
 * Class encapsulating session data.
 *
 * This class replaces PHP's native sessions with an implementation that contains better security features and is more
 * flexible. It supports different storage backends, auto-expiration of data, automatic session ID regeneration,
 * automatic idle session expiry, multiple concurrent sessions (if you really need to). Backends to use PHP's native
 * sessions (`PhpSessionHandler`) and the local filesystem (`FileSessionHandler`) are included with Bead.
 */
class Session implements DataAccessor
{
    use CanPrefix;
    use ImplementsArrayAccess;

    /** @var string The session cookie name. */
    public const CookieName = "BeadSession";

    /** @var int The default number of seconds after which the session ID is regenerated. */
    public const DefaultSessionRegenerationPeriod = 900;

    /** @var int The default number of seconds after which a session ID that has been regenerated will redirect to the new ID. */
    public const DefaultExpiryGracePeriod = 180;

    /** @var int The default number of seconds after which an unused session is considered dead. */
    public const DefaultSessionIdleTimeoutPeriod = 1800;

    /** @var Handler The session handler. */
    private Handler $m_handler;

    /** @var array<string, int> Stores the keys in the session that are transient, and how many more requests they will
     * last before being removed. */
    private array $m_transientKeys = [];

    /** @var array<string,class-string> The known session handler types. */
    private static array $m_handlerClasses = [
        "file" => FileHandler::class,
        "php" => PhpHandler::class,
    ];

    /**
     * @throws SessionNotFoundException
     * @throws ExpiredSessionIdUsedException
     * @throws SessionExpiredException
     */
    public function __construct(?string $id = null)
    {
        $this->m_handler = self::createHandler($id);

        if ($this->handler()->idHasExpired()) {
            if ($this->handler()->idExpiredAt() < time() - self::expiredSessionGracePeriod()) {
                // expired and beyond the grace period
                $this->destroy();
                throw new ExpiredSessionIdUsedException($id, "The provided session ID is not valid.");
            } else {
                // expired and within the grace period, promote to the regenerated ID for the old session ID
                $this->m_handler = self::createHandler($this->handler()->replacementId());
            }
        } else if ($this->lastUsedAt() < time() - self::sessionIdleTimeoutPeriod()) {
            // not used for too long
            $this->destroy();
            throw new SessionExpiredException($id, "The session with the provided ID has been unused for more than " . self::sessionIdleTimeoutPeriod() . " seconds.");
        } else if ($this->handler()->idGeneratedAt() < time() - self::sessionIdRegenerationPeriod()) {
            // due to expire but not so old that we don't trust it
            $this->regenerateId();
        }

        setcookie(Session::CookieName, $this->id());
    }

    /**
     * Clean up after the Session object.
     *
     * The transient session data is removed, if it's time, and the session is committed.
     */
    public function __destruct()
    {
        $this->purgeTransientData();
        $this->handler()->commit();
    }

    /**
     * How long, in seconds, does a session ID last.
     *
     * After this many seconds a session will be issued with a fresh ID to help mitigate session stealing.
     *
     * @return int The number of seconds.
     */
    public static function sessionIdleTimeoutPeriod(): int
    {
        return WebApplication::instance()->config("session.idle-timeout-period", self::DefaultSessionIdleTimeoutPeriod);
    }

    /**
     * How long, in seconds, does a session ID last.
     *
     * After this many seconds a session will be issued with a fresh ID to help mitigate session stealing.
     *
     * @return int The number of seconds.
     */
    public static function sessionIdRegenerationPeriod(): int
    {
        return WebApplication::instance()->config("session.id-regeneration-period", self::DefaultSessionRegenerationPeriod);
    }

    /**
     * How long, in seconds, after a session ID has been retired can it be used to update to the re-issued session ID.
     *
     * Sometimes users have concurrent instances of the same session (e.g. different browser tabs); sometimes users are
     * on unreliable networks. This grace period allows an old session ID to be used, and will be replaced with the
     * re-issued session ID, for a short period of time after the old ID has been retired.
     *
     * @return int The number of seconds.
     */
    public static function expiredSessionGracePeriod(): int
    {
        return WebApplication::instance()->config("session.expired.grace-period", self::DefaultExpiryGracePeriod);
    }

    /**
     * Fetch the session ID.
     *
     * @return string The ID.
     */
    public function id(): string
    {
        return $this->handler()->id();
    }

    /**
     * Fetch the session handler.
     *
     * @return Handler The handler.
     */
    public function handler(): Handler
    {
        return $this->m_handler;
    }

    /**
     * Fetch the timestamp when the session ID was created.
     *
     * @return int The timestamp.
     */
    public function createdAt(): int
    {
        return $this->handler()->createdAt();
    }

    /**
     * The time at which the session was last used.
     * @return int The timestamp.
     */
    public function lastUsedAt(): int
    {
        return $this->handler()->lastUsedAt();
    }

    /**
     * Check whether a key is set in the session.
     *
     * @param string $key The key
     *
     * @return bool `true` if the session has data for the key, `false` otherwise.
     */
    public function has(string $key): bool
    {
        return !is_null($this->handler()->get($key));
    }

    /**
     * Fetch the data for a key in the session.
     * @param string $key The key of the data to fetch.
     * @param mixed $default The default value, if any, to return if the key is not set. Defaults to `null`.
     *
     * @return mixed|null The value.
     */
    public function get(string $key, $default = null)
    {
        return $this->handler()->get($key) ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function extract($keys)
    {
        if (is_string($keys)) {
            $data = $this->get($keys);
            $this->remove($keys);
            return $data;
        }

        if (!is_array($keys)) {
            throw new TypeError("Parameter \$keys expects a string or array of strings, " . gettype($keys) . " given.");
        }

        if (!all($keys, "is_string")) {
            throw new InvalidArgumentException("Keys for session data must be strings.");
        }

        $data = [];

        foreach ($keys as $key) {
            $value = $this->get($key);

            if (!isset($value)) {
                continue;
            }

            $data[$key] = $value;
            $this->remove($key);
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->handler()->all();
    }

    /**
     * Set one or more values in the session.
     *
     * @param string|array<string, mixed> $keyOrData The key to set, or an array of key-value pairs to set.
     * @param mixed|null $data The data to set if `$keyOrData` is a string key. Ignored otherwise.
     */
    public function set($keyOrData, $data = null): void
    {
        if (is_string($keyOrData)) {
            $this->handler()->set($keyOrData, $data);
            return;
        }

        if (!is_array($keyOrData)) {
            throw new InvalidArgumentException("set() expects a key and value or an array of keys and values.");
        }

        if (!all(array_keys($keyOrData), "is_string")) {
            throw new InvalidArgumentException("Keys for session data must be strings.");
        }

        foreach ($keyOrData as $key => $data) {
            $this->handler()->set($key, $data);
        }
    }

    /**
     * Set one or more values in the session.
     *
     * The value is set for the next request only, after which it is automatically removed.
     *
     * @param string|array<string, mixed> $keyOrData The key to set, or an array of key-value pairs to set.
     * @param mixed|null $data The data to set if `$keyOrData` is a string key. Ignored otherwise.
     */
    public function transientSet($keyOrData, $data = null): void
    {
        if (is_string($keyOrData)) {
            $this->handler()->set($keyOrData, $data);
            $this->m_transientKeys[$keyOrData] = 1;
            return;
        }

        if (!is_array($keyOrData)) {
            throw new InvalidArgumentException("transientSet() expects a key and value or an array of keys and values.");
        }

        if (!all(array_keys($keyOrData), "is_string")) {
            throw new InvalidArgumentException("Keys for session data must be strings.");
        }

        foreach ($keyOrData as $key => $data) {
            $this->handler()->set($key, $data);
            $this->m_transientKeys[$key] = 1;
        }
    }

    /**
     * Purge the transient data that's due to expire.
     *
     * You probably never want to call this yourself. It is called automatically in the destructor so you can safely
     * leave the WebApplication manage the session's lifecycle for you.
     */
    public function purgeTransientData(): void
    {
        $remove = array_filter($this->m_transientKeys, function(int $count): bool {
            return 0 >= $count;
        });

        $this->remove($remove);

        $this->m_transientKeys = array_filter($this->m_transientKeys, function(string $key) use ($remove): bool {
            return !in_array($key, $remove);
        });

        foreach ($this->m_transientKeys as &$count) {
            --$count;
        }
    }

    /**
     * Refresh the transient data so that any that is due to expire won't until the next request.
     */
    public function refreshTransientData(): void
    {
        foreach ($this->m_transientKeys as &$count) {
            if (0 >= $count) {
                $count = 1;
            }
        }
    }

    /**
     * Remove one or more keys from the session data.
     *
     * @param array<string>|string $keys The key or keys to remove.
     */
    public function remove($keys): void
    {
        if (is_string($keys)) {
            $keys = [$keys];
        } else if (!is_array($keys)) {
            throw new InvalidArgumentException("remove() expects a key or an array of keys.");
        } else if (!all($keys, "is_string")) {
            throw new InvalidArgumentException("Keys for session data to remove must be strings.");
        }
        
        foreach ($keys as $key) {
            $this->handler()->remove($key);
        }
    }

    /**
     * Clear the session data.
     */
    public function clear(): void
    {
        $this->handler()->clear();
    }

    /**
     * Commit the session data to permanent storage.
     *
     * The actual mechanism used to store the session data is determined by the handler in use.
     */
    public function commit(): void
    {
        $this->handler()->commit();
    }

    /**
     * Replace the session ID with a freshly-generated one.
     *
     * The data in the session is unmodified. You should regenerate the ID when a user logs on or logs off. This is also
     * used internally when the session's ID has been active for period of time specified in the configuration file
     * (by default 15 minutes) as a security measure.
     */
    public function regenerateId(): void
    {
        $this->handler()->regenerateId();
        setcookie(Session::CookieName, $this->id());
    }

    /**
     * Destroy the session.
     *
     * After calling destroy, nothing else can be done with the session, and you should discard the Session instance.
     *
     * Do not call this method on the session managed by the Session facade.
     */
    public function destroy(): void
    {
        $this->handler()->destroy();
    }

    /**
     * Helper to create the SessionHandler backing sessions.
     *
     * The type of handler is determined by the session config file.
     *
     * @throws SessionNotFoundException if the session handler for the identified session can't be created.
     * @throws Exception if the session handler specified in the configuration file is not recognised.
     */
    protected static function createHandler(?string $id): Handler
    {
        $type = WebApplication::instance()->config("session.handler", "file");
        $class = self::$m_handlerClasses[$type] ?? null;

        if (!isset($class)) {
            throw new Exception("Session handler '{$type}' configured in session config file is not recognised.");
        }

        try {
            return new $class($id);
        } catch (Exception $err) {
            throw new SessionNotFoundException($id ?? "", "Exception creating {$type} session handler ({$class}).", 0, $err);
        }
    }

    public function push(string $key, $data): void
    {
        $this->pushAll($key, [$data]);
    }

    public function pushAll(string $key, array $data): void
    {
        $arr = $this->get($key, []);

        if (!is_array($arr)) {
            throw new RuntimeException("The session key '{$key}' does not contain an array.");
        }

        $arr = array_merge($arr, $data);
        $this->set($key, $arr);
    }

    public function pop(string $key, int $n = 1)
    {
        $arr = $this->get($key);

        if (!is_array($arr)) {
            throw new RuntimeException("The session key '{$key}' does not contain an array.");
        }

        $value = array_pop($arr);
        $this->set($key, $arr);
        return $value;
    }
}