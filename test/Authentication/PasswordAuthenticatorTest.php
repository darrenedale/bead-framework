<?php

declare(strict_types=1);

namespace BeadTests\Authentication;

use Bead\Authentication\AuthenticationResultCode;
use Bead\Authentication\PasswordAuthenticator;
use Bead\Authentication\PasswordCredentials;
use Bead\Contracts\Authentication\Credentials as CredentialsContract;
use Bead\Contracts\Models\Authenticatable as AuthenticatableContract;
use Bead\Contracts\Web\Request as RequestContract;
use Bead\Core\Application;
use Bead\Exceptions\Authentication\AuthenticationException;
use BeadTests\Framework\TestCase;
use LogicException;
use Mockery;
use StdClass;

/** @covers \Bead\Authentication\PasswordAuthenticator */
class PasswordAuthenticatorTest extends TestCase
{
    private PasswordAuthenticator $m_authenticator;

    protected function setUp(): void
    {
        $this->m_authenticator = new PasswordAuthenticator();
    }

    public function tearDown(): void
    {
        unset($this->m_authenticator);
        parent::tearDown();
        Mockery::close();
    }

    public static function providerInvalidUsernames(): iterable
    {
        yield "int" => [42];
        yield "float" => [3.14];
        yield "bool" => [true];
        yield "null" => [null];
        yield "array" => [["darren@example.org"]];
        yield "object" => [new StdClass()];
        yield "resource" => [fopen("php://memory", "w")];
        yield "empty string" => [""];
    }

    public static function providerInvalidPasswords(): iterable
    {
        yield "int" => [42];
        yield "float" => [3.14];
        yield "bool" => [true];
        yield "null" => [null];
        yield "array" => [["secret"]];
        yield "object" => [new StdClass()];
        yield "resource" => [fopen("php://memory", "w")];
        yield "empty string" => [""];
    }

    /** Ensure credentials are extracted as expected. */
    public function testExtractCredentials1(): void
    {
        $request = Mockery::mock(RequestContract::class);

        $request->expects("formFields")
            ->once()
            ->with(["email", "password"])
            ->andReturn([
                "email" => "darren@example.org",
                "password" => "secret",
            ]);

        $actual = $this->m_authenticator->extractCredentials($request);
        self::assertInstanceOf(PasswordCredentials::class, $actual);
        self::assertSame("darren@example.org", $actual->username());
        self::assertSame("secret", $actual->password());
    }

    /**
     * Ensure an invalid username triggers the expected exception.
     * @dataProvider providerInvalidUsernames
     */
    public function testExtractCredentials2(mixed $invalidUsername): void
    {
        $app = Mockery::mock(Application::class);

        $app->expects("config")
            ->once()
            ->with("app.auth-delay", 2)
            ->andReturn(2);

        $this->mockMethod(Application::class, "instance", $app);
        $request = Mockery::mock(RequestContract::class);

        $request->expects("formFields")
            ->once()
            ->with(["email", "password"])
            ->andReturn([
                "email" => $invalidUsername,
                "password" => "secret",
            ]);

        $this->mockFunction(
            "usleep",
            static function (int $microseconds): void {
                TestCase::assertSame(2000000, $microseconds);
            }
        );

        // since we have an Application instance, tr() will ask it for a translator so we mock tr() to avoid that
        $this->mockFunction(
            "Bead\\Helpers\\I18n\\tr",
            static fn (string $template): string => $template,
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Invalid authentication data provided");
        $this->m_authenticator->extractCredentials($request);
    }

    /**
     * Ensure an invalid password triggers the expected exception.
     * @dataProvider providerInvalidPasswords
     */
    public function testExtractCredentials3(mixed $invalidPassword): void
    {
        $app = Mockery::mock(Application::class);

        $app->expects("config")
            ->once()
            ->with("app.auth-delay", 2)
            ->andReturn(2);

        $this->mockMethod(Application::class, "instance", $app);
        $request = Mockery::mock(RequestContract::class);

        $request->expects("formFields")
            ->once()
            ->with(["email", "password"])
            ->andReturn([
                "email" => "darren@example.org",
                "password" => $invalidPassword,
            ]);

        $this->mockFunction(
            "usleep",
            static function (int $microseconds): void {
                TestCase::assertSame(2000000, $microseconds);
            }
        );

        // since we have an Application instance, tr() will ask it for a translator so we mock tr() to avoid that
        $this->mockFunction(
            "Bead\\Helpers\\I18n\\tr",
            static fn (string $template): string => $template,
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Invalid authentication data provided");
        $this->m_authenticator->extractCredentials($request);
    }

    /** Ensure the matching authenticatable is looked up and returned. */
    public function testFindAuthenticatable1(): void
    {
        $credentials = Mockery::mock(CredentialsContract::class);

        $authenticatable = new class ($credentials) implements AuthenticatableContract
        {
            private static CredentialsContract $m_expectedCredentials;

            private static AuthenticatableContract $m_expectedAuthenticatable;

            public function __construct(CredentialsContract $credentials)
            {
                self::$m_expectedCredentials = $credentials;
                self::$m_expectedAuthenticatable = $this;
            }

            public static function fetch(mixed $id): null|static
            {
                throw new LogicException("Not implemented");
            }

            public static function fromCredentials(CredentialsContract $credentials): ?AuthenticatableContract
            {
                TestCase::assertSame(self::$m_expectedCredentials, $credentials);
                return self::$m_expectedAuthenticatable;
            }

            public function verify(CredentialsContract $credentials): bool
            {
                throw new LogicException("Not implemented");
            }
        };

        $this->m_authenticator->authenticateInstancesOf($authenticatable::class);
        $actual = $this->m_authenticator->findAuthenticatable($credentials);
        self::assertSame($authenticatable, $actual);
    }

    /** Ensure the expected exception is thrown when the credentials yield no matching Authenticatable model. */
    public function testFindAuthenticatable2(): void
    {
        $credentials = Mockery::mock(CredentialsContract::class);

        $authenticatable = new class implements AuthenticatableContract
        {
            public static function fetch(mixed $id): null|static
            {
                throw new LogicException("Not implemented");
            }

            public static function fromCredentials(CredentialsContract $credentials): ?AuthenticatableContract
            {
                return null;
            }

            public function verify(CredentialsContract $credentials): bool
            {
                throw new LogicException("Not implemented");
            }
        };

        $this->m_authenticator->authenticateInstancesOf($authenticatable::class);
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("The email and/or password is not valid");
        $this->m_authenticator->findAuthenticatable($credentials);
    }

    /**
     * Ensure valid credentials are verified by the Authenticatable model and the expected AuthenticationResult is
     * returned
     */
    public function testVerifyCredentials1(): void
    {
        $credentials = Mockery::mock(CredentialsContract::class);

        $authenticatable = new class ($credentials) implements AuthenticatableContract
        {
            private CredentialsContract $m_expectedCredentials;

            public function __construct(CredentialsContract $credentials)
            {
                $this->m_expectedCredentials = $credentials;
            }

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
                TestCase::assertSame($this->m_expectedCredentials, $credentials);
                return true;
            }
        };

        $this->m_authenticator->authenticateInstancesOf($authenticatable::class);
        $actual = $this->m_authenticator->verifyCredentials($credentials, $authenticatable);
        self::assertSame(AuthenticationResultCode::Authenticated, $actual->code());
        self::assertSame($authenticatable, $actual->authenticatable());
    }

    /** Ensure the expected exception is thrown when the credentials are not verified by the Authenticatable model. */
    public function testVerifyCredentials2(): void
    {
        $credentials = Mockery::mock(CredentialsContract::class);

        $authenticatable = new class implements AuthenticatableContract
        {
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
                return false;
            }
        };

        $this->m_authenticator->authenticateInstancesOf($authenticatable::class);
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("The email and/or password is not valid");
        $this->m_authenticator->verifyCredentials($credentials, $authenticatable);
    }
}
