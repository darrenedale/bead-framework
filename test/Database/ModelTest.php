<?php

namespace Equit\Test\Database;

use DateTime;
use Equit\Database\Model;
use Equit\Test\Framework\TestCase;
use Generator;
use LogicException;
use PDO;
use ReflectionProperty;
use TypeError;

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
        return new class($this->createMock(PDO::class), $properties, $data) extends Model
        {
            static PDO $connection;

            public function __construct(PDO $connection, array $properties = [], array $data = [])
            {
                static::$connection = $connection;
                parent::__construct();
                static::$properties = $properties;
                $dataProperty = new ReflectionProperty(Model::class, "data");
                $dataProperty->setAccessible(true);
                $dataProperty->setValue($this, $data);
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
     * @return Generator
     *
     * @noinspection PhpDocMissingThrowsInspection DateTime constructor won't throw with our test data.
     */
    public function dataForTestProperties(): Generator
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
        $this->assertEquals($expectedValue, $model->{$property}, "The model's {$property} property does not match the expected value.");
        $model->{$property} = $newValue;
        $this->assertEquals($expectedNewValue, $model->{$property}, "The model's {$property} property was not set to the expected value.");
    }
}
