<?php

declare(strict_types=1);

namespace BeadTests\Authentication;

use Bead\Authentication\AuthenticationResult;
use Bead\Authentication\AuthenticationResultCode;
use Bead\Contracts\Models\Authenticatable;
use BeadTests\Framework\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AuthenticationResult::class)]
class AuthenticationResultTest extends TestCase
{
    private const TestAdditionalFactors = ["totp", "fido2",];

    /** Ensure success is reported when the result code is AuthenticationResultCode::Authenticated. */
    public function testIsSuccess1(): void
    {
        $actual = AuthenticationResult::authenticated(Mockery::mock(Authenticatable::class));
        self::assertTrue($actual->isSuccess());
    }

    /** Ensure success is not reported when the result code is AuthenticationResultCode::AdditionalFactorRequired. */
    public function testIsSuccess2(): void
    {
        $actual = AuthenticationResult::multiFactorRequired(self::TestAdditionalFactors);
        self::assertFalse($actual->isSuccess());
    }

    /**
     * Ensure additional factor required is reported when the result code is
     * AuthenticationResultCode::AdditionalFactorRequired. 
     */
    public function testAdditionalFactorRequired1(): void
    {
        $actual = AuthenticationResult::multiFactorRequired(self::TestAdditionalFactors);
        self::assertTrue($actual->additionalFactorRequired());
    }

    /**
     * Ensure no additional factor required is reported when the result code is
     * AuthenticationResultCode::Authenticated. 
     */
    public function testAdditionalFactorRequired2(): void
    {
        $actual = AuthenticationResult::authenticated(Mockery::mock(Authenticatable::class));
        self::assertFalse($actual->additionalFactorRequired());
    }

    /** Ensure the result code is reported correctly when the result is success. */
    public function testCode1(): void
    {
        $actual = AuthenticationResult::authenticated(Mockery::mock(Authenticatable::class));
        self::assertSame(AuthenticationResultCode::Authenticated, $actual->code());
    }

    /** Ensure the result code is reported correctly when the result is multifactor required. */
    public function testCode2(): void
    {
        $actual = AuthenticationResult::multiFactorRequired(self::TestAdditionalFactors);
        self::assertSame(AuthenticationResultCode::AdditionalFactorRequired, $actual->code());
    }

    /** Ensure the authenticatable is reported correctly when the result is success. */
    public function testAuthenticatable1(): void
    {
        $expectedAuthenticatable = Mockery::mock(Authenticatable::class);
        $actual = AuthenticationResult::authenticated($expectedAuthenticatable);
        self::assertSame($expectedAuthenticatable, $actual->authenticatable());
    }

    /** Ensure the authenticatable is null when the result is multifactor required. */
    public function testAuthenticatable2(): void
    {
        $actual = AuthenticationResult::multiFactorRequired(self::TestAdditionalFactors);
        self::assertNull($actual->authenticatable());
    }

    /** Ensure the supported additional factors are empty when the result is success. */
    public function testSupportedAdditionalFactors1(): void
    {
        $actual = AuthenticationResult::authenticated(Mockery::mock(Authenticatable::class));
        self::assertSame([], $actual->supportedAdditionalFactors());
    }

    /** Ensure the supported additional factors are reported correctly when the result is multifactor required. */
    public function testSupportedAdditionalFactors2(): void
    {
        $actual = AuthenticationResult::multiFactorRequired(self::TestAdditionalFactors);
        self::assertSame(self::TestAdditionalFactors, $actual->supportedAdditionalFactors());
    }
}
