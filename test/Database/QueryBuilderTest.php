<?php

/** @noinspection SqlResolve */

declare(strict_types=1);

namespace BeadTests\Database;

use BeadTests\Framework\TestCase;
use DateTime;
use Bead\Application;
use Bead\Database\Connection;
use Bead\Database\QueryBuilder;
use Bead\Exceptions\Database\DuplicateColumnNameException;
use Bead\Exceptions\Database\DuplicateTableNameException;
use Bead\Exceptions\Database\InvalidColumnNameException;
use Bead\Exceptions\Database\InvalidLimitException;
use Bead\Exceptions\Database\InvalidLimitOffsetException;
use Bead\Exceptions\Database\InvalidOperatorException;
use Bead\Exceptions\Database\InvalidOrderByDirectionException;
use Bead\Exceptions\Database\InvalidQueryExpressionException;
use Bead\Exceptions\Database\InvalidTableNameException;
use Bead\Exceptions\Database\OrphanedJoinException;
use InvalidArgumentException;
use Mockery;
use PDO;
use TypeError;

use function uopz_set_return;
use function uopz_unset_return;

/**
 * Test the query builder class.
 */
class QueryBuilderTest extends TestCase
{
    private ?Application $m_application;
    private ?Connection $m_defaultConnection;

    /**
     * Sets up for every test.
     *
     * If no Application instance is present, a stub instance is created.
     */
    public function setUp(): void
    {
        $this->m_application = Mockery::mock(Application::class);
        $this->m_defaultConnection = Mockery::mock(Connection::class);

        uopz_set_return(Application::class, "instance", $this->m_application);

        $this->m_application->shouldReceive("database")
            ->andReturn($this->m_defaultConnection);
    }

    public function tearDown(): void
    {
        uopz_unset_return(Application::class, "instance");

        unset(
            $this->m_application,
            $this->m_defaultConnection
        );

        Mockery::close();
    }

    /**
     * Helper to create a builder for testing with.
     *
     * @param string|string[]|array<string,string> $selects The initial set of columns selected. Will be passed to the
     * QueryBuilder constructor.
     * @param string|string[]|array<string,string> $tables The initial set of tables. Will be passed to the QueryBuilder
     * constructor.
     * @param string[]|array<string,string> $wheres The initial WHERE expressions. Will be passed to
     * QueryBuilder::where()
     * @param string[]|array<string,string> $orderBys The initial ORDER BY expressions. Will be passed to
     * QueryBuilder::orderBy()
     *
     * @return QueryBuilder
     * @throws \Bead\Exceptions\Database\DuplicateColumnNameException
     * @throws \Bead\Exceptions\Database\DuplicateTableNameException
     * @throws \Bead\Exceptions\Database\InvalidColumnNameException
     */
    private static function createBuilder($selects = null, $tables = null, $wheres = null, $orderBys = null): QueryBuilder
    {
        $builder = new QueryBuilder();

        if (isset($selects)) {
            $builder->select($selects);
        }

        if (isset($tables)) {
            $builder->from($tables);
        }

        if (isset($wheres)) {
            $builder->where($wheres);
        }

        if (isset($orderBys)) {
            $builder->orderBy($orderBys);
        }

        return $builder;
    }

    /**
     * Ensure default constructor sets expected state.
     */
    public function testDefaultConstructor(): void
    {
        $builder = new QueryBuilder();
        $this->m_application->shouldHaveReceived("database")->once();
        self::assertSame($this->m_defaultConnection, $builder->connection());
    }

    public function testConstructorWithConnection(): void
    {
        $connection = Mockery::mock(Connection::class);
        $builder = new QueryBuilder($connection);
        $this->m_application->shouldNotHaveReceived("database");
        self::assertSame($connection, $builder->connection());
    }

    public function dataForTestSetConnection(): iterable
    {
        yield from [
            "typical" => [Mockery::mock(PDO::class),],
            "invalidString" => [PDO::class, TypeError::class,],
            "invalidInt" => [42, TypeError::class,],
            "invalidFloat" => [3.1415926, TypeError::class,],
            "invalidBool" => [true, TypeError::class,],
            "invalidObject" => [new class{}, TypeError::class,],
            "invalidArray" => [[Mockery::mock(PDO::class)], TypeError::class,],
            "invalidNull" => [null, TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestSetConnection
     *
     * @param $connection mixed The value to test the mutator with.
     * @param string|null $exceptionClass The class of the expected exception, if any.
     */
    public function testSetConnection($connection, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = new QueryBuilder();
        $builder->setConnection($connection);
        self::assertSame($connection, $builder->connection());
    }

    /**
     * Ensure connection() returns the expected connection.
     */
    public function testConnection(): void
    {
        $builder = new QueryBuilder();
        $connection = Mockery::mock(PDO::class);
        self::assertSame($this->m_defaultConnection, $builder->connection());
        $builder->setConnection($connection);
        self::assertSame($connection, $builder->connection());
    }

    /**
     * Data provider for testSelect().
     *
     * @return array The test data.
     */
    public function dataForTestSelect(): array
    {
        return [
            "typicalSingleColumn" => [null, "foo", "bar", "SELECT `foo` FROM `bar`"],
            "typicalMultipleColumns" => [null, ["foo", "bar",], "bar", "SELECT `foo`,`bar` FROM `bar`"],
            "typicalSingleColumnWithAlias" => [null, ["bar" => "foo",], "bar", "SELECT `foo` AS `bar` FROM `bar`"],
            "typicalMultipleColumnsOneAlias" => [null, ["bar" => "foo", "baz",], "bar", "SELECT `foo` AS `bar`,`baz` FROM `bar`"],
            "typicalInitialTwoSingleColumn" => [["fizz", "buzz",], "foo", "bar", "SELECT `foo` FROM `bar`"],
            "typicalInitialTwoMultipleColumns" => [["fizz", "buzz",], ["foo", "bar",], "bar", "SELECT `foo`,`bar` FROM `bar`"],
            "typicalInitialTwoSingleColumnWithAlias" => [["fizz", "buzz",], ["bar" => "foo",], "bar", "SELECT `foo` AS `bar` FROM `bar`"],
            "typicalInitialTwoMultipleColumnsOneAlias" => [["fizz", "buzz",], ["bar" => "foo", "baz",], "bar", "SELECT `foo` AS `bar`,`baz` FROM `bar`"],
        ];
    }

    /**
     * @dataProvider dataForTestSelect
     *
     * @param mixed $select The test data for the select() method.
     * @param mixed $tables The tables to add to the test QueryBuilder.
     * @param string $sql The SQL the QueryBuilder is expected to generate.
     */
    public function testSelect($initialSelects, $select, $tables, string $sql): void
    {
        $builder = self::createBuilder($initialSelects, $tables);
        $actual = $builder->select($select);
        self::assertSame($builder, $actual, "QueryBuilder::select() did not return the same QueryBuilder instance.");
        self::assertEquals($sql, $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data provider for testAddSelect().
     *
     * @return array The test data.
     */
    public function dataForTestAddSelect(): array
    {
        return [
            "typicalEmptyAddSingleColumn" => [[], "foo", "bar", "SELECT `foo` FROM `bar`"],
            "typicalEmptyMultipleColumns" => [[], ["foo", "bar",], "bar", "SELECT `foo`,`bar` FROM `bar`"],
            "typicalEmptySingleColumnWithAlias" => [[], ["bar" => "foo",], "bar", "SELECT `foo` AS `bar` FROM `bar`"],
            "typicalEmptyMultipleColumnsOneAlias" => [[], ["bar" => "foo", "baz",], "bar", "SELECT `foo` AS `bar`,`baz` FROM `bar`"],
            "typicalSingleColumnAddSingleColumn" => ["fizz", "foo", "bar", "SELECT `fizz`,`foo` FROM `bar`"],
            "typicalSingleColumnMultipleColumns" => ["fizz", ["foo", "bar",], "bar", "SELECT `fizz`,`foo`,`bar` FROM `bar`"],
            "typicalSingleColumnSingleColumnWithAlias" => ["fizz", ["bar" => "foo",], "bar", "SELECT `fizz`,`foo` AS `bar` FROM `bar`"],
            "typicalSingleColumnMultipleColumnsOneAlias" => ["fizz", ["bar" => "foo", "baz",], "bar", "SELECT `fizz`,`foo` AS `bar`,`baz` FROM `bar`"],
            "typicalMultipleColumnsAddSingleColumn" => [["fizz", "buzz",], "foo", "bar", "SELECT `fizz`,`buzz`,`foo` FROM `bar`"],
            "typicalMultipleColumnsMultipleColumns" => [["fizz", "buzz",], ["foo", "bar",], "bar", "SELECT `fizz`,`buzz`,`foo`,`bar` FROM `bar`"],
            "typicalMultipleColumnsSingleColumnWithAlias" => [["fizz", "buzz",], ["bar" => "foo",], "bar", "SELECT `fizz`,`buzz`,`foo` AS `bar` FROM `bar`"],
            "typicalMultipleColumnsMultipleColumnsOneAlias" => [["fizz", "buzz",], ["bar" => "foo", "baz",], "bar", "SELECT `fizz`,`buzz`,`foo` AS `bar`,`baz` FROM `bar`"],

            "invalidEmptyAddIntColumns" => [[], 42, "bar", "", TypeError::class,],

            "invalidDuplicateColumn" => [["foo"], "foo", "bar", "", DuplicateColumnNameException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestAddSelect
     *
     * @param array|string[]|array<string,string> $initialSelects The test data for the select() method.
     * @param mixed $addSelects The test data for the select() method.
     * @param mixed $tables The tables to add to the test QueryBuilder.
     * @param string $sql The SQL the QueryBuilder is expected to generate.
     */
    public function testAddSelect($initialSelects, $addSelects, $tables, string $sql, ?string $exceptionClass = null): void
    {
        $builder = self::createBuilder($initialSelects, $tables);

        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $actual = $builder->addSelect($addSelects);
        self::assertSame($builder, $actual, "QueryBuilder::addSelect() did not return the same QueryBuilder instance.");
        self::assertEquals($sql, $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data provider for testAddRawSelect().
     * 
     * @return array The test data.
     */
    public function dataForTestAddRawSelect(): array
    {
        return [
            "typicalEmptyAdditionExpression" => [[], "`a` + `b`", "sum", "bar", "SELECT `a` + `b` AS `sum` FROM `bar`"],
            "typicalEmptyMultiplicationExpression" => [[], "`product`.`price` * `product`.`quantity`", "total", "product", "SELECT `product`.`price` * `product`.`quantity` AS `total` FROM `product`"],
            "typicalSingleColumnAddSingleExpression" => ["fizz", "SQRT(POW(`a`, 2) + POW(`b`, 2))", "c", "triangle", "SELECT `fizz`,SQRT(POW(`a`, 2) + POW(`b`, 2)) AS `c` FROM `triangle`"],
            "typicalMultipleColumnsAddSingleColumn" => [["fizz", "buzz",], "IF(`explode` = 1, 'BOOM', 'pfft')", "bang", "fireworks", "SELECT `fizz`,`buzz`,IF(`explode` = 1, 'BOOM', 'pfft') AS `bang` FROM `fireworks`"],
            "invalidIntExpression" => [[], 12, "foo", null, "", TypeError::class],
            "invalidFloatExpression" => [[], 99.99, "foo", null, "", TypeError::class,],
            "invalidNullExpression" => [[], null, "foo", null, "", TypeError::class,],
            "invalidStringableExpression" => [[], new class {
                public function __toString(): string
                {
                    return "foo";
                }
            }, "foo", null, "", TypeError::class,],
            "invalidObjectExpression" => [[], (object)["foo" => "bar"], "foo", null, "", TypeError::class,],
            "invalidBoolExpression" => [[], true, "foo", null, "", TypeError::class,],
            "invalidIntAlias" => [[], "`a` + `b`", 12, null, "", TypeError::class],
            "invalidFloatAlias" => [[], "`product`.`price` * `product`.`quantity`", 99.99, null, "", TypeError::class,],
            "invalidNullAlias" => [[], "`LENGTH(`name`)", null, null, "", TypeError::class,],
            "invalidStringableAlias" => [[], "SQRT(POW(`a`, 2) + POW(`b`, 2))", new class {
                public function __toString(): string
                {
                    return "foo";
                }
            }, null, "", TypeError::class,],
            "invalidObjectAlias" => [[], "IF(`explode` = 1, 'BOOM', 'pfft')", (object)["foo" => "bar"], null, "", TypeError::class,],
            "invalidBoolAlias" => [[], "`c` - `d` + `e` / `f`", true, null, "", TypeError::class,],
            "invalidDuplicateAlias" => [["foo" => "bar",], "fix", "foo", "foobar", "", DuplicateColumnNameException::class,]
        ];
    }

    /**
     * @dataProvider dataForTestAddRawSelect
     *
     * @param array|string[]|array<string,string> $initialSelects The test data for the select() method.
     * @param string $expression The test expression for the addRawSelect() method.
     * @param string $alias The alias for the test expression in the query.
     * @param mixed $tables The tables to add to the test QueryBuilder.
     * @param string $sql The SQL the QueryBuilder is expected to generate.
     */
    public function testAddRawSelect($initialSelects, $expression, $alias, $tables, string $sql, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }
        
        $builder = self::createBuilder($initialSelects, $tables);
        $actual = $builder->addRawSelect($expression, $alias);
        self::assertSame($builder, $actual, "QueryBuilder::addSelect() did not return the same QueryBuilder instance.");
        self::assertEquals($sql, $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data provider for testFrom()
     *
     * @return array The test data.
     */
    public function dataForTestFrom(): array
    {
        return [
            "typicalSingleTableNoAlias" => ["fizz", null, "`fizz`"],
            "typicalSingleTableWithAlias" => ["fizz", "buzz", "`fizz` AS `buzz`"],
            "typicalArraySingleTableNoAlias" => [["buzz" => "fizz"], null, "`fizz` AS `buzz`"],
            "typicalArrayTwoTablesOneAlias" => [["buzz" => "fizz", "bang",], null, "`fizz` AS `buzz`,`bang`"],
            "typicalArrayFourTablesNoAlias" => [["buzz", "fizz", "bang", "boom",], null, "`buzz`,`fizz`,`bang`,`boom`"],
            "extremeSameTableDifferentAliases" => [["buzz" => "fizz", "bang" => "fizz",], null, "`fizz` AS `buzz`,`fizz` AS `bang`"],
            "extremeSameTableOneAliases" => [["buzz" => "fizz", "fizz",], null, "`fizz` AS `buzz`,`fizz`"],
            "invalidInt" => [12, null, "", TypeError::class,],
            "invalidFloat" => [99.99, null, "", TypeError::class,],
            "invalidNull" => [null, null, "", TypeError::class,],
            "invalidStringable" => [new class {
            public function __toString(): string
                {
                    return "foo";
                }
            }, null, "", TypeError::class,],
            "invalidObject" => [(object)["foo" => "bar"], null, "", TypeError::class,],
            "invalidBool" => [true, null, "", TypeError::class,],
            "invalidEmpty" => ["", null, "", InvalidTableNameException::class,],
            "invalidEmptyAlias" => ["foo", "", "", InvalidTableNameException::class,],
            "invalidArrayEmptyAlias" => [["" => "foo",], null, "", InvalidTableNameException::class,],
            "invalidMultipleOneEmptyAlias" => [["bar" => "foo", "" => "buzz",], null, "", InvalidTableNameException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestFrom
     *
     * @param string|string[]|array<string,string> $tables The table(s) to add using the from() method.
     * @param string|null $alias The alias for the table if it's provided as a single string.
     * @param string $sqlFrom The SQL the builder is expected to generate for the FROM clause.
     * @param string|null $exceptionClass The exception expected to be thrown, if any.
     */
    public function testFrom($tables, ?string $alias, string $sqlFrom, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"]);
        $actual = $builder->from($tables, $alias);
        self::assertSame($builder, $actual, "QueryBuilder::from() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM {$sqlFrom}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data provider for `testFromWithDuplicates()`
     * @return array[] The test data.
     */
    public function dataForTestFromWithDuplicates(): array
    {
        return [
            ["foo", null, "foo", null, DuplicateTableNameException::class,],
            ["foo", null, "bar", "foo", DuplicateTableNameException::class,],
            ["foo", null, "_foo", null, null,],
            ["foo", null, "foo", "_foo", null,],
            ["bar", "foo", "foo", null, DuplicateTableNameException::class,],
        ];
    }

    /**
     * Test from() method detects duplicate tables/aliases.
     *
     * @dataProvider dataForTestFromWithDuplicates
     *
     * @param string $table
     * @param string $otherTable
     * @param string|null $exceptionClass
     *
     * @return void
     * @throws \Bead\Exceptions\Database\DuplicateColumnNameException
     * @throws \Bead\Exceptions\Database\DuplicateTableNameException
     * @throws \Bead\Exceptions\Database\InvalidColumnNameException
     */
    public function testFromWithDuplicates(string $table, ?string $alias, string $otherTable, ?string $otherAlias, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"]);
        $actual = $builder->from($table, $alias);
        self::assertSame($builder, $actual, "QueryBuilder::from() did not return the same QueryBuilder instance.");
        $actual = $builder->from($otherTable, $otherAlias);
        self::assertSame($builder, $actual, "QueryBuilder::from() did not return the same QueryBuilder instance.");
        self::assertEquals(
            "SELECT `foo`,`bar` FROM `{$table}`" . (isset($alias) ? " AS `{$alias}`" : "") . ",`{$otherTable}`" . (isset($otherAlias) ? " AS `{$otherAlias}`" : ""),
            $builder->sql(),
            "The QueryBuilder did not generate the expected SQL."
        );
    }

    /**
     * Data provider for testRawFrom()
     *
     * @return array The test data.
     */
    public function dataForTestRawFrom(): array
    {
        return [
            "typicalSingleExpression" => ["(SELECT `foo`, `bar` FROM `foobar` WHERE `deleted` <> 1)", "foobar_alias", "(SELECT `foo`, `bar` FROM `foobar` WHERE `deleted` <> 1) AS `foobar_alias`"],
            "typicalMultipleExpressions" => [
                [
                    "foobar_alias" => "(SELECT `foo`, `bar` FROM `foobar` WHERE `deleted` <> 1)",
                    "fizzbuzz_alias" => "(SELECT `foo`.`fizz`, `bar`.`buzz` FROM `foo`,`bar` WHERE `foo`.`id`=`bar`.`foo_id`)",
                ],
                null,
                "(SELECT `foo`, `bar` FROM `foobar` WHERE `deleted` <> 1) AS `foobar_alias`,(SELECT `foo`.`fizz`, `bar`.`buzz` FROM `foo`,`bar` WHERE `foo`.`id`=`bar`.`foo_id`) AS `fizzbuzz_alias`"
            ],
            "invalidInt" => [12, "foobar_alias", "", TypeError::class,],
            "invalidFloat" => [99.99, "foobar_alias", "", TypeError::class,],
            "invalidNullExpression" => [null, "foobar_alias", "", TypeError::class,],
            "invalidStringable" => [new class {
                public function __toString(): string
                {
                    return "(SELECT `foo`, `bar` FROM `foobar` WHERE `deleted` <> 1)";
                }
            }, "foobar_alias", "", TypeError::class,],
            "invalidObject" => [(object)["foobar_alias" => "(SELECT `foo`, `bar` FROM `foobar` WHERE `deleted` <> 1)"], "foobar_alias", "", TypeError::class,],
            "invalidBool" => [true, "foobar_alias", "", TypeError::class,],
            "invalidEmptyAlias" => ["(SELECT `foo`, `bar` FROM `foobar` WHERE `deleted` <> 1)", "", "", InvalidTableNameException::class,],
            "invalidArrayEmptyAlias" => [["" => "(SELECT `foo`, `bar` FROM `foobar` WHERE `deleted` <> 1)",], "foobar_alias", "", InvalidTableNameException::class,],
            "invalidMultipleOneEmptyAlias" => [
                [
                    "foobar_alias" => "(SELECT `foo`, `bar` FROM `foobar` WHERE `deleted` <> 1)",
                    "" => "(SELECT `foo`.`fizz`, `bar`.`buzz` FROM `foo`,`bar` WHERE `foo`.`id`=`bar`.`foo_id`)",
                ],
                null,
                "",
                InvalidTableNameException::class,
            ],
        ];
    }

    /**
     * @dataProvider dataForTestRawFrom
     *
     * @param string|array<string,string> $expression The expression or map of aliases to expressions.
     * @param string|null $alias The alias (if `$expression` is a string)
     * @param string $sqlFrom The expected SQL FROM clause
     * @param string|null $exceptionClass The expected exception, if any.
     */
    public function testRawFrom($expression, ?string $alias, string $sqlFrom, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"]);
        $actual = $builder->rawFrom($expression, $alias);
        self::assertSame($builder, $actual, "QueryBuilder::rawFrom() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM {$sqlFrom}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data provider for `testRawFromWithDuplicates()`
     * @return iterable The test data.
     */
    public function dataForTestRawFromWithDuplicates(): iterable
    {
        yield from [
            "duplicateAlias" => ["(SELECT foo, bar FROM foo)", "foo", "foo", "foo", DuplicateTableNameException::class,],
            "duplicateAliasArray" => [["foo" => "(SELECT foo, bar FROM foo)",], null, "foo", "foo", DuplicateTableNameException::class,],
            "duplicateAliasArrays" => [["foo" => "(SELECT foo, bar FROM foo)",], null, ["foo" => "(SELECT foo, bar FROM foo)",], null, DuplicateTableNameException::class,],
        ];
    }

    /**
     * Test from() method detects duplicate tables/aliases.
     *
     * @dataProvider dataForTestRawFromWithDuplicates
     *
     * @param string|array<string,string> $expression
     * @param string $alias
     * @param string|array<string,string> $otherExpression
     * @param string $otherAlias
     * @param string|null $exceptionClass
     *
     * @return void
     * @throws \Bead\Exceptions\Database\DuplicateColumnNameException
     * @throws \Bead\Exceptions\Database\DuplicateTableNameException
     * @throws \Bead\Exceptions\Database\InvalidColumnNameException
     */
    public function testRawFromWithDuplicates($expression, ?string $alias, $otherExpression, ?string $otherAlias, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"]);
        $actual = $builder->rawFrom($expression, $alias);
        self::assertSame($builder, $actual, "QueryBuilder::from() did not return the same QueryBuilder instance.");
        $actual = $builder->rawFrom($otherExpression, $otherAlias);
        self::assertSame($builder, $actual, "QueryBuilder::from() did not return the same QueryBuilder instance.");
        self::assertEquals(
            "SELECT `foo`,`bar` FROM {$expression}" . (isset($alias) ? " AS `{$alias}`" : "") . ",`{$otherExpression}`" . (isset($otherAlias) ? " AS `{$otherAlias}`" : ""),
            $builder->sql(),
            "The QueryBuilder did not generate the expected SQL."
        );
    }

    /**
     * Data provider for testLeftJoin()
     *
     * @return array The test data.
     */
    public function dataForTestLeftJoin(): array
    {
        return [
            "typical" => ["fizz", "foobar", "id", "fizz_id", null, "LEFT JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id`"],
            "typicalWith=Operator" => ["fizz", "foobar", "id", "=", "fizz_id", "LEFT JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id`"],
            "typicalWith!=Operator" => ["fizz", "foobar", "id", "!=", "fizz_id", "LEFT JOIN `fizz` ON `fizz`.`id`!=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsOnePair" => ["fizz", "foobar", ["id" => "fizz_id"], "AND", null, "LEFT JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "LEFT JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id` AND `fizz`.`buzz_id`=`foobar`.`buzz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "LEFT JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id` OR `fizz`.`buzz_id`=`foobar`.`buzz_id`"],
            "invalidDuplicateTableName" => ["foobar", "foobar", "id", "fizz_id", null, "", DuplicateTableNameException::class,],
            "invalidTableNotPresent" => ["fizz", "foo", "id", "fizz_id", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith=Operator" => ["fizz", "foo", "id", "=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith!=Operator" => ["fizz", "foo", "id", "!=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsOnePair" => ["fizz", "foo", ["id" => "fizz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "", OrphanedJoinException::class,],
            "invalidIntTable" => [12, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatTable" => [99.99, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullTable" => [null, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableTable" => [new class {
                public function __toString(): string
                {
                    return "fizz";
                }
            }, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolTable" => [true, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyTable" => ["", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestLeftJoin
     *
     * @param mixed $joinTable The table to join.
     * @param mixed $queryTable The table to join it to.
     * @param string|array $foreignFieldOrPairs The field in the foreign table to join on or an array of field pairs to
     * join on.
     * @param string|null $localFieldOrOperator The operator for the join expression or the field in the local table to
     * join on if the operator is implicitly = or `null` if an array of field pairs is being used.
     * @param string|null $localField The field in the local table to join on or null if the operator is implicitly =.
     * @param string $sqlJoin The expected SQL JOIN clause.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testLeftJoin($joinTable, string $queryTable, $foreignFieldOrPairs, ?string $localFieldOrOperator, ?string $localField, string $sqlJoin, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"], $queryTable);
        $actual = $builder->leftJoin($joinTable, "foobar", $foreignFieldOrPairs, $localFieldOrOperator, $localField);
        self::assertSame($builder, $actual, "QueryBuilder::leftJoin() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM `foobar` {$sqlJoin}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Ensure adding a left join with an empty local table throws.
     */
    public function testLeftJoinWithEmptyTable(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(InvalidTableNameException::class);
        $builder->leftJoin("fizz", "", "id", "fizz_id");
    }

    /**
     * Data provider for testInnerJoin()
     *
     * @return array The test data.
     */
    public function dataForTestInnerJoin(): array
    {
        return [
            "typical" => ["fizz", "foobar", "id", "fizz_id", null, "INNER JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id`"],
            "typicalWith=Operator" => ["fizz", "foobar", "id", "=", "fizz_id", "INNER JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id`"],
            "typicalWith!=Operator" => ["fizz", "foobar", "id", "!=", "fizz_id", "INNER JOIN `fizz` ON `fizz`.`id`!=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsOnePair" => ["fizz", "foobar", ["id" => "fizz_id"], "AND", null, "INNER JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "INNER JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id` AND `fizz`.`buzz_id`=`foobar`.`buzz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "INNER JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id` OR `fizz`.`buzz_id`=`foobar`.`buzz_id`"],
            "invalidDuplicateTableName" => ["foobar", "foobar", "id", "fizz_id", null, "", DuplicateTableNameException::class,],
            "invalidTableNotPresent" => ["fizz", "foo", "id", "fizz_id", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith=Operator" => ["fizz", "foo", "id", "=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith!=Operator" => ["fizz", "foo", "id", "!=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsOnePair" => ["fizz", "foo", ["id" => "fizz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "", OrphanedJoinException::class,],
            "invalidIntTable" => [12, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatTable" => [99.99, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullTable" => [null, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableTable" => [new class {
                public function __toString(): string
                {
                    return "fizz";
                }
            }, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolTable" => [true, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyTable" => ["", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestInnerJoin
     *
     * @param mixed $joinTable The table to join.
     * @param mixed $queryTable The table to join it to.
     * @param string|array $foreignFieldOrPairs The field in the foreign table to join on or an array of field pairs to
     * join on.
     * @param string|null $localFieldOrOperator The operator for the join expression or the field in the local table to
     * join on if the operator is implicitly = or `null` if an array of field pairs is being used.
     * @param string|null $localField The field in the local table to join on or null if the operator is implicitly =.
     * @param string $sqlJoin The expected SQL JOIN clause.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testInnerJoin($joinTable, string $queryTable, $foreignFieldOrPairs, ?string $localFieldOrOperator, ?string $localField, string $sqlJoin, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"], $queryTable);
        $actual = $builder->innerJoin($joinTable, "foobar", $foreignFieldOrPairs, $localFieldOrOperator, $localField);
        self::assertSame($builder, $actual, "QueryBuilder::leftJoin() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM `foobar` {$sqlJoin}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Ensure adding an inner join with an empty local table throws.
     */
    public function testInnerJoinWithEmptyTable(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(InvalidTableNameException::class);
        $builder->innerJoin("fizz", "", "id", "fizz_id");
    }

    /**
     * Data provider for testRightJoin()
     *
     * @return array The test data.
     */
    public function dataForTestRightJoin(): array
    {
        return [
            "typical" => ["fizz", "foobar", "id", "fizz_id", null, "RIGHT JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id`"],
            "typicalWith=Operator" => ["fizz", "foobar", "id", "=", "fizz_id", "RIGHT JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id`"],
            "typicalWith!=Operator" => ["fizz", "foobar", "id", "!=", "fizz_id", "RIGHT JOIN `fizz` ON `fizz`.`id`!=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsOnePair" => ["fizz", "foobar", ["id" => "fizz_id"], "AND", null, "RIGHT JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "RIGHT JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id` AND `fizz`.`buzz_id`=`foobar`.`buzz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "RIGHT JOIN `fizz` ON `fizz`.`id`=`foobar`.`fizz_id` OR `fizz`.`buzz_id`=`foobar`.`buzz_id`"],
            "invalidDuplicateTableName" => ["foobar", "foobar", "id", "fizz_id", null, "", DuplicateTableNameException::class,],
            "invalidTableNotPresent" => ["fizz", "foo", "id", "fizz_id", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith=Operator" => ["fizz", "foo", "id", "=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith!=Operator" => ["fizz", "foo", "id", "!=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsOnePair" => ["fizz", "foo", ["id" => "fizz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "", OrphanedJoinException::class,],
            "invalidIntTable" => [12, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatTable" => [99.99, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullTable" => [null, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableTable" => [new class {
                public function __toString(): string
                {
                    return "fizz";
                }
            }, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolTable" => [true, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyTable" => ["", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestRightJoin
     *
     * @param mixed $joinTable The table to join.
     * @param mixed $queryTable The table to join it to.
     * @param string|array $foreignFieldOrPairs The field in the foreign table to join on or an array of field pairs to
     * join on.
     * @param string|null $localFieldOrOperator The operator for the join expression or the field in the local table to
     * join on if the operator is implicitly = or `null` if an array of field pairs is being used.
     * @param string|null $localField The field in the local table to join on or null if the operator is implicitly =.
     * @param string $sqlJoin The expected SQL JOIN clause.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testRightJoin($joinTable, string $queryTable, $foreignFieldOrPairs, ?string $localFieldOrOperator, ?string $localField, string $sqlJoin, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"], $queryTable);
        $actual = $builder->rightJoin($joinTable, "foobar", $foreignFieldOrPairs, $localFieldOrOperator, $localField);
        self::assertSame($builder, $actual, "QueryBuilder::leftJoin() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM `foobar` {$sqlJoin}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Ensure adding a right join with an empty local table throws.
     */
    public function testRightJoinWithEmptyTable(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(InvalidTableNameException::class);
        $builder->rightJoin("fizz", "", "id", "fizz_id");
    }

    /**
     * Data provider for testLeftJoinAs()
     *
     * @return array The test data.
     */
    public function dataForTestLeftJoinAs(): array
    {
        return [
            "typical" => ["fizz", "f", "foobar", "id", "fizz_id", null, "LEFT JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith=Operator" => ["fizz", "f", "foobar", "id", "=", "fizz_id", "LEFT JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith!=Operator" => ["fizz", "f", "foobar", "id", "!=", "fizz_id", "LEFT JOIN `fizz` AS `f` ON `f`.`id`!=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsOnePair" => ["fizz", "f", "foobar", ["id" => "fizz_id"], "AND", null, "LEFT JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "LEFT JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id` AND `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "LEFT JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id` OR `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "invalidTableNotPresent" => ["fizz", "f", "foo", "id", "fizz_id", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith=Operator" => ["fizz", "f", "foo", "id", "=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith!=Operator" => ["fizz", "f", "foo", "id", "!=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsOnePair" => ["fizz", "f", "foo", ["id" => "fizz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "", OrphanedJoinException::class,],
            "invalidIntTable" => [12, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatTable" => [99.99, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullTable" => [null, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableTable" => [new class {
                public function __toString(): string
                {
                    return "fizz";
                }
            }, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolTable" => [true, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyTable" => ["", "f", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
            "invalidIntAlias" => ["fizz", 12, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatAlias" => ["fizz", 99.99, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullAlias" => ["fizz", null, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableAlias" => ["fizz", new class {
                public function __toString(): string
                {
                    return "f";
                }
            }, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolAlias" => ["fizz", true, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyAlias" => ["fizz", "", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
            "invalidEmptyTableAndAlias" => ["", "", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestLeftJoinAs
     *
     * @param mixed $joinTable The table to join.
     * @param mixed $queryTable The table to join it to.
     * @param string|array $foreignFieldOrPairs The field in the foreign table to join on or an array of field pairs to
     * join on.
     * @param string|null $localFieldOrOperator The operator for the join expression or the field in the local table to
     * join on if the operator is implicitly = or `null` if an array of field pairs is being used.
     * @param string|null $localField The field in the local table to join on or null if the operator is implicitly =.
     * @param string $sqlJoin The expected SQL JOIN clause.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testLeftJoinAs($joinTable, $alias, string $queryTable, $foreignFieldOrPairs, ?string $localFieldOrOperator, ?string $localField, string $sqlJoin, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"], $queryTable);
        $actual = $builder->leftJoinAs($joinTable, $alias, "foobar", $foreignFieldOrPairs, $localFieldOrOperator, $localField);
        self::assertSame($builder, $actual, "QueryBuilder::leftJoinAs() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM `foobar` {$sqlJoin}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Ensure adding an aliased left join with an empty local table throws.
     */
    public function testLeftJoinAsWithEmptyTable(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(InvalidTableNameException::class);
        $builder->leftJoinAs("fizz", "f", "", "id", "fizz_id");
    }

    /**
     * Data provider for testInnerJoinAs()
     *
     * @return array The test data.
     */
    public function dataForTestInnerJoinAs(): array
    {
        return [
            "typical" => ["fizz", "f", "foobar", "id", "fizz_id", null, "INNER JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith=Operator" => ["fizz", "f", "foobar", "id", "=", "fizz_id", "INNER JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith!=Operator" => ["fizz", "f", "foobar", "id", "!=", "fizz_id", "INNER JOIN `fizz` AS `f` ON `f`.`id`!=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsOnePair" => ["fizz", "f", "foobar", ["id" => "fizz_id"], "AND", null, "INNER JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "INNER JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id` AND `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "INNER JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id` OR `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "invalidTableNotPresent" => ["fizz", "f", "foo", "id", "fizz_id", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith=Operator" => ["fizz", "f", "foo", "id", "=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith!=Operator" => ["fizz", "f", "foo", "id", "!=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsOnePair" => ["fizz", "f", "foo", ["id" => "fizz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "", OrphanedJoinException::class,],
            "invalidIntTable" => [12, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatTable" => [99.99, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullTable" => [null, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableTable" => [new class {
                public function __toString(): string
                {
                    return "fizz";
                }
            }, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolTable" => [true, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyTable" => ["", "f", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
            "invalidIntAlias" => ["fizz", 12, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatAlias" => ["fizz", 99.99, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullAlias" => ["fizz", null, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableAlias" => ["fizz", new class {
                public function __toString(): string
                {
                    return "f";
                }
            }, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolAlias" => ["fizz", true, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyAlias" => ["fizz", "", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
            "invalidEmptyTableAndAlias" => ["", "", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestInnerJoinAs
     *
     * @param mixed $joinTable The table to join.
     * @param mixed $queryTable The table to join it to.
     * @param string|array $foreignFieldOrPairs The field in the foreign table to join on or an array of field pairs to
     * join on.
     * @param string|null $localFieldOrOperator The operator for the join expression or the field in the local table to
     * join on if the operator is implicitly = or `null` if an array of field pairs is being used.
     * @param string|null $localField The field in the local table to join on or null if the operator is implicitly =.
     * @param string $sqlJoin The expected SQL JOIN clause.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testInnerJoinAs($joinTable, $alias, string $queryTable, $foreignFieldOrPairs, ?string $localFieldOrOperator, ?string $localField, string $sqlJoin, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"], $queryTable);
        $actual = $builder->innerJoinAs($joinTable, $alias, "foobar", $foreignFieldOrPairs, $localFieldOrOperator, $localField);
        self::assertSame($builder, $actual, "QueryBuilder::leftJoinAs() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM `foobar` {$sqlJoin}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Ensure adding an aliased inner join with an empty local table throws.
     */
    public function testInnerJoinAsWithEmptyTable(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(InvalidTableNameException::class);
        $builder->innerJoinAs("fizz", "f", "", "id", "fizz_id");
    }

    /**
     * Data provider for testRightJoinAs()
     *
     * @return array The test data.
     */
    public function dataForTestRightJoinAs(): array
    {
        return [
            "typical" => ["fizz", "f", "foobar", "id", "fizz_id", null, "RIGHT JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith=Operator" => ["fizz", "f", "foobar", "id", "=", "fizz_id", "RIGHT JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith!=Operator" => ["fizz", "f", "foobar", "id", "!=", "fizz_id", "RIGHT JOIN `fizz` AS `f` ON `f`.`id`!=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsOnePair" => ["fizz", "f", "foobar", ["id" => "fizz_id"], "AND", null, "RIGHT JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "RIGHT JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id` AND `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "RIGHT JOIN `fizz` AS `f` ON `f`.`id`=`foobar`.`fizz_id` OR `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "invalidTableNotPresent" => ["fizz", "f", "foo", "id", "fizz_id", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith=Operator" => ["fizz", "f", "foo", "id", "=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith!=Operator" => ["fizz", "f", "foo", "id", "!=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsOnePair" => ["fizz", "f", "foo", ["id" => "fizz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithAnd" => ["fizz", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithOr" => ["fizz", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "", OrphanedJoinException::class,],
            "invalidIntTable" => [12, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatTable" => [99.99, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullTable" => [null, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableTable" => [new class {
                public function __toString(): string
                {
                    return "fizz";
                }
            }, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolTable" => [true, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyTable" => ["", "f", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
            "invalidIntAlias" => ["fizz", 12, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatAlias" => ["fizz", 99.99, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullAlias" => ["fizz", null, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableAlias" => ["fizz", new class {
                public function __toString(): string
                {
                    return "f";
                }
            }, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolAlias" => ["fizz", true, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyAlias" => ["fizz", "", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
            "invalidEmptyTableAndAlias" => ["", "", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestRightJoinAs
     *
     * @param mixed $joinTable The table to join.
     * @param mixed $queryTable The table to join it to.
     * @param string|array $foreignFieldOrPairs The field in the foreign table to join on or an array of field pairs to
     * join on.
     * @param string|null $localFieldOrOperator The operator for the join expression or the field in the local table to
     * join on if the operator is implicitly = or `null` if an array of field pairs is being used.
     * @param string|null $localField The field in the local table to join on or null if the operator is implicitly =.
     * @param string $sqlJoin The expected SQL JOIN clause.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testRightJoinAs($joinTable, $alias, string $queryTable, $foreignFieldOrPairs, ?string $localFieldOrOperator, ?string $localField, string $sqlJoin, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"], $queryTable);
        $actual = $builder->rightJoinAs($joinTable, $alias, "foobar", $foreignFieldOrPairs, $localFieldOrOperator, $localField);
        self::assertSame($builder, $actual, "QueryBuilder::leftJoinAs() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM `foobar` {$sqlJoin}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Ensure adding an aliased right join with an empty local table throws.
     */
    public function testRightJoinAsWithEmptyTable(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(InvalidTableNameException::class);
        $builder->rightJoinAs("fizz", "f", "", "id", "fizz_id");
    }

    /**
     * Data provider for testRightJoinAs()
     *
     * @return array The test data.
     */
    public function dataForTestRawLeftJoin(): array
    {
        return [
            "typical" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", "id", "fizz_id", null, "LEFT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", "id", "=", "fizz_id", "LEFT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith!=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", "id", "!=", "fizz_id", "LEFT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`!=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsOnePair" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", ["id" => "fizz_id"], "AND", null, "LEFT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithAnd" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "LEFT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id` AND `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithOr" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "LEFT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id` OR `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "invalidTableNotPresent" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", "id", "fizz_id", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", "id", "=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith!=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", "id", "!=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsOnePair" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", ["id" => "fizz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithAnd" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithOr" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "", OrphanedJoinException::class,],
            "invalidIntExpression" => [12, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatExpression" => [99.99, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullExpression" => [null, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableExpression" => [new class {
                public function __toString(): string
                {
                    return "(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)";
                }
            }, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolExpression" => [true, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyExpression" => ["", "f", "foobar", "id", "fizz_id", null, "", InvalidQueryExpressionException::class,],
            "invalidIntAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", 12, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", 99.99, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", null, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", new class {
                public function __toString(): string
                {
                    return "f";
                }
            }, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", true, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
            "invalidEmptyExpressionAndAlias" => ["", "", "foobar", "id", "fizz_id", null, "", InvalidQueryExpressionException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestRawLeftJoin
     *
     * @param mixed $joinExpression The raw SQL expression to join.
     * @param mixed $queryTable The table to join it to.
     * @param string|array $foreignFieldOrPairs The field in the foreign table to join on or an array of field pairs to
     * join on.
     * @param string|null $localFieldOrOperator The operator for the join expression or the field in the local table to
     * join on if the operator is implicitly = or `null` if an array of field pairs is being used.
     * @param string|null $localField The field in the local table to join on or null if the operator is implicitly =.
     * @param string $sqlJoin The expected SQL JOIN clause.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testRawLeftJoin($joinExpression, $alias, string $queryTable, $foreignFieldOrPairs, ?string $localFieldOrOperator, ?string $localField, string $sqlJoin, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"], $queryTable);
        $actual = $builder->rawLeftJoin($joinExpression, $alias, "foobar", $foreignFieldOrPairs, $localFieldOrOperator, $localField);
        self::assertSame($builder, $actual, "QueryBuilder::leftJoinAs() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM `foobar` {$sqlJoin}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Ensure adding a raw left join with an empty local table throws.
     */
    public function testRawLeftJoinAsWithEmptyTable(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(InvalidTableNameException::class);
        $builder->rawLeftJoin("(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`))", "f", "", "id", "fizz_id");
    }

    /**
     * Ensure adding a raw left join with an alias that's already in use throws.
     */
    public function testRawLeftJoinWithDuplicateAlias(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(DuplicateTableNameException::class);
        $builder->rawLeftJoin("(SELECT * FROM fizz)", "foobar", "id", "fizz_id");
    }

    /**
     * Data provider for testRightJoinAs()
     *
     * @return array The test data.
     */
    public function dataForTestRawRightJoin(): array
    {
        return [
            "typical" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", "id", "fizz_id", null, "RIGHT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", "id", "=", "fizz_id", "RIGHT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith!=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", "id", "!=", "fizz_id", "RIGHT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`!=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsOnePair" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", ["id" => "fizz_id"], "AND", null, "RIGHT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithAnd" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "RIGHT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id` AND `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithOr" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "RIGHT JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id` OR `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "invalidTableNotPresent" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", "id", "fizz_id", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", "id", "=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith!=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", "id", "!=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsOnePair" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", ["id" => "fizz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithAnd" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithOr" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "", OrphanedJoinException::class,],
            "invalidIntExpression" => [12, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatExpression" => [99.99, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullExpression" => [null, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableExpression" => [new class {
                public function __toString(): string
                {
                    return "(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)";
                }
            }, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolExpression" => [true, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyExpression" => ["", "f", "foobar", "id", "fizz_id", null, "", InvalidQueryExpressionException::class,],
            "invalidIntAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", 12, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", 99.99, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", null, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", new class {
                public function __toString(): string
                {
                    return "f";
                }
            }, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", true, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
            "invalidEmptyExpressionAndAlias" => ["", "", "foobar", "id", "fizz_id", null, "", InvalidQueryExpressionException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestRawRightJoin
     *
     * @param mixed $joinExpression The raw SQL expression to join.
     * @param mixed $queryTable The table to join it to.
     * @param string|array $foreignFieldOrPairs The field in the foreign table to join on or an array of field pairs to
     * join on.
     * @param string|null $localFieldOrOperator The operator for the join expression or the field in the local table to
     * join on if the operator is implicitly = or `null` if an array of field pairs is being used.
     * @param string|null $localField The field in the local table to join on or null if the operator is implicitly =.
     * @param string $sqlJoin The expected SQL JOIN clause.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testRawRightJoin($joinExpression, $alias, string $queryTable, $foreignFieldOrPairs, ?string $localFieldOrOperator, ?string $localField, string $sqlJoin, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"], $queryTable);
        $actual = $builder->rawRightJoin($joinExpression, $alias, "foobar", $foreignFieldOrPairs, $localFieldOrOperator, $localField);
        self::assertSame($builder, $actual, "QueryBuilder::leftJoinAs() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM `foobar` {$sqlJoin}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Ensure adding a raw right join with an empty local table throws.
     */
    public function testRawRightJoinAsWithEmptyTable(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(InvalidTableNameException::class);
        $builder->rawRightJoin("(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`))", "f", "", "id", "fizz_id");
    }

    /**
     * Ensure adding a raw right join with an alias that's already in use throws.
     */
    public function testRawRightJoinWithDuplicateAlias(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(DuplicateTableNameException::class);
        $builder->rawRightJoin("(SELECT * FROM fizz)", "foobar", "id", "fizz_id");
    }

    /**
     * Data provider for testRightJoinAs()
     *
     * @return array The test data.
     */
    public function dataForTestRawInnerJoin(): array
    {
        return [
            "typical" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", "id", "fizz_id", null, "INNER JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", "id", "=", "fizz_id", "INNER JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalWith!=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", "id", "!=", "fizz_id", "INNER JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`!=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsOnePair" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", ["id" => "fizz_id"], "AND", null, "INNER JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithAnd" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "INNER JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id` AND `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "typicalMultipleJoinExpressionsTwoPairsWithOr" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foobar", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "INNER JOIN (SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`) AS `f` ON `f`.`id`=`foobar`.`fizz_id` OR `f`.`buzz_id`=`foobar`.`buzz_id`"],
            "invalidTableNotPresent" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", "id", "fizz_id", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", "id", "=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentWith!=Operator" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", "id", "!=", "fizz_id", "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsOnePair" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", ["id" => "fizz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithAnd" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "AND", null, "", OrphanedJoinException::class,],
            "invalidTableNotPresentMultipleJoinExpressionsTwoPairsWithOr" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "f", "foo", ["id" => "fizz_id", "buzz_id" => "buzz_id"], "OR", null, "", OrphanedJoinException::class,],
            "invalidIntExpression" => [12, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatExpression" => [99.99, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullExpression" => [null, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableExpression" => [new class {
                public function __toString(): string
                {
                    return "(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)";
                }
            }, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolExpression" => [true, "f", "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyExpression" => ["", "f", "foobar", "id", "fizz_id", null, "", InvalidQueryExpressionException::class,],
            "invalidIntAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", 12, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidFloatAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", 99.99, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidNullAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", null, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidStringableAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", new class {
                public function __toString(): string
                {
                    return "f";
                }
            }, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidBoolAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", true, "foobar", "id", "fizz_id", null, "", TypeError::class,],
            "invalidEmptyAlias" => ["(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`)", "", "foobar", "id", "fizz_id", null, "", InvalidTableNameException::class,],
            "invalidEmptyExpressionAndAlias" => ["", "", "foobar", "id", "fizz_id", null, "", InvalidQueryExpressionException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestRawInnerJoin
     *
     * @param mixed $joinExpression The raw SQL expression to join.
     * @param mixed $queryTable The table to join it to.
     * @param string|array $foreignFieldOrPairs The field in the foreign table to join on or an array of field pairs to
     * join on.
     * @param string|null $localFieldOrOperator The operator for the join expression or the field in the local table to
     * join on if the operator is implicitly = or `null` if an array of field pairs is being used.
     * @param string|null $localField The field in the local table to join on or null if the operator is implicitly =.
     * @param string $sqlJoin The expected SQL JOIN clause.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    public function testRawInnerJoin($joinExpression, $alias, string $queryTable, $foreignFieldOrPairs, ?string $localFieldOrOperator, ?string $localField, string $sqlJoin, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder(["foo", "bar"], $queryTable);
        $actual = $builder->rawInnerJoin($joinExpression, $alias, "foobar", $foreignFieldOrPairs, $localFieldOrOperator, $localField);
        self::assertSame($builder, $actual, "QueryBuilder::leftJoinAs() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM `foobar` {$sqlJoin}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Ensure adding a raw inner join with an empty local table throws.
     */
    public function testRawInnerJoinAsWithEmptyTable(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(InvalidTableNameException::class);
        $builder->rawInnerJoin("(SELECT `flux`, `box` FROM `fluxbox` WHERE `flux` > `box`))", "f", "", "id", "fizz_id");
    }

    /**
     * Ensure adding a raw inner join with an alias that's already in use throws.
     */
    public function testRawInnerJoinWithDuplicateAlias(): void
    {
        $builder = self::createBuilder(["foo", "bar"], "foobar");
        $this->expectException(DuplicateTableNameException::class);
        $builder->rawInnerJoin("(SELECT * FROM fizz)", "foobar", "id", "fizz_id");
    }

    /**
     * Date for testWhereWithFields
     *
     * @return array
     */
    public function dataForTestWhereWithFields(): array
    {
        return [
            "typicalSingleField" => ["foo", "value", null, "WHERE (`foo` = 'value')"],
            "typicalSingleFieldWithTable" => ["foobar.foo", "value", null, "WHERE (`foobar`.`foo` = 'value')"],
            "typicalSingleFieldWithOperator" => ["foo", "!=", "value", "WHERE (`foo` != 'value')"],
            "typicalSingleFieldWithTableAndOperator" => ["foobar.foo", "!=", "value", "WHERE (`foobar`.`foo` != 'value')"],
            "typicalMultipleFields" => [["foo" => "foo-value", "bar" => "bar-value",], null, null, "WHERE (`foo` = 'foo-value' AND `bar` = 'bar-value')"],
            "typicalMultipleFieldsWithTable" => [["foobar.foo" => "foo-value", "foobar.bar" => "bar-value",], null, null, "WHERE (`foobar`.`foo` = 'foo-value' AND `foobar`.`bar` = 'bar-value')"],
            "typicalMultipleFieldsWithMix" => [["foobar.foo" => "foo-value", "bar" => "bar-value",], null, null, "WHERE (`foobar`.`foo` = 'foo-value' AND `bar` = 'bar-value')"],

            "extremeLargeNumberOfFields" => [
                [
                    "foo" => "foo-value", "bar" => "bar-value", "fizz" => "fizz-value", "buzz" => "buzz-value",
                    "flux" => "flux-value", "box" => "box-value", "flib" => "flib-value", "bib" => "bib-value",
                    "fang" => "fang-value", "bang" => "bang-value", "fork" => "fork-value", "bork" => "bork-value",
                    "fix" => "fix-value", "bix" => "bix-value", "fab" => "fab-value", "bab" => "bab-value",
                ], null, null,
                "WHERE (`foo` = 'foo-value' AND `bar` = 'bar-value' AND `fizz` = 'fizz-value' AND `buzz` = 'buzz-value' AND `flux` = 'flux-value' AND `box` = 'box-value' AND `flib` = 'flib-value' AND `bib` = 'bib-value' AND `fang` = 'fang-value' AND `bang` = 'bang-value' AND `fork` = 'fork-value' AND `bork` = 'bork-value' AND `fix` = 'fix-value' AND `bix` = 'bix-value' AND `fab` = 'fab-value' AND `bab` = 'bab-value')"],
            
            "typicalSingleFieldWith=Operator" => ["foo", "=", "value", "WHERE (`foo` = 'value')"],
            "typicalSingleFieldWithTableAnd=Operator" => ["foobar.foo", "=", "value", "WHERE (`foobar`.`foo` = 'value')"],
            "typicalSingleFieldWith!=Operator" => ["foo", "!=", "value", "WHERE (`foo` != 'value')"],
            "typicalSingleFieldWithTableAnd!=Operator" => ["foobar.foo", "!=", "value", "WHERE (`foobar`.`foo` != 'value')"],
            "typicalSingleFieldWith<>Operator" => ["foo", "<>", "value", "WHERE (`foo` <> 'value')"],
            "typicalSingleFieldWithTableAnd<>Operator" => ["foobar.foo", "<>", "value", "WHERE (`foobar`.`foo` <> 'value')"],
            "typicalSingleFieldWith>Operator" => ["foo", ">", "value", "WHERE (`foo` > 'value')"],
            "typicalSingleFieldWithTableAnd>Operator" => ["foobar.foo", ">", "value", "WHERE (`foobar`.`foo` > 'value')"],
            "typicalSingleFieldWith<Operator" => ["foo", "<", "value", "WHERE (`foo` < 'value')"],
            "typicalSingleFieldWithTableAnd<Operator" => ["foobar.foo", "<", "value", "WHERE (`foobar`.`foo` < 'value')"],
            "typicalSingleFieldWith>=Operator" => ["foo", ">=", "value", "WHERE (`foo` >= 'value')"],
            "typicalSingleFieldWithTableAnd>=Operator" => ["foobar.foo", ">=", "value", "WHERE (`foobar`.`foo` >= 'value')"],
            "typicalSingleFieldWith<=Operator" => ["foo", "<=", "value", "WHERE (`foo` <= 'value')"],
            "typicalSingleFieldWithTableAnd<=Operator" => ["foobar.foo", "<=", "value", "WHERE (`foobar`.`foo` <= 'value')"],
            "typicalSingleFieldWithLIKEOperator" => ["foo", "LIKE", "value", "WHERE (`foo` LIKE 'value')"],
            "typicalSingleFieldWithTableAndLIKEOperator" => ["foobar.foo", "LIKE", "value", "WHERE (`foobar`.`foo` LIKE 'value')"],
            "typicalSingleFieldWithNOT LIKEOperator" => ["foo", "NOT LIKE", "value", "WHERE (`foo` NOT LIKE 'value')"],
            "typicalSingleFieldWithTableAndNOT LIKEOperator" => ["foobar.foo", "NOT LIKE", "value", "WHERE (`foobar`.`foo` NOT LIKE 'value')"],

            "invalidEmptyField" => ["", "NOT LIKE", "value", "", InvalidColumnNameException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "NOT LIKE", "value", "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "NOT LIKE", "value", "", TypeError::class,],
            "invalidIntField" => [42, "NOT LIKE", "value", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "NOT LIKE", "value", "", TypeError::class,],
            "invalidNullField" => [null, "NOT LIKE", "value", "", TypeError::class,],
            "invalidBoolField" => [true, "NOT LIKE", "value", "", TypeError::class,],

            "invalidEmptyOperator" => ["foobar.foo", "", "value", "", InvalidOperatorException::class,],
            "invalidStringableOperator" => ["foobar.foo", new class() {
                public function __toString(): string {
                    return "=";
                }
            }, "value", "", TypeError::class,],
            "invalidIntOperator" => ["foobar.foo", 42, "value", "", TypeError::class,],
            "invalidFloatOperator" => ["foobar.foo", 3.1415927, "value", "", TypeError::class,],
            "invalidNullOperator" => ["foobar.foo", null, "value", "", TypeError::class,],
            "invalidBoolOperator" => ["foobar.foo", true, "value", "", TypeError::class,],

            "invalidStringableValue" => ["foobar.foo", "=", new class() {
                public function __toString(): string {
                    return "value";
                }
            }, "", TypeError::class,],
            "invalidArrayValue" => ["foobar.foo", "=", ["value",], "", TypeError::class,],
        ];
    }

    /**
     * Test QueryBuilder::where() with one or more fields and operators (i.e. not a grouping closure).
     *
     * @dataProvider dataForTestWhereWithFields
     *
     * @param mixed $field The field or field => value pairs for the WHERE clause.
     * @param mixed $operatorOrValue The operator or value for the WHERE clause if `$field` is a single field.
     * @param mixed $value The value for the WHERE clause if `$field` is a single field and `$operatorOrValue` is an
     * operator.
     */
    public function testWhereWithFields($field, $operatorOrValue, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = $this->createBuilder(["foo", "bar"], "foobar");
        $actual = $builder->where($field, $operatorOrValue, $value);
        self::assertSame($builder, $actual, "QueryBuilder::where() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar` FROM `foobar` {$sqlWhere}", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data provider for testWhereWithClosure.
     *
     * @return array[] The test data.
     */
    public function dataForTestWhereWithClosure(): array
    {
        return [
            "typicalMultipleOr" => [
                function (QueryBuilder $builder): void {
                    $builder->orWhere("foo", "foo");
                    $builder->orWhere("bar", "bar");
                    $builder->orWhere("fizz", "fizz");
                },
                "(`foo` = 'foo' OR `bar` = 'bar' OR `fizz` = 'fizz')"
            ],
            "typicalMultipleAnd" => [
                function (QueryBuilder $builder): void {
                    $builder->where("foo", "foo");
                    $builder->where("bar", "bar");
                    $builder->where("fizz", "fizz");
                },
                "(`foo` = 'foo' AND `bar` = 'bar' AND `fizz` = 'fizz')"
            ],
            "typicalMultipleAndArray" => [
                function (QueryBuilder $builder): void {
                    $builder->where(["foo" => "foo", "bar" => "bar", "fizz" => "fizz",]);
                },
                "(`foo` = 'foo' AND `bar` = 'bar' AND `fizz` = 'fizz')"
            ],
            "typicalMultipleAndWithOr" => [
                function (QueryBuilder $builder): void {
                    $builder->where(function (QueryBuilder $builder): void {
                        $builder->where("foo", "foo");
                        $builder->where("bar", "bar");
                        $builder->where("fizz", "fizz");
                    });
                    $builder->orWhere(function (QueryBuilder $builder): void {
                        $builder->where("buzz", true);
                    });
                },
                "((`foo` = 'foo' AND `bar` = 'bar' AND `fizz` = 'fizz') OR (`buzz` = 1))"
            ],
            "typicalMultipleAndArrayWithOr" => [
                function (QueryBuilder $builder): void {
                    $builder->where(function (QueryBuilder $builder): void {
                        $builder->where(["foo" => "foo", "bar" => "bar", "fizz" => "fizz",]);
                    });
                    $builder->orWhere(function (QueryBuilder $builder): void {
                        $builder->where("buzz", true);
                    });
                },
                "((`foo` = 'foo' AND `bar` = 'bar' AND `fizz` = 'fizz') OR (`buzz` = 1))"
            ],
        ];
    }

    /**
     * @dataProvider dataForTestWhereWithClosure
     *
     * NOTE tests for incorrect parameter types are included in testWhereWithFields().
     * 
     * @param mixed $closure The closure to test with.
     * @param string $sqlWhere The expected WHERE clause
     */
    public function testWhereWithClosure($closure, string $sqlWhere): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->where($closure);
        self::assertSame($builder, $actual, "QueryBuilder::orWhere() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");

        // test with orWhere() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar")->where("foo", "foo");
        $actual = $builder->orWhere($closure);
        self::assertSame($builder, $actual, "QueryBuilder::orWhere() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE (`foo` = 'foo' OR {$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Provider of test data for testWhereNull.
     *
     * @return array[] The test data.
     */
    public function dataForTestWhereNull(): array
    {
        return [
            "typicalSingleField" =>["foo", "`foo` IS NULL",],
            "typicalMultipleFields" =>[["foo", "bar",], "`foo` IS NULL AND `bar` IS NULL",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo", "bar", "fizz", "buzz", "flex", "box", "fen", "bun", "fin", "bin", "flan", "ban", "flub",
                    "bax", "flip", "blip",
                ],
                "`foo` IS NULL AND `bar` IS NULL AND `fizz` IS NULL AND `buzz` IS NULL AND `flex` IS NULL AND " .
                "`box` IS NULL AND `fen` IS NULL AND `bun` IS NULL AND `fin` IS NULL AND `bin` IS NULL AND " .
                "`flan` IS NULL AND `ban` IS NULL AND `flub` IS NULL AND `bax` IS NULL AND `flip` IS NULL AND " .
                "`blip` IS NULL",
            ],
            "invalidEmptyField" => ["", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "", TypeError::class,],
            "invalidIntField" => [42, "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "", TypeError::class,],
            "invalidNullField" => [null, "", TypeError::class,],
            "invalidBoolField" => [true, "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestWhereNull
     *
     * @param mixed $columns The test field(s) to provide to the whereNull() method.
     * @param string $sqlWhere The expected SQL WHERE clause.
     * @param string|null $exceptionClass The expected exception, if any.
     */
    public function testWhereNull($columns, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->whereNull($columns);
        self::assertSame($builder, $actual, "QueryBuilder::whereNull() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Provider of test data for testWhereNotNull.
     *
     * @return array[] The test data.
     */
    public function dataForTestWhereNotNull(): array
    {
        return [
            "typicalSingleField" =>["foo", "`foo` IS NOT NULL",],
            "typicalMultipleFields" =>[["foo", "bar",], "`foo` IS NOT NULL AND `bar` IS NOT NULL",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo", "bar", "fizz", "buzz", "flex", "box", "fen", "bun", "fin", "bin", "flan", "ban", "flub",
                    "bax", "flip", "blip",
                ],
                "`foo` IS NOT NULL AND `bar` IS NOT NULL AND `fizz` IS NOT NULL AND `buzz` IS NOT NULL AND `flex` IS NOT NULL AND " .
                "`box` IS NOT NULL AND `fen` IS NOT NULL AND `bun` IS NOT NULL AND `fin` IS NOT NULL AND `bin` IS NOT NULL AND " .
                "`flan` IS NOT NULL AND `ban` IS NOT NULL AND `flub` IS NOT NULL AND `bax` IS NOT NULL AND `flip` IS NOT NULL AND " .
                "`blip` IS NOT NULL",
            ],
            "invalidEmptyField" => ["", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "", TypeError::class,],
            "invalidIntField" => [42, "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "", TypeError::class,],
            "invalidNullField" => [null, "", TypeError::class,],
            "invalidBoolField" => [true, "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestWhereNotNull
     *
     * @param mixed $columns The test field(s) to provide to the whereNotNull() method.
     * @param string $sqlWhere The expected SQL WHERE clause.
     * @param string|null $exceptionClass The expected exception, if any.
     */
    public function testWhereNotNull($columns, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->whereNotNull($columns);
        self::assertSame($builder, $actual, "QueryBuilder::whereNotNull() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data for testWhereContains()
     *
     * @return array The test data.
     */
    public function dataForTestWhereContains(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` LIKE '%bar%'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` LIKE '%bar%'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` LIKE '%bar%'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` LIKE '%bar%'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` LIKE '%bar%' AND `fizz` LIKE '%buzz%'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` LIKE '%bar%' AND `fizz` LIKE '%buzz%'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` LIKE '%bar%' AND `fizzbuzz`.`fizz` LIKE '%buzz%'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` LIKE '%bar%' AND `fizz` LIKE '%buzz%' AND `flux` LIKE '%blox%' AND `flim` LIKE '%blam%' AND `flub` LIKE '%blib%' AND " .
                "`frag` LIKE '%brag%' AND `fing` LIKE '%bing%' AND `fong` LIKE '%bong%' AND `fish` LIKE '%bash%' AND `frew` LIKE '%brow%' AND " .
                "`fop` LIKE '%bip%' AND `fad` LIKE '%bid%' AND `fort` LIKE '%burt%' AND `funk` LIKE '%benk%' AND `flip` LIKE '%blop%' AND " .
                "`fit` LIKE '%bot%' AND `for` LIKE '%bur%' AND `fell` LIKE '%bill%' AND `flag` LIKE '%blug%' AND `frux` LIKE '%brax%' AND " .
                "`fig` LIKE '%bag%' AND `fey` LIKE '%buy%' AND `flap` LIKE '%bilk%' AND `frop` LIKE '%blap%' AND `fum` LIKE '%bom%'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestWhereContains
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testWhereContains($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->whereContains($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereContains() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data for testWhereNotContains()
     *
     * @return array The test data.
     */
    public function dataForTestWhereNotContains(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` NOT LIKE '%bar%'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` NOT LIKE '%bar%'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` NOT LIKE '%bar%'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` NOT LIKE '%bar%'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` NOT LIKE '%bar%' AND `fizz` NOT LIKE '%buzz%'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE '%bar%' AND `fizz` NOT LIKE '%buzz%'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE '%bar%' AND `fizzbuzz`.`fizz` NOT LIKE '%buzz%'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` NOT LIKE '%bar%' AND `fizz` NOT LIKE '%buzz%' AND `flux` NOT LIKE '%blox%' AND `flim` NOT LIKE '%blam%' AND `flub` NOT LIKE '%blib%' AND " .
                "`frag` NOT LIKE '%brag%' AND `fing` NOT LIKE '%bing%' AND `fong` NOT LIKE '%bong%' AND `fish` NOT LIKE '%bash%' AND `frew` NOT LIKE '%brow%' AND " .
                "`fop` NOT LIKE '%bip%' AND `fad` NOT LIKE '%bid%' AND `fort` NOT LIKE '%burt%' AND `funk` NOT LIKE '%benk%' AND `flip` NOT LIKE '%blop%' AND " .
                "`fit` NOT LIKE '%bot%' AND `for` NOT LIKE '%bur%' AND `fell` NOT LIKE '%bill%' AND `flag` NOT LIKE '%blug%' AND `frux` NOT LIKE '%brax%' AND " .
                "`fig` NOT LIKE '%bag%' AND `fey` NOT LIKE '%buy%' AND `flap` NOT LIKE '%bilk%' AND `frop` NOT LIKE '%blap%' AND `fum` NOT LIKE '%bom%'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestWhereNotContains
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testWhereNotContains($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->whereNotContains($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereNotContains() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data for testWhereStartsWith()
     *
     * @return array The test data.
     */
    public function dataForTestWhereStartsWith(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` LIKE 'bar%'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` LIKE 'bar%'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` LIKE 'bar%'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` LIKE 'bar%'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` LIKE 'bar%' AND `fizz` LIKE 'buzz%'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` LIKE 'bar%' AND `fizz` LIKE 'buzz%'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` LIKE 'bar%' AND `fizzbuzz`.`fizz` LIKE 'buzz%'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` LIKE 'bar%' AND `fizz` LIKE 'buzz%' AND `flux` LIKE 'blox%' AND `flim` LIKE 'blam%' AND `flub` LIKE 'blib%' AND " .
                "`frag` LIKE 'brag%' AND `fing` LIKE 'bing%' AND `fong` LIKE 'bong%' AND `fish` LIKE 'bash%' AND `frew` LIKE 'brow%' AND " .
                "`fop` LIKE 'bip%' AND `fad` LIKE 'bid%' AND `fort` LIKE 'burt%' AND `funk` LIKE 'benk%' AND `flip` LIKE 'blop%' AND " .
                "`fit` LIKE 'bot%' AND `for` LIKE 'bur%' AND `fell` LIKE 'bill%' AND `flag` LIKE 'blug%' AND `frux` LIKE 'brax%' AND " .
                "`fig` LIKE 'bag%' AND `fey` LIKE 'buy%' AND `flap` LIKE 'bilk%' AND `frop` LIKE 'blap%' AND `fum` LIKE 'bom%'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestWhereStartsWith
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testWhereStartsWith($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->whereStartsWith($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereStartsWith() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data for testWhereNotStartsWith()
     *
     * @return array The test data.
     */
    public function dataForTestWhereNotStartsWith(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` NOT LIKE 'bar%'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` NOT LIKE 'bar%'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` NOT LIKE 'bar%'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` NOT LIKE 'bar%'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` NOT LIKE 'bar%' AND `fizz` NOT LIKE 'buzz%'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE 'bar%' AND `fizz` NOT LIKE 'buzz%'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE 'bar%' AND `fizzbuzz`.`fizz` NOT LIKE 'buzz%'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` NOT LIKE 'bar%' AND `fizz` NOT LIKE 'buzz%' AND `flux` NOT LIKE 'blox%' AND `flim` NOT LIKE 'blam%' AND `flub` NOT LIKE 'blib%' AND " .
                "`frag` NOT LIKE 'brag%' AND `fing` NOT LIKE 'bing%' AND `fong` NOT LIKE 'bong%' AND `fish` NOT LIKE 'bash%' AND `frew` NOT LIKE 'brow%' AND " .
                "`fop` NOT LIKE 'bip%' AND `fad` NOT LIKE 'bid%' AND `fort` NOT LIKE 'burt%' AND `funk` NOT LIKE 'benk%' AND `flip` NOT LIKE 'blop%' AND " .
                "`fit` NOT LIKE 'bot%' AND `for` NOT LIKE 'bur%' AND `fell` NOT LIKE 'bill%' AND `flag` NOT LIKE 'blug%' AND `frux` NOT LIKE 'brax%' AND " .
                "`fig` NOT LIKE 'bag%' AND `fey` NOT LIKE 'buy%' AND `flap` NOT LIKE 'bilk%' AND `frop` NOT LIKE 'blap%' AND `fum` NOT LIKE 'bom%'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestWhereNotStartsWith
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testWhereNotStartsWith($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->whereNotStartsWith($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereNotStartsWith() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data for testWhereEndsWith()
     *
     * @return array The test data.
     */
    public function dataForTestWhereEndsWith(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` LIKE '%bar'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` LIKE '%bar'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` LIKE '%bar'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` LIKE '%bar'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` LIKE '%bar' AND `fizz` LIKE '%buzz'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` LIKE '%bar' AND `fizz` LIKE '%buzz'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` LIKE '%bar' AND `fizzbuzz`.`fizz` LIKE '%buzz'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` LIKE '%bar' AND `fizz` LIKE '%buzz' AND `flux` LIKE '%blox' AND `flim` LIKE '%blam' AND `flub` LIKE '%blib' AND " .
                "`frag` LIKE '%brag' AND `fing` LIKE '%bing' AND `fong` LIKE '%bong' AND `fish` LIKE '%bash' AND `frew` LIKE '%brow' AND " .
                "`fop` LIKE '%bip' AND `fad` LIKE '%bid' AND `fort` LIKE '%burt' AND `funk` LIKE '%benk' AND `flip` LIKE '%blop' AND " .
                "`fit` LIKE '%bot' AND `for` LIKE '%bur' AND `fell` LIKE '%bill' AND `flag` LIKE '%blug' AND `frux` LIKE '%brax' AND " .
                "`fig` LIKE '%bag' AND `fey` LIKE '%buy' AND `flap` LIKE '%bilk' AND `frop` LIKE '%blap' AND `fum` LIKE '%bom'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestWhereEndsWith
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testWhereEndsWith($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->whereEndsWith($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereEndsWith() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data for testWhereNotEndsWith()
     *
     * @return array The test data.
     */
    public function dataForTestWhereNotEndsWith(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` NOT LIKE '%bar'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` NOT LIKE '%bar'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` NOT LIKE '%bar'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` NOT LIKE '%bar'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` NOT LIKE '%bar' AND `fizz` NOT LIKE '%buzz'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE '%bar' AND `fizz` NOT LIKE '%buzz'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE '%bar' AND `fizzbuzz`.`fizz` NOT LIKE '%buzz'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` NOT LIKE '%bar' AND `fizz` NOT LIKE '%buzz' AND `flux` NOT LIKE '%blox' AND `flim` NOT LIKE '%blam' AND `flub` NOT LIKE '%blib' AND " .
                "`frag` NOT LIKE '%brag' AND `fing` NOT LIKE '%bing' AND `fong` NOT LIKE '%bong' AND `fish` NOT LIKE '%bash' AND `frew` NOT LIKE '%brow' AND " .
                "`fop` NOT LIKE '%bip' AND `fad` NOT LIKE '%bid' AND `fort` NOT LIKE '%burt' AND `funk` NOT LIKE '%benk' AND `flip` NOT LIKE '%blop' AND " .
                "`fit` NOT LIKE '%bot' AND `for` NOT LIKE '%bur' AND `fell` NOT LIKE '%bill' AND `flag` NOT LIKE '%blug' AND `frux` NOT LIKE '%brax' AND " .
                "`fig` NOT LIKE '%bag' AND `fey` NOT LIKE '%buy' AND `flap` NOT LIKE '%bilk' AND `frop` NOT LIKE '%blap' AND `fum` NOT LIKE '%bom'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestWhereNotEndsWith
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testWhereNotEndsWith($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->whereNotEndsWith($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereNotEndsWith() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Test data for testWhereIn()
     *
     * @return array[] The test data.
     */
    public function dataForTestWhereIn(): array
    {
        return [
            "typicalSingleFieldSingleValue" => ["foo", ["foo",], "`foo` IN ('foo')",],
            "typicalSingleFieldMultipleValues" => ["foo", ["foo", "bar", "baz",], "`foo` IN ('foo','bar','baz')",],
            "typicalSingleFieldSingleValueAsArray" => [["foo" => ["foo",],], null, "`foo` IN ('foo')",],
            "typicalSingleFieldMultipleValuesAsArray" => [["foo" => ["foo", "bar", "baz",],], null, "`foo` IN ('foo','bar','baz')",],
            "typicalMultipleFieldsMultipleValues" => [["foo" => ["foo", "bar", "baz",], "bar" => ["boo", "far", "faz",], "baz" => ["foz", "baz", "bar",],], null, "`foo` IN ('foo','bar','baz') AND `bar` IN ('boo','far','faz') AND `baz` IN ('foz','baz','bar')",],
            "typicalSingleFieldSingleIntValue" => ["foo", [42,], "`foo` IN (42)",],
            "typicalSingleFieldMultipleAsIntValues" => ["foo", [42, 43, 44,], "`foo` IN (42,43,44)",],
            "typicalSingleFieldSingleIntValueAsArray" => [["foo" => [42,],], null, "`foo` IN (42)",],
            "typicalSingleFieldMultipleIntValuesAsArray" => [["foo" => [42, 43, 44,],], null, "`foo` IN (42,43,44)",],
            "typicalSingleFieldSingleFloatValue" => ["foo", [3.14159,], "`foo` IN (3.14159)",],
            "typicalSingleFieldMultipleAsFloatValues" => ["foo", [3.14159, 4.2526, 5.36371,], "`foo` IN (3.14159,4.2526,5.36371)",],
            "typicalSingleFieldSingleFloatValueAsArray" => [["foo" => [3.14159,],], null, "`foo` IN (3.14159)",],
            "typicalSingleFieldMultipleFloatValuesAsArray" => [["foo" => [3.14159, 4.2526, 5.36371,],], null, "`foo` IN (3.14159,4.2526,5.36371)",],
            "typicalSingleFieldSingleBoolValue" => ["foo", [true,], "`foo` IN (1)",],
            "typicalSingleFieldMultipleAsBoolValues" => ["foo", [true, false, true,], "`foo` IN (1,0,1)",],
            "typicalSingleFieldSingleBoolValueAsArray" => [["foo" => [true,],], null, "`foo` IN (1)",],
            "typicalSingleFieldMultipleBoolValuesAsArray" => [["foo" => [true, false, true,],], null, "`foo` IN (1,0,1)",],
            "typicalSingleFieldSingleNullValue" => ["foo", [null,], "`foo` IN (NULL)",],
            "typicalSingleFieldMultipleIntAndNullValues" => ["foo", [null, 43, 44,], "`foo` IN (NULL,43,44)",],
            "typicalSingleFieldSingleNullValueAsArray" => [["foo" => [null,],], null, "`foo` IN (NULL)",],
            "typicalSingleFieldMultipleIntAndNullValuesAsArray" => [["foo" => [null, 43, 44,],], null, "`foo` IN (NULL,43,44)",],
            "typicalSingleFieldSingleDateTimeValue" => ["foo", [new DateTime("2022-07-01"),], "`foo` IN ('2022-07-01 00:00:00')",],
            "typicalSingleFieldMultipleDateTimeValues" => ["foo", [new DateTime("2022-07-01"), new DateTime("2024-01-08"), new DateTime("2020-04-23"),], "`foo` IN ('2022-07-01 00:00:00','2024-01-08 00:00:00','2020-04-23 00:00:00')",],
            "typicalSingleFieldSingleDateTimeValueAsArray" => [["foo" => [new DateTime("2022-07-01"),],], null, "`foo` IN ('2022-07-01 00:00:00')",],
            "typicalSingleFieldMultipleDateTimeValuesAsArray" => [["foo" => [new DateTime("2022-07-01"), new DateTime("2024-01-08"), new DateTime("2020-04-23"),],], null, "`foo` IN ('2022-07-01 00:00:00','2024-01-08 00:00:00','2020-04-23 00:00:00')",],

            "typicalMultipleFieldsMultipleMixedValues" => [["foo" => [42, 43, 44,], "bar" => ["boo", "far", "faz",], "baz" => [3.14159, 4.28208, 5.39319,],], null, "`foo` IN (42,43,44) AND `bar` IN ('boo','far','faz') AND `baz` IN (3.14159,4.28208,5.39319)",],
            
            "invalidEmptyInArray" => ["foo", [], "", InvalidArgumentException::class,],
            "invalidEmptyInArrayAsArray" => [["foo" => [],], null, "", InvalidArgumentException::class,],
            "invalidMultipleOneEmptyInArray" => [["foo" => ["foo", "bar",], "bar" => [], "baz" => ["bar", "baz",],], null, "", InvalidArgumentException::class,],

            "invalidNonArray" => [["foo" => ""], null, "", InvalidArgumentException::class,],
            "invalidMultipleOneNonArray" => [["foo" => ["foo", "bar",], "bar" => "", "baz" => ["bar", "baz",],], null, "", InvalidArgumentException::class,],

            "invalidEmptyField" => ["", ["bar", "baz",], "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", ["bar", "baz",], "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => ["bar", "baz",],], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, ["bar", "baz",], "", TypeError::class,],
            "invalidIntField" => [42, ["bar", "baz",], "", TypeError::class,],
            "invalidFloatField" => [3.1415927, ["bar", "baz",], "", TypeError::class,],
            "invalidNullField" => [null, ["bar", "baz",], "", TypeError::class,],
            "invalidBoolField" => [true, ["bar", "baz",], "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestWhereIn
     *
     * @param array<string,array>|string $columnOrPairs
     * @param array|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testWhereIn($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->whereIn($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereIn() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Test data for testWhereNotIn()
     *
     * @return array[] The test data.
     */
    public function dataForTestWhereNotIn(): array
    {
        return [
            "typicalSingleFieldSingleValue" => ["foo", ["foo",], "`foo` NOT IN ('foo')",],
            "typicalSingleFieldMultipleValues" => ["foo", ["foo", "bar", "baz",], "`foo` NOT IN ('foo','bar','baz')",],
            "typicalSingleFieldSingleValueAsArray" => [["foo" => ["foo",],], null, "`foo` NOT IN ('foo')",],
            "typicalSingleFieldMultipleValuesAsArray" => [["foo" => ["foo", "bar", "baz",],], null, "`foo` NOT IN ('foo','bar','baz')",],
            "typicalMultipleFieldsMultipleValues" => [["foo" => ["foo", "bar", "baz",], "bar" => ["boo", "far", "faz",], "baz" => ["foz", "baz", "bar",],], null, "`foo` NOT IN ('foo','bar','baz') AND `bar` NOT IN ('boo','far','faz') AND `baz` NOT IN ('foz','baz','bar')",],
            "typicalSingleFieldSingleIntValue" => ["foo", [42,], "`foo` NOT IN (42)",],
            "typicalSingleFieldMultipleAsIntValues" => ["foo", [42, 43, 44,], "`foo` NOT IN (42,43,44)",],
            "typicalSingleFieldSingleIntValueAsArray" => [["foo" => [42,],], null, "`foo` NOT IN (42)",],
            "typicalSingleFieldMultipleIntValuesAsArray" => [["foo" => [42, 43, 44,],], null, "`foo` NOT IN (42,43,44)",],
            "typicalSingleFieldSingleFloatValue" => ["foo", [3.14159,], "`foo` NOT IN (3.14159)",],
            "typicalSingleFieldMultipleAsFloatValues" => ["foo", [3.14159, 4.2526, 5.36371,], "`foo` NOT IN (3.14159,4.2526,5.36371)",],
            "typicalSingleFieldSingleFloatValueAsArray" => [["foo" => [3.14159,],], null, "`foo` NOT IN (3.14159)",],
            "typicalSingleFieldMultipleFloatValuesAsArray" => [["foo" => [3.14159, 4.2526, 5.36371,],], null, "`foo` NOT IN (3.14159,4.2526,5.36371)",],
            "typicalSingleFieldSingleBoolValue" => ["foo", [true,], "`foo` NOT IN (1)",],
            "typicalSingleFieldMultipleAsBoolValues" => ["foo", [true, false, true,], "`foo` NOT IN (1,0,1)",],
            "typicalSingleFieldSingleBoolValueAsArray" => [["foo" => [true,],], null, "`foo` NOT IN (1)",],
            "typicalSingleFieldMultipleBoolValuesAsArray" => [["foo" => [true, false, true,],], null, "`foo` NOT IN (1,0,1)",],
            "typicalSingleFieldSingleNullValue" => ["foo", [null,], "`foo` NOT IN (NULL)",],
            "typicalSingleFieldMultipleIntAndNullValues" => ["foo", [null, 43, 44,], "`foo` NOT IN (NULL,43,44)",],
            "typicalSingleFieldSingleNullValueAsArray" => [["foo" => [null,],], null, "`foo` NOT IN (NULL)",],
            "typicalSingleFieldMultipleIntAndNullValuesAsArray" => [["foo" => [null, 43, 44,],], null, "`foo` NOT IN (NULL,43,44)",],
            "typicalSingleFieldSingleDateTimeValue" => ["foo", [new DateTime("2022-07-01"),], "`foo` NOT IN ('2022-07-01 00:00:00')",],
            "typicalSingleFieldMultipleDateTimeValues" => ["foo", [new DateTime("2022-07-01"), new DateTime("2024-01-08"), new DateTime("2020-04-23"),], "`foo` NOT IN ('2022-07-01 00:00:00','2024-01-08 00:00:00','2020-04-23 00:00:00')",],
            "typicalSingleFieldSingleDateTimeValueAsArray" => [["foo" => [new DateTime("2022-07-01"),],], null, "`foo` NOT IN ('2022-07-01 00:00:00')",],
            "typicalSingleFieldMultipleDateTimeValuesAsArray" => [["foo" => [new DateTime("2022-07-01"), new DateTime("2024-01-08"), new DateTime("2020-04-23"),],], null, "`foo` NOT IN ('2022-07-01 00:00:00','2024-01-08 00:00:00','2020-04-23 00:00:00')",],

            "typicalMultipleFieldsMultipleMixedValues" => [["foo" => [42, 43, 44,], "bar" => ["boo", "far", "faz",], "baz" => [3.14159, 4.28208, 5.39319,],], null, "`foo` NOT IN (42,43,44) AND `bar` NOT IN ('boo','far','faz') AND `baz` NOT IN (3.14159,4.28208,5.39319)",],
            
            "invalidEmptyInArray" => ["foo", [], "", InvalidArgumentException::class,],
            "invalidEmptyInArrayAsArray" => [["foo" => [],], null, "", InvalidArgumentException::class,],
            "invalidMultipleOneEmptyInArray" => [["foo" => ["foo", "bar",], "bar" => [], "baz" => ["bar", "baz",],], null, "", InvalidArgumentException::class,],
            "invalidMultipleNonArrayIn" => [["foo" => ["foo", "bar",], "bar" => 42, "baz" => ["bar", "baz",],], null, "", InvalidArgumentException::class,],

            "invalidEmptyField" => ["", ["bar", "baz",], "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", ["bar", "baz",], "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => ["bar", "baz",],], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, ["bar", "baz",], "", TypeError::class,],
            "invalidIntField" => [42, ["bar", "baz",], "", TypeError::class,],
            "invalidFloatField" => [3.1415927, ["bar", "baz",], "", TypeError::class,],
            "invalidNullField" => [null, ["bar", "baz",], "", TypeError::class,],
            "invalidBoolField" => [true, ["bar", "baz",], "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestWhereNotIn
     *
     * @param array<string,array>|string $columnOrPairs
     * @param array|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testWhereNotIn($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->whereNotIn($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereIn() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Test data provider for testWhereLength.
     * @return array[] The test data.
     */
    public function dataForTestWhereLength(): array
    {
        return  [
            "typicalSingleField" => ["foo", 7, "LENGTH(`foo`) = 7",],
            "typicalSingleFullyQualifiedField" => ["foobar.foo", 19, "LENGTH(`foobar`.`foo`) = 19",],
            "typicalSingleFieldAsArray" => [["foo" => 37,], null, "LENGTH(`foo`) = 37",],
            "typicalSingleFullyQualifiedFieldAsArray" => [["foobar.foo" => 98,], null, "LENGTH(`foobar`.`foo`) = 98",],
            "typicalMultipleFields" => [["foo" => 21, "bar" => 14,], null, "LENGTH(`foo`) = 21 AND LENGTH(`bar`) = 14",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => 58, "foobar.bar" => 3,], null, "LENGTH(`foobar`.`foo`) = 58 AND LENGTH(`foobar`.`bar`) = 3",],
            "typicalMultipleMixedQualificationFields" => [["foobar.foo" => 36, "bar" => 41, "foobar.fizz" => 103, "buzz" => 88,], null, "LENGTH(`foobar`.`foo`) = 36 AND LENGTH(`bar`) = 41 AND LENGTH(`foobar`.`fizz`) = 103 AND LENGTH(`buzz`) = 88",],
            "extremeZeroLength" => ["foobar.foo", 0, "LENGTH(`foobar`.`foo`) = 0",],
            "extremeNegativeLength" => ["foobar.foo", -1, "LENGTH(`foobar`.`foo`) = -1",],
            "extremeLargeNegativeLength" => ["foobar.foo", -999999999, "LENGTH(`foobar`.`foo`) = -999999999",],
            "extremeZeroLengthAsArray" => [["foobar.foo" => 0,], null, "LENGTH(`foobar`.`foo`) = 0",],
            "extremeNegativeLengthAsArray" => [["foobar.foo" => -1,], null, "LENGTH(`foobar`.`foo`) = -1",],
            "extremeLargeNegativeLengthAsArray" => [["foobar.foo" => -999999999,], null, "LENGTH(`foobar`.`foo`) = -999999999",],

            "invalidEmptyField" => ["", 42, "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], 42, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", 42, "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => 42,], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, 42, "", TypeError::class,],
            "invalidIntField" => [42, 42, "", TypeError::class,],
            "invalidFloatField" => [3.1415927, 42, "", TypeError::class,],
            "invalidNullField" => [null, 42, "", TypeError::class,],
            "invalidBoolField" => [true, 42, "", TypeError::class,],

            "invalidStringLength" => ["foo", "42", "", TypeError::class,],
            "invalidEmptyStringLength" => ["foo", "", "", TypeError::class,],
            "invalidIntArrayLength" => ["foo", [42,], "", TypeError::class,],
            "invalidEmptyArrayLength" => ["foo", [], "", TypeError::class,],
            "invalidStringableLength" => ["foo", new class() {
                public function __toString(): string {
                    return "42";
                }
            }, "", TypeError::class,],
            "invalidClosureLength" => ["foo", fn(): int => 42, "", TypeError::class,],
            "invalidFloatLength" => ["foo", 3.1415927, "", TypeError::class,],
            "invalidNullLength" => ["foo", null, "", TypeError::class,],
            "invalidBoolLength" => ["foo", true, "", TypeError::class,],

            "invalidArrayOneStringLength" => [["foo" => 42, "bar" => "5",], null, "", TypeError::class,],
            "invalidArrayOneIntArrayLength" => [["foo" => 42, "bar" => [5,],], null, "", TypeError::class,],
            "invalidArrayOneEmptyArrayLength" => [["foo" => 42, "bar" => [],], null, "", TypeError::class,],
            "invalidArrayOneStringableLength" => [
                [
                    "foo" => 42,
                    "bar" => new class
                    {
                        public function __string(): string
                        {
                            return "42";
                        }
                    },
                ],
                null,
                "",
                TypeError::class,
            ],
            "invalidArrayOneClosureLength" => [["foo" => 42, "bar" => fn(): int => 42,], null, "", TypeError::class,],
            "invalidArrayOneFloatLength" => [["foo" => 42, "bar" => 3.1415926,], null, "", TypeError::class,],
            "invalidArrayOneNullLength" => [["foo" => 42, "bar" => null,], null, "", TypeError::class,],
            "invalidArrayOneBoolLength" => [["foo" => 42, "bar" => true,], null, "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestWhereLength
     *
     * @param string|array<string,int> $columnOrPairs The column to test or a map of columns to lengths.
     * @param int|null $length The length for the field, or null if the columns are pairs of columns => length.
     * @param string $sqlWhere The expected WHERE clause.
     * @param string|null $exceptionClass The expected exception, if any.
     */
    public function testWhereLength($columnOrPairs, $length, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->whereLength($columnOrPairs, $length);
        self::assertSame($builder, $actual, "QueryBuilder::whereIn() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Ensure a single condition using equality by default can be added using orWhere().
     */
    public function testOrWhere(): void
    {
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->where("foo", "foo");
        self::assertSame($builder, $actual);
        $actual = $builder->orWhere("foo", "bar");
        self::assertSame($builder, $actual);
        $actual = $builder->sql();
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE (`foo` = 'foo' OR `foo` = 'bar')", $actual);
    }

    /**
     * Ensure a single condition with an operator can be added using orWhere().
     */
    public function testOrWhereWithOperator(): void
    {
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->where("foo", 3);
        self::assertSame($builder, $actual);
        $actual = $builder->orWhere("foo", ">", 42);
        self::assertSame($builder, $actual);
        $actual = $builder->sql();
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE (`foo` = 3 OR `foo` > 42)", $actual);
    }

    /**
     * Ensure a single condition with an emmpty operator throws.
     */
    public function testOrWhereWithEmptyOperator(): void
    {
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->where("foo", 3);
        self::assertSame($builder, $actual);
        $this->expectException(InvalidOperatorException::class);
        $actual = $builder->orWhere("foo", "", 42);
    }

    /**
     * Ensure multiple conditions can be added using orWhere().
     */
    public function testOrWhereWithMultiple(): void
    {
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->where("foo", "foo");
        self::assertSame($builder, $actual);
        $actual = $builder->orWhere(["foo" => "bar", "bar" => "fizz", "fizz" => "buzz",]);
        self::assertSame($builder, $actual);
        $actual = $builder->sql();
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE (`foo` = 'foo' OR `foo` = 'bar' OR `bar` = 'fizz' OR `fizz` = 'buzz')", $actual);
    }

    /**
     * Provider of test data for testOrWhereNull.
     *
     * @return array[] The test data.
     */
    public function dataForTestOrWhereNull(): array
    {
        return [
            "typicalSingleField" =>["foo", "`foo` IS NULL",],
            "typicalMultipleFields" =>[["foo", "bar",], "`foo` IS NULL OR `bar` IS NULL",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo", "bar", "fizz", "buzz", "flex", "box", "fen", "bun", "fin", "bin", "flan", "ban", "flub",
                    "bax", "flip", "blip",
                ],
                "`foo` IS NULL OR `bar` IS NULL OR `fizz` IS NULL OR `buzz` IS NULL OR `flex` IS NULL OR " .
                "`box` IS NULL OR `fen` IS NULL OR `bun` IS NULL OR `fin` IS NULL OR `bin` IS NULL OR " .
                "`flan` IS NULL OR `ban` IS NULL OR `flub` IS NULL OR `bax` IS NULL OR `flip` IS NULL OR " .
                "`blip` IS NULL",
            ],
            "invalidEmptyField" => ["", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "", TypeError::class,],
            "invalidIntField" => [42, "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "", TypeError::class,],
            "invalidNullField" => [null, "", TypeError::class,],
            "invalidBoolField" => [true, "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrWhereNull
     *
     * @param mixed $columns The test field(s) to provide to the orWhereNull() method.
     * @param string $sqlWhere The expected SQL WHERE clause.
     * @param string|null $exceptionClass The expected exception, if any.
     */
    public function testOrWhereNull($columns, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->orWhereNull($columns);
        self::assertSame($builder, $actual, "QueryBuilder::orWhereNull() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Provider of test data for testOrWhereNotNull.
     *
     * @return array[] The test data.
     */
    public function dataForTestOrWhereNotNull(): array
    {
        return [
            "typicalSingleField" =>["foo", "`foo` IS NOT NULL",],
            "typicalMultipleFields" =>[["foo", "bar",], "`foo` IS NOT NULL OR `bar` IS NOT NULL",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo", "bar", "fizz", "buzz", "flex", "box", "fen", "bun", "fin", "bin", "flan", "ban", "flub",
                    "bax", "flip", "blip",
                ],
                "`foo` IS NOT NULL OR `bar` IS NOT NULL OR `fizz` IS NOT NULL OR `buzz` IS NOT NULL OR `flex` IS NOT NULL OR " .
                "`box` IS NOT NULL OR `fen` IS NOT NULL OR `bun` IS NOT NULL OR `fin` IS NOT NULL OR `bin` IS NOT NULL OR " .
                "`flan` IS NOT NULL OR `ban` IS NOT NULL OR `flub` IS NOT NULL OR `bax` IS NOT NULL OR `flip` IS NOT NULL OR " .
                "`blip` IS NOT NULL",
            ],
            "invalidEmptyField" => ["", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "", TypeError::class,],
            "invalidIntField" => [42, "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "", TypeError::class,],
            "invalidNullField" => [null, "", TypeError::class,],
            "invalidBoolField" => [true, "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrWhereNotNull
     *
     * @param mixed $columns The test field(s) to provide to the orWhereNull() method.
     * @param string $sqlWhere The expected SQL WHERE clause.
     * @param string|null $exceptionClass The expected exception, if any.
     */
    public function testOrWhereNotNull($columns, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->orWhereNotNull($columns);
        self::assertSame($builder, $actual, "QueryBuilder::orWhereNull() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data for testOrWhereContains()
     *
     * @return array The test data.
     */
    public function dataForTestOrWhereContains(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` LIKE '%bar%'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` LIKE '%bar%'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` LIKE '%bar%'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` LIKE '%bar%'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` LIKE '%bar%' OR `fizz` LIKE '%buzz%'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` LIKE '%bar%' OR `fizz` LIKE '%buzz%'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` LIKE '%bar%' OR `fizzbuzz`.`fizz` LIKE '%buzz%'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` LIKE '%bar%' OR `fizz` LIKE '%buzz%' OR `flux` LIKE '%blox%' OR `flim` LIKE '%blam%' OR `flub` LIKE '%blib%' OR " .
                "`frag` LIKE '%brag%' OR `fing` LIKE '%bing%' OR `fong` LIKE '%bong%' OR `fish` LIKE '%bash%' OR `frew` LIKE '%brow%' OR " .
                "`fop` LIKE '%bip%' OR `fad` LIKE '%bid%' OR `fort` LIKE '%burt%' OR `funk` LIKE '%benk%' OR `flip` LIKE '%blop%' OR " .
                "`fit` LIKE '%bot%' OR `for` LIKE '%bur%' OR `fell` LIKE '%bill%' OR `flag` LIKE '%blug%' OR `frux` LIKE '%brax%' OR " .
                "`fig` LIKE '%bag%' OR `fey` LIKE '%buy%' OR `flap` LIKE '%bilk%' OR `frop` LIKE '%blap%' OR `fum` LIKE '%bom%'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrWhereContains
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testOrWhereContains($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->orWhereContains($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereContains() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }


    /**
     * Data for testOrWhereNotContains()
     *
     * @return array The test data.
     */
    public function dataForTestOrWhereNotContains(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` NOT LIKE '%bar%'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` NOT LIKE '%bar%'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` NOT LIKE '%bar%'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` NOT LIKE '%bar%'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` NOT LIKE '%bar%' OR `fizz` NOT LIKE '%buzz%'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE '%bar%' OR `fizz` NOT LIKE '%buzz%'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE '%bar%' OR `fizzbuzz`.`fizz` NOT LIKE '%buzz%'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` NOT LIKE '%bar%' OR `fizz` NOT LIKE '%buzz%' OR `flux` NOT LIKE '%blox%' OR `flim` NOT LIKE '%blam%' OR `flub` NOT LIKE '%blib%' OR " .
                "`frag` NOT LIKE '%brag%' OR `fing` NOT LIKE '%bing%' OR `fong` NOT LIKE '%bong%' OR `fish` NOT LIKE '%bash%' OR `frew` NOT LIKE '%brow%' OR " .
                "`fop` NOT LIKE '%bip%' OR `fad` NOT LIKE '%bid%' OR `fort` NOT LIKE '%burt%' OR `funk` NOT LIKE '%benk%' OR `flip` NOT LIKE '%blop%' OR " .
                "`fit` NOT LIKE '%bot%' OR `for` NOT LIKE '%bur%' OR `fell` NOT LIKE '%bill%' OR `flag` NOT LIKE '%blug%' OR `frux` NOT LIKE '%brax%' OR " .
                "`fig` NOT LIKE '%bag%' OR `fey` NOT LIKE '%buy%' OR `flap` NOT LIKE '%bilk%' OR `frop` NOT LIKE '%blap%' OR `fum` NOT LIKE '%bom%'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrWhereNotContains
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testOrWhereNotContains($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->orWhereNotContains($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereContains() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data for testOrWhereStartsWith()
     *
     * @return array The test data.
     */
    public function dataForTestOrWhereStartsWith(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` LIKE 'bar%'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` LIKE 'bar%'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` LIKE 'bar%'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` LIKE 'bar%'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` LIKE 'bar%' OR `fizz` LIKE 'buzz%'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` LIKE 'bar%' OR `fizz` LIKE 'buzz%'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` LIKE 'bar%' OR `fizzbuzz`.`fizz` LIKE 'buzz%'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` LIKE 'bar%' OR `fizz` LIKE 'buzz%' OR `flux` LIKE 'blox%' OR `flim` LIKE 'blam%' OR `flub` LIKE 'blib%' OR " .
                "`frag` LIKE 'brag%' OR `fing` LIKE 'bing%' OR `fong` LIKE 'bong%' OR `fish` LIKE 'bash%' OR `frew` LIKE 'brow%' OR " .
                "`fop` LIKE 'bip%' OR `fad` LIKE 'bid%' OR `fort` LIKE 'burt%' OR `funk` LIKE 'benk%' OR `flip` LIKE 'blop%' OR " .
                "`fit` LIKE 'bot%' OR `for` LIKE 'bur%' OR `fell` LIKE 'bill%' OR `flag` LIKE 'blug%' OR `frux` LIKE 'brax%' OR " .
                "`fig` LIKE 'bag%' OR `fey` LIKE 'buy%' OR `flap` LIKE 'bilk%' OR `frop` LIKE 'blap%' OR `fum` LIKE 'bom%'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrWhereStartsWith
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testOrWhereStartsWith($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->orWhereStartsWith($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereStartsWith() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data for testOrWhereNotStartsWith()
     *
     * @return array The test data.
     */
    public function dataForTestOrWhereNotStartsWith(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` NOT LIKE 'bar%'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` NOT LIKE 'bar%'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` NOT LIKE 'bar%'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` NOT LIKE 'bar%'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` NOT LIKE 'bar%' OR `fizz` NOT LIKE 'buzz%'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE 'bar%' OR `fizz` NOT LIKE 'buzz%'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE 'bar%' OR `fizzbuzz`.`fizz` NOT LIKE 'buzz%'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` NOT LIKE 'bar%' OR `fizz` NOT LIKE 'buzz%' OR `flux` NOT LIKE 'blox%' OR `flim` NOT LIKE 'blam%' OR `flub` NOT LIKE 'blib%' OR " .
                "`frag` NOT LIKE 'brag%' OR `fing` NOT LIKE 'bing%' OR `fong` NOT LIKE 'bong%' OR `fish` NOT LIKE 'bash%' OR `frew` NOT LIKE 'brow%' OR " .
                "`fop` NOT LIKE 'bip%' OR `fad` NOT LIKE 'bid%' OR `fort` NOT LIKE 'burt%' OR `funk` NOT LIKE 'benk%' OR `flip` NOT LIKE 'blop%' OR " .
                "`fit` NOT LIKE 'bot%' OR `for` NOT LIKE 'bur%' OR `fell` NOT LIKE 'bill%' OR `flag` NOT LIKE 'blug%' OR `frux` NOT LIKE 'brax%' OR " .
                "`fig` NOT LIKE 'bag%' OR `fey` NOT LIKE 'buy%' OR `flap` NOT LIKE 'bilk%' OR `frop` NOT LIKE 'blap%' OR `fum` NOT LIKE 'bom%'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrWhereNotStartsWith
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testOrWhereNotStartsWith($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->orWhereNotStartsWith($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereStartsWith() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data for testOrWhereEndsWith()
     *
     * @return array The test data.
     */
    public function dataForTestOrWhereEndsWith(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` LIKE '%bar'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` LIKE '%bar'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` LIKE '%bar'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` LIKE '%bar'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` LIKE '%bar' OR `fizz` LIKE '%buzz'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` LIKE '%bar' OR `fizz` LIKE '%buzz'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` LIKE '%bar' OR `fizzbuzz`.`fizz` LIKE '%buzz'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` LIKE '%bar' OR `fizz` LIKE '%buzz' OR `flux` LIKE '%blox' OR `flim` LIKE '%blam' OR `flub` LIKE '%blib' OR " .
                "`frag` LIKE '%brag' OR `fing` LIKE '%bing' OR `fong` LIKE '%bong' OR `fish` LIKE '%bash' OR `frew` LIKE '%brow' OR " .
                "`fop` LIKE '%bip' OR `fad` LIKE '%bid' OR `fort` LIKE '%burt' OR `funk` LIKE '%benk' OR `flip` LIKE '%blop' OR " .
                "`fit` LIKE '%bot' OR `for` LIKE '%bur' OR `fell` LIKE '%bill' OR `flag` LIKE '%blug' OR `frux` LIKE '%brax' OR " .
                "`fig` LIKE '%bag' OR `fey` LIKE '%buy' OR `flap` LIKE '%bilk' OR `frop` LIKE '%blap' OR `fum` LIKE '%bom'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrWhereEndsWith
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testOrWhereEndsWith($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->orWhereEndsWith($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereEndsWith() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Data for testOrWhereNotEndsWith()
     *
     * @return array The test data.
     */
    public function dataForTestOrWhereNotEndsWith(): array
    {
        return  [
            "typicalSingleField" => ["foo", "bar", "`foo` NOT LIKE '%bar'",],
            "typicalFullyQualifiedSingleField" => ["foobar.foo", "bar", "`foobar`.`foo` NOT LIKE '%bar'",],
            "typicalSingleFieldInArray" => [["foo" => "bar",], null, "`foo` NOT LIKE '%bar'",],
            "typicalSingleFullyQualifiedFieldInArray" => [["foobar.foo" => "bar",], null, "`foobar`.`foo` NOT LIKE '%bar'",],
            "typicalMultipleFields" => [["foo" => "bar", "fizz" => "buzz",], null, "`foo` NOT LIKE '%bar' OR `fizz` NOT LIKE '%buzz'",],
            "typicalMultipleMixedFields" => [["foobar.foo" => "bar", "fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE '%bar' OR `fizz` NOT LIKE '%buzz'",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => "bar", "fizzbuzz.fizz" => "buzz",], null, "`foobar`.`foo` NOT LIKE '%bar' OR `fizzbuzz`.`fizz` NOT LIKE '%buzz'",],
            "typicalLargeNumberOfFields" => [
                [
                    "foo" => "bar", "fizz" => "buzz", "flux" => "blox", "flim" => "blam", "flub" => "blib",
                    "frag" => "brag", "fing" => "bing", "fong" => "bong", "fish" => "bash", "frew" => "brow",
                    "fop" => "bip", "fad" => "bid", "fort" => "burt", "funk" => "benk", "flip" => "blop",
                    "fit" => "bot", "for" => "bur", "fell" => "bill", "flag" => "blug", "frux" => "brax",
                    "fig" => "bag", "fey" => "buy", "flap" => "bilk", "frop" => "blap", "fum" => "bom",
                ],
                null,
                "`foo` NOT LIKE '%bar' OR `fizz` NOT LIKE '%buzz' OR `flux` NOT LIKE '%blox' OR `flim` NOT LIKE '%blam' OR `flub` NOT LIKE '%blib' OR " .
                "`frag` NOT LIKE '%brag' OR `fing` NOT LIKE '%bing' OR `fong` NOT LIKE '%bong' OR `fish` NOT LIKE '%bash' OR `frew` NOT LIKE '%brow' OR " .
                "`fop` NOT LIKE '%bip' OR `fad` NOT LIKE '%bid' OR `fort` NOT LIKE '%burt' OR `funk` NOT LIKE '%benk' OR `flip` NOT LIKE '%blop' OR " .
                "`fit` NOT LIKE '%bot' OR `for` NOT LIKE '%bur' OR `fell` NOT LIKE '%bill' OR `flag` NOT LIKE '%blug' OR `frux` NOT LIKE '%brax' OR " .
                "`fig` NOT LIKE '%bag' OR `fey` NOT LIKE '%buy' OR `flap` NOT LIKE '%bilk' OR `frop` NOT LIKE '%blap' OR `fum` NOT LIKE '%bom'",
            ],
            "invalidEmptyField" => ["", "bar", "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", "", "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => "bar",], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, "bar", "", TypeError::class,],
            "invalidIntField" => [42, "bar", "", TypeError::class,],
            "invalidFloatField" => [3.1415927, "bar", "", TypeError::class,],
            "invalidNullField" => [null, "bar", "", TypeError::class,],
            "invalidBoolField" => [true, "bar", "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrWhereNotEndsWith
     *
     * @param array<string,string>|string $columnOrPairs
     * @param string|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testOrWhereNotEndsWith($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->orWhereNotEndsWith($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereNotEndsWith() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Test data for testOrWhereIn()
     *
     * @return array[] The test data.
     */
    public function dataForTestOrWhereIn(): array
    {
        return [
            "typicalSingleFieldSingleValue" => ["foo", ["foo",], "`foo` IN ('foo')",],
            "typicalSingleFieldMultipleValues" => ["foo", ["foo", "bar", "baz",], "`foo` IN ('foo','bar','baz')",],
            "typicalSingleFieldSingleValueAsArray" => [["foo" => ["foo",],], null, "`foo` IN ('foo')",],
            "typicalSingleFieldMultipleValuesAsArray" => [["foo" => ["foo", "bar", "baz",],], null, "`foo` IN ('foo','bar','baz')",],
            "typicalMultipleFieldsMultipleValues" => [["foo" => ["foo", "bar", "baz",], "bar" => ["boo", "far", "faz",], "baz" => ["foz", "baz", "bar",],], null, "`foo` IN ('foo','bar','baz') OR `bar` IN ('boo','far','faz') OR `baz` IN ('foz','baz','bar')",],
            "typicalSingleFieldSingleIntValue" => ["foo", [42,], "`foo` IN (42)",],
            "typicalSingleFieldMultipleAsIntValues" => ["foo", [42, 43, 44,], "`foo` IN (42,43,44)",],
            "typicalSingleFieldSingleIntValueAsArray" => [["foo" => [42,],], null, "`foo` IN (42)",],
            "typicalSingleFieldMultipleIntValuesAsArray" => [["foo" => [42, 43, 44,],], null, "`foo` IN (42,43,44)",],
            "typicalSingleFieldSingleFloatValue" => ["foo", [3.14159,], "`foo` IN (3.14159)",],
            "typicalSingleFieldMultipleAsFloatValues" => ["foo", [3.14159, 4.2526, 5.36371,], "`foo` IN (3.14159,4.2526,5.36371)",],
            "typicalSingleFieldSingleFloatValueAsArray" => [["foo" => [3.14159,],], null, "`foo` IN (3.14159)",],
            "typicalSingleFieldMultipleFloatValuesAsArray" => [["foo" => [3.14159, 4.2526, 5.36371,],], null, "`foo` IN (3.14159,4.2526,5.36371)",],
            "typicalSingleFieldSingleBoolValue" => ["foo", [true,], "`foo` IN (1)",],
            "typicalSingleFieldMultipleAsBoolValues" => ["foo", [true, false, true,], "`foo` IN (1,0,1)",],
            "typicalSingleFieldSingleBoolValueAsArray" => [["foo" => [true,],], null, "`foo` IN (1)",],
            "typicalSingleFieldMultipleBoolValuesAsArray" => [["foo" => [true, false, true,],], null, "`foo` IN (1,0,1)",],
            "typicalSingleFieldSingleNullValue" => ["foo", [null,], "`foo` IN (NULL)",],
            "typicalSingleFieldMultipleIntAndNullValues" => ["foo", [null, 43, 44,], "`foo` IN (NULL,43,44)",],
            "typicalSingleFieldSingleNullValueAsArray" => [["foo" => [null,],], null, "`foo` IN (NULL)",],
            "typicalSingleFieldMultipleIntAndNullValuesAsArray" => [["foo" => [null, 43, 44,],], null, "`foo` IN (NULL,43,44)",],
            "typicalSingleFieldSingleDateTimeValue" => ["foo", [new DateTime("2022-07-01"),], "`foo` IN ('2022-07-01 00:00:00')",],
            "typicalSingleFieldMultipleDateTimeValues" => ["foo", [new DateTime("2022-07-01"), new DateTime("2024-01-08"), new DateTime("2020-04-23"),], "`foo` IN ('2022-07-01 00:00:00','2024-01-08 00:00:00','2020-04-23 00:00:00')",],
            "typicalSingleFieldSingleDateTimeValueAsArray" => [["foo" => [new DateTime("2022-07-01"),],], null, "`foo` IN ('2022-07-01 00:00:00')",],
            "typicalSingleFieldMultipleDateTimeValuesAsArray" => [["foo" => [new DateTime("2022-07-01"), new DateTime("2024-01-08"), new DateTime("2020-04-23"),],], null, "`foo` IN ('2022-07-01 00:00:00','2024-01-08 00:00:00','2020-04-23 00:00:00')",],

            "typicalMultipleFieldsMultipleMixedValues" => [["foo" => [42, 43, 44,], "bar" => ["boo", "far", "faz",], "baz" => [3.14159, 4.28208, 5.39319,],], null, "`foo` IN (42,43,44) OR `bar` IN ('boo','far','faz') OR `baz` IN (3.14159,4.28208,5.39319)",],

            "invalidEmptyInArray" => ["foo", [], "", InvalidArgumentException::class,],
            "invalidEmptyInArrayAsArray" => [["foo" => [],], null, "", InvalidArgumentException::class,],
            "invalidMultipleOneEmptyInArray" => [["foo" => ["foo", "bar",], "bar" => [], "baz" => ["bar", "baz",],], null, "", InvalidArgumentException::class,],

            "invalidNonArray" => [["foo" => ""], null, "", InvalidArgumentException::class,],
            "invalidMultipleOneNonArray" => [["foo" => ["foo", "bar",], "bar" => "", "baz" => ["bar", "baz",],], null, "", InvalidArgumentException::class,],

            "invalidEmptyField" => ["", ["bar", "baz",], "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", ["bar", "baz",], "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => ["bar", "baz",],], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, ["bar", "baz",], "", TypeError::class,],
            "invalidIntField" => [42, ["bar", "baz",], "", TypeError::class,],
            "invalidFloatField" => [3.1415927, ["bar", "baz",], "", TypeError::class,],
            "invalidNullField" => [null, ["bar", "baz",], "", TypeError::class,],
            "invalidBoolField" => [true, ["bar", "baz",], "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrWhereIn
     *
     * @param array<string,array>|string $columnOrPairs
     * @param array|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testOrWhereIn($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->orWhereIn($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereIn() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }


    /**
     * Test data for testOrWhereNotIn()
     *
     * @return array[] The test data.
     */
    public function dataForTestOrWhereNotIn(): array
    {
        return [
            "typicalSingleFieldSingleValue" => ["foo", ["foo",], "`foo` NOT IN ('foo')",],
            "typicalSingleFieldMultipleValues" => ["foo", ["foo", "bar", "baz",], "`foo` NOT IN ('foo','bar','baz')",],
            "typicalSingleFieldSingleValueAsArray" => [["foo" => ["foo",],], null, "`foo` NOT IN ('foo')",],
            "typicalSingleFieldMultipleValuesAsArray" => [["foo" => ["foo", "bar", "baz",],], null, "`foo` NOT IN ('foo','bar','baz')",],
            "typicalMultipleFieldsMultipleValues" => [["foo" => ["foo", "bar", "baz",], "bar" => ["boo", "far", "faz",], "baz" => ["foz", "baz", "bar",],], null, "`foo` NOT IN ('foo','bar','baz') OR `bar` NOT IN ('boo','far','faz') OR `baz` NOT IN ('foz','baz','bar')",],
            "typicalSingleFieldSingleIntValue" => ["foo", [42,], "`foo` NOT IN (42)",],
            "typicalSingleFieldMultipleAsIntValues" => ["foo", [42, 43, 44,], "`foo` NOT IN (42,43,44)",],
            "typicalSingleFieldSingleIntValueAsArray" => [["foo" => [42,],], null, "`foo` NOT IN (42)",],
            "typicalSingleFieldMultipleIntValuesAsArray" => [["foo" => [42, 43, 44,],], null, "`foo` NOT IN (42,43,44)",],
            "typicalSingleFieldSingleFloatValue" => ["foo", [3.14159,], "`foo` NOT IN (3.14159)",],
            "typicalSingleFieldMultipleAsFloatValues" => ["foo", [3.14159, 4.2526, 5.36371,], "`foo` NOT IN (3.14159,4.2526,5.36371)",],
            "typicalSingleFieldSingleFloatValueAsArray" => [["foo" => [3.14159,],], null, "`foo` NOT IN (3.14159)",],
            "typicalSingleFieldMultipleFloatValuesAsArray" => [["foo" => [3.14159, 4.2526, 5.36371,],], null, "`foo` NOT IN (3.14159,4.2526,5.36371)",],
            "typicalSingleFieldSingleBoolValue" => ["foo", [true,], "`foo` NOT IN (1)",],
            "typicalSingleFieldMultipleAsBoolValues" => ["foo", [true, false, true,], "`foo` NOT IN (1,0,1)",],
            "typicalSingleFieldSingleBoolValueAsArray" => [["foo" => [true,],], null, "`foo` NOT IN (1)",],
            "typicalSingleFieldMultipleBoolValuesAsArray" => [["foo" => [true, false, true,],], null, "`foo` NOT IN (1,0,1)",],
            "typicalSingleFieldSingleNullValue" => ["foo", [null,], "`foo` NOT IN (NULL)",],
            "typicalSingleFieldMultipleIntAndNullValues" => ["foo", [null, 43, 44,], "`foo` NOT IN (NULL,43,44)",],
            "typicalSingleFieldSingleNullValueAsArray" => [["foo" => [null,],], null, "`foo` NOT IN (NULL)",],
            "typicalSingleFieldMultipleIntAndNullValuesAsArray" => [["foo" => [null, 43, 44,],], null, "`foo` NOT IN (NULL,43,44)",],
            "typicalSingleFieldSingleDateTimeValue" => ["foo", [new DateTime("2022-07-01"),], "`foo` NOT IN ('2022-07-01 00:00:00')",],
            "typicalSingleFieldMultipleDateTimeValues" => ["foo", [new DateTime("2022-07-01"), new DateTime("2024-01-08"), new DateTime("2020-04-23"),], "`foo` NOT IN ('2022-07-01 00:00:00','2024-01-08 00:00:00','2020-04-23 00:00:00')",],
            "typicalSingleFieldSingleDateTimeValueAsArray" => [["foo" => [new DateTime("2022-07-01"),],], null, "`foo` NOT IN ('2022-07-01 00:00:00')",],
            "typicalSingleFieldMultipleDateTimeValuesAsArray" => [["foo" => [new DateTime("2022-07-01"), new DateTime("2024-01-08"), new DateTime("2020-04-23"),],], null, "`foo` NOT IN ('2022-07-01 00:00:00','2024-01-08 00:00:00','2020-04-23 00:00:00')",],

            "typicalMultipleFieldsMultipleMixedValues" => [["foo" => [42, 43, 44,], "bar" => ["boo", "far", "faz",], "baz" => [3.14159, 4.28208, 5.39319,],], null, "`foo` NOT IN (42,43,44) OR `bar` NOT IN ('boo','far','faz') OR `baz` NOT IN (3.14159,4.28208,5.39319)",],

            "invalidEmptyInArray" => ["foo", [], "", InvalidArgumentException::class,],
            "invalidEmptyInArrayAsArray" => [["foo" => [],], null, "", InvalidArgumentException::class,],
            "invalidMultipleOneEmptyInArray" => [["foo" => ["foo", "bar",], "bar" => [], "baz" => ["bar", "baz",],], null, "", InvalidArgumentException::class,],
            "invalidMultipleNonArrayIn" => [["foo" => ["foo", "bar",], "bar" => 42, "baz" => ["bar", "baz",],], null, "", InvalidArgumentException::class,],

            "invalidNonArray" => [["foo" => ""], null, "", InvalidArgumentException::class,],
            "invalidMultipleOneNonArray" => [["foo" => ["foo", "bar",], "bar" => "", "baz" => ["bar", "baz",],], null, "", InvalidArgumentException::class,],

            "invalidEmptyField" => ["", ["bar", "baz",], "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], null, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", ["bar", "baz",], "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => ["bar", "baz",],], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, ["bar", "baz",], "", TypeError::class,],
            "invalidIntField" => [42, ["bar", "baz",], "", TypeError::class,],
            "invalidFloatField" => [3.1415927, ["bar", "baz",], "", TypeError::class,],
            "invalidNullField" => [null, ["bar", "baz",], "", TypeError::class,],
            "invalidBoolField" => [true, ["bar", "baz",], "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrWhereNotIn
     *
     * @param array<string,array>|string $columnOrPairs
     * @param array|null $value
     * @param string $sqlWhere
     * @param string|null $exceptionClass
     */
    public function testOrWhereNotIn($columnOrPairs, $value, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->orWhereNotIn($columnOrPairs, $value);
        self::assertSame($builder, $actual, "QueryBuilder::whereIn() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    /**
     * Test data provider for testOrWhereLength.
     * @return array[] The test data.
     */
    public function dataForTestOrWhereLength(): array
    {
        return  [
            "typicalSingleField" => ["foo", 7, "LENGTH(`foo`) = 7",],
            "typicalSingleFullyQualifiedField" => ["foobar.foo", 19, "LENGTH(`foobar`.`foo`) = 19",],
            "typicalSingleFieldAsArray" => [["foo" => 37,], null, "LENGTH(`foo`) = 37",],
            "typicalSingleFullyQualifiedFieldAsArray" => [["foobar.foo" => 98,], null, "LENGTH(`foobar`.`foo`) = 98",],
            "typicalMultipleFields" => [["foo" => 21, "bar" => 14,], null, "LENGTH(`foo`) = 21 OR LENGTH(`bar`) = 14",],
            "typicalMultipleFullyQualifiedFields" => [["foobar.foo" => 58, "foobar.bar" => 3,], null, "LENGTH(`foobar`.`foo`) = 58 OR LENGTH(`foobar`.`bar`) = 3",],
            "typicalMultipleMixedQualificationFields" => [["foobar.foo" => 36, "bar" => 41, "foobar.fizz" => 103, "buzz" => 88,], null, "LENGTH(`foobar`.`foo`) = 36 OR LENGTH(`bar`) = 41 OR LENGTH(`foobar`.`fizz`) = 103 OR LENGTH(`buzz`) = 88",],
            "extremeZeroLength" => ["foobar.foo", 0, "LENGTH(`foobar`.`foo`) = 0",],
            "extremeNegativeLength" => ["foobar.foo", -1, "LENGTH(`foobar`.`foo`) = -1",],
            "extremeLargeNegativeLength" => ["foobar.foo", -999999999, "LENGTH(`foobar`.`foo`) = -999999999",],
            "extremeZeroLengthAsArray" => [["foobar.foo" => 0,], null, "LENGTH(`foobar`.`foo`) = 0",],
            "extremeNegativeLengthAsArray" => [["foobar.foo" => -1,], null, "LENGTH(`foobar`.`foo`) = -1",],
            "extremeLargeNegativeLengthAsArray" => [["foobar.foo" => -999999999,], null, "LENGTH(`foobar`.`foo`) = -999999999",],

            "invalidEmptyField" => ["", 42, "", InvalidColumnNameException::class,],
            "invalidEmptyFieldArray" => [[], 42, "", InvalidArgumentException::class,],
            "invalidMalformedField" => ["foo.bar.baz", 42, "", InvalidColumnNameException::class,],
            "invalidMalformedFieldArray" => [["foo.bar.baz" => 42,], null, "", InvalidColumnNameException::class,],
            "invalidStringableField" => [new class() {
                public function __toString(): string {
                    return "foobar.foo";
                }
            }, 42, "", TypeError::class,],
            "invalidIntField" => [42, 42, "", TypeError::class,],
            "invalidFloatField" => [3.1415927, 42, "", TypeError::class,],
            "invalidNullField" => [null, 42, "", TypeError::class,],
            "invalidBoolField" => [true, 42, "", TypeError::class,],

            "invalidStringLength" => ["foo", "42", "", TypeError::class,],
            "invalidEmptyStringLength" => ["foo", "", "", TypeError::class,],
            "invalidIntArrayLength" => ["foo", [42,], "", TypeError::class,],
            "invalidEmptyArrayLength" => ["foo", [], "", TypeError::class,],
            "invalidStringableLength" => ["foo", new class() {
                public function __toString(): string {
                    return "42";
                }
            }, "", TypeError::class,],
            "invalidClosureLength" => ["foo", fn(): int => 42, "", TypeError::class,],
            "invalidFloatLength" => ["foo", 3.1415927, "", TypeError::class,],
            "invalidNullLength" => ["foo", null, "", TypeError::class,],
            "invalidBoolLength" => ["foo", true, "", TypeError::class,],

            "invalidArrayOneStringLength" => [["foo" => 42, "bar" => "5",], null, "", TypeError::class,],
            "invalidArrayOneIntArrayLength" => [["foo" => 42, "bar" => [5,],], null, "", TypeError::class,],
            "invalidArrayOneEmptyArrayLength" => [["foo" => 42, "bar" => [],], null, "", TypeError::class,],
            "invalidArrayOneStringableLength" => [
                [
                    "foo" => 42,
                    "bar" => new class
                    {
                        public function __string(): string
                        {
                            return "42";
                        }
                    },
                ],
                null,
                "",
                TypeError::class,
            ],
            "invalidArrayOneClosureLength" => [["foo" => 42, "bar" => fn(): int => 42,], null, "", TypeError::class,],
            "invalidArrayOneFloatLength" => [["foo" => 42, "bar" => 3.1415926,], null, "", TypeError::class,],
            "invalidArrayOneNullLength" => [["foo" => 42, "bar" => null,], null, "", TypeError::class,],
            "invalidArrayOneBoolLength" => [["foo" => 42, "bar" => true,], null, "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrWhereLength
     *
     * @param string|array<string,int> $columnOrPairs The column to test or a map of columns to lengths.
     * @param int|null $length The length for the field, or null if the columns are pairs of columns => length.
     * @param string $sqlWhere The expected WHERE clause.
     * @param string|null $exceptionClass The expected exception, if any.
     */
    public function testOrWhereLength($columnOrPairs, $length, string $sqlWhere, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // test with where() method
        $builder = $this->createBuilder(["foo", "bar", "fizz", "buzz",], "foobar");
        $actual = $builder->orWhereLength($columnOrPairs, $length);
        self::assertSame($builder, $actual, "QueryBuilder::whereIn() did not return the same QueryBuilder instance.");
        self::assertEquals("SELECT `foo`,`bar`,`fizz`,`buzz` FROM `foobar` WHERE ({$sqlWhere})", $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }

    public function dataForTestOrderBy(): iterable
    {
        yield from [
            "typicalSingleColumnDefaultDirection" => ["foo", null, "`foo` ASC"],
            "typicalSingleColumnLowerCaseAsc" => ["foo", "asc", "`foo` ASC"],
            "typicalSingleColumnLowerCaseDesc" => ["foo", "desc", "`foo` DESC"],
            "typicalSingleColumnAsc" => ["foo", "ASC", "`foo` ASC"],
            "typicalSingleColumnDesc" => ["foo", "DESC", "`foo` DESC"],
            "typicalMultipleColumnsDefaultDirection" => [["foo", "bar",], null, "`foo` ASC,`bar` ASC"],
            "typicalMultipleColumnsSameDirectionLoewrCase" => [["foo", "bar",], "asc", "`foo` ASC,`bar` ASC"],
            "typicalMultipleColumnsSameDirection" => [["foo", "bar",], "ASC", "`foo` ASC,`bar` ASC"],
            "typicalMultipleColumnsSameDirectionDescLoewrCase" => [["foo", "bar",], "desc", "`foo` DESC,`bar` DESC"],
            "typicalMultipleColumnsSameDirectionDesc" => [["foo", "bar",], "DESC", "`foo` DESC,`bar` DESC"],
            "typicalMultipleColumnsCustomLowerCaseDirections" => [["foo" => "asc", "bar" => "desc",], null, "`foo` ASC,`bar` DESC"],
            "typicalMultipleColumnsCustomDirections" => [["foo" => "ASC", "bar" => "DESC",], null, "`foo` ASC,`bar` DESC"],
            "typicalArrayOfAscColumns" => [["foo", "bar",], null, "`foo` ASC,`bar` ASC",],

            "extremeEmptyArrayColumn" => [[], null, "",],

            "invalidBadColumnName" => ["foobar.foo.bar", null, "`foo` ASC,`bar` DESC", InvalidColumnNameException::class,],
            "invalidEmptyStringColumn" => ["", null, "", InvalidColumnNameException::class,],

            "invalidSingleColumnBadDirection" => ["foo", "foo", "", InvalidOrderByDirectionException::class,],
            "invalidMultipleColumnsBadDirection" => [["foo", "bar",], "foo", "", InvalidOrderByDirectionException::class,],
            "invalidMultipleColumnsCustomDirectionsOneBad" => [["foo" => "desc", "bar" => "foo",], null, "", InvalidOrderByDirectionException::class,],

            "invalidIntColumn" => [42, null, "", TypeError::class,],
            "invalidStringableColumn" => [new class() {
                public function __toString(): string {
                    return "foo";
                }
            }, null, "", TypeError::class,],
            "invalidClosureColumn" => [fn(): string => "foo", null, "", TypeError::class,],
            "invalidFloatColumn" => [3.1415927, null, "", TypeError::class,],
            "invalidNullColumn" => [null, null, "", TypeError::class,],
            "invalidBoolColumn" => [true, null, "", TypeError::class,],

            "invalidArrayBadColumnName" => [["foobar.foo.bar" => "asc",], null, "`foo` ASC,`bar` DESC", InvalidColumnNameException::class,],
            "invalidArrayEmptyStringColumn" => [["" => "asc",], null, "", InvalidColumnNameException::class,],

            "invalidArrayIntColumn" => [[42 => "asc",], null, "", TypeError::class,],
            "invalidArrayStringableColumn" => [new class() {
                public function __toString(): string {
                    return "foo";
                }
            }, null, "", TypeError::class,],
            "invalidArrayClosureDirection" => [["foo" => (fn(): string => "foo"),], null, "", TypeError::class,],
            "invalidArrayFloatDirection" => [["foo" => 3.1415927,], null, "", TypeError::class,],
            "invalidArrayNullDirection" => [["foo" => null,], null, "", TypeError::class,],
            "invalidArrayBoolDirection" => [["foo" => true,], null, "", TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestOrderBy
     *
     * @param $columns
     * @param $direction
     * @param string|null $exceptionClass
     */
    public function testOrderBy($columns, $direction, string $sqlOrderBy, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = $this->createBuilder(["foo", "bar", "buzz",], "foobar");
        $builder->orderBy($columns, $direction);

        if (empty($sqlOrderBy)) {
            $expectedSql = "SELECT `foo`,`bar`,`buzz` FROM `foobar`";
        } else {
            $expectedSql = "SELECT `foo`,`bar`,`buzz` FROM `foobar` ORDER BY {$sqlOrderBy}";
        }

        self::assertEquals($expectedSql, $builder->sql());
    }

    public function dataForTestRawOrderBy(): iterable
    {
        yield from [
            "typicalExpressionDefaultDirection" => ["`foo` IS NOT NULL", null, "`foo` IS NOT NULL ASC",],
            "typicalMultipleExpressions" => [["`foo` IS NOT NULL", "`bar` IS NULL",], null, "`foo` IS NOT NULL ASC,`bar` IS NULL ASC",],
            "typicalMultipleExpressionsWithDirections" => [["`foo` IS NOT NULL" => "ASC", "`bar` IS NULL" => "DESC",], null, "`foo` IS NOT NULL ASC,`bar` IS NULL DESC",],

            "invalidBadDirection" => ["foo", "RANDOM", "", InvalidOrderByDirectionException::class,],
            "invalidIntExpression" => [42, null, "", TypeError::class,],
            "invalidFloatExpression" => [3.1415927, null, "", TypeError::class,],
            "invalidNullExpression" => [null, null, "", TypeError::class,],
            "invalidBoolExpression" => [true, null, "", TypeError::class,],
            "invalidObjectExpression" => [
                (object) [
                    "__toString" => function(): string
                    {
                        return "foo";
                    },
                ],
                null,
                "",
                TypeError::class,
            ],
            "invalidClosureExpression" => [
                function(): string
                {
                    return "foo";
                },
                null,
                "",
                TypeError::class,
            ],
            "invalidStringableExpression" => [
                new class
                {
                    public function __toString(): string
                    {
                        return "foo";
                    }
                },
                null,
                "",
                TypeError::class,
            ],
        ];
    }

    /**
     * @dataProvider dataForTestRawOrderBy
     *
     * @param mixed $expressions The ORDER BY expression(s)
     * @param mixed $direction The direction for the order by, if `$expressions` is a string.
     * @param string $sqlOrderBy The expects SQL ORDER BY clause that the builder will generate.
     * @param class-string|null $exceptionClass The class of exception expected, if any.
     */
    public function testRawOrderBy($expressions, $direction, string $sqlOrderBy, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = $this->createBuilder(["foo", "bar", "buzz",], "foobar");
        $builder->rawOrderBy($expressions, $direction);

        if (empty($sqlOrderBy)) {
            $expectedSql = "SELECT `foo`,`bar`,`buzz` FROM `foobar`";
        } else {
            $expectedSql = "SELECT `foo`,`bar`,`buzz` FROM `foobar` ORDER BY {$sqlOrderBy}";
        }

        self::assertEquals($expectedSql, $builder->sql());
    }

    /**
     * Ensure attempting to add bad order by does not alter builder state.
     */
    public function testInvalidOrderByPreservesState(): void
    {
        $builder = $this->createBuilder(["foo", "bar", "baz",], "foobar", ["foo" => "foo"], ["foo" => "desc"]);

        try {
            $builder->orderBy(["bar" => "asc", "baz" => "no-direction",]);
        } catch (InvalidOrderByDirectionException $err) {
        }

        self::assertEquals("SELECT `foo`,`bar`,`baz` FROM `foobar` WHERE (`foo` = 'foo') ORDER BY `foo` DESC", $builder->sql());
    }

    /**
     * Test data for testLimit()
     *
     * @return array[] The test data.
     */
    public function dataForTestLimit(): array
    {
        return [
            [["foo", "bar",], "baz", 50, null, "SELECT `foo`,`bar` FROM `baz` LIMIT 50",],
            [["foo", "bar",], "baz", 50, 50, "SELECT `foo`,`bar` FROM `baz` LIMIT 50,50",],
            [["foo", "bar",], "baz", 0, 50, "SELECT `foo`,`bar` FROM `baz` LIMIT 50,0",],
            [["foo", "bar",], "baz", 100, 0, "SELECT `foo`,`bar` FROM `baz` LIMIT 0,100",],
            [["foo", "bar",], "baz", -1, 10, "", InvalidLimitException::class,],
            [["foo", "bar",], "baz", 100, -1, "", InvalidLimitOffsetException::class,],
        ];
    }

    /**
     * @dataProvider dataForTestLimit
     *
     * @param string|string[]|array<string,string> $columns The initial set of columns for the builder.
     * @param string|string[]|array<string,string> $tables The initial set of tables for the builder.
     * @param mixed $limit The limit to test.
     * @param mixed $offset The offset to test.
     * @param string $sql The expected SQL the builder will generate.
     * @param string|null $exceptionClass The exception expected to be thrown, if any.
     */
    public function testLimit($columns, $tables, $limit, $offset, string $sql, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $builder = self::createBuilder($columns, $tables);
        $actual = $builder->limit($limit, $offset);
        self::assertSame($builder, $actual, "QueryBuilder::limit() did not return the same QueryBuilder instance.");
        self::assertEquals($sql, $builder->sql(), "The QueryBuilder did not generate the expected SQL.");
    }
}
