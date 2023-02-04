<?php

declare(strict_types=1);

namespace BeadTests\Validation\Rules;

use Bead\Validation\Rule;
use Bead\Validation\Rules\Email;
use Generator;
use BeadTests\Framework\RuleTestCase;
use TypeError;

/**
 * Test case for the Email validator rule.
 */
class EmailTest extends RuleTestCase
{
    /**
     * @inheritDoc
     */
    protected function ruleInstance(): Rule
    {
        return new Email();
    }

    /**
     * Data provider for testPasses().
     *
     * @return \Generator The test data.
     */
    public function dataForTestPasses(): Generator
    {
        yield from [
            "typicalAddress" => ["field", "darren@example.com", true,],
            "typicalShortAddress" => ["field", "m@e.com", true,],
            "typicalNonAddress" => ["field", "foo", false,],
            "typicalInt" => ["foo", 1, false,],
            "typicalFloat" => ["field", 1.5, false,],
            "typicalArray" => ["field", [1, 2, 3, 4, 5,], false,],
            "extremeArrayEmail" => ["field", ["darren@example.com",], false,],
            "extremeObjectEmail" => ["field", (object)["darren@example.com",], false,],
            "extremeStringableEmail" => ["field", new class
            {
                public function __toString(): string
                {
                    return "darren@example.com";
                }
            }, true,],
            "typicalObject" => ["field", (object)[], false,],
            "typicalAnonymousClass" => ["field", new class{}, false,],
            "typicalTrue" => ["field", true, false,],
            "typicalFalse" => ["field", false, false,],
            "typicalEmptyString" => ["field", "", false,],
            "typicalEmptyNull" => ["field", null, false,],
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
