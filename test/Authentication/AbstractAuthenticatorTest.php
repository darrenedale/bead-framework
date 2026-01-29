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
use Equit\XRay\StaticXRay;
use Equit\XRay\XRay;
use LogicException;
use Mockery;

class AbstractAuthenticatorTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
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
        $authenticator = new class() extends AbstractAuthenticator
        {
            public function extractCredentials(RequestContract $request): CredentialsContract
            {
                throw new LogicException("Not implemented");
            }

            public function findAuthenticatable(CredentialsContract $credentials): AuthenticatableContract
            {
                throw new LogicException("Not implemented");
            }

            public function verifyCredentials(CredentialsContract $credentials, AuthenticatableContract $authenticatable): AuthenticationResult
            {
                throw new LogicException("Not implemented");
            }
        };

        $authenticator->authenticateInstancesOf($model::class);
        self::assertSame($model::class, (new XRay($authenticator))->authenticatableClass);
    }

    /** Ensure authenticateInstancesOf() throws when an invalid class is provided. */
    public function testAuthenticateInstancesOf2(): void
    {
        $authenticator = new class() extends AbstractAuthenticator
        {
            public function extractCredentials(RequestContract $request): CredentialsContract
            {
                throw new LogicException("Not implemented");
            }

            public function findAuthenticatable(CredentialsContract $credentials): AuthenticatableContract
            {
                throw new LogicException("Not implemented");
            }

            public function verifyCredentials(CredentialsContract $credentials, AuthenticatableContract $authenticatable): AuthenticationResult
            {
                throw new LogicException("Not implemented");
            }
        };

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

        $authenticator = new class($credentials, $authenticatable, $request) extends AbstractAuthenticator
        {
            public CredentialsContract $credentials;

            public AuthenticatableContract $authenticatable;

            public RequestContract $request;

            public function __construct(CredentialsContract $credentials, AuthenticatableContract $authenticatable, RequestContract $request)
            {
                $this->credentials = $credentials;
                $this->authenticatable = $authenticatable;
                $this->request = $request;
            }

            public function extractCredentials(RequestContract $request): CredentialsContract
            {
                TestCase::assertSame($this->request, $request);
                return $this->credentials;
            }

            public function findAuthenticatable(CredentialsContract $credentials): AuthenticatableContract
            {
                TestCase::assertSame($this->credentials, $credentials);
                return $this->authenticatable;
            }

            public function verifyCredentials(CredentialsContract $credentials, AuthenticatableContract $authenticatable): AuthenticationResult
            {
                TestCase::assertSame($this->credentials, $credentials);
                TestCase::assertSame($this->authenticatable, $authenticatable);
                return new AuthenticationResult(AuthenticationResultCode::Authenticated, $this->authenticatable);
            }
        };

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

        $authenticator = new class($credentials, $authenticatable, $request) extends AbstractAuthenticator
        {
            public CredentialsContract $credentials;

            public AuthenticatableContract $authenticatable;

            public RequestContract $request;

            public function __construct(CredentialsContract $credentials, AuthenticatableContract $authenticatable, RequestContract $request)
            {
                $this->credentials = $credentials;
                $this->authenticatable = $authenticatable;
                $this->request = $request;
            }

            public function extractCredentials(RequestContract $request): CredentialsContract
            {
                TestCase::assertSame($this->request, $request);
                return $this->credentials;
            }

            public function findAuthenticatable(CredentialsContract $credentials): AuthenticatableContract
            {
                TestCase::assertSame($this->credentials, $credentials);
                return $this->authenticatable;
            }

            public function verifyCredentials(CredentialsContract $credentials, AuthenticatableContract $authenticatable): AuthenticationResult
            {
                TestCase::assertSame($this->credentials, $credentials);
                TestCase::assertSame($this->authenticatable, $authenticatable);
                return new AuthenticationResult(AuthenticationResultCode::AdditionalFactorRequired, null, ["totp"]);
            }
        };

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
}
