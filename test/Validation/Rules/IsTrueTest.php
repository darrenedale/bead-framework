<?php

declare(strict_types=1);

namespace BeadTests\Validation\Rules;

use Equit\Validation\Rule;
use Equit\Validation\Rules\IsTrue;
use Generator;
use BeadTests\Framework\RuleTestCase;
use TypeError;

/**
 * Test case for the IsString validator rule.
 */
class IsTrueTest extends RuleTestCase
{
    /**
     * @inheritDoc
     */
    protected function ruleInstance(): Rule
    {
        return new IsTrue();
    }

    /**
     * Data provider for testPasses().
     *
     * @return \Generator The test data.
     */
    public function dataForTestPasses(): Generator
    {
        yield from [
            "typicalTrue" => ["field", true, true,],
            "typicalInt1" => ["field", 1, true,],
            "typicalStringOn" => ["field", "on", true,],
            "typicalStringYes" => ["field", "yes", true,],
            "typicalStringTrue" => ["field", "true", true,],
            "typicalStringOnUpper" => ["field", "ON", true,],
            "typicalStringYesUpper" => ["field", "YES", true,],
            "typicalStringTrueUpper" => ["field", "TRUE", true,],
            "typicalFalse" => ["field", false, false,],
            "typicalInt0" => ["field", 0, false,],
            "typicalStringOff" => ["field", "off", false,],
            "typicalStringNo" => ["field", "no", false,],
            "typicalStringFalse" => ["field", "false", false,],
            "typicalEmptyString" => ["field", "", false,],
            "extremeInt2" => ["field", 2, false,],
            "extremeInt-1" => ["field", -1, false,],
            "extremeIntMax" => ["field", PHP_INT_MAX, false,],
            "typicalStringable" => ["field", new class
            {
                public function __toString(): string
                {
                    return "string";
                }
            }, false,],
            "typicalObject" => ["field", (object)[], false,],
            "typicalAnonymousClass" => ["field", new class{}, false,],
            "typicalInt" => ["field", 123, false,],
            "typicalFloat" => ["field", 123.456, false,],
            "typicalNull" => ["field", null, false,],
            "typicalArray" => ["field", ["foo",], false,],
            "extremeStringableTrue" => ["field", new class
            {
                public function __toString(): string
                {
                    return "true";
                }
            }, false,],
            "extremeStringableInt1" => ["field", new class
            {
                public function __toString(): string
                {
                    return "1";
                }
            }, false,],
            "extremeStringableYes" => ["field", new class
            {
                public function __toString(): string
                {
                    return "yes";
                }
            }, false,],
            "extremeStringableOn" => ["field", new class
            {
                public function __toString(): string
                {
                    return "on";
                }
            }, false,],
            "extremeArrayStringTrue" => ["field", ["true",], false,],
            "extremeArrayTrue" => ["field", [true,], false,],
            "extremeArrayInt1" => ["field", [1,], false,],
            "extremeArrayStringOn" => ["field", ["on",], false,],
            "extremeArrayStringYes" => ["field", ["yes",], false,],

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
}
