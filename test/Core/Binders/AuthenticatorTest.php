<?php

declare(strict_types=1);

namespace BeadTests\Core\Binders;

use Bead\Authentication\AuthenticationResult;
use Bead\Contracts\Authentication\Authenticator as AuthenticatorContract;
use Bead\Contracts\Authentication\Credentials as CredentialsContract;
use Bead\Contracts\Models\Authenticatable as AuthenticatableContract;
use Bead\Contracts\Web\Request as RequestContract;
use Bead\Core\Binders\Authenticator as AuthenticatorBinder;
use Bead\core\Application as Application;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Web\Application as WebApplication;
use BeadTests\Framework\TestCase;
use Equit\XRay\XRay;
use LogicException;
use Mockery;

/** @covers AuthenticatorBinder */
class AuthenticatorTest extends TestCase
{
    private AuthenticatorBinder $m_binder;

    // Can't think of a better way to make the anonymous model class visible in the Authenticator test instance in
    // testBindServices2()
    public static ?string $m_testBindServices2ExpectedModelClass = null;

    protected function setUp(): void
    {
        parent::setUp();
        self::$m_testBindServices2ExpectedModelClass = null;
        $this->m_binder = new AuthenticatorBinder();
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
        self::$m_testBindServices2ExpectedModelClass = null;
        unset($this->m_binder);
    }

    /** Ensure the service should be bound when the app is a Web\Application. */
    public function testShouldBindAuthenticator1(): void
    {
        self::assertTrue((new XRay($this->m_binder))->shouldBindAuthenticator(Mockery::mock(WebApplication::class)));
    }

    /** Ensure the service shouldn't be bound when the app is not a Web\Application. */
    public function testShouldBindAuthenticator2(): void
    {
        self::assertFalse((new XRay($this->m_binder))->shouldBindAuthenticator(Mockery::mock(Application::class)));
    }

    /** Ensure an Authenticator of the requested class is intantiated. */
    public function testCreateAuthenticator1(): void
    {
        $authenticator = new class implements AuthenticatorContract
        {
            public function authenticateInstancesOf(string $modelClass): void
            {
                throw new LogicException("authenticateInstancesOf should not be called");
            }

            public function extractCredentials(RequestContract $request): CredentialsContract
            {
                throw new LogicException("authenticateInstancesOf should not be called");
            }

            public function findAuthenticatable(CredentialsContract $credentials): AuthenticatableContract
            {
                throw new LogicException("authenticateInstancesOf should not be called");
            }

            public function verifyCredentials(CredentialsContract $credentials, AuthenticatableContract $authenticatable): AuthenticationResult
            {
                throw new LogicException("authenticateInstancesOf should not be called");
            }

            public function authenticate(RequestContract $request): AuthenticationResult
            {
                throw new LogicException("authenticateInstancesOf should not be called");
            }

            public function currentlyAuthenticated(): ?AuthenticatableContract
            {
                throw new LogicException("authenticateInstancesOf should not be called");
            }

            public function checkTimeout(): void
            {
                throw new LogicException("authenticateInstancesOf should not be called");
            }

            public function setCurrentlyAuthenticated(AuthenticatableContract $authenticatable): void
            {
                throw new LogicException("authenticateInstancesOf should not be called");
            }

            public function deauthenticate(): void
            {
                throw new LogicException("authenticateInstancesOf should not be called");
            }
        };

        $actual = (new XRay($this->m_binder))->createAuthenticator([
            "authenticator-class" => $authenticator::class,
        ]);

        self::assertInstanceOf($authenticator::class, $actual);
        self::assertNotSame($authenticator, $actual);
    }

    /** Ensure the expected exception is thrown when the requested authenticator class is not valid. */
    public function testCreateAuthenticator2(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Expected class implementing " . AuthenticatorContract::class . " contract, found " . self::class);
        (new XRay($this->m_binder))->createAuthenticator([
            "authenticator-class" => self::class,
        ]);
    }

    /** Ensure the requested model class can be set as the authenticatable model type. */
    public function testSetAuthenticatableModel1(): void
    {
        $model = Mockery::mock(AuthenticatableContract::class);
        $authenticator = Mockery::mock(AuthenticatorContract::class);

        $authenticator->expects("authenticateInstancesOf")
            ->once()
            ->with($model::class);

        (new XRay($this->m_binder))->setAuthenticatableModel($authenticator, [
            "model-class" => $model::class,
        ]);

        // Mockery takes care of test expectations
        self::markTestAsExternallyVerified();
    }

    /** Ensure the expected exception is thrown when the requested authenticator class is not valid. */
    public function testSetAuthenticatableModel2(): void
    {
        $authenticator = Mockery::mock(AuthenticatorContract::class);
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Expected class implementing " . AuthenticatableContract::class . " contract, found " . self::class);
        (new XRay($this->m_binder))->setAuthenticatableModel($authenticator, [
            "model-class" => self::class,
        ]);
    }

    /** Ensure no service is bound when shouldBindAuthenticator() returns false. */
    public function testBindServices1(): void
    {
        $app = Mockery::mock(Application::class);

        $app->expects("bindService")
            ->never();

        $this->m_binder->bindServices($app);

        // Mockery takes care of test expectations
        self::markTestAsExternallyVerified();
    }

    /** Ensure an authenticator is bound when shouldBindAuthenticator() returns true. */
    public function testBindServices2(): void
    {
        $model = Mockery::mock(AuthenticatableContract::class);
        self::$m_testBindServices2ExpectedModelClass = $model::class;

        $authenticator = new class() implements AuthenticatorContract
        {
            public function authenticateInstancesOf(string $modelClass): void
            {
                TestCase::assertSame(AuthenticatorTest::$m_testBindServices2ExpectedModelClass, $modelClass);
                AuthenticatorTest::$m_testBindServices2ExpectedModelClass = null;
            }

            public function extractCredentials(RequestContract $request): CredentialsContract
            {
                throw new LogicException("extractCredentials() should not be called");
            }

            public function findAuthenticatable(CredentialsContract $credentials): AuthenticatableContract
            {
                throw new LogicException("findAuthenticatable() should not be called");
            }

            public function verifyCredentials(CredentialsContract $credentials, AuthenticatableContract $authenticatable): AuthenticationResult
            {
                throw new LogicException("verifyCredentials() should not be called");
            }

            public function authenticate(RequestContract $request): AuthenticationResult
            {
                throw new LogicException("authenticate() should not be called");
            }

            public function currentlyAuthenticated(): ?AuthenticatableContract
            {
                throw new LogicException("currentlyAuthenticated() should not be called");
            }

            public function checkTimeout(): void
            {
                throw new LogicException("checkTimeout() should not be called");
            }

            public function setCurrentlyAuthenticated(AuthenticatableContract $authenticatable): void
            {
                throw new LogicException("setCurrentlyAuthenticated() should not be called");
            }

            public function deauthenticate(): void
            {
                throw new LogicException("deauthenticate() should not be called");
            }
        };

        $app = Mockery::mock(WebApplication::class);

        $app->expects("bindService")
            ->once()
            ->with(
                AuthenticatorContract::class,
                Mockery::on(static fn (AuthenticatorContract $actualAuthenticator): bool => $actualAuthenticator::class === $authenticator::class),
            );

        $app->expects("config")
            ->once()
            ->with("auth.authenticators")
            ->andReturn([
                "test-authenticator" => [
                    "authenticator-class" => $authenticator::class,
                    "model-class" => $model::class,
                ],
            ]);

        $app->expects("config")
            ->once()
            ->with("auth.authenticator")
            ->andReturn("test-authenticator");

        $this->m_binder->bindServices($app);

        // if the Authenticator sets this back to null, the test passes
        self::assertNull(AuthenticatorTest::$m_testBindServices2ExpectedModelClass);
    }
}
