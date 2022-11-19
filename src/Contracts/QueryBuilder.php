<?php

namespace Equit\Contracts;

use DateTime;

interface QueryBuilder
{
    /**
     * Replace the query builder's selects with a new one/set.
     *
     * @param string|array<string> $columns The columns.
     *
     * @return $this The QueryBuilder instance for further method chaining.
     */
    public function select($columns): self;

    /**
     * Add columns to the query's SELECT clause.
     *
     * @param string|array<string> $columns The column(s) to add.
     *
     * @return $this The QueryBuilder instance for further method chaining.
     */
    public function addSelect($columns): self;

    /**
     * Add a raw expression to the SELECT clause.
     *
     * @param string $expression The SQL-ready expression.
     * @param string $alias The alias to use as the expression's column name in the query results.
     *
     * @return $this The QueryBuilder instance for further method chaining.
     */
    public function addRawSelect(string $expression, string $alias): self;

    /**
     * Add a table to the query's FROM clause.
     *
     * If more than one table is added in a call to this method, the key in the array is the alias for the table, the
     * value the table name. If you don't need an alias for any table in the array just omit the key and the table name
     * will be used.,
     *
     * @param string|array<string,string> $table The table or tables.
     * @param string|null $alias
     *
     * @return $this The QueryBuilder instance for further method chaining.
     */
    public function from($table, ?string $alias = null): self;

    /**
     * Add a raw SQL expression to the FROM clause.
     *
     * This can be useful to add subquery expressions.
     *
     * @param mixed $expression The expression.
     * @param string $alias The alias for the expression.
     *
     * @return $this The QueryBuilder instance for further method chaining.
     */
    public function rawFrom($expression, string $alias): self;

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
     */
    public function leftJoin(string $foreign, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self;

    /**
     * Add a right join to the query.
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
     * @see leftJoin() for examples of how to add joins.
     *
     */
    public function rightJoin(string $foreign, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self;

    /**
     * Add an inner join to the query.
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
     * @see leftJoin() for examples of how to add joins.
     *
     */
    public function innerJoin(string $foreign, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self;

    /**
     * Add a left join to the query with a table alias.
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
     * @return $this
     * @see leftJoin() for examples of how to add joins.
     *
     */
    public function leftJoinAs(string $foreign, string $alias, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self;

    /**
     * Add a right join to the query with a table alias.
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
     * @return $this
     * @see leftJoin() for examples of how to add joins.
     *
     */
    public function rightJoinAs(string $foreign, string $alias, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self;

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
     * @return $this
     * @see leftJoin() for examples of how to add joins.
     *
     */
    public function innerJoinAs(string $foreign, string $alias, string $local, $expr1OrExpressions, ?string $operatorOrExpr2 = "AND", ?string $expr2 = null): self;

    /**
     * @param array|string|Closure $column The field to add to the where clause, or an array of fields and values to add to the
     * clause using the = operator, or a closure to call with this QueryBuilder to add one or more WHERE
     * expressions to a parenthesised group (e.g. WHERE ... AND (...) ... ).
     * @param string|int|float|DateTime|null $operatorOrValue The operator or value. Ignored if `$field` is an array. If
     * `$value` is not set, this is assumed to be the value and the operator is `=`.
     * @param string|int|float|DateTime|null $value The value. Ignored if `$field` is an array.
     *
     * @return $this The query builder for further method chaining.
     */
    public function where($column, $operatorOrValue = null, $value = null): self;

    /**
     * @param array|string|Closure $column The field to add to the where clause, or an array of fields and values to add
     * to the clause using the = operator, or a closure to call with this QueryBuilder to add one or more WHERE
     * expressions to a parenthesised group (e.g. WHERE ... OR (...) ... ).
     * @param string|int|float|DateTime|null $operatorOrValue The operator or value. Ignored if `$field` is an array. If
     * `$value` is not set, this is assumed to be the value and the operator is `=`.
     * @param string|int|float|DateTime|null $value The value. Ignored if `$field` is an array.
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhere($column, $operatorOrValue = null, $value = null): self;

    /**
     * Add one or more IS NOT NULL expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "AND" if necessary.
     *
     * @param array<string>|string $columns The field(s) to check are not null.
     *
     * @return $this The query builder for further method chaining.
     */
    public function whereNotNull($columns): self;

    /**
     * Add one or more IS NOT NULL expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "OR" if necessary.
     *
     * @param array<string>|string $columns The field(s) to check are not null.
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhereNotNull($columns): self;

    /**
     * Add one or more IS NULL expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "AND" if necessary.
     *
     * @param array<string>|string $columns The field(s) to check are null.
     *
     * @return $this The query builder for further method chaining.
     */
    public function whereNull($columns): self;

    /**
     * Add one or more IS NULL expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "OR" if necessary.
     *
     * @param array<string>|string $columns The field(s) to check are null.
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhereNull($columns): self;

    /**
     * Add one or more LIKE '%value%' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "AND" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function whereContains($columns, ?string $value = null): self;

    /**
     * Add one or more LIKE '%value%' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "OR" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhereContains($columns, ?string $value = null): self;

    /**
     * Add one or more NOT LIKE '%value%' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "AND" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function whereNotContains($columns, ?string $value = null): self;

    /**
     * Add one or more NOT LIKE '%value%' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "OR" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhereNotContains($columns, ?string $value = null): self;

    /**
     * Add one or more LIKE 'value%' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "AND" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function whereStartsWith($columns, ?string $value = null): self;

    /**
     * Add one or more LIKE 'value%' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "OR" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhereStartsWith($columns, ?string $value = null): self;

    /**
     * Add one or more NOT LIKE 'value%' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "AND" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function whereNotStartsWith($columns, ?string $value = null): self;

    /**
     * Add one or more NOT LIKE 'value%' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "OR" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhereNotStartsWith($columns, ?string $value = null): self;

    /**
     * Add one or more LIKE '%value' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "AND" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function whereEndsWith($columns, ?string $value = null): self;

    /**
     * Add one or more LIKE '%value' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "OR" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhereEndsWith($columns, ?string $value = null): self;

    /**
     * Add one or more NOT LIKE '%value' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "AND" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function whereNotEndsWith($columns, ?string $value = null): self;

    /**
     * Add one or more NOT LIKE '%value' expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "OR" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhereNotEndsWith($columns, ?string $value = null): self;

    /**
     * Add one or more IN (...) expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "AND" if necessary.
     *
     * @param $columns
     * @param array|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function whereIn($columns, ?array $value = null): self;

    /**
     * Add one or more IN (...) expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "OR" if necessary.
     *
     * @param $columns
     * @param array|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhereIn($columns, ?array $value = null): self;

    /**
     * Add one or more NOT IN (...) expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "AND" if necessary.
     *
     * @param $columns
     * @param array|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function whereNotIn($columns, ?array $value = null): self;

    /**
     * Add one or more NOT IN (...) expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "OR" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhereNotIn($columns, ?array $value = null): self;

    /**
     * Add one or more LENGTH(...) =|<>|<|>|<=|>= value expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "AND" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function whereLength($columns, $operatorOrValue = null, $value = null): self;

    /**
     * Add one or more LENGTH(...) =|<>|<|>|<=|>= value expressions to the query's WHERE clause.
     *
     * The expression is joined to the WHERE clause with "OR" if necessary.
     *
     * @param $columns
     * @param string|null $value
     *
     * @return $this The query builder for further method chaining.
     */
    public function orWhereLength($columns, $operatorOrValue = null, $value = null): self;

    /**
     * Add an ORDER BY clause to the query.
     *
     * @param array<string,string>|array<string>|string $columns The field(s) to order by.
     * @param string|null $direction The direction. Must be "ASC" or "DESC". Ignored if `$fields` is an array.
     *
     * @return $this The query builder for further method chaining.
     */
    public function orderBy($columns, ?string $direction = null): self;

    /**
     * Add an ORDER BY clause to the query using a raw SQL expression.
     *
     * @param array<string,string>|array<string>|string $expressions The expression(s) to use for ordering.
     * @param string|null $direction The direction. Must be "ASC" or "DESC". Ignored if `$fields` is an array.
     *
     * @return $this The query builder for further method chaining.
     */
    public function rawOrderBy($expressions, ?string $direction = "ASC"): self;

    /**
     * Add a LIMIT clause to the query.
     *
     * @param int $limit The limit on the number of rows returned.
     * @param int|null $offset The offset of the first row to return. `null` means the default (i.e. the first row in
     * the query's results).
     *
     * @return $this The query builder for further method chaining.
     */
    public function limit(int $limit, ?int $offset = null): self;

    /**
     * Fetch the SQL for the query.
     *
     * @return string The SQL.
     */
    public function sql(): string;
}
