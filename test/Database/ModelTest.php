<?php

namespace BeadTests\Database;

use BeadTests\Framework\CallTracker;
use BeadTests\Framework\TestCase;
use DateTime;
use Bead\Database\Model;
use Bead\Exceptions\Database\ModelPropertyCastException;
use LogicException;
use PDO;
use ReflectionProperty;
use TypeError;

use function Bead\Helpers\Iterable\all;

/**
 * Test case for the database Model class.
 *
 * Currently only tests for property set/get are present.
 */
class ModelTest extends TestCase
{
    /**
     * Helper to create a mock model with a given set of properties and a given initial state.
     *
     * @param array $properties The property definitions for the model.
     * @param array $data The initial data for the model.
     *
     * @return Model A mock model.
     */
    protected function createModel(array $properties, array $data): Model
    {
        return new class ($this->createMock(PDO::class), $properties, $data) extends Model
        {
            private static PDO $connection;

            public function __construct(PDO $connection, array $properties = [], array $data = [])
            {
                static::$connection = $connection;
                parent::__construct();
                static::$properties = $properties;
                $this->data = $data;
            }

            public static function defaultConnection(): PDO
            {
                return static::$connection;
            }
        };
    }

    /**
     * Data provider for testProperty().
     *
     * @return iterable
     *
     * @noinspection PhpDocMissingThrowsInspection DateTime constructor won't throw with our test data.
     */
    public function dataForTestProperties(): iterable
    {
        yield from [
            "typicalStringProperty" => [
                [
                    "id" => "int",
                    "name" => "string",
                    "email" => "string",
                ],
                [
                    "id" => 1,
                    "name" => "Darren",
                    "email" => "bead-framework@equituk.net",
                ],
                "name",
                "Darren",
                "Susan",
                "Susan",
            ],
            "extremeStringPropertyEmpty" => [
                [
                    "id" => "int",
                    "name" => "string",
                    "email" => "string",
                ],
                [
                    "id" => 1,
                    "name" => "Darren",
                    "email" => "bead-framework@equituk.net",
                ],
                "name",
                "Darren",
                "",
                "",
            ],
            "typicalIntProperty" => [
                [
                    "id" => "int",
                    "name" => "string",
                    "email" => "string",
                ],
                [
                    "id" => 1,
                    "name" => "Darren",
                    "email" => "bead-framework@equituk.net",
                ],
                "id",
                1,
                2,
                2,
            ],
            "typicalTimestampPropertyDateTime" => [
                [
                    "id" => "int",
                    "name" => "string",
                    "email" => "string",
                    "created_at" => "timestamp",
                ],
                [
                    "id" => 1,
                    "name" => "Darren",
                    "email" => "bead-framework@equituk.net",
                    "created_at" => 0,
                ],
                "created_at",
                0,
                new DateTime("@" . (60 * 60 * 24)),
                (60 * 60 * 24),
            ],
            "typicalTimestampPropertyInt" => [
                [
                    "id" => "int",
                    "name" => "string",
                    "email" => "string",
                    "created_at" => "timestamp",
                ],
                [
                    "id" => 1,
                    "name" => "Darren",
                    "email" => "bead-framework@equituk.net",
                    "created_at" => 0,
                ],
                "created_at",
                0,
                (60 * 60 * 24),
                (60 * 60 * 24),
            ],
            "invalidNonExistentProperty" => [
                [
                    "id" => "int",
                    "name" => "string",
                    "email" => "string",
                ],
                [
                    "id" => 1,
                    "name" => "Darren",
                    "email" => "bead-framework@equituk.net",
                ],
                "foo",
                "",
                "",
                "",
                LogicException::class,
            ],
            "invalidWrongDataType" => [
                [
                    "id" => "int",
                    "name" => "string",
                    "email" => "string",
                ],
                [
                    "id" => 1,
                    "name" => "Darren",
                    "email" => "bead-framework@equituk.net",
                ],
                "id",
                1,
                "foo",
                "foo",
                TypeError::class,
            ],
        ];

        // 50 random DateTime columns
        for ($idx = 0; $idx < 50; ++$idx) {
            /** @noinspection PhpUnhandledExceptionInspection DateTime constructor won't throw with our test data. */
            $original = new DateTime("@" . mt_rand(0, 60 * 60 * 24 * 265 * 40));
            /** @noinspection PhpUnhandledExceptionInspection DateTime constructor won't throw with our test data. */
            $subsequent = new DateTime("@" . mt_rand(0, 60 * 60 * 24 * 265 * 40));

            yield "typicalDateTimePropertyDateTime" . sprintf("%02d", $idx) => [
                [
                    "id" => "int",
                    "name" => "string",
                    "email" => "string",
                    "created_at" => "datetime",
                ],
                [
                    "id" => 1,
                    "name" => "Darren",
                    "email" => "bead-framework@equituk.net",
                    "created_at" => $original->format("Y-m-d H:i:s"),
                ],
                "created_at",
                $original,
                $subsequent,
                $subsequent,
            ];
        }

        // 50 random DateTime columns as strings
        for ($idx = 0; $idx < 50; ++$idx) {
            /** @noinspection PhpUnhandledExceptionInspection DateTime constructor won't throw with our test data. */
            $original = new DateTime("@" . mt_rand(0, 60 * 60 * 24 * 265 * 40));
            /** @noinspection PhpUnhandledExceptionInspection DateTime constructor won't throw with our test data. */
            $subsequent = new DateTime("@" . mt_rand(0, 60 * 60 * 24 * 265 * 40));

            yield "typicalDateTimePropertyString" . sprintf("%02d", $idx) => [
                [
                    "id" => "int",
                    "name" => "string",
                    "email" => "string",
                    "created_at" => "datetime",
                ],
                [
                    "id" => 1,
                    "name" => "Darren",
                    "email" => "bead-framework@equituk.net",
                    "created_at" => $original->format("Y-m-d H:i:s"),
                ],
                "created_at",
                $original,
                $subsequent->format("Y-m-d H:i:s"),
                $subsequent,
            ];
        }
    }

    /**
     * @dataProvider dataForTestProperties
     *
     * @param array $modelProperties The properties and their types for the mock model class.
     * @param array $modelData The data for the mock model instance.
     * @param string $property The property to test with.
     * @param mixed $expectedValue The value it's expected to return initially.
     * @param mixed $newValue The value to set it to.
     * @param mixed $expectedNewValue The value it's expected to return after being set.
     * @param string|null $expectedException The exception expected to be thrown, if any.
     */
    public function testProperties(array $modelProperties, array $modelData, string $property, $expectedValue, $newValue, $expectedNewValue, ?string $expectedException = null): void
    {
        if (isset($expectedException)) {
            $this->expectException($expectedException);
        }

        $model = static::createModel($modelProperties, $modelData);
        self::assertEquals($expectedValue, $model->{$property}, "The model's {$property} property does not match the expected value.");
        $model->{$property} = $newValue;
        self::assertEquals($expectedNewValue, $model->{$property}, "The model's {$property} property was not set to the expected value.");
    }

    /**
     * Test data provider for testCustomAccessor.
     *
     * @return array The test data.
     */
    public function dataForTestCustomAccessor(): array
    {
        return [
            ["bar,baz", ["bar", "baz",],],
            [null, [],],
            ["barbaz", ["barbaz"],],
            ["", [],],
            [" ", [" "],],
        ];
    }

    /**
     * @dataProvider dataForTestCustomAccessor
     *
     * @param mixed $value The test value.
     * @param mixed $expected The expected value of the foo property.
     * @param string|null $exceptionClass The exception expected to be thrown, if any.
     *
     * @return void
     */
    public function testCustomAccessor($value, $expected, ?string $exceptionClass = null): void
    {
        $connection = $this->createMock(PDO::class);
        $callTracker = new CallTracker();

        $model = new class ($connection, $callTracker) extends Model
        {
            protected static PDO $connection;
            protected CallTracker $callTracker;

            protected static array $properties = [
                "foo_bar" => "string",
            ];

            public function __construct(PDO $connection, $callTracker)
            {
                static::$connection = $connection;
                $this->callTracker = $callTracker;
            }

            public static function defaultConnection(): PDO
            {
                return static::$connection;
            }

            protected function getFooBarProperty(): ?array
            {
                $this->callTracker->increment();
                return empty($this->data["foo_bar"]) ? [] :  explode(",", $this->data["foo_bar"]);
            }
        };

        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $model->foo_bar = $value;
        $actual = $model->foo_bar;
        self::assertIsArray($actual, "Value of foo_bar property expected to be array.");
        self::assertEquals($expected, $actual, "Value of foo_bar does not match expected.");
        self::assertEquals(1, $callTracker->callCount(), "Custom accessor was not called the correct number of times.");
    }

    /**
     * Test data provider for testCustomMutator.
     *
     * @return array The test data.
     */
    public function dataForTestCustomMutator(): array
    {
        return [
            ["bar,baz", "bar,baz",],
            [null, "",],
            [["bar", "baz",], "bar,baz",],
            ["", "",],
            [" ", " ",],
            [
                new class
                {
                },
                null,
                TypeError::class,
            ]
        ];
    }

    /**
     * @dataProvider dataForTestCustomMutator
     *
     * @param mixed $value The test value.
     * @param mixed $expected The expected value of the "foo" member in the Model data array.
     * @param string|null $exceptionClass The exception expected to be thrown, if any.
     */
    public function testCustomMutator($value, $expected, ?string $exceptionClass = null): void
    {
        $connection = $this->createMock(PDO::class);
        $callTracker = new CallTracker();

        $model = new class ($connection, $callTracker) extends Model
        {
            protected static PDO $connection;
            private CallTracker $callTracker;

            protected static array $properties = [
                "foo_bar" => "string",
            ];

            public function __construct(PDO $connection, CallTracker $tracker)
            {
                static::$connection = $connection;
                $this->callTracker = $tracker;
            }

            public static function defaultConnection(): PDO
            {
                return static::$connection;
            }

            protected function setFooBarProperty($value): void
            {
                $this->callTracker->increment();

                if (!isset($value)) {
                    $this->data["foo_bar"] = "";
                } elseif (is_array($value) && all($value, "is_string")) {
                    $this->data["foo_bar"] = implode(",", $value);
                } elseif (is_string($value)) {
                    $this->data["foo_bar"] = $value;
                } else {
                    throw new ModelPropertyCastException(self::class, "foo_bar", $value, "The value cannot be cast to a comma-delimited array of strings.");
                }
            }
        };

        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $modelData = new ReflectionProperty(Model::class, "data");
        $modelData->setAccessible(true);

        $model->foo_bar = $value;
        $actual = $modelData->getValue($model)["foo_bar"];
        self::assertIsString($actual, "Value of foo_bar property expected to be string.");
        self::assertEquals($expected, $actual, "Value of foo_bar does not match expected.");
        self::assertEquals(1, $callTracker->callCount(), "Custom mutator was not called the correct number of times.");
    }
}
