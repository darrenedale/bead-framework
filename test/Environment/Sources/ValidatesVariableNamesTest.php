<?php

namespace BeadTests\Environment\Sources;

use Bead\Environment\Sources\ValidatesVariableNames;
use Bead\Testing\StaticXRay;
use BeadTests\Framework\TestCase;

class ValidatesVariableNamesTest extends TestCase
{
    private $instance;

    public function setUp(): void
    {
        $this->instance = new class {
            use ValidatesVariableNames;

            public function delegateValidateVariableName(string $name): string
            {
                return self::validateVariableName($name);
            }
        };
    }
    
    public function tearDown(): void
    {
        unset ($this->instance);
        parent::tearDown();
    }

    /**
     * Test data for testValidateVariableName.
     * 
     * @return iterable The test data.
     */
    public function dataForTestValidateVariableName(): iterable
    {
        yield ["KEY_1", "KEY_1",];
        yield ["_KEY_1", "_KEY_1",];
        yield ["_1", "_1",];
        yield ["A", "A",];
        yield [" KEY_1 ", "KEY_1",];
        yield [" _KEY_1 ", "_KEY_1",];
        yield [" _1 ", "_1",];
        yield [" A ", "A",];
        yield [" key_1 ", "key_1",];
        yield [" _key_1 ", "_key_1",];
        yield [" _1 ", "_1",];
        yield [" a ", "a",];
    }

    /**
     * Ensure validateVariableName successfully validates valid names.
     * 
     * @dataProvider dataForTestValidateVariableName
     * @param string $name The name to validate.
     * @param string $expected The exptected validated name.
     */
    public function testValidateVariableName(string $name, string $expected): void
    {
        self::assertEquals($expected, $this->instance->delegateValidateVariableName($name));
    }

    /**
     * Test data for testValidateVariableName.
     * 
     * @return iterable The test data.
     */
    public function dataForTestValidateVariableNameFails(): iterable
    {
        yield ["1",];
        yield ["",];
        yield [" 1 ",];
        yield [" ",];
    }

    /**
     * Ensure validateVariableName throws with invalid names.
     * 
     * @dataProvider dataForTestValidateVariableNameThrows
     * @param string $name The name to validate.
     */
    public function testValidateVariableNameFails(string $name): void
    {
        self::assertNull($this->instance->delegateValidateVariableName($name));
    }
}
