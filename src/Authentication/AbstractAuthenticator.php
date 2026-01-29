<?php

namespace Bead\Authentication;

use Bead\Contracts\Authentication\Authenticator as AuthenticatorContract;
use Bead\Contracts\Authentication\Credentials as CredentialsContract;
use Bead\Contracts\Models\Authenticatable as AuthenticatableContract;
use Bead\Contracts\Web\Request as RequestContract;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Facades\Application;
use Bead\Facades\Session;
use LogicException;

/**
 * Base class that should suffice for most authenticators.
 *
 * The base class:
 * - implements authenticate() with a standard flow of "get credentials", "identify user", "verify"
 * - stores the ID of the user in a session variable
 *
 * It assumes an id property on the Authenticatable model.
 *
 * The subclass should define the $authenticatableClass with a class string indicating the model class to use as the
 * Authenticatable. It should also implement the three phases - extractCredentials(), findAuthenticatable() and
 * verifyCredentials().
 */
abstract class AbstractAuthenticator implements AuthenticatorContract
{
    /** @var int Default sign-in inactivity timeout in seconds. */
    public const DefaultTimeout = 1800;

    /** @var class-string<AuthenticatableContract> The model class the authenticator works with. */
    protected string $authenticatableClass;

    /** @var string Where in the session to store the authenticated user data. */
    protected static string $sessionKey = "current-user";

    private ?AuthenticatableContract $authenticatable = null;

    /** @inheritDoc */
    abstract public function extractCredentials(RequestContract $request): CredentialsContract;

    /** @inheritDoc */
    abstract public function findAuthenticatable(CredentialsContract $credentials): AuthenticatableContract;

    /** @inheritDoc */
    abstract public function verifyCredentials(CredentialsContract $credentials, AuthenticatableContract $authenticatable): AuthenticationResult;

    /** Get the inactive timeout, in seconds. */
    protected static function inactiveSessionTimeout(): int
    {
        $timeout = Application::config("app.authentication.timeout", self::DefaultTimeout);

        if (null === filter_var($timeout, FILTER_VALIDATE_INT, ["options" => ["min_range" => 60], "flags" => FILTER_NULL_ON_FAILURE])) {
            throw new InvalidConfigurationException("app.authentication.timeout", "Expected a valid integer number of seconds >= 60");
        }

        return (int) $timeout;
    }

    /** Update the latest-activity timestamp of the current sign-in session. */
    protected static function setLatestActivity(): void
    {
        Session::set("authenticator." . static::$sessionKey . ".latest-activity", time());
    }

    /** Fetch the latest-activity timestamp of the current sign-in session. */
    protected static function latestActivity(): int
    {
        $activity = Session::get("authenticator." . static::$sessionKey . ".latest-activity");
        assert(!is_null($activity), new LogicException("Expected latestActivity() to be called with a current sign-in session, none found"));
        return $activity;
    }

    /**
     * @template T implements Authenticatable
     *
     * Set the model class that the authenticator authenticates.
     *
     * @param class-string<T> $modelClass
     */
    public function authenticateInstancesOf(string $modelClass): void
    {
        assert(is_a($modelClass, AuthenticatableContract::class, true), new LogicException("Expected class implementing " . AuthenticatableContract::class . " contract, found {$modelClass}"));
        $this->authenticatableClass = $modelClass;
    }

    /**
     * Extracts the credentials, identifies the user and verifies the credentials.
     *
     * @param RequestContract $request
     * @return AuthenticationResult
     */
    public function authenticate(RequestContract $request): AuthenticationResult
    {
        $credentials = $this->extractCredentials($request);
        $authenticatable = $this->findAuthenticatable($credentials);
        $result = $this->verifyCredentials($credentials, $authenticatable);

        if ($result->isSuccess()) {
            $this->setCurrentlyAuthenticated($authenticatable);
        }

        return $result;
    }

    /**
     * Fetches the currently authenticated Authenticatable based on the ID stored in the session data.
     *
     * Note that the model is cached, so if you change the persistent data in the db and fetch from here again, the
     * model's data may be out of date.
     */
    public function currentlyAuthenticated(): ?AuthenticatableContract
    {
        if ($this->authenticatable instanceof AuthenticatableContract) {
            return $this->authenticatable;
        }

        $id = Session::get("authenticator." . static::$sessionKey . ".id");

        if (null === $id) {
            return null;
        }

        $this->authenticatable = ($this->authenticatableClass)::fetch($id);
        return $this->authenticatable;
    }

    /**
     * Checks whether the sign-in session has timed out.
     *
     * This method expects the caller to have checked that an Authenticatable is currently authenticated.
     *
     * If the session has timed out, the Authenticatable is de-authenticated. If not, the session is touched to keep it
     * active.
     */
    public function checkTimeout(): void
    {
        assert(null !== $this->currentlyAuthenticated(), new LogicException("Expected an authenticated Authenticatable, none found"));

        if (self::latestActivity() <= (time() - self::inactiveSessionTimeout())) {
            $this->deauthenticate();
        }

        self::setLatestActivity();
    }

    /** Replaces the currently authenticated Authenticatable (if any) with another. */
    public function setCurrentlyAuthenticated(AuthenticatableContract $authenticatable): void
    {
        Session::set("authenticator." . static::$sessionKey . ".id", $authenticatable->id);
        $this->authenticatable = $authenticatable;
        static::setLatestActivity();
    }

    /** Clears the session of all data relating to the currently authenticated Authenticatable, if there is one. */
    public function deauthenticate(): void
    {
        $this->authenticatable = null;
        $session = Session::prefixed("authenticator." . static::$sessionKey);
        $session->remove(".id");
        $session->remove(".last-access");
    }
}
