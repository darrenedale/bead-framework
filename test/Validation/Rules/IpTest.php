<?php

declare(strict_types=1);

namespace BeadTests\Validation\Rules;

use Bead\Validation\Rule;
use Bead\Validation\Rules\Ip;
use Generator;
use BeadTests\Framework\RuleTestCase;
use TypeError;

/**
 * Test case for the Ip validator rule.
 */
class IpTest extends RuleTestCase
{
    /**
     * @inheritDoc
     */
    protected function ruleInstance(): Rule
    {
        return new Ip();
    }

    /**
     * Data provider for testPasses().
     *
     * @return \Generator The test data.
     */
    public function dataForTestPasses(): Generator
    {
        yield from [
            "typicalLocalhost" => ["field", "127.0.0.1", true,],
            "typicalBroadcast" => ["field", "0.0.0.0", true,],
            "typicalClassC" => ["field", "192.168.1.1", true,],
            "typicalNonAddress" => ["field", "foo", false,],
            "extremeAlmostLocalhost" => ["field", "127.0.0.", false,],
            "extremeAlmostClassC" => ["field", "192.168.1.", false,],
            "extremeOneComponentJustTooLarge" => ["field", "255.255.255.256", false,],
            "extremeSpaceBeforeIp" => ["field", " 192.168.1.1", false,],
            "extremeSpaceAfterIp" => ["field", "192.168.1.1 ", false,],
            "extremeSpaceInIp" => ["field", " 192.168. 1.1", false,],
            "extremeIntLocalhost" => ["field", ip2long("127.0.0.1"), false,],
            "extremeIntClassC" => ["field", ip2long("192.168.1.1"), false,],
            "typicalInt" => ["foo", 1, false,],
            "typicalFloat" => ["field", 1.5, false,],
            "typicalArray" => ["field", [1, 2, 3, 4, 5,], false,],
            "extremeArrayLocalhost" => ["field", ["127.0.0.1",], false,],
            "extremeObjectLocalhost" => ["field", (object)["127.0.0.1",], false,],
            "extremeStringableLocalhost" => ["field", new class
            {
                public function __toString(): string
                {
                    return "127.0.0.1";
                }
            }, true,],
            "extremeArrayClassC" => ["field", ["192.168.1.1",], false,],
            "extremeObjectClassC" => ["field", (object)["192.168.1.1",], false,],
            "extremeStringableClassC" => ["field", new class
            {
                public function __toString(): string
                {
                    return "192.168.1.1";
                }
            }, true,],
            "extremeArrayBroadcast" => ["field", ["0.0.0.0",], false,],
            "extremeObjectBroadcast" => ["field", (object)["0.0.0.0",], false,],
            "extremeStringableBroadcast" => ["field", new class
            {
                public function __toString(): string
                {
                    return "0.0.0.0";
                }
            }, true,],
            "extremeArrayMaxIp" => ["field", ["255.255.255.255",], false,],
            "extremeObjectMaxIp" => ["field", (object)["255.255.255.255",], false,],
            "extremeStringableMaxIp" => ["field", new class
            {
                public function __toString(): string
                {
                    return "255.255.255.255";
                }
            }, true,],
            "typicalObject" => ["field", (object)[], false,],
            "typicalAnonymousClass" => ["field", new class{}, false,],
            "typicalIntClassC" => ["field", ip2long("192.168.1.1"), false,],
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
