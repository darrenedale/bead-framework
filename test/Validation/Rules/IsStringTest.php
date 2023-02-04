<?php

declare(strict_types=1);

namespace BeadTests\Validation\Rules;

use Bead\Validation\Rule;
use Bead\Validation\Rules\IsString;
use Generator;
use BeadTests\Framework\RuleTestCase;
use TypeError;

/**
 * Test case for the IsString validator rule.
 */
class IsStringTest extends RuleTestCase
{
    /**
     * @inheritDoc
     */
    protected function ruleInstance(): Rule
    {
        return new IsString();
    }

    /**
     * Data provider for testPasses().
     *
     * @return \Generator The test data.
     */
    public function dataForTestPasses(): Generator
    {
        yield from [
            "typicalString" => ["field", "string", true,],
            "typicalEmptyString" => ["field", "", true,],
            "typicalLongString" => ["field", "jkdlfhgkhfoiwanvkasd nklvnbkjabfjwabfkljdsanlfkbasdkjhfbsdajkfbkjsadb", true,],
            "extremeStringable" => ["field", new class
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
            "typicalArray" => ["field", ["string",], false,],

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
        self::assertSame($shouldPass, $rule->passes($field, $data), "The rule did not provide the expected result from passes().");
    }
}
