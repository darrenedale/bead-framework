<?php

namespace Bead\Facades;

use BadMethodCallException;
use Bead\Exceptions\Session\ExpiredSessionIdUsedException;
use Bead\Exceptions\Session\InvalidSessionHandlerException;
use Bead\Exceptions\Session\SessionException;
use Bead\Exceptions\Session\SessionExpiredException;
use Bead\Exceptions\Session\SessionNotFoundException;
use Bead\Session\PrefixedAccessor;
use Bead\Session\Session as BeadSession;
use Bead\Session\SessionHandler;
use Exception;
use LogicException;

/**
 * Facade for easy access to the current session.
 *
 * @method static int sessionIdleTimeoutPeriod()
 * @method static int sessionIdRegenerationPeriod()
 * @method static int expiredSessionGracePeriod()
 * @method static string id()
 * @method static SessionHandler handler()
 * @method static int createdAt()
 * @method static int lastUsedAt()
 * @method static bool has(string $key)
 * @method static mixed get(string $key, $default = null)
 * @method static array all()
 * @method static void set($keyOrData, $data = null)
 * @method static void remove($keys)
 * @method static void transientSet($keyOrData, $data = null)
 * @method static PrefixedAccessor prefixed(string $prefix)
 * @method static void pruneTransientData()
 * @method static void refreshTransientData()
 * @method static void clear()
 * @method static void commit()
 * @method static void regenerateId()
 * @method static void destroy()
 */
final class Session
{
    /** @var BeadSession|null The active session. */
    private static ?BeadSession $session = null;

    /**
     * Start the session.
     *
     * The session ID is retrieved from the session cookie. The WebApplication constructor manages the session for you,
     * including calling start(), so you should never need to call this.
     *
     * @return BeadSession The session.
     *
     * @throws LogicException if the session has already been started
     * @throws ExpiredSessionIdUsedException if the session identified by the session cookie has expired
     * @throws SessionExpiredException if the current session has expired
     * @throws SessionNotFoundException If the ID provided does not identify an existing session.
     * @throws InvalidSessionHandlerException if the session handler specified in the configuration file is not
     * recognised.
     */
    public static function start(): BeadSession
    {
        if (isset(self::$session)) {
            throw new LogicException("Session already started.");
        }

        try {
            self::$session = new BeadSession($_COOKIE[BeadSession::CookieName] ?? null);
        } catch (SessionNotFoundException $err) {
            self::$session = new BeadSession();
        } catch (SessionExpiredException $err) {
            self::$session = new BeadSession();
        }

        return self::$session;
    }

    /**
     * Fetch the session if it has been started, null if it hasn't.
     * @return Session|null The session.
     */
    public static function session(): ?BeadSession
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
