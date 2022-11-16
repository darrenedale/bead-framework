<?php

declare(strict_types=1);

namespace BeadTests\Validation\Rules;

use Equit\Validation\Rule;
use Equit\Validation\Rules\Alphanumeric;
use Generator;
use BeadTests\Framework\RuleTestCase;
use TypeError;

/**
 * Test case for the Alphanumeric validator rule.
 */
class AlphanumericTest extends RuleTestCase
{
    /**
     * @inheritDoc
     */
    protected function ruleInstance(): Rule
    {
        return new Alphanumeric();
    }

    /**
     * Data provider for testPasses().
     *
     * @return \Generator The test data.
     */
    public function dataForTestPasses(): Generator
    {
        yield from [
            "typicalAlphaString" => ["field", "alpha", true,],
            "typicalNumericString" => ["field", "12345", true,],
            "typicalAlphanumericString" => ["field", "alpha12345", true,],
            "typicalShortAlphaString" => ["field", "a", true,],
            "typicalShortNumericString" => ["field", "1", true,],
            "typicalShortAlphanumericString" => ["field", "a1", true,],
            "typicalLongAlphaString" => ["field", "hachabitasseplateadictumstvestibulumrhoncusestpellentesqueelitullamcorperdignissimcrastinciduntlobortisfeugiatvivamusataugueegetarcu", true,],
            "typicalLongNumericString" => ["field", "29658769798257984651237407542658409237406594870136536473938706501526598476587461061047365764537638566463868932674386712647913867861476", true,],
            "typicalLongAlphanumericString" => ["field", "hach145425a5bitass124eplat52e3a34dictum15s3tve4stib423u4l125umrh41oncu52se13s4tpellentesque42e3lit5ullamcorp5er41dign4i1ssi5mc23ras4tinci51d3unt6lob9orti756sfe8ugiatviv32amu353sataug355u6eeg6etarcu", true,],
            "extremeEmptyString" => ["field", "", false,],
            "extremeAllSymbolsString" => ["field", "%\$&^%!*&><?:@}_+:", false,],
            "extremeLongAlphaStringSingleSpaceEnd" => ["field", "hachabitasseplateadictumstvestibulumrhoncusestpellentesqueelitullamcorperdignissimcrastinciduntlobortisfeugiatvivamusataugueegetarcu ", false,],
            "extremeLongAlphaStringSingleSpaceStart" => ["field", " hachabitasseplateadictumstvestibulumrhoncusestpellentesqueelitullamcorperdignissimcrastinciduntlobortisfeugiatvivamusataugueegetarcu", false,],
            "extremeLongAlphaStringSingleSpaceInline" => ["field", "hachabitasseplateadictumstvestibulumrhoncusestpellentesqueelitullamcorperdi gnissimcrastinciduntlobortisfeugiatvivamusataugueegetarcu", false,],
            "typicalLongNumericStringSpaceEnd" => ["field", "29658769798257984651237407542658409237406594870136536473938706501526598476587461061047365764537638566463868932674386712647913867861476 ", false,],
            "typicalLongNumericStringSpaceStart" => ["field", " 29658769798257984651237407542658409237406594870136536473938706501526598476587461061047365764537638566463868932674386712647913867861476", false,],
            "typicalLongNumericStringSpaceInline" => ["field", "296587697982579846512374075426584092374065948701365364739387065015265984765874610610 47365764537638566463868932674386712647913867861476", false,],
            "extremeStringableAlpha" => ["field", new class
            {
                public function __toString(): string
                {
                    return "alpha";
                }
            }, false,],
            "extremeStringableNumeric" => ["field", new class
            {
                public function __toString(): string
                {
                    return "12345";
                }
            }, false,],
            "extremeStringableAlphanumeric" => ["field", new class
            {
                public function __toString(): string
                {
                    return "alpha12345";
                }
            }, false,],
            "typicalNonAlphaString" => ["field", "non-alpha string", false,],
            "typicalInt" => ["field", 1, false,],
            "typicalFloat" => ["field", 1.5, false,],
            "typicalArray" => ["field", [1, 2, 3, 4, 5,], false,],
            "typicalObject" => ["field", (object)["alpha",], false,],
            "typicalAnonymousClass" => ["field", new class{}, false,],
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

        // 100 random numeric strings
        for ($idx = 0; $idx < 100; ++$idx) {
            yield "typicalRandomNumericString" . sprintf("%02d", $idx) => ["field", self::randomString(10, "0123456789"), true,];
        }

        // 100 random alphanumeric strings
        for ($idx = 0; $idx < 100; ++$idx) {
            yield "typicalRandomAlphanumericString" . sprintf("%02d", $idx) => ["field", self::randomString(10, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), true,];
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
}
