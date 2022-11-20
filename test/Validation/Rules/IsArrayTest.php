<?php

declare(strict_types=1);

namespace BeadTests\Validation\Rules;

use Bead\Validation\Rule;
use Bead\Validation\Rules\IsArray;
use Generator;
use BeadTests\Framework\RuleTestCase;
use TypeError;

/**
 * Test case for the IsString validator rule.
 */
class IsArrayTest extends RuleTestCase
{
    /**
     * @inheritDoc
     */
    protected function ruleInstance(): Rule
    {
        return new IsArray();
    }

    /**
     * Data provider for testPasses().
     *
     * @return \Generator The test data.
     */
    public function dataForTestPasses(): Generator
    {
        yield from [
            "typicalArray" => ["field", ["first", "second", "third",], true,],
            "typicalEmptyArray" => ["field", [], true,],
            "typicalLargeArray" => [
                "field",
                [
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                    "first", "second", "third", "first", "second", "third", "first", "second", "third",
                ],
                true,
            ],
            "typicalNestedArray" => ["field", [["first", "second", "third",], ["first", "second", "third",], ["first", "second", "third",], ], true,],
            "typicalString" => ["field", "[]", false,],
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
            "typicalTrue" => ["field", true, false,],
            "typicalFalse" => ["field", false, false,],
            "typicalNull" => ["field", null, false,],

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
