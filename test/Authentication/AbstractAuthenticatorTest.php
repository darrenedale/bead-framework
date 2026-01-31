<?php

declare(strict_types=1);

namespace BeadTests\Authentication;

use Bead\Authentication\AbstractAuthenticator;
use Bead\Authentication\AuthenticationResult;
use Bead\Authentication\AuthenticationResultCode;
use Bead\Contracts\Authentication\Credentials as CredentialsContract;
use Bead\Contracts\Models\Authenticatable as AuthenticatableContract;
use Bead\Contracts\Web\Request as RequestContract;
use Bead\Core\Application;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Facades\Session as SessionFacade;
use Bead\Session\Session;
use BeadTests\Framework\TestCase;
use Closure;
use Equit\XRay\StaticXRay;
use Equit\XRay\XRay;
use LogicException;
use Mockery;

/** @covers \Bead\Authentication\AbstractAuthenticator */
class AbstractAuthenticatorTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /** Create an instance of an anonymous class that extends AbstractAuthenticator. */
    protected static function authenticator(?Closure $extractCredentials = null, ?Closure $findAuthenticatable = null, ?Closure $verifyCredentials = null): AbstractAuthenticator
    {
        return new class($extractCredentials, $findAuthenticatable, $verifyCredentials) extends AbstractAuthenticator
        {
            public ?Closure $extractCredentials;

            public ?Closure $findAuthenticatable;

            public ?Closure $verifyCredentials;

            public function __construct(?Closure $extractCredentials = null, ?Closure $findAuthenticatable = null, ?Closure $verifyCredentials = null)
            {
                $this->extractCredentials = $extractCredentials;
                $this->findAuthenticatable = $findAuthenticatable;
                $this->verifyCredentials = $verifyCredentials;
            }

            public function extractCredentials(RequestContract $request): CredentialsContract
            {
                if ($this->extractCredentials) {
                    return ($this->extractCredentials)($request);
                }

                throw new LogicException("Not implemented");
            }

            public function findAuthenticatable(CredentialsContract $credentials): AuthenticatableContract
            {
                if ($this->findAuthenticatable) {
                    return ($this->findAuthenticatable)($credentials);
                }

                throw new LogicException("Not implemented");
            }

            public function verifyCredentials(CredentialsContract $credentials, AuthenticatableContract $authenticatable): AuthenticationResult
            {
                if ($this->verifyCredentials) {
                    return ($this->verifyCredentials)($credentials, $authenticatable);
                }

                throw new LogicException("Not implemented");
            }
        };
    }

    /** Ensure the correct default inactivity timout is used. */
    public function testInactiveSessionTimeout1(): void
    {
        $app = Mockery::mock(Application::class);

        $app->expects("config")
            ->once()
            ->with("app.authentication.timeout", AbstractAuthenticator::DefaultTimeout)
            ->andReturn(AbstractAuthenticator::DefaultTimeout);

        $this->mockMethod(Application::class, "instance", $app);
        $authenticator = new StaticXRay(AbstractAuthenticator::class);
        self::assertSame(AbstractAuthenticator::DefaultTimeout, $authenticator->inactiveSessionTimeout());
    }

    /** Ensure an invalid configuration exception is thrown when the configured timeout is too short. */
    public function testInactiveSessionTimeout2(): void
    {
        $app = Mockery::mock(Application::class);

        $app->expects("config")
            ->once()
            ->with("app.authentication.timeout", AbstractAuthenticator::DefaultTimeout)
            ->andReturn("59");

        $this->mockMethod(Application::class, "instance", $app);
        $authenticator = new StaticXRay(AbstractAuthenticator::class);
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected a valid integer number of seconds >= 60");
        $authenticator->inactiveSessionTimeout();
    }

    /** Ensure an invalid configuration exception is thrown when the configured timeout is non-numeric. */
    public function testInactiveSessionTimeout3(): void
    {
        $app = Mockery::mock(Application::class);

        $app->expects("config")
            ->once()
            ->with("app.authentication.timeout", AbstractAuthenticator::DefaultTimeout)
            ->andReturn("one hundred");

        $this->mockMethod(Application::class, "instance", $app);
        $authenticator = new StaticXRay(AbstractAuthenticator::class);
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected a valid integer number of seconds >= 60");
        $authenticator->inactiveSessionTimeout();
    }

    /** Ensure a valid timeout in the configuration is used. */
    public function testInactiveSessionTimeout4(): void
    {
        $app = Mockery::mock(Application::class);

        $app->expects("config")
            ->once()
            ->with("app.authentication.timeout", AbstractAuthenticator::DefaultTimeout)
            ->andReturn("60");

        $this->mockMethod(Application::class, "instance", $app);
        $authenticator = new StaticXRay(AbstractAuthenticator::class);
        self::assertSame(60, $authenticator->inactiveSessionTimeout());
    }

    /** Ensure the latest activity is stored in the correct session variable. */
    public function testSetLatestActivity1(): void
    {
        //2026-01-29T18:40:28.000Z
        $this->mockFunction("time", 1769712028);
        $session = Mockery::mock(Session::class);
        $xray = new StaticXRay(SessionFacade::class);
        $xray->session = $session;

        $session->expects("set")
            ->once()
            ->with("authenticator.current-user.latest-activity", 1769712028);

        (new StaticXRay(AbstractAuthenticator::class))->setLatestActivity();
        self::markTestAsExternallyVerified();
    }

    /** Ensure the latest activity is reported from the correct session variable. */
    public function testLatestActivity1(): void
    {
        $session = Mockery::mock(Session::class);
        $xray = new StaticXRay(SessionFacade::class);
        $xray->session = $session;

        //2026-01-29T18:40:28.000Z
        $session->expects("get")
            ->once()
            ->with("authenticator.current-user.latest-activity")
            ->andReturn(1769712028);

        (new StaticXRay(AbstractAuthenticator::class))->latestActivity();
        self::markTestAsExternallyVerified();
    }

    /** Ensure latestActivity() throws when there's no data in the session. */
    public function testLatestActivity2(): void
    {
        $session = Mockery::mock(Session::class);
        $xray = new StaticXRay(SessionFacade::class);
        $xray->session = $session;

        //2026-01-29T18:40:28.000Z
        $session->expects("get")
            ->once()
            ->with("authenticator.current-user.latest-activity")
            ->andReturn(null);

        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected latestActivity() to be called with a current sign-in session, none found");
        (new StaticXRay(AbstractAuthenticator::class))->latestActivity();
    }

    /** Ensure the authenticatable class can be set successfully. */
    public function testAuthenticateInstancesOf1(): void
    {
        $model = Mockery::mock(AuthenticatableContract::class);
        $authenticator = self::authenticator();
        $authenticator->authenticateInstancesOf($model::class);
        self::assertSame($model::class, (new XRay($authenticator))->authenticatableClass);
    }

    /** Ensure authenticateInstancesOf() throws when an invalid class is provided. */
    public function testAuthenticateInstancesOf2(): void
    {
        $authenticator = self::authenticator();
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected class implementing " . AuthenticatableContract::class . " contract, found " . self::class);
        $authenticator->authenticateInstancesOf(self::class);
    }

    /** Ensure a successful authentication sets the currently authenticated model ID and activity timestamp. */
    public function testAuthenticate1(): void
    {
        $request = Mockery::mock(RequestContract::class);
        $credentials = Mockery::mock(CredentialsContract::class);

        $authenticatable = new class implements AuthenticatableContract
        {
            public int $id = 42;

            public static function fetch(mixed $id): null|static
            {
                throw new LogicException("Not implemented");
            }

            public static function fromCredentials(CredentialsContract $credentials): ?AuthenticatableContract
            {
                throw new LogicException("Not implemented");
            }

            public function verify(CredentialsContract $credentials): bool
            {
                throw new LogicException("Not implemented");
            }
        };

        $authenticator = self::authenticator(
            extractCredentials: static function (RequestContract $requestArg) use ($request, $credentials): CredentialsContract
            {
                TestCase::assertSame($request, $requestArg);
                return $credentials;
            },
            findAuthenticatable: static function (CredentialsContract $credentialsArg) use ($credentials, $authenticatable): AuthenticatableContract
            {
                TestCase::assertSame($credentials, $credentialsArg);
                return $authenticatable;
            },
            verifyCredentials: static function (CredentialsContract $credentialsArg, AuthenticatableContract $authenticatableArg) use ($credentials, $authenticatable): AuthenticationResult
            {
                TestCase::assertSame($credentials, $credentialsArg);
                TestCase::assertSame($authenticatable, $authenticatableArg);
                return new AuthenticationResult(AuthenticationResultCode::Authenticated, $authenticatableArg);
            },
        );

        //2026-01-29T18:40:28.000Z
        $this->mockFunction("time", 1769712028);
        $session = Mockery::mock(Session::class);
        $xray = new StaticXRay(SessionFacade::class);
        $xray->session = $session;

        $session->expects("set")
            ->once()
            ->with("authenticator.current-user.id", 42);

        $session->expects("set")
            ->once()
            ->with("authenticator.current-user.latest-activity", 1769712028);

        $result = $authenticator->authenticate($request);
        self::assertSame(AuthenticationResultCode::Authenticated, $result->code());
        self::assertSame($authenticatable, $result->authenticatable());
    }

    /** Ensure an unsuccessful authentication does not set the currently authenticated model ID or latest activity. */
    public function testAuthenticate2(): void
    {
        $request = Mockery::mock(RequestContract::class);
        $credentials = Mockery::mock(CredentialsContract::class);

        $authenticatable = new class implements AuthenticatableContract
        {
            public int $id = 42;

            public static function fetch(mixed $id): null|static
            {
                throw new LogicException("Not implemented");
            }

            public static function fromCredentials(CredentialsContract $credentials): ?AuthenticatableContract
            {
                throw new LogicException("Not implemented");
            }

            public function verify(CredentialsContract $credentials): bool
            {
                throw new LogicException("Not implemented");
            }
        };

        $authenticator = self::authenticator(
            extractCredentials: static function (RequestContract $requestArg) use ($request, $credentials): CredentialsContract
            {
                TestCase::assertSame($request, $requestArg);
                return $credentials;
            },
            findAuthenticatable: static function (CredentialsContract $credentialsArg) use ($credentials, $authenticatable): AuthenticatableContract
            {
                TestCase::assertSame($credentials, $credentialsArg);
                return $authenticatable;
            },
            verifyCredentials: static function (CredentialsContract $credentialsArg, AuthenticatableContract $authenticatableArg) use ($credentials, $authenticatable): AuthenticationResult
            {
                TestCase::assertSame($credentials, $credentialsArg);
                TestCase::assertSame($authenticatable, $authenticatableArg);
                return new AuthenticationResult(AuthenticationResultCode::AdditionalFactorRequired, null, ["totp"]);
            },
        );

        //2026-01-29T18:40:28.000Z
        $this->mockFunction("time", 1769712028);
        $session = Mockery::mock(Session::class);
        $xray = new StaticXRay(SessionFacade::class);
        $xray->session = $session;

        $session->expects("set")
            ->with("authenticator.current-user.id", 42)
            ->never();

        $session->expects("set")
            ->with("authenticator.current-user.latest-activity", 1769712028)
            ->never();

        $result = $authenticator->authenticate($request);
        self::assertSame(AuthenticationResultCode::AdditionalFactorRequired, $result->code());
        self::assertSame(["totp"], $result->supportedAdditionalFactors());
    }

    /** Ensure currentlyAuthenticated() returns null when the session variable for the authenticated id is not set. */
    public function testCurrentlyAuthenticated1(): void
    {
        $authenticator = self::authenticator();
        $session = Mockery::mock(Session::class);
        $xray = new StaticXRay(SessionFacade::class);
        $xray->session = $session;

        $session->expects("get")
            ->once()
            ->with("authenticator.current-user.id")
            ->andReturn(null);

        self::assertNull($authenticator->currentlyAuthenticated());
    }

    /**
     * Ensure currentlyAuthenticated() returns the correct model when the session variable for the authenticated id is
     * valid.
     */
    public function testCurrentlyAuthenticated2(): void
    {
        $model = Mockery::mock(AuthenticatableContract::class);

        $this->mockMethod(
            $model::class,
            "fetch",
            static function (int $id) use ($model): ?AuthenticatableContract
            {
                TestCase::assertSame(42, $id);
                return $model;
            },
        );

        $authenticator = self::authenticator();
        $session = Mockery::mock(Session::class);
        $xray = new StaticXRay(SessionFacade::class);
        $xray->session = $session;

        $session->expects("get")
            ->once()
            ->with("authenticator.current-user.id")
            ->andReturn(42);

        $authenticator->authenticateInstancesOf($model::class);
        self::assertSame($model, $authenticator->currentlyAuthenticated());
    }

    /**
     * Ensure currentlyAuthenticated() returns null when the session variable for the authenticated id does not identify
     * a valid model.
     */
    public function testCurrentlyAuthenticated3(): void
    {
        $model = Mockery::mock(AuthenticatableContract::class);

        $this->mockMethod(
            $model::class,
            "fetch",
            static function (int $id): ?AuthenticatableContract
            {
                TestCase::assertSame(42, $id);
                return null;
            },
        );

        $authenticator = self::authenticator();
        $session = Mockery::mock(Session::class);
        $xray = new StaticXRay(SessionFacade::class);
        $xray->session = $session;

        $session->expects("get")
            ->once()
            ->with("authenticator.current-user.id")
            ->andReturn(42);

        $authenticator->authenticateInstancesOf($model::class);
        self::assertNull($authenticator->currentlyAuthenticated());
    }

    /** Ensure the latest activity timestamp is updated when the authenticated user's session hasn't timed out. */
    public function testCheckTimeout1(): void
    {
        $app = Mockery::mock(Application::class);

        $app->expects("config")
            ->once()
            ->with("app.authentication.timeout", AbstractAuthenticator::DefaultTimeout)
            ->andReturn(AbstractAuthenticator::DefaultTimeout);

        $this->mockMethod(Application::class, "instance", $app);
        $this->mockFunction("time", 1769800154);
        $session = Mockery::mock(Session::class);
        $xray = new StaticXRay(SessionFacade::class);
        $xray->session = $session;

        $session->expects("get")
            ->once()
            ->with("authenticator.current-user.id")
            ->andReturn(42);

        // 2026-01-30T18:39:15.000Z (1 second after the timeout threshold)
        $session->expects("get")
            ->once()
            ->with("authenticator.current-user.latest-activity")
            ->andReturn(1769798355);

        $session->expects("set")
            ->once()
            ->with("authenticator.current-user.latest-activity", 1769800154);

        $model = Mockery::mock(AuthenticatableContract::class);

        $this->mockMethod(
            $model::class,
            "fetch",
            static function (int $id) use ($model)
            {
                TestCase::assertSame(42, $id);
                return $model;
            },
        );

        $authenticator = self::authenticator();
        $authenticator->authenticateInstancesOf($model::class);
        $authenticator->checkTimeout();
    }

    /** Ensure the user is deauthenticated if the session has timed out. */
    public function testCheckTimeout2(): void
    {
        $app = Mockery::mock(Application::class);

        $app->expects("config")
            ->once()
            ->with("app.authentication.timeout", AbstractAuthenticator::DefaultTimeout)
            ->andReturn(AbstractAuthenticator::DefaultTimeout);

        $this->mockMethod(Application::class, "instance", $app);
        $this->mockFunction("time", 1769800154);
        $session = Mockery::mock(Session::class);
        $xray = new StaticXRay(SessionFacade::class);
        $xray->session = $session;

        $session->expects("get")
            ->once()
            ->with("authenticator.current-user.id")
            ->andReturn(42);

        // 2026-01-30T18:39:15.000Z (exactly on the timeout threshold)
        $session->expects("get")
            ->once()
            ->with("authenticator.current-user.latest-activity")
            ->andReturn(1769798354);

        $session->expects("prefixed")
            ->once()
            ->with("authenticator.current-user")
            ->andReturn($session);

        $session->expects("remove")
            ->once()
            ->with(".id");

        $session->expects("remove")
            ->once()
            ->with(".last-access");

        $session->expects("set")
            ->once()
            ->with("authenticator.current-user.latest-activity", 1769800154);

        $model = Mockery::mock(AuthenticatableContract::class);

        $this->mockMethod(
            $model::class,
            "fetch",
            static function (int $id) use ($model)
            {
                TestCase::assertSame(42, $id);
                return $model;
            },
        );

        $authenticator = self::authenticator();
        $authenticator->authenticateInstancesOf($model::class);
        $authenticator->checkTimeout();
    }

    /** Ensure deauthenticate() clears the session data and the currently authenticated model. */
    public function testDeauthenticate1(): void
    {
        $app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $app);
        $session = Mockery::mock(Session::class);
        $xray = new StaticXRay(SessionFacade::class);
        $xray->session = $session;

        # deauthenticate clears the session data
        $session->expects("prefixed")
            ->once()
            ->with("authenticator.current-user")
            ->andReturn($session);

        $session->expects("remove")
            ->once()
            ->with(".id");

        $session->expects("remove")
            ->once()
            ->with(".last-access");

        # after deauthentication we call currentlyAuthenticated() which queries the ID from the session
        $session->expects("get")
            ->once()
            ->with("authenticator.current-user.id")
            ->andReturn(null);

        $authenticatable = Mockery::mock(AuthenticatableContract::class);
        $authenticator = self::authenticator();
        $xray = new XRay($authenticator);
        $xray->authenticatable = $authenticatable;
        self::assertNotNull($authenticator->currentlyAuthenticated());
        $authenticator->deauthenticate();
        self::assertNull($authenticator->currentlyAuthenticated());
    }
}
