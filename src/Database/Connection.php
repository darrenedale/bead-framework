<?php

namespace Bead\Database;

use Bead\Contracts\Database\Connection as DatabaseConnectionContract;
use Bead\Contracts\Database\QueryBuilder as QueryBuilderContract;
use Bead\Contracts\Database\Statement as DatabaseStatementContract;
use PDO;

/**
 * Lightweight extension of PDO to implement the Connecton interface using PDO.
 */
class Connection extends PDO implements DatabaseConnectionContract
{
    /**
     * Escape any SQL wildcards found in some text.
     *
     * This helper function escapes a user-provided piece of text such that it can be safely used in a SQL _LIKE_ clause
     * without any characters provided by the user that have special meanings interfering with the results.
     *
     * @param $text string The text to escape.
     *
     * @return string The escaped text.
     */
    public static function escapeSqlWildcards(string $text): string
    {
        static $s_from = ["%", "_"];
        static $s_to = ["\\%", "\\_"];

        return str_replace($s_from, $s_to, $text);
    }

    /**
     * Translate from _de-facto_ to SQL wildcards.
     *
     * This helper function translates _*_ and _?_ in a user-provided piece of text to *%* and *_* respectively so that
     * it can be used in a SQL _LIKE_ clause with the intended meaning.
     *
     * @see sqlToDefactoWildcards()
     *
     * @param $text string The text to translate.
     *
     * @return string The translated text.
     */
    public static function defactoToSqlWildcards(string $text): string
    {
        static $s_from = ["*", "?"];
        static $s_to = ["%", "_"];

        return str_replace($s_from, $s_to, $text);
    }

    /**
     * Translate from _de-facto_ to SQL wildcards.
     *
     * This helper function translates _*_ and _?_ in a user-provided piece of text to _.*_ and _._ respectively so that
     * it can be used in a SQL _REGEX_ clause with the intended meaning.
     *
     * @param $text string The text to translate.
     *
     * @return string The translated text.
     */
    public static function defactoToRegExpWildcards(string $text): string
    {
        static $s_from = ["*", "?"];
        static $s_to = [".*", "."];

        return str_replace($s_from, $s_to, $text);
    }

    /**
     * Translate from SQL to *de-facto* wildcards.
     *
     * This helper function translates *%* and *_* in a user-provided piece of text to _*_ and _?_ respectively.
     *Type
     * @see defactoToSqlWildcards()
     *
     * @param $text string The text to translate.
     *
     * @return string The translated text.
     */
    public static function sqlToDefactoWildcards(string $text): string
    {
        static $s_from = ["%", "_"];
        static $s_to = ["*", "?"];

        return str_replace($s_from, $s_to, $text);
	}

    public function prepare(string $sql): DatabaseConnectionContract
    {
        return new Statement(parent::prepare($sql));
    }

    public function createQuery(): QueryBuilderContract
    {
        return new QueryBuilder($this);
    }

    public function lastInsertId(): int|string|null
    {
        try {
            $id = parent::lastInsertId();
        } catch (\PDOException $err) {
            $id = false;
        }

        if (false === $id) {
            return null;
        }

        return $id;
    }
}
