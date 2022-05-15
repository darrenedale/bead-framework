<?php

declare(strict_types=1);

namespace Equit\Test\Validation\Rules;

use Equit\Validation\Rule;
use Equit\Validation\Rules\Integer;
use Generator;
use Equit\Test\Framework\RuleTestCase;
use TypeError;

/**
 * Test case for the Email validator rule.
 */
class IntegerTest extends RuleTestCase
{
    protected function ruleInstance(): Rule
    {
        return new Integer();
    }

    /**
     * Data provider for testPasses().
     *
     * @return \Generator The test data.
     */
    public function dataForTestPasses(): Generator
    {
        yield from [
            "typicalPositiveInt" => ["field", 123, true,],
            "typicalNegativeInt" => ["field", -123, true,],
            "typicalZero" => ["field", 0, true,],
            "typicalIntString" => ["field", "123", true,],
            "typicalNegativeIntString" => ["field", "-123", true,],
            "extremeIntMin" => ["field", PHP_INT_MIN, true,],
            "extremeIntMax" => ["field", PHP_INT_MAX, true,],
            "typicalNonInt" => ["field", "alpha", false,],
            "typicalIntArray" => ["foo", [123,], false,],
            "extremeFloat" => ["field", 1.5, false,],
            "extremeHexString" => ["field", "0xff", false,],
            "extremeOctalString" => ["field", "0666", false,],
            "typicalObject" => ["field", (object)[], false,],
            "typicalAnonymousClass" => ["field", new class{}, false,],
            "typicalTrue" => ["field", true, false,],
            "typicalFalse" => ["field", false, false,],
            "typicalEmptyString" => ["field", "", false,],
            "typicalNull" => ["field", null, false,],
            "typicalEmptyArray" => ["field", [], false,],

            "invalidIntField" => [1, "", false, TypeError::class,],
            "invalidFloatField" => [1.5, "", false, TypeError::class,],
            "invalidNullField" => [null, "", false, TypeError::class,],
            "invalidEmptyArrayField" => [[], "", false, TypeError::class,],
            "invalidStringableField" => [new class
            {
                public function __toString(): string
                {
                    return "field";
                }
            }, "", false, TypeError::class,],
            "invalidArrayField" => [["field",], "", false, TypeError::class,],
            "invalidTrueField" => [true, "", false, TypeError::class,],
            "invalidFalseField" => [false, "", false, TypeError::class,],
        ];

        // 100 random valid +ve ints
        for ($idx = 0; $idx < 100; ++$idx) {
            yield "randomPositiveInt" . sprintf("%02d", $idx) => ["field", mt_rand(1, PHP_INT_MAX), true,];
        }

        // 100 random valid -ve ints
        for ($idx = 0; $idx < 100; ++$idx) {
            yield "randomNegativeInt" . sprintf("%02d", $idx) => ["field", mt_rand(PHP_INT_MIN, -1), true,];
        }

        // 100 random valid +ve int strings up to 8 digits
        for ($idx = 0; $idx < 100; ++$idx) {
            yield "randomPositiveIntString" . sprintf("%02d", $idx) => ["field", self::randomString(1, "123456789") . self::randomString(mt_rand(0, 7), "0123456789"), true,];
        }

        // 100 random valid -ve int strings up to 8 digits
        for ($idx = 0; $idx < 100; ++$idx) {
            yield "randomNegativeIntString" . sprintf("%02d", $idx) => ["field", "-" . self::randomString(1, "123456789") . self::randomString(mt_rand(0, 7), "0123456789"), true,];
        }
    }

    /**
     * Test the passes() method.
     *
     * @dataProvider dataForTestPasses
     */
    public function testPasses($field, $data, bool $shouldPass, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $rule = $this->ruleInstance();
        $this->assertSame($shouldPass, $rule->passes($field, $data), "The rule did not provide the expected result from passes().");
    }
    /**
     * Data provider for testConvert().
     *
     * @return \Generator The test data.
     */
    public function dataForTestConvert(): Generator
    {
        yield from [
            "typicalPositiveInt" => [123, 123,],
            "typicalNegativeInt" => [-123, -123,],
            "typicalZero" => [0, 0,],
            "typicalIntString" => ["123", 123,],
            "typicalNegativeIntString" => ["-123", -123,],
            "extremeIntMin" => [PHP_INT_MIN, PHP_INT_MIN,],
            "extremeIntMax" => [PHP_INT_MAX, PHP_INT_MAX,],
        ];

        // 100 random valid +ve ints
        for ($idx = 0; $idx < 100; ++$idx) {
            $data = mt_rand(1, PHP_INT_MAX);
            yield "randomPositiveInt" . sprintf("%02d", $idx) => ["{$data}", $data,];
        }

        // 100 random valid -ve ints
        for ($idx = 0; $idx < 100; ++$idx) {
            $data = mt_rand(PHP_INT_MIN, -1);
            yield "randomNegativeInt" . sprintf("%02d", $idx) => ["{$data}", $data,];
        }
    }

    /**
     * @dataProvider dataForTestConvert
     *
     * @param mixed $data The data to pass to the rule.
     * @param int $expected The expected converted value.
     */
    public function testConvert($data, int $expected): void
    {
        /** @var Integer $rule */
        $rule = $this->ruleInstance();
        $this->assertTrue($rule->passes("field", $data));
        $this->assertSame($expected, $rule->convert($data));
    }
}
