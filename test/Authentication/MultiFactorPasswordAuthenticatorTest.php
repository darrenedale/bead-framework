<?php

declare(strict_types=1);

namespace BeadTests\Authentication;

use Bead\Authentication\AuthenticationResultCode;
use Bead\Authentication\MultiFactorPasswordAuthenticator;
use Bead\Authentication\MultiFactorPasswordCredentials;
use Bead\Contracts\Authentication\Credentials as CredentialsContract;
use Bead\Contracts\Models\Authenticatable as AuthenticatableContract;
use Bead\Contracts\Models\MultiFactorAuthenticatable as MultiFactorAuthenticatableContract;
use Bead\Contracts\Web\Request as RequestContract;
use Bead\Core\Application;
use Bead\Exceptions\Authentication\AuthenticationException;
use Bead\Exceptions\Authentication\MultiFactorAuthenticationException;
use BeadTests\Framework\TestCase;
use LogicException;
use Mockery;
use StdClass;

/** @covers \Bead\Authentication\PasswordAuthenticator */
class MultiFactorPasswordAuthenticatorTest extends TestCase
{
    private MultiFactorPasswordAuthenticator $m_authenticator;

    protected function setUp(): void
    {
        $this->m_authenticator = new MultiFactorPasswordAuthenticator();
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

    public static function providerInvalidSecondFactorMethods(): iterable
    {
        yield "int" => [42];
        yield "float" => [3.14];
        yield "bool" => [true];
        yield "array" => [["totp"]];
        yield "object" => [new StdClass()];
        yield "resource" => [fopen("php://memory", "w")];
        yield "unrecognised method" => ["fido2"];
    }

    public static function providerInvalidSecondFactorPasswords(): iterable
    {
        yield "int" => [42];
        yield "float" => [3.14];
        yield "bool" => [true];
        yield "array" => [["123456"]];
        yield "object" => [new StdClass()];
        yield "resource" => [fopen("php://memory", "w")];
        yield "too few digits" => ["12345"];
        yield "too many digits" => ["1234567"];
    }

    /** Ensure credentials are extracted as expected. */
    public function testExtractCredentials1(): void
    {
        $request = Mockery::mock(RequestContract::class);

        $request->expects("formFields")
            ->once()
            ->with(["email", "password", "second-factor-method", "second-factor-password",])
            ->andReturn([
                "email" => "darren@example.org",
                "password" => "secret",
                "second-factor-method" => "totp",
                "second-factor-password" => "123456",
            ]);

        $actual = $this->m_authenticator->extractCredentials($request);
        self::assertInstanceOf(MultiFactorPasswordCredentials::class, $actual);
        self::assertSame("darren@example.org", $actual->username());
        self::assertSame("secret", $actual->password());
        self::assertSame("totp", $actual->secondFactorMethod());
        self::assertSame("123456", $actual->secondFactorPassword());
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
            ->with(["email", "password", "second-factor-method", "second-factor-password",])
            ->andReturn([
                "email" => $invalidUsername,
                "password" => "secret",
                "second-factor-method" => "totp",
                "second-factor-password" => "123456",
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
            ->with(["email", "password", "second-factor-method", "second-factor-password",])
            ->andReturn([
                "email" => "darren@example.org",
                "password" => $invalidPassword,
                "second-factor-method" => "totp",
                "second-factor-password" => "123456",
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
     * Ensure an invalid second factor method triggers the expected exception.
     * @dataProvider providerInvalidSecondFactorMethods
     */
    public function testExtractCredentials4(mixed $invalidMethod): void
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
            ->with(["email", "password", "second-factor-method", "second-factor-password",])
            ->andReturn([
                "email" => "darren@example.org",
                "password" => "secret",
                "second-factor-method" => $invalidMethod,
                "second-factor-password" => "123456",
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
     * Ensure an invalid second factor method triggers the expected exception.
     * @dataProvider providerInvalidSecondFactorPasswords
     */
    public function testExtractCredentials5(mixed $invalidPassword): void
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
            ->with(["email", "password", "second-factor-method", "second-factor-password",])
            ->andReturn([
                "email" => "darren@example.org",
                "password" => "secret",
                "second-factor-method" => "totp",
                "second-factor-password" => $invalidPassword,
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
        $credentials = new MultiFactorPasswordCredentials(
            "darren@example.org",
            "secret",
            "totp",
            "123456",
        );

        $authenticatable = new class ($credentials) implements MultiFactorAuthenticatableContract
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

            public function hasMultiFactorEnabled(): bool
            {
                return true;
            }

            public function verifyMultiFactor(CredentialsContract $credentials): bool
            {
                TestCase::assertSame($this->m_expectedCredentials, $credentials);
                return true;
            }

            public function multiFactorMethods(): array
            {
                return ["totp"];
            }
        };

        $this->m_authenticator->authenticateInstancesOf($authenticatable::class);
        $actual = $this->m_authenticator->verifyCredentials($credentials, $authenticatable);
        self::assertSame(AuthenticationResultCode::Authenticated, $actual->code());
        self::assertSame($authenticatable, $actual->authenticatable());
    }

    /** Ensure Authenticatable without multi factor enabled verifies without second factor */
    public function testVerifyCredentials2(): void
    {
        $credentials = new MultiFactorPasswordCredentials(
            "darren@example.org",
            "secret",
            null,
            null,
        );

        $authenticatable = new class ($credentials) implements MultiFactorAuthenticatableContract
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

            public function hasMultiFactorEnabled(): bool
            {
                return false;
            }

            public function verifyMultiFactor(CredentialsContract $credentials): bool
            {
                TestCase::fail("verifyMultiFactor should not be invoked");
            }

            public function multiFactorMethods(): array
            {
                TestCase::fail("multiFactorMethods should not be invoked");
            }
        };

        $this->m_authenticator->authenticateInstancesOf($authenticatable::class);
        $actual = $this->m_authenticator->verifyCredentials($credentials, $authenticatable);
        self::assertSame(AuthenticationResultCode::Authenticated, $actual->code());
        self::assertSame($authenticatable, $actual->authenticatable());
    }

    /** Ensure Authenticatable with multi factor enabled verifies with second factor */
    public function testVerifyCredentials3(): void
    {
        $credentials = new MultiFactorPasswordCredentials(
            "darren@example.org",
            "secret",
            "totp",
            "123456",
        );

        $authenticatable = new class ($credentials) implements MultiFactorAuthenticatableContract
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

            public function hasMultiFactorEnabled(): bool
            {
                return true;
            }

            public function verifyMultiFactor(CredentialsContract $credentials): bool
            {
                TestCase::assertSame($this->m_expectedCredentials, $credentials);
                return true;
            }

            public function multiFactorMethods(): array
            {
                return ["totp"];
            }
        };

        $this->m_authenticator->authenticateInstancesOf($authenticatable::class);
        $actual = $this->m_authenticator->verifyCredentials($credentials, $authenticatable);
        self::assertSame(AuthenticationResultCode::Authenticated, $actual->code());
        self::assertSame($authenticatable, $actual->authenticatable());

        // ensure all the assertions in the Authenticatable have fired
        self::assertSame(4, $this->getCount());
    }

    /** Ensure Authenticatable with multi factor enabled fails on second factor */
    public function testVerifyCredentials4(): void
    {
        $credentials = new MultiFactorPasswordCredentials(
            "darren@example.org",
            "secret",
            "totp",
            "123456",
        );

        $authenticatable = new class implements MultiFactorAuthenticatableContract
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
                return true;
            }

            public function hasMultiFactorEnabled(): bool
            {
                return true;
            }

            public function verifyMultiFactor(CredentialsContract $credentials): bool
            {
                return false;
            }

            public function multiFactorMethods(): array
            {
                return ["totp"];
            }
        };

        $this->m_authenticator->authenticateInstancesOf($authenticatable::class);
        $this->expectException(MultiFactorAuthenticationException::class);
        $this->expectExceptionMessage("The code is not valid, please try again with the next code");
        $this->m_authenticator->verifyCredentials($credentials, $authenticatable);
    }

    /** Ensure Authenticatable with multi factor enabled requests second factor if not present in existing credentials. */
    public function testVerifyCredentials5(): void
    {
        $credentials = new MultiFactorPasswordCredentials(
            "darren@example.org",
            "secret",
            null,
            null,
        );

        $authenticatable = new class implements MultiFactorAuthenticatableContract
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
                return true;
            }

            public function hasMultiFactorEnabled(): bool
            {
                return true;
            }

            public function verifyMultiFactor(CredentialsContract $credentials): bool
            {
                TestCase::fail("verifyMultiFactor should not be invoked");
            }

            public function multiFactorMethods(): array
            {
                return ["totp"];
            }
        };

        $this->m_authenticator->authenticateInstancesOf($authenticatable::class);
        $actual = $this->m_authenticator->verifyCredentials($credentials, $authenticatable);
        self::assertSame(AuthenticationResultCode::AdditionalFactorRequired, $actual->code());
        self::assertSame(["totp"], $actual->supportedAdditionalFactors());
    }

    /**
     * Ensure the expected exception is thrown when the first-factor credentials are not verified by the
     * Authenticatable model.
     */
    public function testVerifyCredentials6(): void
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
