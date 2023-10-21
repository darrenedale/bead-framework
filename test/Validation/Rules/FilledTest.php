<?php

declare(strict_types=1);

namespace BeadTests\Validation\Rules;

use Bead\Validation\Rule;
use Bead\Validation\Rules\Filled;
use Generator;
use BeadTests\Framework\RuleTestCase;
use TypeError;

/**
 * Test case for the Filled validator rule.
 */
class FilledTest extends RuleTestCase
{
    /**
     * @inheritDoc
     */
    protected function ruleInstance(): Rule
    {
        return new Filled();
    }

    /**
     * Data provider for testPasses().
     *
     * @return \Generator The test data.
     */
    public function dataForTestPasses(): Generator
    {
        yield from [
            "typicalFilledString" => ["field", "string", true,],
            "typicalFilledTrue" => ["field", true, true,],
            "typicalFilledInt" => ["field", 1, true,],
            "typicalFilledFloat" => ["field", 1.5, true,],
            "typicalFilledArray" => ["field", [1, 2, 3, 4, 5,], true,],
            "typicalFilledObject" => ["field", (object)[], true,],
            "typicalFilledAnonymousClass" => [
                "field",
                new class
                {
                },
                true,
            ],
            "extremeFilledFalse" => ["field", false, true,],
            "extremeFilledInt0" => ["field", 0, true,],
            "extremeFilledFloat0" => ["field", 0.0, true,],
            "extremeFilledArrayInt0" => ["field", [0,], true,],
            "extremeFilledArrayFloat0" => ["field", [0.0,], true,],
            "extremeFilledArrayFalse" => ["field", [false,], true,],
            "extremeFilledArrayNull" => ["field", [null,], true,],
            "typicalEmptyString" => ["field", "", false,],
            "typicalEmptyNull" => ["field", null, false,],
            "typicalEmptyArray" => ["field", [], false,],

            "invalidIntField" => [1, "", false, TypeError::class,],
            "invalidFloatField" => [1.5, "", false, TypeError::class,],
            "invalidNullField" => [null, "", false, TypeError::class,],
            "invalidEmptyArrayField" => [[], "", false, TypeError::class,],
            "invalidStringableField" => [
                new class
                {
                    public function __toString(): string
                    {
                        return "field";
                    }
                },
                "",
                false,
                TypeError::class,
            ],
            "invalidArrayField" => [["field",], "", false, TypeError::class,],
            "invalidTrueField" => [true, "", false, TypeError::class,],
            "invalidFalseField" => [false, "", false, TypeError::class,],
        ];

        // 100 random filled strings
        for ($idx = 0; $idx < 100; ++$idx) {
            yield "typicalRandomString" . sprintf("%02d", $idx) => ["field", self::randomString(10), true,];
        }

        // 50 random filled +ve ints
        for ($idx = 0; $idx < 50; ++$idx) {
            yield "typicalRandomInt" . sprintf("%02d", $idx) => ["field", mt_rand(1, PHP_INT_MAX), true,];
        }

        // 50 random filled -ve ints
        for ($idx = 50; $idx < 100; ++$idx) {
            yield "typicalRandomInt" . sprintf("%02d", $idx) => ["field", mt_rand(PHP_INT_MIN, -1), true,];
        }

        // 50 random filled +ve floats
        for ($idx = 0; $idx < 50; ++$idx) {
            yield "typicalRandomFloat" . sprintf("%02d", $idx) => ["field", self::randomFloat(1.0, 100000.0), true,];
        }

        // 50 random filled -ve floats
        for ($idx = 50; $idx < 100; ++$idx) {
            yield "typicalRandomFLoat" . sprintf("%02d", $idx) => ["field", self::randomFloat(-100000.0, -1.0), true,];
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
        self::assertSame($shouldPass, $rule->passes($field, $data), "The rule did not provide the expected result from passes().");
    }
}
