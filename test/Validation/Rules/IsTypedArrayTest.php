<?php

declare(strict_types=1);

namespace BeadTests\Validation\Rules;

use Bead\Validation\Rule;
use Bead\Validation\Rules\IsTypedArray;
use BeadTests\Framework\RuleTestCase;
use TypeError;

/**
 * Test case for the IsTypedArray validator rule.
 */
class IsTypedArrayTest extends RuleTestCase
{
    protected function ruleInstance(string $type = "string"): Rule
    {
        return new IsTypedArray($type);
    }

    /**
     * Data provider for testPasses1() - data that should pass the validation rule.
     *
     * @return iterable The test data.
     */
    public function dataForTestPasses1(): iterable
    {
        yield "short-string-array" => ["string", ["first", "second", "third",],];
        yield "empty-string-array" => ["string", [],];
        yield "large-string-array" => [
            "string",
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
        ];

        yield "short-int-array" => ["int", [1, 2, 3,],];
        yield "empty-int-array" => ["int", [],];
        yield "large-int-array" => [
            "int",
            [
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3,
            ],
        ];

        yield "short-integer-array" => ["integer", [1, 2, 3,],];
        yield "empty-integer-array" => ["integer", [],];
        yield "large-integer-array" => [
            "integer",
            [
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3,
                1, 2, 3, 1, 2, 3, 1, 2, 3,
            ],
        ];

        yield "short-float-array" => ["float", [1.1, 2.2, 3.3,],];
        yield "empty-float-array" => ["float", [],];
        yield "large-float-array" => [
            "float",
            [
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
            ],
        ];

        yield "short-double-array" => ["double", [1.1, 2.2, 3.3,],];
        yield "empty-double-array" => ["double", [],];
        yield "large-double-array" => [
            "double",
            [
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 
                1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3, 1.1, 2.2, 3.3,
            ],
        ];

        yield "short-bool-array" => ["bool", [true, false, false,],];
        yield "empty-bool-array" => ["bool", [],];
        yield "large-bool-array" => [
            "bool",
            [
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false, 
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false,
            ],
        ];

        yield "short-boolean-array" => ["boolean", [true, false, false,],];
        yield "empty-boolean-array" => ["boolean", [],];
        yield "large-boolean-array" => [
            "boolean",
            [
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false, 
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false, true, false, false,
                true, false, false, true, false, false, true, false, false, true, false, false,
            ],
        ];

        yield "short-array-array" => ["array", [[1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],],];
        yield "empty-array-array" => ["array", [],];
        yield "large-array-array" => [
            "array",
            [
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
                [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",], [1, 2, 3,], [1.1, 2.2, 3.3,], ["a", "B", "c",],
            ],
        ];

        $object = new self();

        yield "short-class-array" => [self::class, [$object, $object, $object,],];
        yield "empty-class-array" => [self::class, [],];
        yield "large-class-array" => [
            self::class,
            [
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
            ],
        ];

        yield "short-object-array" => ["object", [$object, $object, $object,],];
        yield "empty-object-array" => ["object", [],];
        yield "large-object-array" => [
            "object",
            [
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
                $object, $object, $object, $object, $object, $object,
            ],
        ];
    }

    /**
     * Ensure passes() accepts valid arrays.
     *
     * @dataProvider dataForTestPasses1
     */
    public function testPasses1(string $type, array $data): void
    {
        $rule = $this->ruleInstance($type);
        self::assertTrue($rule->passes("field", $data));
    }

    /**
     * Data provider for testPasses1() - data that should not pass the validation rule.
     *
     * @return iterable The test data.
     */
    public function dataForTestPasses2(): iterable
    {
        // non-array values
        foreach (["int", "integer", "float", "double", "bool", "boolean", "string", self::class, "object"] as $type) {
            yield "{$type}-int" => [$type, 1,];
            yield "{$type}-float" => [$type, 1.1,];
            yield "{$type}-bool" => [$type, true,];
            yield "{$type}-string" => [$type, "string",];
            yield "{$type}-class" => [$type, new self(),];
        }

        $object = new self();

        yield "int-array-float" => ["int", [1, 2, 1.1, 3,],];
        yield "int-array-string" => ["int", [1, 2, "string", 3,],];
        yield "int-array-bool" => ["int", [1, 2, true, 3,],];
        yield "int-array-array" => ["int", [1, 2, [], 3,],];
        yield "int-array-class" => ["int", [1, 2, $object, 3,],];

        yield "integer-array-float" => ["integer", [1, 2, 1.1, 3,],];
        yield "integer-array-string" => ["integer", [1, 2, "string", 3,],];
        yield "integer-array-bool" => ["integer", [1, 2, true, 3,],];
        yield "integer-array-array" => ["integer", [1, 2, [], 3,],];
        yield "integer-array-class" => ["integerint", [1, 2, $object, 3,],];

        yield "float-array-int" => ["float", [1.1, 2.2, 1, 3.3,],];
        yield "float-array-string" => ["float", [1.1, 2.2, "string", 3.3,],];
        yield "float-array-bool" => ["float", [1.1, 2.2, true, 3.3,],];
        yield "float-array-array" => ["float", [1.1, 2.2, [], 3.3,],];
        yield "float-array-class" => ["float", [1.1, 2.2, $object, 3.3,],];

        yield "double-array-int" => ["double", [1.1, 2.2, 1, 3.3,],];
        yield "double-array-string" => ["double", [1.1, 2.2, "string", 3.3,],];
        yield "double-array-bool" => ["double", [1.1, 2.2, true, 3.3,],];
        yield "double-array-array" => ["double", [1.1, 2.2, [], 3.3,],];
        yield "double-array-class" => ["double", [1.1, 2.2, $object, 3.3,],];

        yield "bool-array-int" => ["bool", [true, true, 1, false,],];
        yield "bool-array-float" => ["bool", [true, true, 1.1, false,],];
        yield "bool-array-string" => ["bool", [true, true, "string", false,],];
        yield "bool-array-array" => ["bool", [true, true, [], false,],];
        yield "bool-array-class" => ["bool", [true, true, $object, false,],];

        yield "boolean-array-int" => ["boolean", [true, true, 1, false,],];
        yield "boolean-array-float" => ["boolean", [true, true, 1.1, false,],];
        yield "boolean-array-string" => ["boolean", [true, true, "string", false,],];
        yield "boolean-array-array" => ["boolean", [true, true, [], false,],];
        yield "boolean-array-class" => ["boolean", [true, true, $object, false,],];

        yield "string-array-int" => ["string", ["one", "three", 1, "five",],];
        yield "string-array-float" => ["string", ["one", "three", 1.1, "five",],];
        yield "string-array-bool" => ["string", ["one", "three", false, "five",],];
        yield "string-array-array" => ["string", ["one", "three", [], "five",],];
        yield "string-array-class" => ["string", ["one", "three", $object, "five",],];

        yield "array-array-int" => ["array", [["one",], ["three",], 1, ["five",],],];
        yield "array-array-float" => ["array", [["one",], ["three",], 1.1, ["five",],],];
        yield "array-array-bool" => ["array", [["one",], ["three",], false, ["five",],],];
        yield "array-array-string" => ["array", [["one",], ["three",], "string", ["five",],],];
        yield "array-array-class" => ["array", [["one",], ["three",], $object, ["five",],],];

        yield "class-array-int" => ["array", [$object, $object, 1, $object,],];
        yield "class-array-float" => ["array", [$object, $object, 1.1, $object,],];
        yield "class-array-bool" => ["array", [$object, $object, false, $object,],];
        yield "class-array-string" => ["array", [$object, $object, "string", $object,],];
        yield "class-array-array" => ["array", [$object, $object, [$object], $object,],];
    }

    /**
     * Ensure passes() rejects invalid arrays.
     *
     * @dataProvider dataForTestPasses2
     */
    public function testPasses2(string $type, mixed $data): void
    {
        $rule = $this->ruleInstance($type);
        self::assertFalse($rule->passes("field", $data));
    }

}
