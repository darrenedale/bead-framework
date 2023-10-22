<?php

declare(strict_types=1);

namespace Bead\Database;

use Closure;
use DateTime;
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
use PDO;
use TypeError;

use function Bead\Helpers\Iterable\some;

trait BuildsQueries
{
    /** @var array The columns/expressions to select. */
    private array $selects = [];

    /** @var array The tables in the FROM clause. */
    private array $tables = [];

    /** @var array The join definitions. */
    private array $joins = [];

    /**
     * @var array Flat array of the tables/aliases available in the query (whether FROM or JOIN). Speeds up
     * identification of orphaned joins when compiling the FROM clause.
     */
    private array $tableAliases = [];

    /** @var array The expressions for the WHERE clause. */
    private array $wheres = [];

    /** @var array Tracks the grouping of WHERE clauses when a closure is used to provide grouped WHERE conditions. */
    private array $whereGroupStack = [];

    /** @var array The expressions for the ORDER BY clause. */
    private array $orderBys = [];

    /** @var int|null The LIMIT on the number of rows to return from the query. */
    private ?int $limit = null;

    /** @var int|null The offset for the LIMIT clause. */
    private ?int $offset = null;

    /** @var string|null The compiled SELECT clause, or `null` if the current SELECT clause has yet to be compiled. */
    private ?string $compiledSelects = null;

    /** @var string|null The compiled FROM clause, or `null` if the current FROM clause has yet to be compiled. */
    private ?string $compiledFrom = null;

    /** @var string|null The compiled WHERE clause, or `null` if the current WHERE clause has yet to be compiled. */
    private ?string $compiledWheres = null;

    /** @var string|null The compiled ORDER BY clause, or `null` if the current ORDER BY clause has yet to be compiled. */
    private ?string $compiledOrderBys = null;

    /**
     * Given a name, extract the table and column parts.
     *
     * A tuple of two strings is returned. The first is the table name, the second is the column name. Where no table
     * is explicitly given, the table in the returned tuple will be an empty string.
     *
     * @param string $name The name.
     *
     * @return array The table and column name.
     * @throws InvalidColumnNameException
     */
    protected static function extractTableAndColumn(string $name): array
    {
        $names = explode(".", $name);

        if (some($names, fn (string $name): bool => empty($name))) {
            throw new InvalidColumnNameException($name, "The column {$name} has an empty table and/or column name.");
        }

        switch (count($names)) {
            case 1:
                return ["", $name];

            case 2:
                return $names;

            default:
                throw new InvalidColumnNameException($name, "The column {$name} cannot be extracted to an optional table and a column name.");
        }
    }

    /**
     * Check and wrap a table or column name.
     *
     * If the name is already wrapped in `backtick` characters, this will be a no-op. Do not pass an empty string.
     *
     * @param string $name The name to wrap.
     *
     * @return string The wrapped name.
     */
    protected static function wrapName(string $name): string
    {
        if ("`" === $name[0] && "`" === $name[strlen($name) - 1]) {
            return $name;
        }

        return "`{$name}`";
    }

    /**
     * Check and if necessary wrap a "table.column" specification as `table`.`column`.
     *
     * The name to wrap can be either a fully-qualified table and column or just a column, and can be already wrapped or
     * not. If already wrapped, the method won't wrap again. If just a column name is given, just that will be wrapped.
     *
     * @param string $name The name to wrap.
     *
     * @return string The wrapped name(s).
     * @throws InvalidColumnNameException
     */
    protected static function wrapNames(string $name): string
    {
        [$table, $column] = self::extractTableAndColumn($name);
        $column = self::wrapName($column);

        if (!empty($table)) {
            return self::wrapName($table) . ".{$column}";
        }

        return $column;
    }

    /**
     * Wrap a string in 'quotes' and escape any ' in the content for use in a SQL statement.
     *
     * Unlike some of the other helpers, this one won't detect whether the string has already been wrapped - it will
     * wrap and escape the provided string no matter what.
     *
     * @param string $str The string to process.
     *
     * @return string The wrapped and escaped string.
     */
    protected static function wrapString(string $str): string
    {
        return "'" . str_replace("'", "\\'", $str) . "'";
    }

    /**
     * Helper to SQL-ify a PHP value for use as an SQL value.
     *
     * Strings are escaped and wrapped in 'quotes'. Dates are formatted as strings. Nulls are converted to NULL and
     * ints/floats are stringified (but not quoted).
     *
     * @param string|int|float|DateTime|bool|null $arg The argument to SQL-ify.
     *
     * @return string The SQL-ified value.
     */
    protected static function sqlifyValue($arg): string
    {
        if (is_string($arg)) {
            return self::wrapString($arg);
        } elseif ($arg instanceof DateTime) {
            return "'{$arg->format("Y-m-d H:i:s")}'";
        } elseif (is_null($arg)) {
            return "NULL";
        } elseif (is_int($arg) || is_float($arg)) {
            return "{$arg}";
        } elseif (is_bool($arg)) {
            return ($arg ? "1" : "0");
        }

        throw new TypeError("Where condition arguments must be string, numeric, bool, DateTime or null.");
    }

    /**
     * Replace the query builder's selects with a new one/set.
     *
     * @param $columns
     *
     * @return $this The QueryBuilder instance for further method chaining.
     * @throws DuplicateColumnNameException If an array of columns contains a duplicate name or alias.
     * @throws InvalidColumnNameException If any column in the selects is not a valid SQL column.
     */
    public function select($columns): self
    {
        $this->selects = [];
        $this->compiledSelects = null;
        return $this->addSelect($columns);
    }

    /**
     * @param $columns
     *
     * @return $this The QueryBuilder instance for further method chaining.
     * @throws DuplicateColumnNameException
     * @throws InvalidColumnNameException
     */
    public function addSelect($columns): self
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        if (!is_array($columns)) {
            throw new TypeError("Selects must be a single column or an array of columns.");
        }

        foreach ($columns as $alias => $column) {
            if (is_int($alias)) {
                // use the column name as the alias if none is given
                [, $alias] = self::extractTableAndColumn($column);
            }

            $alias = self::wrapNames($alias);

            if (isset($this->selects[$alias])) {
                throw new DuplicateColumnNameException($alias, "The column {$alias} is already present in the QueryBuilder.");
            }

            $this->selects[$alias] = self::wrapNames($column);
        }

        $this->compiledSelects = null;
        return $this;
    }

    /**
     * @param string $expression
     * @param string $alias
     *
     * @return $this The QueryBuilder instance for further method chaining.
     * @throws DuplicateColumnNameException
     * @throws InvalidColumnNameException
     */
    public function addRawSelect(string $expression, string $alias): self
    {
        $alias = self::wrapNames($alias);

        if (isset($this->selects[$alias])) {
            throw new DuplicateColumnNameException($alias, "The column {$alias} is already present in the QueryBuilder.");
        }

        $this->selects[$alias] = $expression;
        $this->compiledSelects = null;
        return $this;
    }

    /**
     * @param $table
     * @param string|null $alias
     *
     * @return $this The QueryBuilder instance for further method chaining.
     * @throws DuplicateTableNameException
     * @throws InvalidColumnNameException
     * @throws InvalidTableNameException
     */
    public function from($table, ?string $alias = null): self
    {
        if (is_string($table)) {
            $table = [($alias ?? $table) => $table];
        }

        if (!is_array($table)) {
            throw new TypeError("The table must be a string or an array of table names.");
        }

        foreach ($table as $alias => $tableName) {
            if (is_int($alias)) {
                $alias = $tableName;
            }

            if (empty($tableName)) {
                throw new InvalidTableNameException($tableName, "Table names must not be empty.");
            }

            if (empty($alias)) {
                throw new InvalidTableNameException($alias, "Table aliases, if given, must not be empty.");
            }

            $alias = self::wrapNames($alias);

            if ($this->tableNameOrAliasIsInUse($alias)) {
                throw new DuplicateTableNameException($alias, "The table name or alias {$alias} is already present in the QueryBuilder.");
            }

            $this->tables[$alias] = self::wrapNames($tableName);
            $this->tableAliases[] = $alias;
        }

        $this->compiledFrom = null;
        return $this;
    }

    /**
     * @param array|array<string,string> $expression The expression or map of aliases to expressions.
     * @param string|null $alias The alias (if `$expression` is a string).
     *
     * @return $this The QueryBuilder instance for further method chaining.
     * @throws DuplicateTableNameException if the alias is already used in the query.
     * @throws InvalidColumnNameException
     * @throws InvalidTableNameException
     */
    public function rawFrom($expression, ?string $alias = null): self
    {
        if (is_string($expression)) {
            $expression = [$alias => $expression,];
        }

        if (!is_array($expression)) {
            throw new TypeError("The raw FROM expression must be a string or an array of strings.");
        }

        foreach ($expression as $alias => $aliasExpression) {
            if (empty($alias)) {
                throw new InvalidTableNameException($alias, "Table aliases must not be empty.");
            }

            $alias = self::wrapNames($alias);

            if ($this->tableNameOrAliasIsInUse($alias)) {
                throw new DuplicateTableNameException($alias, "The table name or alias {$alias} is already present in the QueryBuilder.");
            }

            $this->tables[$alias] = $aliasExpression;
            $this->tableAliases[] = $alias;
        }

        $this->compiledFrom = null;
        return $this;
    }

    /**
     * Helper to determine whether a table name or alias is already used in the query.
     *
     * The name to check must already be wrapped in backticks.
     *
     * @param string $name The name to check.
     *
     * @return bool `true` if the name is already in use, `false` otherwise.
     */
    protected function tableNameOrAliasIsInUse(string $name): bool
    {
        return isset($this->tables[$name]) || some($this->joins, fn (array $join) => ($name === $join["alias"]));
    }

    /**
     * Internal helper to add joins.
     *
     * The expression defining the join is supplied in `$expr1OrExpressions`, `$operatorOrCombine` and `$expr2`. It  can
     * be specified as:
     * - LHS OP RHS using all three args
     * - LHS = RHS using just the first two of the args as strings
     * - an array of equality expressions where the key of the array is the LHS and the value in the array is the RHS.
     *   In this case `$operatorOrCombine` defines how the expressions are combined - with AND or OR - which defaults to
     *   AND. (e.g. ["yyy.foo" => "xxx.bar, "yyy.baz" => "xxx.quux"], "OR" means ... FROM xxx JOIN yyy ON yyy.foo =
     *   xxx.bar OR yyy.baz = xxx.quux
     *   One drawback of this is that it can't represent joins where the same LHS is used more than once (e.g.
     *   `foo`.`id` = `bar`.`id OR `foo`.`id` = `bar`.`other_id`).
     *
     * If either LHS or RHS is not fully-qualified the table/alias will be added automatically. This works on the
     * convention of the column for the joining table being on the LHS of the ON clause and the column for the table it
     * is joining onto being on the RHS of the ON clause. This allows clients to omit tables/aliases in column
     * specifications when defining the join (e.g. leftJoin("other", "table", "other_id", "id") equates to "... FROM table LEFT JOIN
     * other ON other.table_id = table.id ...").
     *
     * @param string $type
     * @param string $foreign
     * @param string $alias
     * @param string $local
     * @param array<string,string>|string $expr1OrExpressions The LHS of the join expression, or the array of
     * expressions to use in the join.
     * @param string $operatorOrCombine
     * @param string|null $expr2
     *
     * @return void
     * @throws InvalidColumnNameException
     * @throws InvalidTableNameException if either table or the alias is not a valid SQL table name.
     * @throws DuplicateTableNameException if the alias is already in use in a FROM or JOIN.
     */
    protected function addJoin(string $type, string $foreign, string $alias, string $local, $expr1OrExpressions, string $operatorOrCombine = "AND", ?string $expr2 = null): void
    {
        if (empty($foreign)) {
            throw new InvalidTableNameException($foreign, "'{$foreign}' is not a valid table name.");
        }

        if (empty($alias)) {
            throw new InvalidTableNameException($alias, "'{$alias}' is not a valid table name.");
        }

        if (empty($local)) {
            throw new InvalidTableNameException($local, "'{$local}' is not a valid table name.");
        }

        $foreign = self::wrapNames($foreign);
        $alias = self::wrapNames($alias);
        $local = self::wrapNames($local);

        if ($this->tableNameOrAliasIsInUse($alias)) {
            throw new DuplicateTableNameException($alias, "The table name or alias {$alias} is already present in the QueryBuilder.");
        }

        if (is_array($expr1OrExpressions)) {
            $combine = $operatorOrCombine;
            $operatorOrCombine = "=";
        } else {
            $combine = "AND";

            if (!isset($expr2)) {
                $expr2 = $operatorOrCombine;
                $operatorOrCombine = "=";
            }

            $expr1OrExpressions = [
                $expr1OrExpressions => $expr2,
            ];
        }

        if (!isset($this->joins[$local])) {
            $this->joins[$local] = [
                "left" => [],
                "right" => [],
                "inner" => [],
            ];
        }

        $this->joins[$local][$type][] = [
            "foreignOrSubquery" => $foreign,
            "alias" => $alias,
            "combine" => $combine,
            "expressions" => array_map(function (string $lhs, string $rhs) use ($local, $alias, $operatorOrCombine): array {
                [$table, $column] = self::extractTableAndColumn($lhs);

                if (empty($table)) {
                    $lhs = "{$alias}.{$lhs}";
                }

                [$table, $column] = self::extractTableAndColumn($rhs);

                if (empty($table)) {
                    $rhs = "{$local}.{$rhs}";
                }

                return [
                    "lhs" => self::wrapNames($lhs),
                    "operator" => $operatorOrCombine,
                    "rhs" => self::wrapNames($rhs),
                ];
            }, array_keys($expr1OrExpressions), $expr1OrExpressions),
        ];

        $this->tableAliases[] = $alias;
        $this->compiledFrom = null;
    }

    /**
     * Internal helper to add joins using raw expressions (usually subqueries).
     *
     * The expression defining the join is supplied in `$expr1OrExpressions`, `$operatorOrCombine` and `$expr2`. It  can
     * be specified as:
     * - LHS OP RHS using all three args
     * - LHS = RHS using just the first two of the args as strings
     * - an array of equality expressions where the key of the array is the LHS and the value in the array is the RHS.
     *   In this case `$operatorOrCombine` defines how the expressions are combined - with AND or OR - which defaults to
     *   AND. (e.g. ["yyy.foo" => "xxx.bar, "yyy.baz" => "xxx.quux"], "OR" means ... FROM xxx JOIN yyy ON yyy.foo =
     *   xxx.bar OR yyy.baz = xxx.quux
     *   One drawback of this is that it can't represent joins where the same LHS is used more than once (e.g.
     *   `foo`.`id` = `bar`.`id OR `foo`.`id` = `bar`.`other_id`).
     *
     * If either LHS or RHS is not fully-qualified the table/alias will be added automatically. This works on the
     * convention of the column for the joining table being on the LHS of the ON clause and the column for the table it
     * is joining onto being on the RHS of the ON clause. This allows clients to omit tables/aliases in column
     * specifications when defining the join (e.g. leftJoin("other", "table", "other_id", "id") equates to "... FROM table LEFT JOIN
     * other ON other.table_id = table.id ...").
     *
     * @param string $type The join type - LEFT, INNER or RIGHT.
     * @param string $expression The expression to join to the query.
     * @param string $alias The alias for the joined expression.
     * @param string $local The table/alias to join the expression to.
     * @param array<string,string>|string $expr1OrExpressions The LHS of the join expression, or the array of
     * expressions to use in the join.
     * @param string $operatorOrCombine
     * @param string|null $expr2
     *
     * @return void
     * @throws InvalidColumnNameException
     * @throws InvalidTableNameException if either table or the alias is not a valid SQL table name.
     * @throws DuplicateTableNameException if the alias is already in use in a FROM or JOIN.
     * @throws InvalidQueryExpressionException if the expression to join is empty.
     */
    protected function addRawJoin(string $type, string $expression, string $alias, string $local, $expr1OrExpressions, string $operatorOrCombine = "AND", ?string $expr2 = null): void
    {
        $expression = trim($expression);

        if (empty($expression)) {
            throw new InvalidQueryExpressionException($expression, "The expression to join is empty..");
        }

        if (empty($alias)) {
            throw new InvalidTableNameException($alias, "'{$alias}' is not a valid table name.");
        }

        if (empty($local)) {
            throw new InvalidTableNameException($local, "'{$local}' is not a valid table name.");
        }

        $alias = self::wrapNames($alias);
        $local = self::wrapNames($local);

        if ($this->tableNameOrAliasIsInUse($alias)) {
            throw new DuplicateTableNameException($alias, "The table name or alias {$alias} is already present in the QueryBuilder.");
        }

        if (is_array($expr1OrExpressions)) {
            $combine = $operatorOrCombine;
            $operatorOrCombine = "=";
        } else {
            $combine = "AND";

            if (!isset($expr2)) {
                $expr2 = $operatorOrCombine;
                $operatorOrCombine = "=";
            }

            $expr1OrExpressions = [
                $expr1OrExpressions => $expr2,
            ];
        }

        if (!isset($this->joins[$local])) {
            $this->joins[$local] = [
                "left" => [],
                "right" => [],
                "inner" => [],
            ];
        }

        $this->joins[$local][$type][] = [
            "foreignOrSubquery" => $expression,
            "alias" => $alias,
            "combine" => $combine,
            "expressions" => array_map(function (string $lhs, string $rhs) use ($local, $alias, $operatorOrCombine): array {
                [$table, $column] = self::extractTableAndColumn($lhs);

                if (empty($table)) {
                    $lhs = "{$alias}.{$lhs}";
                }

                [$table, $column] = self::extractTableAndColumn($rhs);

                if (empty($table)) {
                    $rhs = "{$local}.{$rhs}";
                }

                return [
                    "lhs" => self::wrapNames($lhs),
                    "operator" => $operatorOrCombine,
                    "rhs" => self::wrapNames($rhs),
                ];
            }, array_keys($expr1OrExpressions), $expr1OrExpressions),
        ];

        $this->tableAliases[] = $alias;
        $this->compiledFrom = null;
    }

    /**
     * Add a left join to the query.
     *
     * The formal definition of the parameters is not easy to digest, so some examples should clarify. You can call thi
     * method in these ways:
     *
     * - $builder->leftJoin("other_table", "table", "table_id", "id")
     *   ... FROM `table` LEFT JOIN `other_table` ON `other_table`.`table_id` = `table`.`id` ...
     *
     * - $builder->leftJoin("other_table", "table", ["table_id" => "id", "original_table_id" => "id"])
     *   ... FROM `table` LEFT JOIN `other_table` ON `other_table`.`table_id` = `table`.`id` AND `other_table`.`original_table_id` = `table`.`id` ...
     *
     * - $builder->leftJoin("other_table", "table", "table_id", "<>", "id")
     *   ... FROM `table` LEFT JOIN `other_table` ON `other_table`.`table_id` <> `table`.`id` ...
     *
     * @param string $foreign The table to join.
     * @param string $local The table in the query to join it to.
     * @param string|array<string,string> $expr1OrExpressions The LHS of the join expression, or an array of ON clause
     * LHS and RHS pairs (which will use the `=` operator)
     * @param string|null $operatorOrExpr2 The operator to use to join the LHS and RHS of the ON clause, or the RHS of
     * the ON clause if `$expr2` is null (in which case `=` is assumed as the operator). Ignored if the preceding
     * argument is an array.
     * @param string|null $expr2 The RHS of the ON expression. Ignored if `$expr1OrExpressions` is an array.
     *
     * @return $this
     * @throws InvalidTableNameException If either table is not a valid SQL table name.
     * @throws DuplicateTableNameException if the foreign table is already in use in a FROM or JOIN.
     */
    public function leftJoin(string $foreign, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self
    {
        $this->addJoin("left", $foreign, $foreign, $local, $expr1OrExpressions, $operatorOrExpr2, $expr2);
        return $this;
    }

    /**
     * Add a right join to the query.
     *
     * @see leftJoin() for examples of how to add joins.
     *
     * @param string $foreign The table to join.
     * @param string $local The table in the query to join it to.
     * @param string|array<string,string> $expr1OrExpressions The LHS of the join expression, or an array of ON clause
     * LHS and RHS pairs (which will use the `=` operator)
     * @param string|null $operatorOrExpr2 The operator to use to join the LHS and RHS of the ON clause, or the RHS of
     * the ON clause if `$expr2` is null (in which case `=` is assumed as the operator). Ignored if the preceding
     * argument is an array.
     * @param string|null $expr2 The RHS of the ON expression. Ignored if `$expr1OrExpressions` is an array.
     *
     * @return $this The QueryBuilder for further method chaining.
     * @throws InvalidTableNameException If either table is not a valid SQL table name.
     * @throws DuplicateTableNameException if the foreign table is already in use in a FROM or JOIN.
     */
    public function rightJoin(string $foreign, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self
    {
        $this->addJoin("right", $foreign, $foreign, $local, $expr1OrExpressions, $operatorOrExpr2, $expr2);
        return $this;
    }

    /**
     * Add an inner join to the query.
     *
     * @see leftJoin() for examples of how to add joins.
     *
     * @param string $foreign The table to join.
     * @param string $local The table in the query to join it to.
     * @param string|array<string,string> $expr1OrExpressions The LHS of the join expression, or an array of ON clause
     * LHS and RHS pairs (which will use the `=` operator)
     * @param string|null $operatorOrExpr2 The operator to use to join the LHS and RHS of the ON clause, or the RHS of
     * the ON clause if `$expr2` is null (in which case `=` is assumed as the operator). Ignored if the preceding
     * argument is an array.
     * @param string|null $expr2 The RHS of the ON expression. Ignored if `$expr1OrExpressions` is an array.
     *
     * @return $this The QueryBuilder for further method chaining.
     * @throws InvalidTableNameException If either table is not a valid SQL table name.
     * @throws DuplicateTableNameException if the foreign table is already in use in a FROM or JOIN.
     */
    public function innerJoin(string $foreign, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self
    {
        $this->addJoin("inner", $foreign, $foreign, $local, $expr1OrExpressions, $operatorOrExpr2, $expr2);
        return $this;
    }

    /**
     * Add a left join to the query with a table alias.
     *
     * @see leftJoin() for examples of how to add joins.
     *
     * @param string $foreign The table to join.
     * @param string $alias The alias to use for the table to join.
     * @param string $local The table in the query to join it to.
     * @param string|array<string,string> $expr1OrExpressions The LHS of the join expression, or an array of ON clause
     * LHS and RHS pairs (which will use the `=` operator)
     * @param string|null $operatorOrExpr2 The operator to use to join the LHS and RHS of the ON clause, or the RHS of
     * the ON clause if `$expr2` is null (in which case `=` is assumed as the operator). Ignored if the preceding
     * argument is an array.
     * @param string|null $expr2 The RHS of the ON expression. Ignored if `$expr1OrExpressions` is an array.
     *
     * @return $this The QueryBuilder for further method chaining.
     * @throws InvalidTableNameException If either table, or the alias, is not a valid SQL table name.
     * @throws DuplicateTableNameException if the alias is already in use in a FROM or JOIN.
     */
    public function leftJoinAs(string $foreign, string $alias, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self
    {
        $this->addJoin("left", $foreign, $alias, $local, $expr1OrExpressions, $operatorOrExpr2, $expr2);
        return $this;
    }

    /**
     * Add a right join to the query with a table alias.
     *
     * @see leftJoin() for examples of how to add joins.
     *
     * @param string $foreign The table to join.
     * @param string $alias The alias to use for the table to join.
     * @param string $local The table in the query to join it to.
     * @param string|array<string,string> $expr1OrExpressions The LHS of the join expression, or an array of ON clause
     * LHS and RHS pairs (which will use the `=` operator)
     * @param string|null $operatorOrExpr2 The operator to use to join the LHS and RHS of the ON clause, or the RHS of
     * the ON clause if `$expr2` is null (in which case `=` is assumed as the operator). Ignored if the preceding
     * argument is an array.
     * @param string|null $expr2 The RHS of the ON expression. Ignored if `$expr1OrExpressions` is an array.
     *
     * @return $this The QueryBuilder for further method chaining.
     * @throws InvalidTableNameException If either table, or the alias, is not a valid SQL table name.
     * @throws DuplicateTableNameException if the alias is already in use in a FROM or JOIN.
     */
    public function rightJoinAs(string $foreign, string $alias, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self
    {
        $this->addJoin("right", $foreign, $alias, $local, $expr1OrExpressions, $operatorOrExpr2, $expr2);
        return $this;
    }

    /**
     * Add an inner join to the query with a table alias.
     *
     * @param string $foreign The table to join.
     * @param string $alias The alias to use for the table to join.
     * @param string $local The table in the query to join it to.
     * @param string|array<string,string> $expr1OrExpressions The LHS of the join expression, or an array of ON clause
     * LHS and RHS pairs (which will use the `=` operator)
     * @param string|null $operatorOrExpr2 The operator to use to join the LHS and RHS of the ON clause, or the RHS of
     * the ON clause if `$expr2` is null (in which case `=` is assumed as the operator). Ignored if the preceding
     * argument is an array.
     * @param string|null $expr2 The RHS of the ON expression. Ignored if `$expr1OrExpressions` is an array.
     *
     * @return $this The QueryBuilder for further method chaining.
     * @see leftJoin() for examples of how to add joins.
     * @throws InvalidTableNameException If either table, or the alias, is not a valid SQL table name.
     * @throws DuplicateTableNameException if the alias is already in use in a FROM or JOIN.
     */
    public function innerJoinAs(string $foreign, string $alias, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self
    {
        $this->addJoin("inner", $foreign, $alias, $local, $expr1OrExpressions, $operatorOrExpr2, $expr2);
        return $this;
    }

    /**
     * Add a left join to the query using a raw expression (e.g. a subquery).
     *
     * It's the caller's responsibility to ensure the provided expression is valid SQL. The only validation that happens
     * is to ensure the expression is not empty.
     *
     * @param string $expression The SQL expression to join.
     * @param string $alias The alias to use for the table to join.
     * @param string $local The table in the query to join it to.
     * @param string|array<string,string> $expr1OrExpressions The LHS of the join expression, or an array of ON clause
     * LHS and RHS pairs (which will use the `=` operator)
     * @param string|null $operatorOrExpr2 The operator to use to join the LHS and RHS of the ON clause, or the RHS of
     * the ON clause if `$expr2` is null (in which case `=` is assumed as the operator). Ignored if the preceding
     * argument is an array.
     * @param string|null $expr2 The RHS of the ON expression. Ignored if `$expr1OrExpressions` is an array.
     *
     * @return $this The QueryBuilder for further method chaining.
     * @throws InvalidTableNameException If either table, or the alias, is not a valid SQL table name.
     * @throws DuplicateTableNameException if the alias is already in use in a FROM or JOIN.
     * @throws InvalidQueryExpressionException if the expression to join is empty.
     */
    public function rawLeftJoin(string $expression, string $alias, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self
    {
        $this->addRawJoin("left", $expression, $alias, $local, $expr1OrExpressions, $operatorOrExpr2, $expr2);
        return $this;
    }

    /**
     * Add a right join to the query using a raw expression (e.g. a subquery).
     *
     * It's the caller's responsibility to ensure the provided expression is valid SQL. The only validation that happens
     * is to ensure the expression is not empty.
     *
     * @param string $expression The SQL expression to join.
     * @param string $alias The alias to use for the table to join.
     * @param string $local The table in the query to join it to.
     * @param string|array<string,string> $expr1OrExpressions The LHS of the join expression, or an array of ON clause
     * LHS and RHS pairs (which will use the `=` operator)
     * @param string|null $operatorOrExpr2 The operator to use to join the LHS and RHS of the ON clause, or the RHS of
     * the ON clause if `$expr2` is null (in which case `=` is assumed as the operator). Ignored if the preceding
     * argument is an array.
     * @param string|null $expr2 The RHS of the ON expression. Ignored if `$expr1OrExpressions` is an array.
     *
     * @return $this The QueryBuilder for further method chaining.
     * @throws InvalidTableNameException If either table, or the alias, is not a valid SQL table name.
     * @throws DuplicateTableNameException if the alias is already in use in a FROM or JOIN.
     * @throws InvalidQueryExpressionException if the expression to join is empty.
     */
    public function rawRightJoin(string $expression, string $alias, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self
    {
        $this->addRawJoin("right", $expression, $alias, $local, $expr1OrExpressions, $operatorOrExpr2, $expr2);
        return $this;
    }

    /**
     * Add an inner join to the query using a raw expression (e.g. a subquery).
     *
     * It's the caller's responsibility to ensure the provided expression is valid SQL. The only validation that happens
     * is to ensure the expression is not empty.
     *
     * @param string $expression The SQL expression to join.
     * @param string $alias The alias to use for the table to join.
     * @param string $local The table in the query to join it to.
     * @param string|array<string,string> $expr1OrExpressions The LHS of the join expression, or an array of ON clause
     * LHS and RHS pairs (which will use the `=` operator)
     * @param string|null $operatorOrExpr2 The operator to use to join the LHS and RHS of the ON clause, or the RHS of
     * the ON clause if `$expr2` is null (in which case `=` is assumed as the operator). Ignored if the preceding
     * argument is an array.
     * @param string|null $expr2 The RHS of the ON expression. Ignored if `$expr1OrExpressions` is an array.
     *
     * @return $this The QueryBuilder for further method chaining.
     * @throws InvalidTableNameException If either table, or the alias, is not a valid SQL table name.
     * @throws DuplicateTableNameException if the alias is already in use in a FROM or JOIN.
     * @throws InvalidQueryExpressionException if the expression to join is empty.
     */
    public function rawInnerJoin(string $expression, string $alias, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self
    {
        $this->addRawJoin("inner", $expression, $alias, $local, $expr1OrExpressions, $operatorOrExpr2, $expr2);
        return $this;
    }

    /**
     * Internal helper to add a WHERE condition to the current group.
     *
     * @param string $combine AND or OR to join the condition to the WHERE clause.
     * @param string $lhs The LHS of the WHERE condition. Must be ready for direct insertion into the SQL statement.
     * @param string $op The SQL operator.
     * @param string $rhs The RHS of the WHERE condition. Must be ready for direct insertion into the SQL statement.
     */
    protected function addWhere(string $combine, string $lhs, string $op, string $rhs): void
    {
        $where = compact("combine", "lhs", "op", "rhs");

        if (empty($this->whereGroupStack)) {
            $this->wheres[] = $where;
        } else {
            $this->whereGroupStack[count($this->whereGroupStack) - 1][] = $where;
        }
    }

    /**
     * Internal helper to add a WHERE condition group to the current group.
     *
     * A WHERE condition group is a parenthesised group of WHERE conditions.
     *
     * @param string $combine AND or OR to join the condition to the WHERE clause.
     * @param array $wheres The conditions. Must be an array of data structures with "lhs", "op" and "rhs" keys, as
     * would be provided to the `addWhere()` method. All must be ready for direct insertion into the SQL statement.
     */
    protected function addWhereGroup(string $combine, array $wheres): void
    {
        $where = ["combine" => $combine, "group" => $wheres,];

        if (empty($this->whereGroupStack)) {
            $this->wheres[] = $where;
        } else {
            $this->whereGroupStack[count($this->whereGroupStack) - 1][] = $where;
        }
    }

    /**
     * @param array|string|Closure $column The column to add to the where clause, or an array of fields and values to
     * add to the clause using the = operator, or a closure to call with this QueryBuilder to add one or more WHERE
     * expressions to a parenthesised group (e.g. WHERE ... AND (...) ... ).
     * @param string|int|float|DateTime|null $operatorOrValue The operator or value. Ignored if `$field` is an array. If
     * `$value` is not set, this is assumed to be the value and the operator is `=`.
     * @param string|int|float|DateTime|null $value The value. Ignored if `$field` is an array.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     * @throws InvalidOperatorException If the operator is provided and is empty.
     */
    public function where($column, $operatorOrValue = null, $value = null): self
    {
        if ($column instanceof Closure) {
            $this->whereGroupStack[] = [];
            $column($this);
            $this->addWhereGroup("AND", array_pop($this->whereGroupStack));
        } elseif (is_array($column)) {
            foreach ($column as $lhs => $rhs) {
                $this->addWhere("AND", self::wrapNames($lhs), "=", self::sqlifyValue($rhs));
            }
        } else {
            if (!isset($value)) {
                $value = $operatorOrValue;
                $operatorOrValue = "=";
            } elseif (empty($operatorOrValue)) {
                throw new InvalidOperatorException($operatorOrValue, "The operator must not be empty.");
            }

            $this->addWhere("AND", self::wrapNames($column), $operatorOrValue, self::sqlifyValue($value));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * @param array|string|Closure $column The column to add to the where clause, or an array of fields and values to add
     * to the clause using the = operator, or a closure to call with this QueryBuilder to add one or more WHERE
     * expressions to a parenthesised group (e.g. WHERE ... OR (...) ... ).
     * @param string|int|float|DateTime|null $operatorOrValue The operator or value. Ignored if `$field` is an array. If
     * `$value` is not set, this is assumed to be the value and the operator is `=`.
     * @param string|int|float|DateTime|null $value The value. Ignored if `$field` is an array.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function orWhere($column, $operatorOrValue = null, $value = null): self
    {
        if ($column instanceof Closure) {
            $this->whereGroupStack[] = [];
            $column($this);
            $this->addWhereGroup("OR", array_pop($this->whereGroupStack));
        } elseif (is_array($column)) {
            foreach ($column as $lhs => $rhs) {
                $this->addWhere("OR", self::wrapNames($lhs), "=", self::sqlifyValue($rhs));
            }
        } else {
            if (!isset($value)) {
                $value = $operatorOrValue;
                $operatorOrValue = "=";
            } elseif (empty($operatorOrValue)) {
                throw new InvalidOperatorException($operatorOrValue, "The operator must not be empty.");
            }

            $this->addWhere("OR", self::wrapNames($column), $operatorOrValue, self::sqlifyValue($value));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * @param array<string>|string $columns The column(s) to check are not null.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If one of the columns provided is not a valid SQL column name.
     */
    public function whereNotNull($columns): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            $columns = [$columns];
        }

        foreach ($columns as $column) {
            $this->addWhere("AND", self::wrapNames($column), "IS NOT", "NULL");
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * @param array<string>|string $columns The column(s) to check are not null.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If one of the columns provided is not a valid SQL column name.
     */
    public function orWhereNotNull($columns): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            $columns = [$columns];
        }

        foreach ($columns as $column) {
            $this->addWhere("OR", self::wrapNames($column), "IS NOT", "NULL");
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * @param array<string>|string $columns The column(s) to check are null.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function whereNull($columns): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            $columns = [$columns];
        }

        foreach ($columns as $column) {
            $this->addWhere("AND", self::wrapNames($column), "IS", "NULL");
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * @param array<string>|string $columns The columns(s) to check are null.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function orWhereNull($columns): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            $columns = [$columns];
        }

        foreach ($columns as $column) {
            $this->addWhere("OR", self::wrapNames($column), "IS", "NULL");
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns contains a value as a substring.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using AND.
     *
     * @param array<string,string>|string $columns A map of columns to contained text, or a single column name.
     * @param string|null $value The contained text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function whereContains($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("AND", self::wrapNames($column), "LIKE", self::wrapString("%{$value}%"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns does not contain a value as a substring.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using OR.
     *
     * @param array<string,string>|string $columns A map of columns to contained text, or a single column name.
     * @param string|null $value The contained text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function orWhereContains($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("OR", self::wrapNames($column), "LIKE", self::wrapString("%{$value}%"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns does not contain a value as a substring.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using AND.
     *
     * @param array<string,string>|string $columns A map of columns to contained text, or a single column name.
     * @param string|null $value The contained text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function whereNotContains($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("AND", self::wrapNames($column), "NOT LIKE", self::wrapString("%{$value}%"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns does not contain a value as a substring.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using OR.
     *
     * @param array<string,string>|string $columns A map of columns to contained text, or a single column name.
     * @param string|null $value The contained text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function orWhereNotContains($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("OR", self::wrapNames($column), "NOT LIKE", self::wrapString("%{$value}%"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns starts with a value.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using AND.
     *
     * @param array<string,string>|string $columns A map of columns to leading text, or a single column name.
     * @param string|null $value The leading text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function whereStartsWith($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("AND", self::wrapNames($column), "LIKE", self::wrapString("{$value}%"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns starts with a value.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using AND.
     *
     * @param array<string,string>|string $columns A map of columns to leading text, or a single column name.
     * @param string|null $value The leading text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function orWhereStartsWith($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("OR", self::wrapNames($column), "LIKE", self::wrapString("{$value}%"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns does not start with a value.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using AND.
     *
     * @param array<string,string>|string $columns A map of columns to leading text, or a single column name.
     * @param string|null $value The leading text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function whereNotStartsWith($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("AND", self::wrapNames($column), "NOT LIKE", self::wrapString("{$value}%"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns does not start with a value.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using OR.
     *
     * @param array<string,string>|string $columns A map of columns to leading text, or a single column name.
     * @param string|null $value The leading text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function orWhereNotStartsWith($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("OR", self::wrapNames($column), "NOT LIKE", self::wrapString("{$value}%"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns ends with a value.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using AND.
     *
     * @param array<string,string>|string $columns A map of columns to trailing text, or a single column name.
     * @param string|null $value The trailing text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function whereEndsWith($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("AND", self::wrapNames($column), "LIKE", self::wrapString("%{$value}"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns ends with a value.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using OR.
     *
     * @param array<string,string>|string $columns A map of columns to trailing text, or a single column name.
     * @param string|null $value The trailing text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function orWhereEndsWith($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("OR", self::wrapNames($column), "LIKE", self::wrapString("%{$value}"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns does not end with a value.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using AND.
     *
     * @param array<string,string>|string $columns A map of columns to trailing text, or a single column name.
     * @param string|null $value The trailing text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function whereNotEndsWith($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("AND", self::wrapNames($column), "NOT LIKE", self::wrapString("%{$value}"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add WHERE conditions to check one or more columns does not end with a value.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using OR.
     *
     * @param array<string,string>|string $columns A map of columns to trailing text, or a single column name.
     * @param string|null $value The trailing text, if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function orWhereNotEndsWith($columns, ?string $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            $this->addWhere("OR", self::wrapNames($column), "NOT LIKE", self::wrapString("%{$value}"));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add a WHERE condition checking the value of one or more fields is in an array of values.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using AND.
     *
     * @param array<string,array>|string $columns A map of columns to the array of values, or the column.
     * @param array|null $value The array of values for the column, if `$column` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function whereIn($columns, ?array $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            if (!is_array($value)) {
                throw new InvalidArgumentException("IN conditions must be arrays.");
            }

            if (empty($value)) {
                throw new InvalidArgumentException("IN conditions cannot be added with empty arrays.");
            }

            foreach ($value as & $convertedValue) {
                $convertedValue = self::sqlifyValue($convertedValue);
            }

            $this->addWhere("AND", self::wrapNames($column), "IN", "(" . implode(",", $value) . ")");
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add a WHERE condition checking the value of one or more fields is in an array of values.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using OR.
     *
     * @param array<string,array>|string $columns A map of columns to the array of values, or the column.
     * @param array|null $value The array of values for the column, if `$column` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function orWhereIn($columns, ?array $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            if (!is_array($value)) {
                throw new InvalidArgumentException("IN conditions must be arrays.");
            }

            if (empty($value)) {
                throw new InvalidArgumentException("IN conditions cannot be added with empty arrays.");
            }

            foreach ($value as & $convertedValue) {
                $convertedValue = self::sqlifyValue($convertedValue);
            }

            $this->addWhere("OR", self::wrapNames($column), "IN", "(" . implode(",", $value) . ")");
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add a WHERE condition checking the value of one or more fields is not in an array of values.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using AND.
     *
     * @param array<string,array>|string $columns A map of columns to the array of values, or the column.
     * @param array|null $value The array of values for the column, if `$column` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function whereNotIn($columns, ?array $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            if (!is_array($value)) {
                throw new InvalidArgumentException("IN conditions must be arrays.");
            }

            if (empty($value)) {
                throw new InvalidArgumentException("IN conditions cannot be added with empty arrays.");
            }

            foreach ($value as & $convertedValue) {
                $convertedValue = self::sqlifyValue($convertedValue);
            }

            $this->addWhere("AND", self::wrapNames($column), "NOT IN", "(" . implode(",", $value) . ")");
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add a WHERE condition checking the value of one or more fields is not in an array of values.
     *
     * Either provide a column name and an array of values to check against the value of the column, or a map of columns
     * with an array of values to check against the column for each column.
     *
     * The WHERE condition(s) is(are) added to any existing conditions in the current group using AND.
     *
     * @param array<string,array>|string $columns A map of columns to the array of values, or the column.
     * @param array|null $value The array of values for the column, if `$column` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function orWhereNotIn($columns, ?array $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => $value];
        }

        foreach ($columns as $column => $value) {
            if (!is_array($value)) {
                throw new InvalidArgumentException("IN conditions must be arrays.");
            }

            if (empty($value)) {
                throw new InvalidArgumentException("IN conditions cannot be added with empty arrays.");
            }

            foreach ($value as & $convertedValue) {
                $convertedValue = self::sqlifyValue($convertedValue);
            }

            $this->addWhere("OR", self::wrapNames($column), "NOT IN", "(" . implode(",", $value) . ")");
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add a WHERE clause that checks the length of a text or binary column against a given value.
     *
     * The condition is added to any existing conditions in the current group using AND.
     *
     * @param array<string,int>|string $columns The column or a map of columns to lengths.
     * @param int|null $value The length if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function whereLength($columns, $operatorOrValue = null, $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }

            foreach ($columns as $column => $length) {
                if (!is_int($length)) {
                    throw new TypeError("Constraints for LENGTH-based WHERE clauses must be ints.");
                }

                $this->addWhere("AND", "LENGTH(" . self::wrapNames($column) . ")", "=", self::sqlifyValue($length));
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            if (!isset($value)) {
                $value = $operatorOrValue;
                $operatorOrValue = "=";
            }

            if (!is_int($value)) {
                throw new TypeError("Constraints for LENGTH-based WHERE clauses must be ints.");
            }

            $this->addWhere("AND", "LENGTH(" . self::wrapNames($columns) . ")", $operatorOrValue, self::sqlifyValue($value));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add a WHERE clause that checks the length of a text or binary column against a given value.
     *
     * The condition is added to any existing conditions in the current group using OR.
     *
     * @param array<string,int>|string $columns The column or a map of columns to lengths.
     * @param int|null $value The length if `$columns` is a string.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     */
    public function orWhereLength($columns, $operatorOrValue = null, $value = null): self
    {
        if (is_array($columns)) {
            if (empty($columns)) {
                throw new InvalidArgumentException("WHERE conditions cannot be added with an empty array of columns.");
            }

            foreach ($columns as $column => $length) {
                if (!is_int($length)) {
                    throw new TypeError("Constraints for LENGTH-based WHERE clauses must be ints.");
                }

                $this->addWhere("OR", "LENGTH(" . self::wrapNames($column) . ")", "=", self::sqlifyValue($length));
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            if (!isset($value)) {
                $value = $operatorOrValue;
                $operatorOrValue = "=";
            }

            if (!is_int($value)) {
                throw new TypeError("Constraints for LENGTH-based WHERE clauses must be ints.");
            }

            $this->addWhere("OR", "LENGTH(" . self::wrapNames($columns) . ")", $operatorOrValue, self::sqlifyValue($value));
        }

        $this->compiledWheres = null;
        return $this;
    }

    /**
     * Add an ORDER BY clause to the query.
     *
     * If this method throws the builder's state is guaranteed not to be modified.
     *
     * @param array<string,string>|array<string>|string $columns The column(s) to order by.
     * @param string|null $direction The direction. Must be "ASC" or "DESC". Ignored if `$columns` is an array.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidColumnNameException If any field is not valid.
     * @throws InvalidOrderByDirectionException If any field's ORDER BY direction is not valid.
     */
    public function orderBy($columns, ?string $direction = null): self
    {
        static $validDirections = ["ASC", "DESC",];

        if (is_array($columns)) {
            if (array_is_list($columns)) {
                $columns = array_combine($columns, array_fill(0, count($columns), $direction ?? "ASC"));
            }
        } else {
            if (!is_string($columns)) {
                throw new TypeError("Argument #1 \$columns to " . __FUNCTION__ . " must be a string or array.");
            }

            $columns = [$columns => ($direction ?? "ASC")];
        }

        // validate first, we don't want to end up with half the order bys applied if there's an invalid one in the set
        foreach ($columns as $direction) {
            if (!in_array(strtoupper($direction), $validDirections)) {
                throw new InvalidOrderByDirectionException($direction, "The direction {$direction} is not valid for an SQL ORDER BY clause.");
            }
        }

        foreach ($columns as $column => $direction) {
            $column = self::wrapNames($column);
            $this->orderBys[$column] = strtoupper($direction);
        }

        $this->compiledOrderBys = null;
        return $this;
    }

    /**
     * Add an ORDER BY clause to the query using a raw SQL expression.
     *
     * If this method throws the builder's state is guaranteed not to be modified.
     *
     * @param array<string,string>|array<string>|string $expressions The expression(s) to use for ordering.
     * @param string|null $direction The direction. Must be "ASC" or "DESC". Ignored if `$columns` is an array.
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidOrderByDirectionException if any expression is paired with an invalid ORDER BY direction.
     */
    public function rawOrderBy($expressions, ?string $direction = "ASC"): self
    {
        static $validDirections = ["ASC", "DESC",];

        if (is_array($expressions)) {
            if (array_is_list($expressions)) {
                $expressions = array_combine($expressions, array_fill(0, count($expressions), $direction ?? "ASC"));
            }
        } else {
            if (!is_string($expressions)) {
                throw new TypeError("Argument #1 \$expressions to " . __FUNCTION__ . " must be a string or array.");
            }

            $expressions = [$expressions => ($direction ?? "ASC")];
        }

        // validate first, we don't want to end up with half the order bys applied if there's an invalid one in the set
        foreach ($expressions as $direction) {
            if (!in_array(strtoupper($direction), $validDirections)) {
                throw new InvalidOrderByDirectionException($direction, "The direction {$direction} is not valid for an SQL ORDER BY clause.");
            }
        }

        foreach ($expressions as $expression => $direction) {
            $this->orderBys[$expression] = $direction;
        }

        $this->compiledOrderBys = null;
        return $this;
    }

    /**
     * Set the query's LIMIT clause.
     *
     * @param int $limit The limit on the number of rows returned.
     * @param int|null $offset The offset of the first row to return. `null` means the default (i.e. the first row in
     * the query's results).
     *
     * @return $this The query builder for further method chaining.
     * @throws InvalidLimitException If the size of the limit is < 0.`
     * @throws InvalidLimitOffsetException If the offset is specified and is < 0.
     */
    public function limit(int $limit, ?int $offset = null): self
    {
        if (0 > $limit) {
            throw new InvalidLimitException($limit, "The limit {$limit} is not valid.");
        }

        if (isset($offset) && 0 > $offset) {
            throw new InvalidLimitOffsetException($offset, "The limit offset {$offset} is not valid.");
        }

        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * Helper to compile the SELECT clause for the query.
     *
     * If the SELECT clause has not changed since it was last compiled it will not be recompiled, the previous compiled
     * clause will be returned.
     *
     * @return string The compiled SELECT clause.
     */
    protected function compileSelects(): string
    {
        if (!isset($this->compiledSelects)) {
            $selects = array_map(function (string $expression, string $alias): string {
                return ($alias === $expression ? $expression : "{$expression} AS {$alias}");
            }, $this->selects, array_keys($this->selects));
            $this->compiledSelects = "SELECT " . implode(",", $selects);
        }

        return $this->compiledSelects;
    }

    /**
     * Recursively compile the joins for a given table/alias.
     *
     * Anything joined to the given table/alias is compiled to the SQL (LEFT|RIGHT|INNER) JOIN clause. This is done
     * recursively for all the tables/aliases joined to the original. So for example if table `b` is joined to table
     * `a` and table `c` is joined to `b`, then calling this method with `a` will generate "(LEFT|RIGHT|INNER) JOIN `b`
     * ON `b`.`...` = `a`.`...` (LEFT|RIGHT|INNER) JOIN `c` ON `c`.`...` = `b`.`...`"
     *
     * The returned SQL will be an empty string if the table/alias doesn't have any joins.
     *
     * @param string $table The table or alias for which to generate the joins SQL.
     *
     * @return string The SQL for the joins.
     */
    protected function compileJoinsOn(string $table): string
    {
        if (!isset($this->joins[$table])) {
            return "";
        }

        $sql = "";

        foreach ($this->joins[$table] as $type => $joins) {
            $type = strtoupper($type);

            foreach ($joins as $join) {
                $sql .= " {$type} JOIN {$join["foreignOrSubquery"]}";

                if ($join["alias"] !== $join["foreignOrSubquery"]) {
                    $sql .= " AS {$join["alias"]}";
                }

                $ons = array_map(function (array $on): string {
                    return "{$on["lhs"]}{$on["operator"]}{$on["rhs"]}";
                }, $join["expressions"]);

                $sql .= " ON " . implode(" {$join["combine"]} ", $ons);
                $sql .= $this->compileJoinsOn($join["alias"]);
            }
        }

        return $sql;
    }

    /**
     * Helper to compile the FROM clause for the query.
     *
     * If the FROM clause has not changed since it was last compiled it will not be recompiled, the previous compiled
     * clause will be returned.
     *
     * @return string The compiled FROM clause.
     *
     * @throws OrphanedJoinException if a join can't be compiled because the table or alias it depends on is not present
     * in the query builder.
     */
    protected function compileFrom(): string
    {
        $orphanedJoins = array_diff(array_keys($this->joins), $this->tableAliases);

        if (!empty($orphanedJoins)) {
            throw new OrphanedJoinException($orphanedJoins[0], "The table or alias {$orphanedJoins[0]} is not present in the query builder.");
        }

        if (!isset($this->compiledFrom)) {
            $from = "";

            foreach ($this->tables as $alias => $table) {
                if (!empty($from)) {
                    $from .= ",";
                }

                $from .= "{$table}";

                if ($alias !== $table) {
                    $from .= " AS {$alias}";
                }

                $from .= $this->compileJoinsOn($alias);
            }

            $this->compiledFrom = "FROM {$from}";
        }

        return $this->compiledFrom;
    }

    /**
     * Helper to compile a group of WHERE conditions to SQL.
     *
     * @param array $wheres The group of WHERE conditions to compile.
     *
     * @return string The SQL for the conditions.
     */
    private static function compileWhereGroup(array $wheres): string
    {
        $compiledWheres = "";

        foreach ($wheres as $where) {
            if (!empty($compiledWheres)) {
                $compiledWheres .= " " . ($where["combine"] ?? "AND") . " ";
            }

            if (isset($where["group"])) {
                $compiledWheres .= self::compileWhereGroup($where["group"]);
            } else {
                $compiledWheres .= "{$where["lhs"]} {$where["op"]} {$where["rhs"]}";
            }
        }

        return trim("({$compiledWheres})");
    }

    /**
     * Helper to compile the WHERE clause for the query.
     *
     * If the WHERE clause has not changed since it was last compiled it will not be recompiled, the previous compiled
     * clause will be returned.
     *
     * @return string The compiled WHERE clause.
     */
    protected function compileWheres(): string
    {
        if (!isset($this->compiledWheres)) {
            if (empty($this->wheres)) {
                $this->compiledWheres = "";
            } else {
                $this->compiledWheres = "WHERE " . self::compileWhereGroup($this->wheres);
            }
        }

        return $this->compiledWheres;
    }

    /**
     * Helper to compile the ORDER BY clause for the query.
     *
     * If the ORDER BY clause has not changed since it was last compiled it will not be recompiled, the previous
     * compiled clause will be returned.
     *
     * @return string The compiled WHERE clause.
     * @throws InvalidOrderByDirectionException if any of the ORDER BY clauses contains a direction that is neither ASC nor DESC.
     */
    protected function compileOrderBys(): string
    {
        if (!isset($this->compiledOrderBys)) {
            if (empty($this->orderBys)) {
                $this->compiledOrderBys = "";
            } else {
                $orderBys = array_map(
                    fn (?string $direction, string $expression): string  => "{$expression} {$direction}",
                    $this->orderBys,
                    array_keys($this->orderBys)
                );

                $this->compiledOrderBys = "ORDER BY " . implode(",", $orderBys);
            }
        }

        return $this->compiledOrderBys;
    }

    /**
     * Compile the LIMIT clause of the SELECT statement.
     *
     * An empty string will be returned if there is no limit clause.
     *
     * @return string The LIMIT clause.
     */
    protected function compileLimit(): string
    {
        if (!isset($this->limit)) {
            return "";
        }

        if (isset($this->offset)) {
            return "LIMIT {$this->offset},{$this->limit}";
        }

        return "LIMIT {$this->limit}";
    }

    /**
     * Fetch the SQL for the query.
     *
     * @return string The SQL.
     * @throws InvalidOrderByDirectionException
     * @throws OrphanedJoinException
     */
    public function sql(): string
    {
        $sql      = "{$this->compileSelects()} {$this->compileFrom()}";
        $wheres   = $this->compileWheres();
        $orderBys = $this->compileOrderBys();
        $limit    = $this->compileLimit();

        foreach ([$wheres, $orderBys, $limit] as $clause) {
            if (!empty($clause)) {
                $sql .= " {$clause}";
            }
        }

        return $sql;
    }
}
