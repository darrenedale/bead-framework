<?php

declare(strict_types=1);

namespace BeadTests\Testing;

use ArrayObject;
use DateTime;
use BeadTests\Framework\TestCase;
use Bead\Testing\MockFunction;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use SplFileInfo;
use SplFixedArray;
use TypeError;

class MockFunctionTest extends TestCase
{
    private const StrlenStaticValue = 42;
    private const StrlenSequence = [42, 12,];
    private const StrlenMap = ["foo" => 42, "bar" => 12,];

    private const SplFileInfoGetSizeStaticValue = 1024;
    private const SplFileInfoGetSizeSequence = [1024, 2048,];
    private const SplFileInfoGetSizeMap = ["foo" => 1024, "bar" => 2048,];

    public function tearDown(): void
    {
        MockFunction::removeAllMocks();
    }

    public function dataForTestConstructorForFunction(): iterable
    {
        yield from [
            "typical" => ["strpos"],
            "typicalNullName" => [null,],
            "invalidUndefinedFunction" => ["foo_function_does_not_exist", InvalidArgumentException::class,],
            "invalidEmptyFunctionName" => ["", InvalidArgumentException::class,],
            "invalidStringableName" => [new class()
            {
                public function __toString(): string
                {
                    return "strpos";
                }
            }, TypeError::class,],
            "invalidArrayName" => [["strpos"], TypeError::class,],
            "invalidIntName" => [42, TypeError::class,],
            "invalidFloatName" => [3.1415927, TypeError::class,],
            "invalidBoolName" => [true, TypeError::class,],
            "invalidObjectName" => [(object)[
                "__toString" => function(): string
                {
                    return "strpos";
                }
            ], TypeError::class,],
        ];
    }

    /**
     * Test the constructor when used to mock a freestanding function.
     *
     * @dataProvider dataForTestConstructorForFunction
     *
     * @param mixed $name The function name to pass to the constructor.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testConstructorForFunction($name, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = new MockFunction($name);

        if (!isset($name)) {
            self::expectException(LogicException::class);
        }

        self::assertEquals($name, $mock->functionName());
        self::assertTrue($mock->isFunction());
    }

    public function dataForTestConstructorForMethod(): iterable
    {
        yield from [
            "typical" => [SplFileInfo::class, "getSize",],
            "typicalNullClass" => [null, null,],
            "invalidUndefinedClass" => ["foo_class_does_not_exist", "getSize", InvalidArgumentException::class,],
            "invalidUndefinedMethod" => [SplFileInfo::class, "fooGetSizeDoesNotExist", InvalidArgumentException::class,],
            "invalidEmptyClassName" => ["", "getSize", InvalidArgumentException::class,],
            "invalidEmptyMethodName" => [SplFileInfo::class, "", InvalidArgumentException::class,],
            "invalidStringableClassName" => [new class()
            {
                public function __toString(): string
                {
                    return SplFileInfo::class;
                }
            }, "getSize", TypeError::class,],
            "invalidArrayClassName" => [[SplFileInfo::class], "getSize", TypeError::class,],
            "invalidIntClassName" => [42, "getSize", TypeError::class,],
            "invalidFloatClassName" => [3.1415927, "getSize", TypeError::class,],
            "invalidBoolClassName" => [true, "getSize", TypeError::class,],
            "invalidObjectClassName" => [(object)[
                "__toString" => function(): string
                {
                    return SplFileInfo::class;
                }
            ], "getSize", TypeError::class,],
            "invalidStringableMethodName" => [SplFileInfo::class, new class()
            {
                public function __toString(): string
                {
                    return SplFileInfo::class;
                }
            }, TypeError::class,],
            "invalidArrayMethodName" => [SplFileInfo::class, ["getSize"], TypeError::class,],
            "invalidIntMethodName" => [SplFileInfo::class, 42, TypeError::class,],
            "invalidFloatMethodName" => [SplFileInfo::class, 3.1415927, TypeError::class,],
            "invalidBoolMethodName" => [SplFileInfo::class, true, TypeError::class,],
            "invalidObjectMethodName" => [
                SplFileInfo::class,
                (object) [
                "__toString" => function(): string
                {
                    return "getSize";
                }
            ], TypeError::class,],
        ];
    }

    /**
     * Test the constructor when used to mock a class method.
     *
     * @dataProvider dataForTestConstructorForMethod
     *
     * @param mixed $className The class name to pass to the constructor.
     * @param mixed $methodName The method name to pass to the constructor.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testConstructorForMethod($className, $methodName, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = new MockFunction($className, $methodName);

        if (!isset($className)) {
            self::expectException(LogicException::class);
        }

        self::assertEquals($className, $mock->className());
        self::assertEquals($methodName, $mock->functionName());
        self::assertTrue($mock->isMethod());
    }
    
    public function testConstructorInitialState(): void
    {
        $mock = new MockFunction("strpos");
        self::assertTrue($mock->isFunction(), "MockFunction was not initialised as a function mock.");
        self::assertTrue($mock->willCheckParameters(), "MockFunction does not have correct initial state for parameter checks.");
        self::assertTrue($mock->willCheckReturnType(), "MockFunction does not have correct initial state for return type checks.");
        self::assertTrue($mock->willCheckArguments(), "MockFunction does not have correct initial state for call-time argument checks.");

        $mock = new MockFunction(SplFileInfo::class, "getSize");
        self::assertTrue($mock->isMethod(), "MockFunction was not initialised as a function mock.");
        self::assertTrue($mock->willCheckParameters(), "MockFunction does not have correct initial state for parameter checks.");
        self::assertTrue($mock->willCheckReturnType(), "MockFunction does not have correct initial state for return type checks.");
        self::assertTrue($mock->willCheckArguments(), "MockFunction does not have correct initial state for call-time argument checks.");
    }
    
    /**
     * Test data for setFunctionName()/forFunction().
     *
     * Each dataset consists of:
     * - the name to pass to the method under test
     * - the optional name of an exception class that is expected to be thrown.
     *
     * @return iterable The test data.
     */
    public function dataForTestSetFunctionName(): iterable
    {
        yield from [
            "typical" => ["strpos",],
            "invalidNull" => [null, TypeError::class,],
            "invalidUndefinedFunction" => ["foo_function_does_not_exist", InvalidArgumentException::class,],
            "invalidEmptyFunctionName" => ["", InvalidArgumentException::class,],
            "invalidStringableName" => [new class()
            {
                public function __toString(): string
                {
                    return "strpos";
                }
            }, TypeError::class,],
            "invalidArrayName" => [["strpos"], TypeError::class,],
            "invalidIntName" => [42, TypeError::class,],
            "invalidFloatName" => [3.1415927, TypeError::class,],
            "invalidBoolName" => [true, TypeError::class,],
            "invalidObjectName" => [(object)[
                "__toString" => function(): string
                {
                    return "strpos";
                }
            ], TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestSetFunctionName
     *
     * @param mixed $name The function name to pass to setFunctionName().
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testSetFunctionName($name, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = new MockFunction();
        $mock->setFunctionName($name);
        self::assertTrue($mock->isFunction());
        self::assertEquals($name, $mock->functionName());
    }

    /**
     * Ensure setFunctionName() throws when use while the mock is installed.
     */
    public function testSetFunctionNameWhenInstalled(): void
    {
        $mock = new MockFunction();
        $mock->setFunctionName("strlen");
        $mock->shouldReturn(42);
        $mock->install();
        self::assertTrue($mock->isInstalled());
        self::expectException(RuntimeException::class);
        $mock->setFunctionName("strspn");
    }

    /**
     * @dataProvider dataForTestSetFunctionName
     *
     * @param mixed $name The function name to pass to the forFunction().
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testForFunction($name, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = new MockFunction();
        $actual = $mock->forFunction($name);
        self::assertSame($mock, $actual, "forFunction() did not return the same MockFunction object.");
        self::assertTrue($mock->isFunction());
        self::assertEquals($name, $mock->functionName());
    }

    /**
     * Ensure forFunction() throws when use while the mock is installed.
     */
    public function testForFunctionWhenInstalled(): void
    {
        $mock = new MockFunction();
        $mock->forFunction("strlen");
        $mock->shouldReturn(42);
        $mock->install();
        self::assertTrue($mock->isInstalled());
        self::expectException(RuntimeException::class);
        $mock->forFunction("strspn");
    }

    /**
     * Test data for setMethod()/forMethod().
     *
     * Each dataset consists of:
     * - the name to pass to the method under test
     * - the optional name of an exception class that is expected to be thrown.
     *
     * @return iterable The test data.
     */
    public function dataForTestSetMethod(): iterable
    {
        yield from [
            "typical" => [SplFileInfo::class, "getSize",],
            "invalidNullClass" => [null, "getSize", TypeError::class,],
            "invalidUndefinedMethod" => [SplFileInfo::class, "foo_method_does_not_exist", InvalidArgumentException::class,],
            "invalidEmptyMethodMethodName" => [SplFileInfo::class, "", InvalidArgumentException::class,],
            "invalidStringableMethodName" => [SplFileInfo::class, new class()
            {
                public function __toString(): string
                {
                    return "getSize";
                }
            }, TypeError::class,],
            "invalidArrayMethodName" => [SplFileInfo::class, ["getSize"], TypeError::class,],
            "invalidIntMethodName" => [SplFileInfo::class, 42, TypeError::class,],
            "invalidFloatMethodName" => [SplFileInfo::class, 3.1415927, TypeError::class,],
            "invalidBoolMethodName" => [SplFileInfo::class, true, TypeError::class,],
            "invalidObjectMethodName" => [SplFileInfo::class, (object)[
                "__toString" => function(): string
                {
                    return "getSize";
                }
            ], TypeError::class,],
            "invalidUndefinedClass" => ["FooNonExistentClass", "getSize", InvalidArgumentException::class,],
            "invalidEmptyClassClassName" => ["", "getSize", InvalidArgumentException::class,],
            "invalidStringableClassName" => [new class()
            {
                public function __toString(): string
                {
                    return SplFileInfo::class;
                }
            }, "getSize", TypeError::class,],
            "invalidArrayClassName" => [[SplFileInfo::class,], "getSize", TypeError::class,],
            "invalidIntClassName" => [42, "getSize", TypeError::class,],
            "invalidFloatClassName" => [3.1415927, "getSize", TypeError::class,],
            "invalidBoolClassName" => [true, "getSize", TypeError::class,],
            "invalidObjectClassName" => [(object)[
                "__toString" => function(): string
                {
                    return SplFileInfo::class;
                }
            ], "getSize", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestSetMethod
     *
     * @param mixed $className The class name to pass to setMethod().
     * @param mixed $methodName The method name to pass to setMethod().
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testSetMethod($className, $methodName, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = new MockFunction();
        $mock->setMethod($className, $methodName);
        self::assertTrue($mock->isMethod());
        self::assertEquals($className, $mock->className());
        self::assertEquals($methodName, $mock->functionName());
    }

    /**
     * Ensure setMethod() throws when use while the mock is installed.
     */
    public function testSetMethodWhenInstalled(): void
    {
        $mock = new MockFunction();
        $mock->setMethod(SplFileInfo::class, "getSize");
        $mock->shouldReturn(42);
        $mock->install();
        self::assertTrue($mock->isInstalled());
        self::expectException(RuntimeException::class);
        $mock->setMethod(SplFileInfo::class, "getInode");
    }

    /**
     * @dataProvider dataForTestSetMethod
     *
     * @param mixed $className The class name to pass to forMethod().
     * @param mixed $methodName The method name to pass to forMethod().
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testForMethod($className, $methodName, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = new MockFunction();
        $actual = $mock->forMethod($className, $methodName);
        self::assertSame($mock, $actual, "forMethod() did not return the same MockFunction object.");
        self::assertTrue($mock->isMethod());
        self::assertEquals($className, $mock->className());
        self::assertEquals($methodName, $mock->functionName());
    }

    /**
     * Ensure forMethod() throws when use while the mock is installed.
     */
    public function testForMethodWhenInstalled(): void
    {
        $mock = new MockFunction();
        $mock->forMethod(SplFileInfo::class, "getSize");
        $mock->shouldReturn(42);
        $mock->install();
        self::assertTrue($mock->isInstalled());
        self::expectException(RuntimeException::class);
        $mock->forMethod(SplFileInfo::class, "getInode");
    }

    public function dataForTestFunctionName(): iterable
    {
        yield from [
            [null,],
            ["strpos",],
            ["array_map",],
        ];
    }

    /**
     * @dataProvider dataForTestFunctionName
     *
     * @param string|null $name The name to initialise the mock with.
     */
    public function testFunctionName(?string $name): void
    {
        $mock = new MockFunction($name);

        if (!isset($name)) {
            self::expectException(LogicException::class);
        }

        self::assertEquals($name, $mock->functionName());
    }

    public function dataForTestClassName(): iterable
    {
        yield from [
            [null, null,],
            [SplFileInfo::class, "getSize",],
            [ArrayObject::class, "offsetExists",],
        ];
    }

    /**
     * @dataProvider dataForTestClassName
     *
     * @param string|null $className The class name to initialise the mock with.
     * @param string|null $methodName The method name to initialise the mock with.
     */
    public function testClassName(?string $className, ?string $methodName): void
    {
        $mock = new MockFunction($className, $methodName);

        if (!isset($className)) {
            self::expectException(LogicException::class);
        }

        self::assertEquals($className, $mock->className());
    }

    public function testWillCheckParameters(): void
    {
        $mock = new MockFunction("strpos");
        self::assertTrue($mock->willCheckParameters());
        $mock->setCheckParameters(false);
        self::assertFalse($mock->willCheckParameters());
        $mock->setCheckParameters(true);
        self::assertTrue($mock->willCheckParameters());
    }
    
    /**
     * Test data for setCheckParameters().
     *
     * Each dataset consists of:
     * - the value to pass to the method under test
     * - the optional name of an exception class that is expected to be thrown.
     *
     * @return iterable The test data.
     */
    public function dataForTestSetCheckParameters(): iterable
    {
        yield from [
            "typicalTrue" => [true,],
            "typicalFalse" => [false,],
            "invalidNull" => [null, TypeError::class,],
            "invalidString" => ["true", TypeError::class,],
            "invalidStringable" => [new class()
            {
                public function __toString(): string
                {
                    return "true";
                }
            }, TypeError::class,],
            "invalidArray" => [[true,], TypeError::class,],
            "invalidInt" => [42, TypeError::class,],
            "invalidFloat" => [3.1415927, TypeError::class,],
            "invalidObject" => [(object)[
                "__toString" => function(): string
                {
                    return "true";
                }
            ], TypeError::class,],
        ];
    }
    
    /**
     * @dataProvider dataForTestSetCheckParameters
     * @param mixed $check The value to pass to setCheckParameters()
     * @param string|null $exceptionClass
     */
    public function testSetCheckParameters($check, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = new MockFunction("strpos");
        $mock->setCheckParameters($check);
        self::assertEquals($check, $mock->willCheckParameters());
    }
    
    public function testWithoutParameterChecks(): void
    {
        $mock = new MockFunction("strpos");
        $actual = $mock->withoutParameterChecks();
        self::assertSame($mock, $actual, "withoutParameterChecks() did not return the same MOckFunction.");
        self::assertFalse($mock->willCheckParameters(), "withoutParameterChecks() failed to set parameter checks to false.");
    }
    
    public function testWithParameterChecks(): void
    {
        $mock = new MockFunction("strpos");
        $mock->setCheckParameters(false);
        $actual = $mock->withParameterChecks();
        self::assertSame($mock, $actual, "withParameterChecks() did not return the same MOckFunction.");
        self::assertTrue($mock->willCheckParameters(), "withParameterChecks() failed to set parameter checks to true.");
    }
    
    public function testWillCheckReturnType(): void
    {
        $mock = new MockFunction("strpos");
        self::assertTrue($mock->willCheckReturnType());
        $mock->setCheckReturnType(false);
        self::assertFalse($mock->willCheckReturnType());
        $mock->setCheckReturnType(true);
        self::assertTrue($mock->willCheckReturnType());
    }
    
    /**
     * Test data for setCheckReturnType().
     *
     * Each dataset consists of:
     * - the value to pass to the method under test
     * - the optional name of an exception class that is expected to be thrown.
     *
     * @return iterable The test data.
     */
    public function dataForTestSetCheckReturnType(): iterable
    {
        yield from [
            "typicalTrue" => [true,],
            "typicalFalse" => [false,],
            "invalidNull" => [null, TypeError::class,],
            "invalidString" => ["true", TypeError::class,],
            "invalidStringable" => [new class()
            {
                public function __toString(): string
                {
                    return "true";
                }
            }, TypeError::class,],
            "invalidArray" => [[true,], TypeError::class,],
            "invalidInt" => [42, TypeError::class,],
            "invalidFloat" => [3.1415927, TypeError::class,],
            "invalidObject" => [(object)[
                "__toString" => function(): string
                {
                    return "true";
                }
            ], TypeError::class,],
        ];
    }
    
    /**
     * @dataProvider dataForTestSetCheckReturnType
     * @param mixed $check The value to pass to setCheckReturnType()
     * @param string|null $exceptionClass
     */
    public function testSetCheckReturnType($check, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = new MockFunction("strpos");
        $mock->setCheckReturnType($check);
        self::assertEquals($check, $mock->willCheckReturnType());
    }
    
    public function testWithoutReturnTypeCheck(): void
    {
        $mock = new MockFunction("strpos");
        $actual = $mock->withoutReturnTypeCheck();
        self::assertSame($mock, $actual, "withoutReturnTypeCheck() did not return the same MOckFunction.");
        self::assertFalse($mock->willCheckReturnType(), "withoutReturnTypeCheck() failed to set parameter checks to false.");
    }
    
    public function testWithReturnTypeChecks(): void
    {
        $mock = new MockFunction("strpos");
        $mock->setCheckReturnType(false);
        $actual = $mock->withReturnTypeCheck();
        self::assertSame($mock, $actual, "withReturnTypeCheck() did not return the same MOckFunction.");
        self::assertTrue($mock->willCheckReturnType(), "withReturnTypeCheck() failed to set parameter checks to true.");
    }
    
    public function testWillCheckArguments(): void
    {
        $mock = new MockFunction("strpos");
        self::assertTrue($mock->willCheckArguments());
        $mock->setCheckArguments(false);
        self::assertFalse($mock->willCheckArguments());
        $mock->setCheckArguments(true);
        self::assertTrue($mock->willCheckArguments());
    }
    
    /**
     * Test data for setCheckArgument().
     *
     * Each dataset consists of:
     * - the value to pass to the method under test
     * - the optional name of an exception class that is expected to be thrown.
     *
     * @return iterable The test data.
     */
    public function dataForTestSetCheckArguments(): iterable
    {
        yield from [
            "typicalTrue" => [true,],
            "typicalFalse" => [false,],
            "invalidNull" => [null, TypeError::class,],
            "invalidString" => ["true", TypeError::class,],
            "invalidStringable" => [new class()
            {
                public function __toString(): string
                {
                    return "true";
                }
            }, TypeError::class,],
            "invalidArray" => [[true,], TypeError::class,],
            "invalidInt" => [42, TypeError::class,],
            "invalidFloat" => [3.1415927, TypeError::class,],
            "invalidObject" => [(object)[
                "__toString" => function(): string
                {
                    return "true";
                }
            ], TypeError::class,],
        ];
    }
    
    /**
     * @dataProvider dataForTestSetCheckArguments
     * @param mixed $check The value to pass to setCheckArgument()
     * @param string|null $exceptionClass
     */
    public function testSetCheckArguments($check, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = new MockFunction("strpos");
        $mock->setCheckArguments($check);
        self::assertEquals($check, $mock->willCheckArguments());
    }
    
    public function testWithoutArgumentCheck(): void
    {
        $mock = new MockFunction("strpos");
        $actual = $mock->withoutArgumentChecks();
        self::assertSame($mock, $actual, "withoutArgumentChecks() did not return the same MOckFunction.");
        self::assertFalse($mock->willCheckArguments(), "withoutArgumentChecks() failed to set parameter checks to false.");
    }
    
    public function testWithArgumentChecks(): void
    {
        $mock = new MockFunction("strpos");
        $mock->setCheckArguments(false);
        $actual = $mock->withArgumentChecks();
        self::assertSame($mock, $actual, "withArgumentChecks() did not return the same MOckFunction.");
        self::assertTrue($mock->willCheckArguments(), "withArgumentChecks() failed to set parameter checks to true.");
    }

    private static function strlenReplacement(): callable
    {
        // returns actual length less 1
        return function(string $str): int
        {
            $len = -1;
            for ($idx = 0; false !== substr($str, $idx, 1); ++$idx, ++$len);
            return $len;
        };
    }

    private static function splFileInfoGetSizeReplacement(): callable
    {
        return function(): int
        {
            return 1024;
        };
    }

    public function testShouldBeReplacedWithForFunction(): void
    {
        $mock = new MockFunction("strlen");
        $actual = $mock->shouldBeReplacedWith(self::strlenReplacement());
        self::assertSame($mock, $actual, "shouldBeReplacedWith() did not return the same MockFunction instance.");
    }

    public function testShouldBeReplacedWithForMethod(): void
    {
        $mock = new MockFunction(SplFileInfo::class, "getSize");
        $actual = $mock->shouldBeReplacedWith(self::splFileInfoGetSizeReplacement());
        self::assertSame($mock, $actual, "shouldBeReplacedWith() did not return the same MockFunction instance.");
    }

    public function testShouldReturnForFunction(): void
    {
        $mock = new MockFunction("strlen");
        $actual = $mock->shouldReturn(42);
        self::assertSame($mock, $actual, "shouldReturn() did not return the same MockFunction instance.");
    }

    public function testShouldReturnForMethod(): void
    {
        $mock = new MockFunction(SplFileInfo::class, "getSize");
        $actual = $mock->shouldReturn(1024);
        self::assertSame($mock, $actual, "shouldReturn() did not return the same MockFunction instance.");
    }

    public function testShouldReturnSequenceForFunction(): void
    {
        $mock = new MockFunction("strlen");
        $actual = $mock->shouldReturnSequence([42, 12,]);
        self::assertSame($mock, $actual, "shouldReturnSequence() did not return the same MockFunction instance.");
    }

    public function testShouldReturnSequenceForMethod(): void
    {
        $mock = new MockFunction(SplFileInfo::class, "getSize");
        $actual = $mock->shouldReturnSequence([1024, 2048,]);
        self::assertSame($mock, $actual, "shouldReturnSequence() did not return the same MockFunction instance.");
    }

    public function testShouldReturnMappedValueForFunction(): void
    {
        $mock = new MockFunction("strlen");
        $actual = $mock->shouldReturnMappedValue(["foo" => 42, "bar" => 12,]);
        self::assertSame($mock, $actual, "shouldReturnMappedValue() did not return the same MockFunction instance.");
    }

    public function testShouldReturnMappedValueForMethod(): void
    {
        $mock = new MockFunction(SplFileInfo::class, "getSize");
        $actual = $mock->shouldReturnMappedValue(["foo" => 1024, "bar" => 2048,]);
        self::assertSame($mock, $actual, "shouldReturnMappedValue() did not return the same MockFunction instance.");
    }

    public function testInstallForFunction(): void
    {
        $replacement = self::strlenReplacement();

        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldBeReplacedWith($replacement);

        $mock->install();
        self::assertTrue($mock->isInstalled());
        self::assertTrue($mock->isActive());
        $expected = $replacement("foo");
        self::assertEquals($expected, strlen("foo"), "Installed mock with replacement closure did not return expected value.");
    }

    public function testInstallForMethod(): void
    {
        $replacement = self::splFileInfoGetSizeReplacement();

        $mock = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldBeReplacedWith($replacement);

        $mock->install();
        self::assertTrue($mock->isInstalled());
        self::assertTrue($mock->isActive());
        $expected = $replacement("foo");
        self::assertEquals($expected, (new SplFileInfo("foo"))->getSize(), "Installed mock with replacement closure did not return expected value.");
    }

    public function testRemoveForFunction(): void
    {
        $replacement = self::strlenReplacement();

        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldBeReplacedWith($replacement);

        $mock->install();
        self::assertTrue($mock->isActive());
        $mock->remove();
        self::assertFalse($mock->isInstalled());
        self::assertFalse($mock->isActive());
        self::assertEquals(3, strlen("foo"), "Function with removed mock did not return expected value.");
    }

    public function testRemoveForMethod(): void
    {
        $replacement = self::splFileInfoGetSizeReplacement();

        $mock = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldBeReplacedWith($replacement);

        $mock->install();
        self::assertTrue($mock->isActive());
        $mock->remove();
        self::assertFalse($mock->isInstalled());
        self::assertFalse($mock->isActive());
        $expected = strlen(file_get_contents(__FILE__));
        self::assertEquals($expected, (new SplFileInfo(__FILE__))->getSize(), "Method with removed mock did not return expected value.");
    }

    public function testSuspendForFunction(): void
    {
        $replacement = self::strlenReplacement();

        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldBeReplacedWith($replacement);

        $mock->install();
        self::assertTrue($mock->isActive());
        $mock->suspend();
        self::assertTrue($mock->isInstalled());
        self::assertTrue($mock->isTop());
        self::assertFalse($mock->isActive());
        self::assertEquals(3, strlen("foo"), "Function with suspended mock did not return expected value.");

        // test it's safe to call suspend on an inactive mock
        $mock->suspend();
        self::assertFalse($mock->isActive());

        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldBeReplacedWith($replacement);

        self::assertFalse($mock->isActive());
        $mock->suspend();
        self::assertFalse($mock->isActive());
    }

    public function testSuspendForMethod(): void
    {
        $replacement = self::splFileInfoGetSizeReplacement();

        $mock = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldBeReplacedWith($replacement);

        $mock->install();
        self::assertTrue($mock->isActive());
        $mock->suspend();
        self::assertTrue($mock->isInstalled());
        self::assertTrue($mock->isTop());
        self::assertFalse($mock->isActive());
        $expected = strlen(file_get_contents(__FILE__));
        self::assertEquals($expected, (new SplFileInfo(__FILE__))->getSize(), "Method with suspended mock did not return expected value.");

        // test it's safe to call suspend on an inactive mock
        $mock->suspend();
        self::assertFalse($mock->isActive());

        $mock = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldBeReplacedWith($replacement);

        self::assertFalse($mock->isActive());
        $mock->suspend();
        self::assertFalse($mock->isActive());
    }

    public function testResumeForFunction(): void
    {
        $replacement = self::strlenReplacement();

        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldBeReplacedWith($replacement);

        $mock->install();
        self::assertTrue($mock->isActive());
        $mock->suspend();
        self::assertTrue($mock->isInstalled(), "Suspended mock reports it is not installed.");
        self::assertTrue($mock->isTop(), "Suspended mock is not top of the stack.");
        self::assertFalse($mock->isActive(), "Suspended mock function did not return false from isActive().");
        $mock->resume();
        self::assertTrue($mock->isActive());
        $expected = $replacement("foo");
        self::assertEquals($expected, strlen("foo"), "Function with resumed mock did not return expected value.");

        // test it's safe to call resume on an active mock
        $mock->resume();
        self::assertTrue($mock->isActive());
    }

    public function testResumeForMethod(): void
    {
        $replacement = self::splFileInfoGetSizeReplacement();

        $mock = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldBeReplacedWith($replacement);

        $mock->install();
        self::assertTrue($mock->isActive());
        $mock->suspend();
        self::assertTrue($mock->isInstalled(), "Suspended mock reports it is not installed.");
        self::assertTrue($mock->isTop(), "Suspended mock is not top of the stack.");
        self::assertFalse($mock->isActive(), "Suspended mock method did not return false from isActive().");
        $mock->resume();
        self::assertTrue($mock->isActive());
        $expected = $replacement("foo");
        self::assertEquals($expected,  (new SplFileInfo("foo"))->getSize(), "Method with resumed mock did not return expected value.");

        // test it's safe to call resume on an active mock
        $mock->resume();
        self::assertTrue($mock->isActive());
    }

    public function testIsTopForFunction(): void
    {
        $mock1 = (new MockFunction("strlen"))
            ->shouldBeReplacedWith(self::strlenReplacement());
        $mock1->install();
        self::assertTrue($mock1->isTop());
        $mock2 = (new MockFunction("strlen"))
            ->shouldReturn(-1);
        $mock2->install();

        self::assertFalse($mock1->isTop());
        self::assertTrue($mock2->isTop());
        $mock2->remove();
        self::assertTrue($mock1->isTop());
    }

    public function testIsTopForMethod(): void
    {
        $mock1 = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldBeReplacedWith(self::splFileInfoGetSizeReplacement());
        $mock1->install();
        self::assertTrue($mock1->isTop());
        $mock2 = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldReturn(-1);
        $mock2->install();

        self::assertFalse($mock1->isTop());
        self::assertTrue($mock2->isTop());
        $mock2->remove();
        self::assertTrue($mock1->isTop());
    }

    public function testIsActiveForFunction(): void
    {
        // ensure installing a mock makes it active
        $mock1 = (new MockFunction("strlen"))
            ->shouldBeReplacedWith(self::strlenReplacement());
        $mock1->install();
        self::assertTrue($mock1->isActive());

        // ensure installing a second mock makes it active
        $mock2 = (new MockFunction("strlen"))
            ->shouldReturn(-1);
        $mock2->install();

        self::assertFalse($mock1->isActive());
        self::assertTrue($mock2->isActive());

        // ensure removing an active mock activates the next mock on the stack
        $mock2->remove();
        self::assertTrue($mock1->isActive());

        // just putting it back on the stack
        $mock2->install();
        self::assertFalse($mock1->isActive());
        self::assertTrue($mock2->isActive());

        // ensure installing a mock already on the stack activates it
        $mock1->install();
        self::assertTrue($mock1->isActive());
        self::assertFalse($mock2->isActive());

        // ensure suspending an active mock makes it inactive
        $mock1->suspend();
        self::assertFalse($mock1->isActive());
        self::assertFalse($mock2->isActive());

        // ensure resuming a suspended mock makes it active
        $mock1->resume();
        self::assertTrue($mock1->isActive());
        self::assertFalse($mock2->isActive());

        // ensure removing a promoted mock activates the next mock on the stack
        $mock1->remove();
        self::assertFalse($mock1->isActive());
        self::assertTrue($mock2->isActive());

        // ensure removing the last mock on the stack deactivates it
        $mock2->remove();
        self::assertFalse($mock1->isActive());
        self::assertFalse($mock2->isActive());
    }

    public function testIsActiveForMethod(): void
    {
        // ensure installing a mock makes it active
        $mock1 = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldBeReplacedWith(self::splFileInfoGetSizeReplacement());
        $mock1->install();
        self::assertTrue($mock1->isActive());

        // ensure installing a second mock makes it active
        $mock2 = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldReturn(-1);
        $mock2->install();

        self::assertFalse($mock1->isActive());
        self::assertTrue($mock2->isActive());

        // ensure removing an active mock activates the next mock on the stack
        $mock2->remove();
        self::assertTrue($mock1->isActive());

        // just putting it back on the stack
        $mock2->install();
        self::assertFalse($mock1->isActive());
        self::assertTrue($mock2->isActive());

        // ensure installing a mock already on the stack activates it
        $mock1->install();
        self::assertTrue($mock1->isActive());
        self::assertFalse($mock2->isActive());

        // ensure suspending an active mock makes it inactive
        $mock1->suspend();
        self::assertFalse($mock1->isActive());
        self::assertFalse($mock2->isActive());

        // ensure resuming a suspended mock makes it active
        $mock1->resume();
        self::assertTrue($mock1->isActive());
        self::assertFalse($mock2->isActive());

        // ensure removing a promoted mock activates the next mock on the stack
        $mock1->remove();
        self::assertFalse($mock1->isActive());
        self::assertTrue($mock2->isActive());

        // ensure removing the last mock on the stack deactivates it
        $mock2->remove();
        self::assertFalse($mock1->isActive());
        self::assertFalse($mock2->isActive());
    }

    public function testIsInstalledForFunction(): void
    {
        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldBeReplacedWith(self::strlenReplacement());

        self::assertFalse($mock->isInstalled());
        $mock->install();
        self::assertTrue($mock->isInstalled());
        $mock->remove();
        self::assertFalse($mock->isInstalled());
    }

    public function testIsInstalledForMethod(): void
    {
        $mock = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldBeReplacedWith(self::splFileInfoGetSizeReplacement());

        self::assertFalse($mock->isInstalled());
        $mock->install();
        self::assertTrue($mock->isInstalled());
        $mock->remove();
        self::assertFalse($mock->isInstalled());
    }

    public function testStaticSuspendMock(): void
    {
        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldReturn(self::StrlenStaticValue);

        $mock->install();
        self::assertTrue($mock->isActive());
        self::assertEquals(self::StrlenStaticValue, strlen("foo"));
        MockFunction::suspendMock("strlen");
        self::assertFalse($mock->isActive());
        self::assertEquals(3, strlen("foo"));

        // ensure it's safe to call when there is no mock active
        $mock->remove();
        self::assertFalse($mock->isInstalled());
        MockFunction::suspendMock("strlen");
    }

    public function testStaticResumeMockWithFunction(): void
    {
        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldReturn(self::StrlenStaticValue);

        $mock->install();
        self::assertTrue($mock->isActive());
        self::assertEquals(self::StrlenStaticValue, strlen("foo"));
        MockFunction::suspendMock("strlen");
        self::assertFalse($mock->isActive());
        self::assertEquals(3, strlen("foo"));
        MockFunction::resumeMock("strlen");
        self::assertTrue($mock->isActive());
        self::assertEquals(self::StrlenStaticValue, strlen("foo"));

        // ensure call when no mocks available throws
        $mock->remove();
        self::assertFalse($mock->isInstalled());

        self::expectException(RuntimeException::class);
        MockFunction::resumeMock("strlen");
    }

    public function testStaticResumeMockWithMethod(): void
    {
        $mock = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldReturn(self::SplFileInfoGetSizeStaticValue);

        $mock->install();
        self::assertTrue($mock->isActive());
        self::assertEquals(self::SplFileInfoGetSizeStaticValue, (new SplFileInfo("any-file"))->getSize());
        MockFunction::suspendMock(SplFileInfo::class, "getSize");
        self::assertFalse($mock->isActive());
        self::assertEquals(strlen(file_get_contents(__FILE__)), (new SplFileInfo(__FILE__))->getSize());
        MockFunction::resumeMock(SplFileInfo::class, "getSize");
        self::assertTrue($mock->isActive());
        self::assertEquals(self::SplFileInfoGetSizeStaticValue, (new SplFileInfo("any-file"))->getSize());

        // ensure call when no mocks available throws
        $mock->remove();
        self::assertFalse($mock->isInstalled());

        self::expectException(RuntimeException::class);
        MockFunction::resumeMock(SplFileInfo::class, "getSize");
    }

    public function testStaticActiveMockForFunction(): void
    {
        // ensure null is returned when no mocks have ever been activated
        self::assertNull(MockFunction::activeMock("strlen"));

        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldReturn(self::StrlenStaticValue);

        // ensure a mock is retrievable when it's active
        $mock->install();
        self::assertTrue($mock->isActive());
        self::assertSame($mock, MockFunction::activeMock("strlen"));
        self::assertNull(MockFunction::activeMock("strspn"));

        // ensure the correct mock is returned when a new mock replaces the active one
        $mock2 = (new MockFunction())
            ->forFunction("strlen")
            ->shouldReturn(self::strlenReplacement());

        $mock2->install();
        self::assertTrue($mock2->isActive());
        self::assertSame($mock2, MockFunction::activeMock("strlen"));

        // ensure the correct mock is returned when an installed one is promoted
        $mock->install();
        self::assertTrue($mock->isActive());
        self::assertSame($mock, MockFunction::activeMock("strlen"));

        // ensure null is returned when the active mock is suspended
        MockFunction::suspendMock("strlen");
        self::assertFalse($mock->isActive());
        self::assertNull(MockFunction::activeMock("strlen"));

        // ensure the correct mock is returned when the active mock is resumed
        MockFunction::resumeMock("strlen");
        self::assertTrue($mock->isActive());
        self::assertSame($mock, MockFunction::activeMock("strlen"));

        // ensure the correct mock is returned when the active one is removed
        $mock->remove();
        self::assertFalse($mock->isActive());
        self::assertTrue($mock2->isActive());
        self::assertSame($mock2, MockFunction::activeMock("strlen"));

        // ensure null is returned when the stack is empty
        $mock2->remove();
        self::assertFalse($mock2->isActive());
        self::assertNull(MockFunction::activeMock("strlen"));
    }

    public function testStaticActiveMockForMethod(): void
    {
        // ensure null is returned when no mocks have ever been activated
        self::assertNull(MockFunction::activeMock(SplFileInfo::class, "getSize"));

        $mock = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldReturn(self::SplFileInfoGetSizeStaticValue);

        // ensure a mock is retrievable when it's active
        $mock->install();
        self::assertTrue($mock->isActive());
        self::assertSame($mock, MockFunction::activeMock(SplFileInfo::class, "getSize"));
        self::assertNull(MockFunction::activeMock(SplFileInfo::class, "getInode"));

        // ensure the correct mock is returned when a new mock replaces the active one
        $mock2 = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldReturn(self::splFileInfoGetSizeReplacement());

        $mock2->install();
        self::assertTrue($mock2->isActive());
        self::assertSame($mock2, MockFunction::activeMock(SplFileInfo::class, "getSize"));

        // ensure the correct mock is returned when an installed one is promoted
        $mock->install();
        self::assertTrue($mock->isActive());
        self::assertSame($mock, MockFunction::activeMock(SplFileInfo::class, "getSize"));

        // ensure null is returned when the active mock is suspended
        MockFunction::suspendMock(SplFileInfo::class, "getSize");
        self::assertFalse($mock->isActive());
        self::assertNull(MockFunction::activeMock(SplFileInfo::class, "getSize"));

        // ensure the correct mock is returned when the active mock is resumed
        MockFunction::resumeMock(SplFileInfo::class, "getSize");
        self::assertTrue($mock->isActive());
        self::assertSame($mock, MockFunction::activeMock(SplFileInfo::class, "getSize"));

        // ensure the correct mock is returned when the active one is removed
        $mock->remove();
        self::assertFalse($mock->isActive());
        self::assertTrue($mock2->isActive());
        self::assertSame($mock2, MockFunction::activeMock(SplFileInfo::class, "getSize"));

        // ensure null is returned when the stack is empty
        $mock2->remove();
        self::assertFalse($mock2->isActive());
        self::assertNull(MockFunction::activeMock(SplFileInfo::class, "getSize"));
    }

    public function testStaticTopMockForFunction(): void
    {
        // ensure null is returned when no mocks have ever been activated
        self::assertNull(MockFunction::topMock("strlen"));

        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldReturn(self::StrlenStaticValue);

        // ensure a mock is retrievable when it's active
        $mock->install();
        self::assertTrue($mock->isTop());
        self::assertSame($mock, MockFunction::topMock("strlen"));
        self::assertNull(MockFunction::topMock("strspn"));

        // ensure the correct mock is returned when a new mock replaces the active one
        $mock2 = (new MockFunction())
            ->forFunction("strlen")
            ->shouldReturn(self::strlenReplacement());

        $mock2->install();
        self::assertTrue($mock2->isTop());
        self::assertSame($mock2, MockFunction::topMock("strlen"));

        // ensure the correct mock is returned when an installed one is promoted
        $mock->install();
        self::assertTrue($mock->isTop());
        self::assertSame($mock, MockFunction::topMock("strlen"));

        // ensure correct mock is returned when the active mock is suspended
        MockFunction::suspendMock("strlen");
        self::assertTrue($mock->isTop());
        self::assertSame($mock, MockFunction::topMock("strlen"));

        // ensure the correct mock is returned when the active mock is resumed
        MockFunction::resumeMock("strlen");
        self::assertTrue($mock->isTop());
        self::assertSame($mock, MockFunction::topMock("strlen"));

        // ensure the correct mock is returned when the active one is removed
        $mock->remove();
        self::assertFalse($mock->isActive());
        self::assertTrue($mock2->isActive());
        self::assertSame($mock2, MockFunction::topMock("strlen"));

        // ensure null is returned when the stack is empty
        $mock2->remove();
        self::assertFalse($mock2->isActive());
        self::assertNull(MockFunction::activeMock("strlen"));
    }

    public function testStaticTopMockForMethod(): void
    {
        // ensure null is returned when no mocks have ever been activated
        self::assertNull(MockFunction::topMock(SplFileInfo::class, "getSize"));

        $mock = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldReturn(self::SplFileInfoGetSizeStaticValue);

        // ensure a mock is retrievable when it's active
        $mock->install();
        self::assertTrue($mock->isTop());
        self::assertSame($mock, MockFunction::topMock(SplFileInfo::class, "getSize"));
        self::assertNull(MockFunction::topMock(SplFileInfo::class, "getInode"));

        // ensure the correct mock is returned when a new mock replaces the active one
        $mock2 = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldReturn(self::splFileInfoGetSizeReplacement());

        $mock2->install();
        self::assertTrue($mock2->isTop());
        self::assertSame($mock2, MockFunction::topMock(SplFileInfo::class, "getSize"));

        // ensure the correct mock is returned when an installed one is promoted
        $mock->install();
        self::assertTrue($mock->isTop());
        self::assertSame($mock, MockFunction::topMock(SplFileInfo::class, "getSize"));

        // ensure the correct mock is still returned when the active mock is suspended
        MockFunction::suspendMock(SplFileInfo::class, "getSize");
        self::assertTrue($mock->isTop());
        self::assertSame($mock, MockFunction::topMock(SplFileInfo::class, "getSize"));

        // ensure the correct mock is returned when the active mock is resumed
        MockFunction::resumeMock(SplFileInfo::class, "getSize");
        self::assertTrue($mock->isTop());
        self::assertSame($mock, MockFunction::topMock(SplFileInfo::class, "getSize"));

        // ensure the correct mock is returned when the active one is removed
        $mock->remove();
        self::assertFalse($mock->isTop());
        self::assertTrue($mock2->isTop());
        self::assertSame($mock2, MockFunction::topMock(SplFileInfo::class, "getSize"));

        // ensure null is returned when the stack is empty
        $mock2->remove();
        self::assertFalse($mock2->isTop());
        self::assertNull(MockFunction::topMock(SplFileInfo::class, "getSize"));
    }

    public function testStaticInstallMockForFunction(): void
    {
        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldReturn(self::StrlenStaticValue);

        self::assertFalse(MockFunction::mockIsInstalled($mock));
        MockFunction::installMock($mock);
        self::assertTrue(MockFunction::mockIsInstalled($mock));
        self::assertEquals(self::StrlenStaticValue, strlen("foo"));
        
        // ensure installing mock replaces existing mock
        $replacement = self::strlenReplacement();
        $mock2 = (new MockFunction())
            ->forFunction("strlen")
            ->shouldBeReplacedWith($replacement);

        self::assertFalse(MockFunction::mockIsInstalled($mock2));
        MockFunction::installMock($mock2);
        self::assertTrue(MockFunction::mockIsInstalled($mock2));
        self::assertTrue(MockFunction::mockIsActive($mock2));
        self::assertEquals($replacement("foo"), strlen("foo"));
        
        // ensure installing existing mock promotes it
        self::assertFalse(MockFunction::mockIsActive($mock));
        MockFunction::installMock($mock);
        self::assertTrue(MockFunction::mockIsActive($mock));
        self::assertEquals(self::StrlenStaticValue, strlen("foo"));
    }

    public function testStaticInstallMockForMethod(): void
    {
        $mock = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldReturn(self::SplFileInfoGetSizeStaticValue);

        self::assertFalse(MockFunction::mockIsInstalled($mock));
        MockFunction::installMock($mock);
        self::assertTrue(MockFunction::mockIsInstalled($mock));
        self::assertEquals(self::SplFileInfoGetSizeStaticValue, (new SplFileInfo("any-file"))->getSize());

        // ensure installing mock replaces existing mock
        $replacement = self::splFileInfoGetSizeReplacement();
        $mock2 = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldBeReplacedWith($replacement);

        self::assertFalse(MockFunction::mockIsInstalled($mock2));
        MockFunction::installMock($mock2);
        self::assertTrue(MockFunction::mockIsInstalled($mock2));
        self::assertTrue(MockFunction::mockIsActive($mock2));
        self::assertEquals($replacement(), (new SplFileInfo("any-file"))->getSize());

        // ensure installing existing mock promotes it
        self::assertFalse(MockFunction::mockIsActive($mock));
        MockFunction::installMock($mock);
        self::assertTrue(MockFunction::mockIsActive($mock));
        self::assertEquals(self::SplFileInfoGetSizeStaticValue, (new SplFileInfo("any-file"))->getSize());
    }

    public function testStaticRemoveMockForFunction(): void
    {
        $mock = (new MockFunction())
            ->forFunction("strlen")
            ->shouldReturn(self::StrlenStaticValue);

        $mock->install();
        self::assertTrue(MockFunction::mockIsInstalled($mock));
        MockFunction::removeMock($mock);
        self::assertFalse(MockFunction::mockIsInstalled($mock));

        // ensure it's safe to call removeMock with a mock that isn't installed
        MockFunction::removeMock($mock);
    }

    public function testStaticRemoveMockForMethod(): void
    {
        $mock = (new MockFunction())
            ->forMethod(SplFileInfo::class, "getSize")
            ->shouldReturn(self::SplFileInfoGetSizeStaticValue);

        $mock->install();
        self::assertTrue(MockFunction::mockIsInstalled($mock));
        MockFunction::removeMock($mock);
        self::assertFalse(MockFunction::mockIsInstalled($mock));

        // ensure it's safe to call removeMock with a mock that isn't installed
        MockFunction::removeMock($mock);
    }

    public function dataForTestSequenceMockForFunction(): iterable
    {
        yield from [
            "typical" => ["strlen", ["foo",], self::StrlenSequence,],
            "typicalSubstantialArray" => ["strpos", ["foo", "bar",], [14, 12, 42, 901, 8, -11, 44, 7, 24,],],
            "extremeSingleItemArray" => ["strspn", ["foo", "abcdefghijklmnopqrstuvwxyz",], [self::StrlenStaticValue,],],
            "invalidEmptySequence" => ["strlen", [], [], InvalidArgumentException::class],
        ];
    }

    /**
     * @dataProvider dataForTestSequenceMockForFunction
     *
     * @param string $function The function to mock.
     * @param array $sequence The sequence of values the mock should return.
     * @param string|null $exceptionClass The exception expected to be thrown, if any.
     */
    public function testSequenceMockForFunction(string $function, array $callArgs, array $sequence, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = (new MockFunction())
            ->forFunction($function)
            ->shouldReturnSequence($sequence);

        $mock->install();

        // ensure the sequence repeated by the mock when required
        $expectedSequence = [...$sequence, ...$sequence];

        foreach ($expectedSequence as $expected) {
            self::assertEquals($expected, $function(...$callArgs));
        }
    }

    /**
     * @dataProvider dataForTestSequenceMockForFunction
     *
     * @param string $function The function to mock.
     * @param array $sequence The sequence of values the mock should return.
     * @param string|null $exceptionClass The exception expected to be thrown, if any.
     */
    public function testSequenceMockForFunctionWithoutArgChecks(string $function, array $callArgs, array $sequence, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = (new MockFunction())
            ->forFunction($function)
            ->withoutArgumentChecks()
            ->shouldReturnSequence($sequence);

        $mock->install();

        // ensure the sequence repeated by the mock when required
        $expectedSequence = [...$sequence, ...$sequence];

        foreach ($expectedSequence as $expected) {
            self::assertEquals($expected, $function(...$callArgs));
        }
    }

    public function dataForTestSequenceMockForMethod(): iterable
    {
        yield from [
            "typical" => [SplFileInfo::class, "getSize", ["any-file",], [], self::SplFileInfoGetSizeSequence,],
            "typicalSubstantialArray" => [SplFixedArray::class, "key", [20], [], [14, 12, 42, 901, 8, -11, 44, 7, 24,],],
            "extremeSingleItemArray" => [DateTime::class, "format", [], ["Y-m-d H:i:s",], ["FooBar",],],
            "invalidEmptySequence" => [SplFileInfo::class, "getSize", ["any-file",], [], [], InvalidArgumentException::class],
        ];
    }

    /**
     * @dataProvider dataForTestSequenceMockForMethod
     *
     * @param string $class The class to mock.
     * @param string $method The method to mock.
     * @param array $constructorArgs The args to pass to the constructor of the test object.
     * @param array $callArgs The args to pass to the method call.
     * @param array $sequence The sequence of values the mock should return.
     * @param string|null $exceptionClass The exception expected to be thrown, if any.
     */
    public function testSequenceMockForMethod(string $class, string $method, array $constructorArgs, array $callArgs, array $sequence, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = (new MockFunction())
            ->forMethod($class, $method)
            ->shouldReturnSequence($sequence);

        $mock->install();

        // ensure the sequence repeated by the mock when required
        $expectedSequence = [...$sequence, ...$sequence];
        $testObject = new $class(...$constructorArgs);

        foreach ($expectedSequence as $expected) {
            self::assertEquals($expected, $testObject->$method(...$callArgs));
        }
    }

    /**
     * @dataProvider dataForTestSequenceMockForMethod
     *
     * @param string $class The class to mock.
     * @param string $method The method to mock.
     * @param array $constructorArgs The args to pass to the constructor of the test object.
     * @param array $callArgs The args to pass to the method call.
     * @param array $sequence The sequence of values the mock should return.
     * @param string|null $exceptionClass The exception expected to be thrown, if any.
     */
    public function testSequenceMockForMethodWithoutArgChecks(string $class, string $method, array $constructorArgs, array $callArgs, array $sequence, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $mock = (new MockFunction())
            ->forMethod($class, $method)
            ->withoutArgumentChecks()
            ->shouldReturnSequence($sequence);

        $mock->install();

        // ensure the sequence repeated by the mock when required
        $expectedSequence = [...$sequence, ...$sequence];
        $testObject = new $class(...$constructorArgs);

        foreach ($expectedSequence as $expected) {
            self::assertEquals($expected, $testObject->$method(...$callArgs));
        }
    }

    // TODO test stack of mocks
    // TODO test compatibility checks throw appropriately
    // TODO test other replacement types (mapped, static)
    // TODO test replacement types with arg checks turned off
    // TODO test static replacement with object returns clone
    // TODO test with functions that have named return types (coverage)
}
