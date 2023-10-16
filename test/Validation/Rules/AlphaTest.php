<?php

declare(strict_types=1);

namespace BeadTests\Validation\Rules;

use Bead\Validation\Rule;
use Bead\Validation\Rules\Alpha;
use Generator;
use BeadTests\Framework\RuleTestCase;
use TypeError;

/**
 * Test case for the Alpha validator rule.
 */
class AlphaTest extends RuleTestCase
{
    /**
     * @inheritDoc
     */
    protected function ruleInstance(): Rule
    {
        return new Alpha();
    }

    /**
     * Data provider for testPasses().
     *
     * @return \Generator The test data.
     */
    public function dataForTestPasses(): Generator
    {
        yield from [
            "typicalAlphaString" => ["field", "string", true,],
            "typicalShortAlphaString" => ["field", "a", true,],
            "typicalLongAlphaString" => ["field", "hachabitasseplateadictumstvestibulumrhoncusestpellentesqueelitullamcorperdignissimcrastinciduntlobortisfeugiatvivamusataugueegetarcu", true,],
            "extremeEmptyString" => ["field", "", false,],
            "extremeLongAlphaStringSingleSpaceEnd" => ["field", "hachabitasseplateadictumstvestibulumrhoncusestpellentesqueelitullamcorperdignissimcrastinciduntlobortisfeugiatvivamusataugueegetarcu ", false,],
            "extremeLongAlphaStringSingleSpaceStart" => ["field", " hachabitasseplateadictumstvestibulumrhoncusestpellentesqueelitullamcorperdignissimcrastinciduntlobortisfeugiatvivamusataugueegetarcu", false,],
            "extremeLongAlphaStringSingleSpaceInline" => ["field", "hachabitasseplateadictumstvestibulumrhoncusestpellentesqueelitullamcorperdi gnissimcrastinciduntlobortisfeugiatvivamusataugueegetarcu", false,],
            "extremeStringable" => ["field", new class
            {
                public function __toString(): string
                {
                    return "field";
                }
            }, false,],
            "typicalNonAlphaString" => ["field", "non-alpha string", false,],
            "typicalInt" => ["field", 1, false,],
            "typicalFloat" => ["field", 1.5, false,],
            "typicalArray" => ["field", [1, 2, 3, 4, 5,], false,],
            "typicalObject" => ["field", (object)["alpha",], false,],
            "typicalAnonymousClass" => [
                "field",
                new class
                {
                },
                false,
            ],
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
                    return "alpha";
                }
            }, "", false, TypeError::class,],
            "invalidArrayField" => [["field",], "", false, TypeError::class,],
            "invalidTrueField" => [true, "", false, TypeError::class,],
            "invalidFalseField" => [false, "", false, TypeError::class,],
        ];

        // 100 random alpha strings
        for ($idx = 0; $idx < 100; ++$idx) {
            yield "typicalRandomAlphaString" . sprintf("%02d", $idx) => ["field", self::randomString(10, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), true,];
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
