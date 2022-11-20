<?php

declare(strict_types=1);

namespace BeadTests;

use BeadTests\Framework\TestCase;
use Generator;
use TypeError;

/**
 * Test for the functions in array.php.
 */
class ArrayFunctionsTest extends TestCase
{
    /**
     * Test recursiveCount()
     *
     * @dataProvider dataForTestRecursiveCount
     *
     * @param $data mixed The value to test with.
     * @param $expected int|string The expected recursive count result or the class name of an expected exception.
     */
    public function testRecursiveCount($data, $expected)
    {
        if (is_string($expected)) {
            $this->expectException($expected);
        }

        $this->assertSame($expected, recursiveCount($data));
    }

    /**
     * Data provider for testRecursiveCount.
     *
     * @return array The test data.
     */
    public function dataForTestRecursiveCount(): array
    {
        return [
            "typicalNested" => [[1, 2, 3, 4, 5, 6, 7, 8, 9, 0], 10,],
            "typicalMultipleNested" => [[[1, 2, 3], [], [4, 5]], 5,],
            "extremeEmpty" => [[], 0,],
            "extremeNestedEmpty" => [[[], [[],], [],], 0,],
            "extremeObject" => [
                (object) ["property" => "value",],
                1,
            ],
            "extremeAnonymousClass" => [
                new class {
                    public string $property = "value";
                },
                1,
            ],
            "extremeEmptyObject" => [
                (object) [],
                0,
            ],
            "extremeEmptyAnonymousClass" => [
                new class{},
                0,
            ],
            "extremeObjectWithArrayProperty" => [
                (object) [
                    "property" => ["value1", "value2", "value3",],
                ],
                3,
            ],
            "extremeAnonymousClassWithArrayProperty" => [
                new class
                {
                    public array $property = ["value1", "value2", "value3",];
                },
                3,
            ],
            "extremeObjectWithMultipleProperties" => [
                (object) [
                    "property1" => "value1",
                    "property2" => ["value1", "value2", "value3",],
                ],
                4,
            ],
            "extremeAnonymousClassWithMultipleProperties" => [
                new class
                {
                    public string $property1 = "value1";
                    public array $property2 = ["value1", "value2", "value3",];
                },
                4,
            ],
            "extremeNestedObjects" => [
                (object) [
                    "property" => "value1",
                    "arrayProperty" => ["value1", "value2", "value3",],
                    "objectProperty" => (object) [
                        "property" => "value1",
                        "arrayProperty" => ["value1", "value2", "value3",],
                    ],
                ],
                8,
            ],
            "extremeNestedAnonymousClasses" => [
                new class
                {
                    public string $property = "value1";
                    public array $arrayProperty = ["value1", "value2", "value3",];
                    public object $objectProperty;

                    public function __construct()
                    {
                        $this->objectProperty = new class
                        {
                            public string $property = "value1";
                            public array $arrayProperty = ["value1", "value2", "value3",];
                        };
                    }
                },
                // one for each $property (parent and nested), three for each $arrayProperty (parent and nested)
                // $objectProperty in the parent does not count as one since it is a traversable
                8,
            ],
            "extremeNestedEmptyObjects" => [
                (object) [(object)[], (object)[], (object)[],],
                0,
            ],
            "extremeNestedEmptyAnonymousClasses" => [
                new Class{
                    public object $property1;
                    public object $property2;
                    public object $property3;

                    public function __constructor()
                    {
                        $this->property1 = new class{};
                        $this->property2 = new class{};
                        $this->property3 = new class{};
                    }
                },
                0,
            ],
            "invalidNull" => [null, TypeError::class,],
            "invalidString" => ["string", TypeError::class,],
            "invalidInt" => [0, TypeError::class,],
            "invalidFloat" => [1.534, TypeError::class,],
            "invalidTrue" => [true, TypeError::class,],
            "invalidFalse" => [false, TypeError::class,],
        ];
    }

    /**
     * Test flatten()
     *
     * @dataProvider dataForTestFlatten
     *
     * @param $data mixed The value to test with.
     * @param $expected array|string The expected flattened array or the class name of an expected exception.
     */
    public function testFlatten($data, $expected)
    {
        if (is_string($expected)) {
            $this->expectException($expected);
        }

        $this->assertEquals($expected, flatten($data));
    }

    /**
     * Data provider for testFlatten()
     *
     * @return array The test data.
     */
    public function dataForTestFlatten(): array
    {
        return [
            // array to flatten, expected flattened array or expected exception class
            "typicalFlat" => [
                [1, 2, 3, 4, 5, 6, 7, 8, 9, 0],
                [1, 2, 3, 4, 5, 6, 7, 8, 9, 0],
            ],
            "typicalSmallNested" => [
                [[1, 2, 3], [], [4, 5]],
                [1, 2, 3, 4, 5],
            ],
            "typicalLargerNested" => [
                [[1, 2, 3, 4, 5], [6, 7], [8, 9]],
                [1, 2, 3, 4, 5, 6, 7, 8, 9],
            ],
            "extremeSomeEmptyNested" => [
                [[], [], [], [1, 2, 3, 4, 5, 6, 7, 8, 9]],
                [1, 2, 3, 4, 5, 6, 7, 8, 9],
            ],
            "extremeEmpty" => [
                [],
                [],
            ],
            "extremeNestedEmpty" => [
                [[], [], []],
                [],
            ],
            "invalidObject" => [
                (object) [],
                TypeError::class,
            ],
            "invalidAnonymousClass" => [
                new class{},
                TypeError::class,
            ],
            "invalidString" => [
                "string",
                TypeError::class,
            ],
            "invalidNull" => [
                null,
                TypeError::class,
            ],
            "invalidInt" => [
                1,
                TypeError::class,
            ],
            "invalidFloat" => [
                1.563,
                TypeError::class,
            ],
            "invalidTrue" => [
                true,
                TypeError::class,
            ],
            "invalidFalse" => [
                false,
                TypeError::class,
            ],
        ];
    }

    /**
     * Test grammaticalImplode().
     *
     * @param $arr mixed The value to test with. Not type hinted so that we can test exceptions with invalid values.
     * @param $glue mixed The glue to test with. Will be null when testing with default parameter value. Not type hinted
     * so that we can test exceptions with invalid glue types.
     * @param $lastGlue mixed The last glue to test with. Will be null when testing with default parameter value. Not
     * type hinted so that we can test exceptions with invalid last glue types.
     * @param $expected string The expected imploded string. This is effectively redundant if an exception is expected.
     * @param $expectedException string|null The expected exception class, if any.
     *
     * @dataProvider dataForGrammaticalImplode
     */
    public function testGrammaticalImplode($arr, $glue, $lastGlue, string $expected, ?string $expectedException = null)
    {
        if (isset($expectedException)) {
            $this->expectException($expectedException);
        }

        if (isset($lastGlue)) {
            $this->assertSame($expected, grammaticalImplode($arr, $glue, $lastGlue));
        } else if (isset($glue)) {
            $this->assertSame($expected, grammaticalImplode($arr, $glue));
        } else {
            $this->assertSame($expected, grammaticalImplode($arr));
        }
    }

    /**
     * Data provider for testGrammaticalImplode()
     *
     * Uses a generator so that optional test data for later PHP versions can be included easily.
     * 
     * @return \Generator The test data.
     */
    public function dataForGrammaticalImplode(): Generator
    {
        yield from [
            // array to implode, glue, last glue, expected string
            "typical" => [
                ["Hello", "Goodbye"],
                ",",
                " or ",
                "Hello or Goodbye",
            ],
            "typicalWithDefaultArgs" => [
                ["Hello", "Goodbye"],
                null,
                null,
                "Hello and Goodbye",
            ],
            "typicalLongerArray" =>[
                ["Darren", "Junaid", "Susan", "Gillian", "Frederick"],
                "; ",
                " or ",
                "Darren; Junaid; Susan; Gillian or Frederick",
            ],
            "typicalWithDefaultFinalGlue" =>[
                ["Darren", "Junaid", "Susan", "Gillian", "Frederick"],
                "; ",
                null,
                "Darren; Junaid; Susan; Gillian and Frederick",
            ],
            "typicalLongerArrayWithDefaultArgs" =>[
                ["Darren", "Junaid", "Susan", "Gillian", "Frederick"],
                null,
                null,
                "Darren, Junaid, Susan, Gillian and Frederick",
            ],
            "invalidInt" => [
                1,
                ", ",
                " and ",
                "1",
                TypeError::class,
            ],
            "invalidFloat" => [
                1.546,
                ", ",
                " and ",
                "1.546",
                TypeError::class,
            ],
            "invalidObject" => [
                (object)["one", "two",],
                ", ",
                " and ",
                "one and two",
                TypeError::class,
            ],
            "invalidAnonymousClass" => [
                new class {
                    public string $one = "one";
                    public string $two = "two";
                },
                ", ",
                " and ",
                "one and two",
                TypeError::class,
            ],
            "invalidNull" => [
                null,
                ", ",
                " and ",
                "",
                TypeError::class,
            ],
            "invalidTrue" => [
                true,
                ", ",
                " and ",
                "1",
                TypeError::class,
            ],
            "invalidFalse" => [
                true,
                ", ",
                " and ",
                "0",
                TypeError::class,
            ],
            "invalidIntGlue" => [
                ["one", "two", "three",],
                1,
                " and ",
                "one1two and three",
                TypeError::class,
            ],
            "invalidFloatGlue" => [
                ["one", "two", "three",],
                1.546,
                " and ",
                "one1.546two and three",
                TypeError::class,
            ],
            "invalidNullGlue" => [
                ["one", "two", "three",],
                null,
                " and ",
                "onetwo and three",
                TypeError::class,
            ],
            "invalidObjectGlue" => [
                ["one", "two", "three",],
                (object) [
                    "__toString" => function(): string
                    {
                        return ", ";
                    },
                ],
                " and ",
                "one, two and three",
                TypeError::class,
            ],
            "invalidAnonymousClassGlue" => [
                ["one", "two", "three",],
                new class {
                    public function __toString(): string
                    {
                        return ", ";
                    }
                },
                " and ",
                "one, two and three",
                TypeError::class,
            ],
            "invalidIntLastGlue" => [
                ["one", "two", "three",],
                ", ",
                1,
                "one, two1three",
                TypeError::class,
            ],
            "invalidFloatLastGlue" => [
                ["one", "two", "three",],
                ", ",
                1.546,
                "one, two1.546three",
                TypeError::class,
            ],
            "invalidObjectLastGlue" => [
                ["one", "two", "three",],
                ", ",
                (object) [
                    "__toString" => function(): string
                    {
                        return " and ";
                    },
                ],
                "one, two and three",
                TypeError::class,
            ],
            "invalidAnonymousClassLastGlue" => [
                ["one", "two", "three",],
                ", ",
                new class {
                    public function __toString(): string
                    {
                        return " and ";
                    }
                },
                "one, two and three",
                TypeError::class,
            ],
        ];

        if (8 <= PHP_MAJOR_VERSION) {
            yield from [
                "invalidStringableGlue" => [
                    ["one", "two", "three",],
                    new class implements \Stringable {
                        public function __toString(): string
                        {
                            return ", ";
                        }
                    },
                    " and ",
                    "one, two and three",
                    TypeError::class,
                ],
                "invalidStringableLastGlue" => [
                    ["one", "two", "three",],
                    ", ",
                    new class implements \Stringable {
                        public function __toString(): string
                        {
                            return " and ";
                        }
                    },
                    "one, two and three",
                    TypeError::class,
                ],
            ];
        }
    }

    /**
     * Test removeEmptyElements()
     *
     * @param mixed $arr The value to test. Not type hinted so we can test exceptions with invalid types.
     * @param array $expected The expected array with empties removed. This is effectively redundant if we're expecting
     * an exception to be thrown.
     * @param string | null $expectedException The class name of the expected exception, if any.
     *
     * @dataProvider dataForTestRemoveEmptyElements
     */
    public function testRemoveEmptyElements($arr, array $expected, ?string $expectedException = null): void
    {
        if (isset($expectedException)) {
            $this->expectException($expectedException);
        }
        
        removeEmptyElements($arr);
        $this->assertEquals(array_values($arr), array_values($expected));
    }

    /**
     * Data provider for testRemoveEmptyElements()
     *
     * @return array
     */
    public function dataForTestRemoveEmptyElements(): array
    {
        return [
            // array to process, expected resulting array
            "typicalNoEmpties" => [[1, 2, 3], [1, 2, 3],],
            "typicalNoEmptiesWithZeroes" => [[0, 0.0, 1, 2, 3], [0, 0.0, 1, 2, 3],],
            "typicalEmptyString" => [["one", "two", "", "three"], ["one", "two", "three"],],
            "typicalMixedWithEmptyStrings" => [[1, "two", "", 3, 4, "five", "", 7], [1, "two", 3, 4, "five", 7],],
            "typicalMixedAssociativeWithEmptyStrings" => [["one" => 1, "two" => 2, "three" => "", "four" => 4], [1, 2, 4],],
            "typicalMixedNumericWithEmptyStrings" => [[0 => 1, 1 => 2, 2 => "three", 3 => "", 4 => 4], [0 => 1, 1 => 2, 2 => "three", 4 => 4],],
            "extremeEmptyArray" => [[], [],],
            "extremeAllEmptyStrings" => [["", "", "", "",], [],],
            "extremeAllNull" => [[null, null, null, null,], [],],
            "extremeAllEmptyArrays" => [[[], [], [], [],], [],],
            "extremeAllMixedEmptyTypes" => [[[], null, "",], [],],
            "invalidNull" => [null, [], TypeError::class,],
            "invalidInt" => [1, [], TypeError::class,],
            "invalidFloat" => [1.6546, [], TypeError::class,],
            "invalidString" => ["", [], TypeError::class,],
            "invalidObject" => [(object) ["", "",], [], TypeError::class,],
            "invalidAnonymousClass" => [new class{}, [], TypeError::class,],
            "invalidTrue" => [true, [], TypeError::class,],
            "invalidFalse" => [false, [], TypeError::class,],
        ];
    }
}
