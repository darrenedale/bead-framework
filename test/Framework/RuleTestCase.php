<?php

declare(strict_types=1);

namespace Framework;

namespace BeadTests\Framework;

use Bead\Validation\Rule;
use Bead\Validation\Rules\Integer;
use TypeError;

/**
 * Base class for tests for validator Rule classes.
 *
 * Provides a test for the message() method. All messages should be provided as translations, which means we can't test
 * the actual content. What we can do is test that the method throws when given an invalid type for the field name and
 * that it always provides a string. This is common behaviour for all Rule classes, so this base class provides a test
 * that does this so that the test cases for individual rules don't need to repeat the same tests. Implementing classes
 * should return an instance of the appropriate Rule class from the ruleInstance() method.
 */
abstract class RuleTestCase extends TestCase
{
    /**
     * Fetch an instance of the Rule class to test.
     */
    abstract protected function ruleInstance(): Rule;

    /**
     * Data provider for testMessage().
     *
     * @return array The test data.
     */
    public function dataForTestMessage(): array
    {
        return [
            "typicalStringField" => ["field",],
            "extremeEmptyField" => ["",],

            "invalidIntField" => [1, TypeError::class,],
            "invalidFloatField" => [1.5, TypeError::class,],
            "invalidNullField" => [null, TypeError::class,],
            "invalidEmptyArrayField" => [[], TypeError::class,],
            "invalidStringableField" => [new class
            {
                public function __toString(): string
                {
                    return "field";
                }
            }, TypeError::class,],
            "invalidArrayField" => [["field",], TypeError::class,],
            "invalidTrueField" => [true, TypeError::class,],
            "invalidFalseField" => [false, TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestMessage
     *
     * @param mixed $field The field name.
     * @param string|null $exceptionClass The exception that should be thrown, if any.
     */
    public function testMessage($field, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $rule = new Integer();
        self::assertIsString($rule->message($field), "The message method did not produce a string.");
    }
}
