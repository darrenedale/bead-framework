<?php

declare(strict_types=1);

namespace Database;

use Bead\Database\Connection;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{

    public function dataForTestSqlToDefactoWildcards(): iterable
    {
        yield from [
            "typicalUnderscore" => ["foo_", "foo?",],
            "typicalPercent" => ["foo%", "foo*",],
            "extremeQuestionMark" => ["foo?", "foo?",],
            "typicalAsterisk" => ["foo*", "foo*",],
            "typicalNoEscaping" => ["foo", "foo",],
            "extremeEmpty" => ["", "",],
        ];
    }

    /**
     * @dataProvider dataForTestSqlToDefactoWildcards
     *
     * @param string $sql The SQL expression to convert.
     * @param string $expected The expected converted expression.
     */
    public function testSqlToDefactoWildcards(string $sql, string $expected): void
    {
        $this->assertEquals($expected, Connection::sqlToDefactoWildcards($sql));
    }

    public function dataForTestDefactoToSqlWildcards(): iterable
    {
        yield from [
            "typicalQuestionMark" => ["foo?", "foo_",],
            "typicalAsterisk" => ["foo*", "foo%",],
            "extremeUnderscore" => ["foo_", "foo_",],
            "typicalPercent" => ["foo%", "foo%",],
            "typicalNoEscaping" => ["foo", "foo",],
            "extremeEmpty" => ["", "",],
        ];
    }

    /**
     * @dataProvider dataForTestDefactoToSqlWildcards
     *
     * @param string $defacto The de facto expression to convert.
     * @param string $expected The expected converted expression.
     */
    public function testDefactoToSqlWildcards(string $defacto, string $expected): void
    {
        $this->assertEquals($expected, Connection::defactoToSqlWildcards($defacto));
    }

    public function dataForTestEscapeSqlWildcards(): iterable
    {
        yield from [
            "typicalUnderscore" => ["foo_", "foo\\_",],
            "typicalPercent" => ["foo%", "foo\\%",],
            "typicalNoEscaping" => ["foo", "foo",],
            "extremeEmpty" => ["", "",],
        ];
    }

    /**
     * @dataProvider dataForTestEscapeSqlWildcards
     *
     * @param string $sql The SQL to escape.
     * @param string $expected The expected escaped SQL.
     */
    public function testEscapeSqlWildcards(string $sql, string $expected): void
    {
        $this->assertEquals($expected, Connection::escapeSqlWildcards($sql));
    }

    public function dataForTestDefactoToRegExpWildcards(): iterable
    {
        yield from [
            "typicalQuestionMark" => ["foo?", "foo.",],
            "typicalAsterisk" => ["foo*", "foo.*",],
            "typicalNoEscaping" => ["foo", "foo",],
            "extremeEmpty" => ["", "",],
        ];
    }

    /**
     * @dataProvider dataForTestDefactoToRegExpWildcards
     *
     * @param string $sql The de facto wildcard string to convert.
     * @param string $expected The expected converted string.
     */
    public function testDefactoToRegExpWildcards(string $defacto, string $expected): void
    {
        $this->assertEquals($expected, Connection::defactoToRegExpWildcards($defacto));
    }
}
