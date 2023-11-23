<?php

declare(strict_types=1);

namespace BeadTests\Validation\useBead\Validator;

use Bead\Testing\StaticXRay;
use Bead\Validation\Rule;
use Bead\Validation\Validator;
use BeadTests\Framework\TestCase;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Mockery;
use ReflectionNamedType;

final class ValidatorTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDonw(): void
    {
        Mockery::close();
    }

    private static function mockNamedType(string $typeName): ReflectionNamedType
    {
        $mock = Mockery::mock(ReflectionNamedType::class);
        $mock->shouldReceive("getName")->andReturn($typeName)->byDefault();
        return $mock;
    }

    private static function createDateTime(int $year, int $month, int $day, int $hour, int $minute, int $second, string $timezeone = "UTC"): DateTimeInterface
    {
        $dateTime = new DateTime();
        $dateTime->setDate($year, $month, $day);
        $dateTime->setTime($hour, $minute, $second);
        $dateTime->setTimezone(new DateTimeZone($timezeone));
        return $dateTime;
    }

    private static function createDateTimeImmutable(int $year, int $month, int $day, int $hour, int $minute, int $second, string $timezeone = "UTC"): \DateTimeImmutable
    {
        return (new DateTimeImmutable())
            ->setDate($year, $month, $day)
            ->setTime($hour, $minute, $second)
            ->setTimezone(new DateTimeZone($timezeone));
    }

    private static function typeOf(mixed $value): string
    {
        if (is_object($value)) {
            return $value::class;
        }

        return gettype($value);
    }

    public static function dataForTestConvertRuleConstructorArg1(): iterable
    {
        yield "string type" => ["bead", self::mockNamedType("string"), "bead",];
        yield "empty string for string type" => ["", self::mockNamedType("string"), "",];
        yield "int string for string type" => ["1", self::mockNamedType("string"), "1",];

        yield "0 for int type" => ["0", self::mockNamedType("int"), 0, ];
        yield "1 for int type" => ["1", self::mockNamedType("int"), 1,];
        yield "negative for int type" => ["-1", self::mockNamedType("int"), -1,];
        yield "leading whitespace for int type" => ["  2", self::mockNamedType("int"), 2,];
        yield "trailing whitespace for int type" => ["3  ", self::mockNamedType("int"), 3,];
        yield "leading and trailing whitespace for int type" => ["  4  ", self::mockNamedType("int"), 4,];

        yield "0.0 for float type" => ["0.0", self::mockNamedType("float"), 0.0, ];
        yield "1.1 for float type" => ["1.1", self::mockNamedType("float"), 1.1,];
        yield "int 0 for float type" => ["0", self::mockNamedType("float"), 0.0, ];
        yield "int 1 for float type" => ["1", self::mockNamedType("float"), 1.0,];
        yield "negative for float type" => ["-3.14159", self::mockNamedType("float"), -3.14159,];
        yield "leading whitespace for float type" => ["  2.7", self::mockNamedType("float"), 2.7,];
        yield "trailing whitespace for float type" => ["33.333333  ", self::mockNamedType("float"), 33.333333,];
        yield "leading and trailing whitespace for float type" => ["  42.314  ", self::mockNamedType("float"), 42.314,];

        yield "0 for bool type" => ["0", self::mockNamedType("bool"), false, ];
        yield "1 for bool type" => ["1", self::mockNamedType("bool"), true,];
        yield "true for bool type" => ["true", self::mockNamedType("bool"), true,];
        yield "false for bool type" => ["false", self::mockNamedType("bool"), false,];
        yield "true with leading whitespace for bool type" => ["   true", self::mockNamedType("bool"), true,];
        yield "false with leading whitespace for bool type" => ["   false", self::mockNamedType("bool"), false,];
        yield "true with trailing whitespace for bool type" => ["true   ", self::mockNamedType("bool"), true,];
        yield "false with trailing whitespace for bool type" => ["false   ", self::mockNamedType("bool"), false,];
        yield "true with leading and trailing whitespace for bool type" => ["   true   ", self::mockNamedType("bool"), true,];
        yield "false with leading and trailing whitespace for bool type" => ["   false   ", self::mockNamedType("bool"), false,];

        yield "single element array" => ["bead", self::mockNamedType("array"), ["bead",],];
        yield "two element array" => ["bead,framework", self::mockNamedType("array"), ["bead", "framework",],];
        yield "empty array" => ["", self::mockNamedType("array"), [],];
        yield "whitespace array" => ["  ", self::mockNamedType("array"), ["  "],];
        yield "empty elements" => [",,,", self::mockNamedType("array"), ["", "", "", "",],];

        yield "datetime ISO8601 Z" => ["2023-11-22T19:01:04Z", self::MockNamedType("DateTime"), self::createDateTime(2023, 11, 22, 19, 01, 04),];
        yield "datetime ISO8601 offset" => ["2022-10-18T11:43:12+01:00", self::MockNamedType("DateTime"), self::createDateTime(2022, 10, 18, 10, 43, 12, "+01:00"),];
        yield "datetime SQL" => ["2023-12-02 08:00:38", self::MockNamedType("DateTime"), self::createDateTime(2023, 12, 2, 8, 00, 38),];
        yield "datetime leading whitespace" => [" 2023-12-02 08:00:38", self::MockNamedType("DateTime"), self::createDateTime(2023, 12, 2, 8, 00, 38),];
        yield "datetime trailing whitespace" => ["2023-12-02 08:00:38 ", self::MockNamedType("DateTime"), self::createDateTime(2023, 12, 2, 8, 00, 38),];

        yield "datetimeinterface ISO8601 Z" => ["2023-11-22T19:01:04Z", self::MockNamedType("DateTimeInterface"), self::createDateTime(2023, 11, 22, 19, 01, 04),];
        yield "datetimeinterface ISO8601 offset" => ["2022-10-18T11:43:12+01:00", self::MockNamedType("DateTimeInterface"), self::createDateTime(2022, 10, 18, 10, 43, 12, "+01:00"),];
        yield "datetimeinterface SQL" => ["2023-12-02 08:00:38", self::MockNamedType("DateTimeInterface"), self::createDateTime(2023, 12, 2, 8, 00, 38),];
        yield "datetimeinterface leading whitespace" => [" 2023-12-02 08:00:38", self::MockNamedType("DateTimeInterface"), self::createDateTime(2023, 12, 2, 8, 00, 38),];
        yield "datetimeinterface trailing whitespace" => ["2023-12-02 08:00:38 ", self::MockNamedType("DateTimeInterface"), self::createDateTime(2023, 12, 2, 8, 00, 38),];

        yield "datetimeimmutable ISO8601 Z" => ["2023-11-22T19:01:04Z", self::MockNamedType("DateTimeImmutable"), self::createDateTimeImmutable(2023, 11, 22, 19, 01, 04),];
        yield "datetimeimmutable ISO8601 offset" => ["2022-10-18T11:43:12+01:00", self::MockNamedType("DateTimeImmutable"), self::createDateTimeImmutable(2022, 10, 18, 10, 43, 12, "+01:00"),];
        yield "datetimeimmutable SQL" => ["2023-12-02 08:00:38", self::MockNamedType("DateTimeImmutable"), self::createDateTimeImmutable(2023, 12, 2, 8, 00, 38),];
        yield "datetimeimmutable leading whitespace" => [" 2023-12-02 08:00:38", self::MockNamedType("DateTimeImmutable"), self::createDateTimeImmutable(2023, 12, 2, 8, 00, 38),];
        yield "datetimeimmutable trailing whitespace" => ["2023-12-02 08:00:38 ", self::MockNamedType("DateTimeImmutable"), self::createDateTimeImmutable(2023, 12, 2, 8, 00, 38),];
    }

    /**
     * Ensure strings can successfully be converted to required types.
     *
     * @dataProvider dataForTestConvertRuleConstructorArg1
     * @param string $arg The argument in the rule definition.
     * @param ReflectionNamedType $type The type to attempt to convert it to.
     * @param mixed $expectedValue The value the conversion attempt is expected to yield.
     */
    public function testConvertRuleConstructorArg1(string $arg, ReflectionNamedType $type, mixed $expectedValue): void
    {
        $validator = new StaticXRay(Validator::class);
        [$value, $success] = $validator->convertRuleConstructorArg($arg, $type);
        self::assertTrue($success);
        self::assertEquals(self::typeOf($expectedValue), self::typeOf($value));
        self::assertEquals($expectedValue, $value);
    }

    public static function dataForTestConvertRuleConstructorArg2(): iterable
    {
        yield "empty string for int type" => ["", self::mockNamedType("int"),];
        yield "whitespace for int type" => ["  ", self::mockNamedType("int"),];
        yield "alpha for int type" => ["a", self::mockNamedType("int"),];
        yield "internal whitespace for int type" => ["1 0", self::mockNamedType("int"),];

        yield "empty string for float type" => ["", self::mockNamedType("float"),];
        yield "whitespace for float type" => ["  ", self::mockNamedType("float"),];
        yield "alpha for float type" => ["a", self::mockNamedType("float"),];
        yield "internal whitespace for float type" => ["1 .0", self::mockNamedType("float"),];

        yield "empty string for double type" => ["", self::mockNamedType("double"),];
        yield "whitespace for double type" => ["  ", self::mockNamedType("double"),];
        yield "alpha for double type" => ["a", self::mockNamedType("double"),];
        yield "internal whitespace for double type" => ["1 .0", self::mockNamedType("double"),];

        yield "empty string for bool type" => ["", self::mockNamedType("bool"),];
        yield "whitespace for bool type" => ["  ", self::mockNamedType("bool"),];
        yield "alpha for bool type" => ["a", self::mockNamedType("bool"),];

        yield "datetime empty string" => ["", self::MockNamedType("DateTime"),];
        yield "datetime whitespace" => ["  ", self::MockNamedType("DateTime"),];

        yield "datetimeinterface empty string" => ["", self::MockNamedType("DateTimeInterface"),];
        yield "datetimeinterface whitespace" => ["  ", self::MockNamedType("DateTimeInterface"),];

        yield "datetimeimmutable empty string" => ["", self::MockNamedType("DateTimeImmutable"),];
        yield "datetimeimmutable whitespace" => ["  ", self::MockNamedType("DateTimeImmutable"),];

        yield "unsupported argument type" => ["", self::MockNamedType("SomeClass"),];
    }

    /**
     * Ensure invalid strings are rejected.
     *
     * @dataProvider dataForTestConvertRuleConstructorArg2
     * @param string $arg The argument in the rule definition.
     * @param ReflectionNamedType $type The type to attempt to convert it to.
     * @param mixed $expectedValue The value the conversion attempt is expected to yield.
     */
    public function testConvertRuleConstructorArg2(string $arg, ReflectionNamedType $type): void
    {
        $validator = new StaticXRay(Validator::class);
        [$value, $success] = $validator->convertRuleConstructorArg($arg, $type);
        self::assertFalse($success);
    }

    /** Ensure union types are handled for rule constructors. */
    public function testConvertRuleConstructorArgs1(): void
    {
        $rule = new class (0) implements Rule {
            public function __construct(string|DateTimeImmutable|DateTimeInterface|DateTime|array|bool|float|int $value)
            {
            }

            public function passes(string $field, mixed $data): bool
            {
                return false;
            }

            public function message(string $field): string
            {
                return "";
            }
        };

        $validator = new StaticXRay(Validator::class);
        $args = $validator->convertRuleConstructorArgs(["0"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsInt($args[0]);
        self::assertEquals(0, $args[0]);

        $args = $validator->convertRuleConstructorArgs(["1.1"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsFloat($args[0]);
        self::assertEquals(1.1, $args[0]);

        $args = $validator->convertRuleConstructorArgs(["true"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsBool($args[0]);
        self::assertTrue($args[0]);

        $args = $validator->convertRuleConstructorArgs(["false"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsBool($args[0]);
        self::assertFalse($args[0]);

        $args = $validator->convertRuleConstructorArgs(["this-will-be-an-array"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsArray($args[0]);
        self::assertEquals(["this-will-be-an-array",], $args[0]);

        $args = $validator->convertRuleConstructorArgs(["1,2,3"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsArray($args[0]);
        self::assertEquals(["1", "2", "3",], $args[0]);

        $args = $validator->convertRuleConstructorArgs(["2023-11-23 21:40:00"], $rule::class);
        self::assertCount(1, $args);
        self::assertInstanceOf(DateTime::class, $args[0]);
        self::assertEquals(self::createDateTime(2023, 11, 23, 21, 40, 00), $args[0]);
    }

    /** Ensure float is preferred over string for int strings when int isn't one of the types but float is */
    public function testConvertRuleConstructorArgs2(): void
    {
        $rule = new class (0) implements Rule {
            public function __construct(string|float $value)
            {
            }

            public function passes(string $field, mixed $data): bool
            {
                return false;
            }

            public function message(string $field): string
            {
                return "";
            }
        };

        $validator = new StaticXRay(Validator::class);
        $args = $validator->convertRuleConstructorArgs(["1"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsFloat($args[0]);
        self::assertEquals(1.0, $args[0]);

        $args = $validator->convertRuleConstructorArgs([" 2 "], $rule::class);
        self::assertCount(1, $args);
        self::assertIsFloat($args[0]);
        self::assertEquals(2.0, $args[0]);

        $args = $validator->convertRuleConstructorArgs([" 3"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsFloat($args[0]);
        self::assertEquals(3.0, $args[0]);

        $args = $validator->convertRuleConstructorArgs(["4 "], $rule::class);
        self::assertCount(1, $args);
        self::assertIsFloat($args[0]);
        self::assertEquals(4.0, $args[0]);
    }

    /** Ensure bool is preferred over string for numeric data when int/float isn't one of the types but bool is. */
    public function testConvertRuleConstructorArgs3(): void
    {
        $rule = new class ("") implements Rule {
            public function __construct(string|bool $value)
            {
            }

            public function passes(string $field, mixed $data): bool
            {
                return false;
            }

            public function message(string $field): string
            {
                return "";
            }
        };

        $validator = new StaticXRay(Validator::class);
        $args = $validator->convertRuleConstructorArgs(["1"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsBool($args[0]);
        self::assertTrue($args[0]);

        $args = $validator->convertRuleConstructorArgs(["0"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsBool($args[0]);
        self::assertFalse($args[0]);
    }

    /** Ensure string is used for int strings when int is not one of the types. */
    public function testConvertRuleConstructorArgs4(): void
    {
        $rule = new class ("") implements Rule {
            public function __construct(string|DateTime $value)
            {
            }

            public function passes(string $field, mixed $data): bool
            {
                return false;
            }

            public function message(string $field): string
            {
                return "";
            }
        };

        $validator = new StaticXRay(Validator::class);
        $args = $validator->convertRuleConstructorArgs(["1"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsString($args[0]);
        self::assertEquals("1", $args[0]);
    }

    /** Ensure string is used for float strings when float is not one of the types. */
    public function testConvertRuleConstructorArgs5(): void
    {
        $rule = new class ("") implements Rule {
            public function __construct(string|DateTime $value)
            {
            }

            public function passes(string $field, mixed $data): bool
            {
                return false;
            }

            public function message(string $field): string
            {
                return "";
            }
        };

        $validator = new StaticXRay(Validator::class);
        $args = $validator->convertRuleConstructorArgs(["3.1415927"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsString($args[0]);
        self::assertEquals("3.1415927", $args[0]);
    }

    /** Ensure string is used for bool strings when bool is not one of the types. */
    public function testConvertRuleConstructorArgs6(): void
    {
        $rule = new class ("") implements Rule {
            public function __construct(string|DateTime $value)
            {
            }

            public function passes(string $field, mixed $data): bool
            {
                return false;
            }

            public function message(string $field): string
            {
                return "";
            }
        };

        $validator = new StaticXRay(Validator::class);
        $args = $validator->convertRuleConstructorArgs(["true"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsString($args[0]);
        self::assertEquals("true", $args[0]);

        $args = $validator->convertRuleConstructorArgs(["false"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsString($args[0]);
        self::assertEquals("false", $args[0]);
    }

    /** Ensure string is used for array-like strings when array is not one of the types. */
    public function testConvertRuleConstructorArgs7(): void
    {
        $rule = new class ("") implements Rule {
            public function __construct(string|DateTime $value)
            {
            }

            public function passes(string $field, mixed $data): bool
            {
                return false;
            }

            public function message(string $field): string
            {
                return "";
            }
        };

        $validator = new StaticXRay(Validator::class);
        $args = $validator->convertRuleConstructorArgs(["1,2,3"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsString($args[0]);
        self::assertEquals("1,2,3", $args[0]);
    }

    /** Ensure string is used for date-time strings when DateTime is not one of the types. */
    public function testConvertRuleConstructorArgs8(): void
    {
        $rule = new class ("") implements Rule {
            public function __construct(string|int $value)
            {
            }

            public function passes(string $field, mixed $data): bool
            {
                return false;
            }

            public function message(string $field): string
            {
                return "";
            }
        };

        $validator = new StaticXRay(Validator::class);
        $args = $validator->convertRuleConstructorArgs(["2023-11-23 21:40:00"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsString($args[0]);
        self::assertEquals("2023-11-23 21:40:00", $args[0]);
    }

    /** Ensure floats are not converted to ints when float is not one of the types but int is. */
    public function testConvertRuleConstructorArgs9(): void
    {
        $rule = new class ("") implements Rule {
            public function __construct(string|int $value)
            {
            }

            public function passes(string $field, mixed $data): bool
            {
                return false;
            }

            public function message(string $field): string
            {
                return "";
            }
        };

        $validator = new StaticXRay(Validator::class);
        $args = $validator->convertRuleConstructorArgs(["3.1415927"], $rule::class);
        self::assertCount(1, $args);
        self::assertIsString($args[0]);
        self::assertEquals("3.1415927", $args[0]);
    }
}
