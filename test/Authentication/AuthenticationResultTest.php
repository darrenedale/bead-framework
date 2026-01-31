<?php

declare(strict_types=1);

namespace BeadTests\Authentication;

use Bead\Authentication\AuthenticationResult;
use Bead\Authentication\AuthenticationResultCode;
use Bead\Contracts\Models\Authenticatable;
use BeadTests\Framework\TestCase;
use LogicException;
use Mockery;
use stdClass;

/** @covers \Bead\Authentication\AuthenticationResult */
class AuthenticationResultTest extends TestCase
{
    /** Provides arrays containing invalid sets of additional authentication factors. */
    public static function providerInvalidAdditionalFactors(): iterable
    {
        yield "int" => [[42]];
        yield "float" => [[3.14]];
        yield "bool" => [[true]];
        yield "null" => [[null]];
        yield "object" => [[new StdClass()]];
        yield "array" => [[[]]];
        yield "resource" => [[fopen("php://memory", "w")]];
        yield "mixed" => [[42, 3.14, null, false]];
        yield "all but one string" => [["totp", "fido2", 42]];
    }

    /** Provides sets of arguments for the constructor and the expected result code. */
    public static function providerTestCode1(): iterable
    {
        yield "authenticated" => [[AuthenticationResultCode::Authenticated, Mockery::mock(Authenticatable::class)], AuthenticationResultCode::Authenticated];
        yield "additional factor required" => [[AuthenticationResultCode::AdditionalFactorRequired, null, ["totp"]], AuthenticationResultCode::AdditionalFactorRequired];
    }

    /** Provides sets of arguments for the constructor and the expected Authenticatable. */
    public static function providerTestAuthenticatable1(): iterable
    {
        $authenticatable = Mockery::mock(Authenticatable::class);
        yield "authenticated" => [[AuthenticationResultCode::Authenticated, $authenticatable], $authenticatable];
        yield "additional factor required" => [[AuthenticationResultCode::AdditionalFactorRequired, null, ["totp"]], null];
    }

    /** Provides sets of arguments for the constructor and the expected additional factors. */
    public static function providerTestSupportedAdditionalFactors1(): iterable
    {
        yield "authenticated" => [[AuthenticationResultCode::Authenticated, Mockery::mock(Authenticatable::class)], []];
        yield "additional factor required" => [[AuthenticationResultCode::AdditionalFactorRequired, null, ["totp"]], ["totp"]];
    }

    /**
     * Ensure the constructor throws if given an invalid set of additional factors.
     * @dataProvider providerInvalidAdditionalFactors
     */
    public function testConstructor1(array $additionalFactors): void
    {
        self::skipIfAssertionsDisabled();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Expected an array of strings identifying supported additional authentication factors");
        new AuthenticationResult(AuthenticationResultCode::AdditionalFactorRequired, null, $additionalFactors);
    }

    /** Ensure the constructor throws if the result is Authenticated and the authenticatable is null. */
    public function testConstructor2(): void
    {
        self::skipIfAssertionsDisabled();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Expected valid combination of result code and authenticatable, found Authenticated and no authenticatable");
        new AuthenticationResult(AuthenticationResultCode::Authenticated, null);
    }

    /** Ensure the constructor throws if the result is AdditionalFactorRequired and the authenticatable is non-null. */
    public function testConstructor3(): void
    {
        self::skipIfAssertionsDisabled();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Expected valid combination of result code and authenticatable, found AdditionalFactorRequired and an authenticatable");
        new AuthenticationResult(AuthenticationResultCode::AdditionalFactorRequired, Mockery::mock(Authenticatable::class));
    }

    /** Ensure the constructor throws if the result is AdditionalFactorRequired and there are no additional factors. */
    public function testConstructor4(): void
    {
        self::skipIfAssertionsDisabled();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Expected one or more additional supported factors for code = AdditionalFactorRequired");
        new AuthenticationResult(AuthenticationResultCode::AdditionalFactorRequired, additionalFactors: []);
    }

    /** Ensure the constructor defaults the authenticatable to null. */
    public function testConstructor5(): void
    {
        $actual = new AuthenticationResult(AuthenticationResultCode::AdditionalFactorRequired, additionalFactors: ["totp"]);
        self::assertNull($actual->authenticatable());
    }

    /** Ensure the constructor defaults the additional factors to an empty array. */
    public function testConstructor6(): void
    {
        $actual = new AuthenticationResult(AuthenticationResultCode::Authenticated, Mockery::mock(Authenticatable::class));
        self::assertSame([], $actual->supportedAdditionalFactors());
    }

    /** Ensure the constructor can be invoked with a valid Authenticated result. */
    public function testConstructor7(): void
    {
        $authenticatable = Mockery::mock(Authenticatable::class);
        $actual = new AuthenticationResult(AuthenticationResultCode::Authenticated, $authenticatable);
        self::assertSame(AuthenticationResultCode::Authenticated, $actual->code());
        self::assertSame($authenticatable, $actual->authenticatable());
        self::assertSame([], $actual->supportedAdditionalFactors());
    }

    /** Ensure the constructor can be invoked with a valid AdditionalFactorRequired result. */
    public function testConstructor8(): void
    {
        $actual = new AuthenticationResult(AuthenticationResultCode::AdditionalFactorRequired, additionalFactors: ["totp"]);
        self::assertSame(AuthenticationResultCode::AdditionalFactorRequired, $actual->code());
        self::assertNull($actual->authenticatable());
        self::assertSame(["totp"], $actual->supportedAdditionalFactors());
    }

    /** Ensure success is reported when the result code is AuthenticationResultCode::Authenticated. */
    public function testIsSuccess1(): void
    {
        $actual = new AuthenticationResult(AuthenticationResultCode::Authenticated, Mockery::mock(Authenticatable::class));
        self::assertTrue($actual->isSuccess());
    }

    /** Ensure success is not reported when the result code is AuthenticationResultCode::AdditionalFactorRequired. */
    public function testIsSuccess2(): void
    {
        $actual = new AuthenticationResult(AuthenticationResultCode::AdditionalFactorRequired, additionalFactors: ["totp"]);
        self::assertFalse($actual->isSuccess());
    }

    /** Ensure additional factor required is reported when the result code is AuthenticationResultCode::AdditionalFactorRequired. */
    public function testAdditionalFactorRequired1(): void
    {
        $actual = new AuthenticationResult(AuthenticationResultCode::AdditionalFactorRequired, additionalFactors: ["totp"]);
        self::assertTrue($actual->additionalFactorRequired());
    }

    /** Ensure success is not reported when the result code is AuthenticationResultCode::AdditionalFactorRequired. */
    public function testAdditionalFactorRequired2(): void
    {
        $actual = new AuthenticationResult(AuthenticationResultCode::Authenticated, Mockery::mock(Authenticatable::class));
        self::assertFalse($actual->additionalFactorRequired());
    }

    /**
     * Ensure the result code is reported correctly.
     * @dataProvider providerTestCode1
     */
    public function testCode1(array $constructorArgs, AuthenticationResultCode $expectedCode): void
    {
        $actual = new AuthenticationResult(...$constructorArgs);
        self::assertSame($expectedCode, $actual->code());
    }

    /**
     * Ensure the authenticatable is reported correctly.
     * @dataProvider providerTestAuthenticatable1
     */
    public function testAuthenticatable1(array $constructorArgs, ?Authenticatable $expectedAuthenticatable): void
    {
        $actual = new AuthenticationResult(...$constructorArgs);
        self::assertSame($expectedAuthenticatable, $actual->authenticatable());
    }

    /**
     * Ensure the supported additional factors are reported correctly.
     * @dataProvider providerTestSupportedAdditionalFactors1
     */
    public function testSupportedAdditionalFactors1(array $constructorArgs, array $expectedFactors): void
    {
        $actual = new AuthenticationResult(...$constructorArgs);
        self::assertSame($expectedFactors, $actual->supportedAdditionalFactors());
    }
}
