<?php

namespace Equit\Facades;

use BadMethodCallException;
use Equit\Exceptions\ExpiredSessionIdUsedException;
use Equit\Exceptions\SessionExpiredException;
use Equit\Exceptions\SessionNotFoundException;
use Equit\Session\PrefixedAccessor;
use Equit\Session\Handler;
use Exception;
use LogicException;
use Equit\Session\Session as EquitSession;

/**
 * @method static int sessionIdleTimeoutPeriod()
 * @method static int sessionIdRegenerationPeriod()
 * @method static int expiredSessionGracePeriod()
 * @method static string id()
 * @method static Handler handler()
 * @method static int createdAt()
 * @method static int lastUsedAt()
 * @method static bool has(string $key)
 * @method static mixed get(string $key, $default = null)
 * @method static array all()
 * @method static void set($keyOrData, $data = null)
 * @method static void remove($keys)
 * @method static void transientSet($keyOrData, $data = null)
 * @method static PrefixedAccessor prefixed(string $prefix)
 * @method static void purgeTransientData()
 * @method static void refreshTransientData()
 * @method static void clear()
 * @method static void commit()
 * @method static void regenerateId()
 * @method static void destroy()
 */
final class Session
{
    /** @var EquitSession|null The active session. */
    private static ?EquitSession $session = null;

    /**
     * Start the session.
     *
     * The session ID is retrieved from the session cookie. The WebApplication constructor manages the session for you,
     * including calling start(), so you should never need to call this.
     *
     * @return EquitSession The session.
     * @throws ExpiredSessionIdUsedException if the session identified by the session cookie has expired
     */
    public static function start(): EquitSession
    {
        if (isset(self::$session)) {
            throw new LogicException("Session already started.");
        }

        try {
            self::$session = new EquitSession($_COOKIE[EquitSession::CookieName] ?? null);
        } catch (SessionNotFoundException $err) {
            self::$session = new EquitSession();
        } catch (SessionExpiredException $err) {
            self::$session = new EquitSession();
        }

        return self::$session;
    }

    /**
     * Fetch the session if it has been started, null if it hasn't.
     * @return Session|null The session.
     */
    public static function session(): ?EquitSession
    {
        return self::$session;
    }

    /**
     * Forward static calls on the facade to the underlying Session object.
     *
     * @param string $method The method to forward.
     * @param array $args The method arguments.
     *
     * @return mixed
     * @throws Exception if the session has not been started.
     * @throws BadMethodCallException if the method does not exist in the Session class.
     */
    public static function __callStatic(string $method, array $args)
    {
        if (!isset(self::$session)) {
            throw new Exception("Session not started.");
        }

        if (!method_exists(self::$session, $method)) {
            throw new BadMethodCallException("The method '{$method}' does not exist in the Session class.");
        }

        return [self::$session, $method](...$args);
    }
}
