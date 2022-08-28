<?php

declare(strict_types=1);

namespace Equit\Test\Testing;

use ArrayObject;
use Equit\Test\Framework\TestCase;
use Equit\Testing\MockFunction;
use Equit\Util\ScopeGuard;
use InvalidArgumentException;
use LogicException;
use SplFileInfo;
use TypeError;

class MockFunctionTest extends TestCase
{
    private const StrlenStaticValue = 42;
    private const StrlenSequence = [42, 12,];
    private const StrlenMap = ["foo" => 42, "bar" => 12,];

    private const SplFileInfoGetSizeStaticValue = 1024;
    private const SplFileInfoGetSizeSequence = [1024, 2048,];
    private const SplFileInfoGetSizeMap = ["foo" => 1024, "bar" => 2048,];

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

    private static final function strlenReplacement(): callable
    {
        // returns actual length less 1
        return function(string $str): int
        {
            $len = -1;
            for ($idx = 0; false !== substr($str, $idx, 1); ++$idx, ++$len);
            return $len;
        };
    }

    private static final function splFileInfoGetSizeReplacement(): callable
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

        $guard = new ScopeGuard(fn() => $mock->remove());
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

        $guard = new ScopeGuard(fn() => $mock->remove());
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

        $guard = new ScopeGuard(fn() => $mock->remove());
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

        $guard = new ScopeGuard(fn() => $mock->remove());
        $mock->install();
        self::assertTrue($mock->isActive());
        $mock->remove();
        self::assertFalse($mock->isInstalled());
        self::assertFalse($mock->isActive());
        $expected = strlen(file_get_contents(__FILE__));
        self::assertEquals($expected, (new SplFileInfo(__FILE__))->getSize(), "Method with removed mock did not return expected value.");
    }

    // TODO test stack of mocks
}
