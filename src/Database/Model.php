<?php

namespace Equit\Database;

use DateTime;
use Equit\Application;
use Equit\Exceptions\ModelPropertyCastException;
use Equit\Exceptions\UnknownRelationException;
use Equit\Exceptions\UnrecognisedQueryOperatorException;
use Exception;
use JsonException;
use LogicException;
use PDO;
use PDOStatement;
use TypeError;

/**
 * Base class for database model classes.
 *
 * Subclasses **must** have a default constructor.
 *
 * TODO modification tracking
 * TODO set relations
 * TODO save() should save modified relations?
 */
abstract class Model
{
    /** @var string The database table the models' data lives in. */
    protected static string $table = "";

    /** @var string The name of the column containing the primary key. */
    protected static string $primaryKey = "id";

    /**
     * The model's properties.
     *
     * Property names (columns) are keys; values are the column type. The type indicates how the value in the field will
     * be converted when accessed. The value will be converted in both directions. Types are:
     * -
     * @var array
     */
    protected static array $properties = [];

    /**
     * The model's relations with other models.
     *
     * This array contains three elements, each of which is an array defining the relations of different types:
     * - The `has` element defines relations where this model is the parent and the related models are the children;
     * - The `belongs` element defines relations where this model is the child and the related model is the parent;
     * - The `many` element defines many-to-many relations between this model and another model
     *
     * Each model class can have any number of defined relations of each type. Each defined relation must have the
     * model class to which this model is related specified in the `model`. Each defined relation can also contain the
     * `key` and `related_key` elements, which define the names of the properties on the two models that are linked -
     * `key` is the property on this model, `related_key` is the property on the related model. For `has` and `many`
     * relations, `key` can be omitted, in which case the primary key field for this model is used; for `belongs` and
     * `many` relations, the `related_key` can be omitted, in which case the primary key field for the related model is
     * used.
     *
     * `many` relations must define the `pivot` model, which is the model for the link table between the two related
     * models, and the fields in the pivot model that link to this model and the related model, respectively the
     * `pivot_key` and `pivot_related_key` elements.
     *
     * Each relation must have a unique name. This name is used as the name of a property that can be fetched or set
     * on the model to retrieve or set the related models. For clarity, names must be unique amongst all the different
     * relation types defined for a model - you can't have a relation named "authors" in both the "has" and "many"
     * relation arrays.
     *
     * A quick example for a putative Article model:
     *
     * ```php
     * protected static $relations = [
     *    "has" => [
     *       // Articles have many related Authors, joined by the article_id property of the Author matching the primary
     *       // key of the Article. The Authors for an Article are fetched using $article->authors.
     *       "authors" => [
     *          "model" => Author::class,
     *          "related_key" => "article_id",
     *       ],
     *    "belongs" => [
     *       // Articles belong to a Journal, joined by the journal_id property of the Article matching the primary
     *       // key of the Journal. The Journal for an Article is fetched using $article->journal.
     *       "journal" => [
     *          "model" => Journal::class,
     *          "key" => "journal_id",
     *       ],
     *    ],
     * ];
     *
     * ...
     *
     * $article->journal;    // the Journal model related to the Article
     * $article->authors;    // the Author models related to the Article
     * ```
     *
     * @var array|array[]
     */
    protected static array $relations = [
        "has" => [],
        "belongs" => [],
        "many" => [],
    ];

    /** @var PDO The database connection the model uses. */
    private PDO $connection;

    /**
     * @var array The model instance's data. Always stored as it comes out of the database.
     */
    private array $data = [];

    /**
     * @var array The loaded related models.
     */
    private array $related = [];

    /**
     * Initialise a new instance of the model.
     */
    public function __construct()
    {
        $this->connection = static::defaultConnection();
    }

    /**
     * Locate a single model instance in the database.
     *
     * @param string $primaryKey The primary key for the instance to fetch.
     *
     * @return Model|null The instance or `null` if the row with the provided primary key does not exist.
     */
    public static function fetch(string $primaryKey): ?Model
    {
        $connection = static::defaultConnection();
        $stmt = $connection->prepare("SELECT " . static::buildSelectList() . " FROM `" . static::$table . "` WHERE `" . static::$primaryKey . "` = :primary_key LIMIT 1");
        $stmt->execute([":primary_key" => $primaryKey]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (false === $data) {
            return null;
        }

        $model = new static();
        $model->connection = $connection;
        $model->data = $data;
        return $model;
    }

    /**
     * The properties for this type of model.
     * @return array
     */
    protected static function propertyNames(): array
    {
        return array_keys(static::$properties);
    }

    /**
     * The default connection for models of this type.
     * @return PDO
     */
    protected static function defaultConnection(): PDO
    {
        return Application::instance()->dataController();
    }

    /**
     * Create a valid parameter name for a property name.
     *
     * Property names (i.e. column names) may have spaces; query parameter names may not.
     *
     * @param string $property The property.
     *
     * @return string The query parameter name.
     */
    protected static function queryParameterName(string $property): string
    {
        return str_replace(" ", "_", $property);
    }

    /**
     * Build the SET clause for an insert/update query.
     *
     * The SET clause should be a comma-separated list of fields to be updated, with parameters to have data bound to
     * them when the prepared statement is executed (e.g. "`foo` = :foo, `bar` = :bar").
     *
     * @param array $except Properties to exclude.
     *
     * @return string The SET clause.
     */
    protected function buildSetClause(array $except = []): string
    {
        $set = [];

        foreach (array_filter(static::propertyNames(), function(string $property) use ($except): bool {
            return !in_array($property, $except);
        }) as $property) {
            $set[] = "`{$property}` = :" . static::queryParameterName($property);
        }

        return implode(", ", $set);
    }

    /**
     * Build the parameter array to bind values to query parameters in an insert/update query.
     *
     * For example:
     *
     *     [
     *         ":foo" => "foo's value",
     *         ":bar" => "bar's value",
     *     ]
     *
     *
     * @return array The parameters.
     */
    protected function buildParameters(): array
    {
        $params = [];

        foreach ($this->data as $property => $value) {
            $params[":" . static::queryParameterName($property)] = $value;
        }

        return $params;
    }

    /**
     * Store the model.
     *
     * If it's already in the database it's updated; otherwise it's inserted.
     *
     * @return bool `true` if the model was saved, `false` otherwise.
     */
    public function save(): bool
    {
        if (isset($this->{static::$primaryKey})) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
     * Insert the model into the database.
     *
     * A new record will be inserted into the table, and the model will have its primary key updated to the new one.
     *
     * @return bool `true` if the record was inserted successfully, `false` otherwise.
     */
    public function insert(): bool
    {
        if ($this->connection
            ->prepare("INSERT INTO `" . static::$table . "` SET {$this->buildSetClause()}")
            ->execute($this->buildParameters())) {
            // TODO does it need to be cast?
            $this->data[static::$primaryKey] = $this->connection->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Insert the model into the database.
     *
     * A new record will be inserted into the table, and the model will have its primary key updated to the new one.
     *
     * @return bool `true` if the record was inserted successfully, `false` otherwise.
     */
    public function update(?array $data = null): bool
    {
        if (isset($data)) {
            foreach ($data as $property => $value) {
                $this->$property = $value;
            }
        }

        if (!isset($this->{static::$primaryKey})) {
            // can't update a model that's not in the database
            return false;
        }

        if ($this->connection
            ->prepare("UPDATE `" . static::$table . "` SET {$this->buildSetClause([static::$primaryKey])} WHERE `" . static::$primaryKey . "` = :" . static::queryParameterName(static::$primaryKey) . " LIMIT 1")
            ->execute($this->buildParameters())) {
            // TODO does it need to be cast?
            $this->data[static::$primaryKey] = $this->connection->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * @param string|int $value
     * @param string $property
     *
     * @return DateTime
     * @throws ModelPropertyCastException
     */
    protected static function castFromTimestampColumn($value, string $property): DateTime
    {
        $intValue = filter_var($value, FILTER_VALIDATE_INT);

        if (false === $intValue) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Could not create a timestamp from the value {$value}.");
        }

        return $intValue;
    }

    /**
     * @param string $value
     * @param string $property
     *
     * @return DateTime
     * @throws ModelPropertyCastException
     */
    protected static function castFromDateColumn(string $value, string $property): DateTime
    {
        try {
            return new DateTime($value);
        } catch (Exception $err) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Could not create a DateTime instance from the value {$value} retrieved from the database.", 0, $err);
        }
    }

    /**
     * @param string $value
     * @param string $property
     *
     * @return DateTime
     * @throws ModelPropertyCastException
     */
    protected static function castFromDateTimeColumn(string $value, string $property): DateTime
    {
        try {
            return new DateTime($value);
        } catch (Exception $err) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Could not create a DateTime instance from the value {$value} retrieved from the database.", 0, $err);
        }
    }

    /**
     * @param string $value
     * @param string $property
     *
     * @return DateTime
     * @throws ModelPropertyCastException
     */
    protected static function castFromTimeColumn(string $value, string $property): DateTime
    {
        try {
            return new DateTime($value);
        } catch (Exception $err) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Could not create a DateTime instance from the value {$value}.", 0, $err);
        }
    }

    /**
     * @param $value
     * @param string $property
     *
     * @return int
     * @throws ModelPropertyCastException
     */
    protected static function castFromIntColumn($value, string $property): int
    {
        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        
        if (false === $intValue) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Could not create an int from the value {$value}.");
        }
        
        return $intValue;
    }

    /**
     * Take the data from a float, double, decimal or numeric column and produce a PHP float.
     *
     * @param mixed $value The database value.
     * @param string $property The column name.
     *
     * @return float The float value.
     * @throws ModelPropertyCastException
     */
    protected static function castFromFloatColumn($value, string $property): float
    {
        $floatValue = filter_var($value, FILTER_VALIDATE_FLOAT);
        
        if (false === $floatValue) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Could not create a float from the value {$value}.");
        }
        
        return $floatValue;
    }

    /**
     * Take the data from a string, int, float, double, decimal, numeric or boolean column and produce a PHP string.
     *
     * @param mixed $value The database value.
     * @param string $property The column name.
     *
     * @return string The string value.
     * @throws ModelPropertyCastException
     */
    protected static function castFromStringColumn($value, string $property): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_callable([$value, "__toString"], false)) {
            return $value->__toString();
        }

        if (is_int($value) || is_float($value)) {
            return "{$value}";
        }

        if (is_bool($value)) {
            return ($value ? "1" : "0");
        }

        throw new ModelPropertyCastException(static::class, $property, $value, "Could not cast value to a string.");
    }

    /**
     * @param string $value
     * @param string $property
     *
     * @return object
     * @throws ModelPropertyCastException
     */
    protected static function castFromJsonColumn(string $value, string $property): object
    {
        try {
            return json_decode($value, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $err) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Could not parse JSON from the value {$value}.", 0, $err);
        }
    }

    /**
     * @param $value
     * @param string $property
     *
     * @return string
     * @throws ModelPropertyCastException
     */
    protected static function castToStringColumn($value, string $property): string
    {
        if (is_string($value)) {
            return $value;
        }
        
        if (is_callable([$value, "__toString"], false)) {
            return $value->__toString();
        }
        
        if (is_int($value) || is_float($value)) {
            return "{$value}";
        }
        
        if (is_bool($value)) {
            return ($value ? "1" : "0");
        }

        throw new ModelPropertyCastException(static::class, $property, $value, "Could not cast value to a string.");
    }

    /**
     * @param DateTime|int $value
     * @param string $property
     *
     * @return int
     * @throws ModelPropertyCastException
     */
    protected static function castToTimestampColumn($value, string $property): int
    {
        if (is_int($value)) {
            return $value;
        }

        if ($value instanceof DateTime) {
            return $value->getTimestamp();
        }

        throw new ModelPropertyCastException(static::class, $property, $value, "The provided value is not valid for a timestamp property.");
    }

    /**
     * Helper to cast a set property value to the database representation for date columns.
     *
     * @param string|DateTime $value
     * @param string $property
     *
     * @return string
     * @throws ModelPropertyCastException
     */
    protected static function castToDateColumn($value, string $property): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (!($value instanceof DateTime)) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Value is not valid for a date column.");
        }

        return $value->format("Y-m-d");
    }

    /**
     * Helper to cast a set property value to the database representation for datetime columns.
     *
     * @param DateTime|string $value
     * @param string $property
     *
     * @return string
     * @throws ModelPropertyCastException
     */
    protected static function castToDateTimeColumn($value, string $property): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (!($value instanceof DateTime)) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Value is not valid for a datetime column.");
        }

        return $value->format("Y-m-d H:i:s");
    }

    /**
     * Helper to cast a set property value to the database representation for time columns.
     *
     * @param DateTime|string $value
     * @param string $property
     *
     * @return string
     * @throws ModelPropertyCastException
     */
    protected static function castToTimeColumn($value, string $property): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (!($value instanceof DateTime)) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Value is not valid for a time column.");
        }

        return $value->format("H:i:s");
    }

    /**
     * Helper to cast a set property value to the database representation for all types of int column.
     *
     * @param mixed $value
     * @param string $property
     *
     * @return int
     * @throws ModelPropertyCastException
     */
    protected static function castToIntColumn($value, string $property): int
    {
        if (!is_int($value)) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Values for int columns must be int values.");
        }

        return $value;
    }

    /**
     * Helper to cast a set property value to the database representation for float, decimal and numeric columns.
     *
     * @param $value
     * @param string $property
     *
     * @return float
     * @throws ModelPropertyCastException
     */
    protected static function castToFloatColumn($value, string $property): float
    {
        if (!is_float($value)) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Values for float columns must be float values.");
        }

        return $value;
    }

    /**
     * Helper to cast a set property value to the database representation for JSON columns.
     *
     * @param mixed $value
     * @param string $property
     *
     * @return string
     * @throws ModelPropertyCastException
     */
    protected static function castToJsonColumn($value, string $property): string
    {
        if (is_string($value)) {
            try {
                json_decode($value, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $err) {
                throw new ModelPropertyCastException(static::class, $property, $value, "The provided string is not valid JSON.");
            }

            return $value;
        }

        $json = json_encode($value);

        if (false === $json) {
            throw new ModelPropertyCastException(static::class, $property, $value, "The provided value cannot be encoded as JSON.");
        }

        return $json;
    }
    
    /**
     * Fetch the value of a property or a relation for the model.
     *
     * If the provided property identifies a property of the model from the database, the value of that property is
     * returned. If it's one of the model's defined relations, that relation is loaded and the related models are
     * returned. Otherwise an exception is thrown.
     *
     * @param string $property The property being accessed.
     *
     * @return mixed|null The property value.
     * 
     * @throws ModelPropertyCastException if the data retrieved from the database is not compatible with the declared
     * type of the column.
     * @throws LogicException if the property does not exist.
     * @noinspection PhpDocMissingThrowsInspection can't throw UnknownRelationException because we know the relation
     * exists by the time we try to load it
     */
    public function __get(string $property)
    {
        if (in_array($property, static::propertyNames())) {
            if (!isset($this->data[$property])) {
                return null;
            }

            switch (static::$properties[$property]) {
                case "timestamp":
                    return static::castFromTimestampColumn($this->data[$property], $property);
                    
                case "date":
                    return static::castFromDateColumn($this->data[$property], $property);

                case "datetime":
                    return static::castFromDateTimeColumn($this->data[$property], $property);
                    
                case "time":
                    return static::castFromTimeColumn($this->data[$property], $property);
                    
                case "int":
                    return static::castFromIntColumn($this->data[$property], $property);
                    
                case "float":
                    return static::castFromFloatColumn($this->data[$property], $property);

                case "string":
                    return static::castFromStringColumn($this->data[$property], $property);

                case "json":
                    return static::castFromJsonColumn($this->data[$property], $property);
            }
        } else if (in_array($property, static::relationPropertyNames())) {
            if (isset($this->related[$property])) {
                return $this->related[$property];
            }

            /** @noinspection PhpUnhandledExceptionInspection can't throw, we know the relation exists by here */
            $related = $this->relatedModels($property);
            $this->related[$property] = $related;
            return $related;
        }
        
        throw new LogicException("The property {$property} does not exist on class " . static::class . ".");
    }

    /**
     * Set a property or the models for a relation for the model.
     *
     * TODO set related model(s).
     *
     * @param string $property The property to assign.
     * @param mixed $value The value to assign to the property.
     *
     * @return mixed The property value.
     * @throws TypeError if the provided value is not a valid type for the property.
     */
    public function __set(string $property, $value)
    {
        if (in_array($property, static::propertyNames())) {
            if (!isset($value)) {
                $this->data[$property] = null;
                return null;
            }

            try {
                switch (static::$properties[$property]) {
                    case "timestamp":
                        $this->data[$property] = static::castToTimestampColumn($value, $property);
                        break;

                    case "date":
                        $this->data[$property] = static::castToDateColumn($value, $property);
                        break;

                    case "datetime":
                        $this->data[$property] = static::castToDateTimeColumn($value, $property);
                        break;

                    case "time":
                        $this->data[$property] = static::castToTimeColumn($value, $property);
                        break;

                    case "int":
                        $this->data[$property] = static::castToIntColumn($value, $property);
                        break;

                    case "float":
                        $this->data[$property] = static::castToFloatColumn($value, $property);
                        break;

                    case "string":
                        $this->data[$property] = static::castToStringColumn($value, $property);
                        break;

                    case "json":
                        $this->data[$property] = static::castToJsonColumn($value, $property);
                        break;

                    default:
                        $this->data[$property] = $value;
                }
            } catch (ModelPropertyCastException $err) {
                throw new TypeError("The {$property} property cannot be set to the provided value.", 0, $err);
            }

            return  $this->data[$property];
        }

        throw new LogicException("The property {$property} does not exist on class " . static::class . ".");
    }

    /**
     * Check whether a model property has a value or a relation is not null.
     *
     * If the provided property identifies a property of the model from the database, `true` is returned if it is set,
     * `false` if not. If it's one of the model's defined relations, that relation is loaded and if it's not null `true`
     * is returned. Otherwise `false` is returned.
     *
     * @param string $property
     *
     * @return bool
     * @throws UnknownRelationException
     */
    public function __isset(string $property)
    {
        if (in_array($property, static::propertyNames())) {
            return isset($this->data[$property]);
        } else if (in_array($property, static::relationPropertyNames())) {
            if (isset($this->related[$property])) {
                return true;
            }

            /** @noinspection PhpUnhandledExceptionInspection can't throw, we know the relation exists by here */
            $related = $this->relatedModels($property);
            $this->related[$property] = $related;
            return isset($related);
        }

        return false;
    }

    /**
     * Bulk populate (a subset of) the model's properties.
     *
     * @param array $data The data to use to populate the model instance.
     *
     * @throws LogicException if any of the properties in the array does not exist.
     * @throws |TypeError if any of the property values provided is not of the correct type for the property.
     */
    public function populate(array $data): void
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    /**
     * Helper to build a list of fields to select for a SELECT clause.
     *
     * @param array|null $only Only these fields. `null` means all fields.
     *
     * @return string The list of fields.
     */
    protected static function buildSelectList(?array $only = null): string
    {
        if (isset($only)) {
            $only = array_filter(static::propertyNames(), fn(string $property): bool => in_array($property, $only));
        } else {
            $only = static::propertyNames();
        }

        return "`" . static::$table . "`.`" . implode("`, `" . static::$table . "`.`", $only) . "`";
    }

    /**
     * Helper to build an operand for the SQL IN/NOT IN operator.
     *
     * A parenthesised list of prepared statement placeholders will be returned that contains the same number of
     * placeholders as there are in the reference `$values` array.
     *
     * @param array $values The values that will be bound to the operand.
     *
     * @return string The IN operand.
     */
    protected static function buildInOperand(array $values): string
    {
        return "(" . implode(",", array_fill(0, count($values), "?")) . ")";
    }

    public static function bindValues(PDOStatement $stmt, array $values): void
    {
        $idx = 1;

        foreach ($values as $value) {
            switch (true) {
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;

                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;

                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;

                default:
                    $type = PDO::PARAM_STR;
                    break;
            }

            $stmt->bindValue($idx, $value, $type);
            ++$idx;
        }
    }

    /**
     * Helper to make an array of models from a prepared statement that has been executed.
     *
     * @param PDOStatement $stmt The statement.
     *
     * @return array The models.
     */
    protected static function makeModelsFromQuery(PDOStatement $stmt): array
    {
        $models = [];

        foreach ($stmt as $data) {
            $model = new static();
            $model->data = $data;
            $models[] = $model;
        }

        return $models;
    }

    /**
     * Helper to query for rows that match all of a given set of terms.
     *
     * The search terms parameter expects an associative array of column => value pairs. A row must match all of the
     * terms in order to be included in the result. The operator for each of the terms is equals.
     *
     * @param array $terms The search terms.
     *
     * @return array An array of matching models.
     */
    protected static function queryAll(array $terms): array
    {
        $stmt = static::defaultConnection()->prepare(
            "SELECT " .
            static::buildSelectList() . " FROM `" . static::$table . "` WHERE " .
            implode(
                " AND ",
                array_map(
                    function(string $property): string {
                        return "`{$property}` = ?";
                    },
                    array_keys($terms)
                )
            )
        );

        $stmt->execute(array_values($terms));
        return static::makeModelsFromQuery($stmt);
    }

    /**
     * Helper to query based on a simple comparison operator for a single property.
     *
     * @param string $property The property to query on.
     * @param string $operator The operator. Must be a valid SQL operator.
     * @param mixed $value The value to query for.
     *
     * @return array The models that match the query.
     */
    protected static function querySimpleComparison(string $property, string $operator, $value): array
    {
        $stmt = static::defaultConnection()->prepare("SELECT " . static::buildSelectList() . " FROM `" . static::$table . "` WHERE `{$property}` {$operator} ?");
        $stmt->execute([$value]);
        return static::makeModelsFromQuery($stmt);
    }

    /**
     * Query for models where a property is equal to a given value.
     *
     * @param string $property The property to query on.
     * @param mixed $value The value to query for.
     *
     * @return array The matching models.
     */
    public static function queryEquals(string $property, $value): array
    {
        return self::querySimpleComparison($property, "=", $value);
    }

    /**
     * Query for models where a property is not equal to a given value.
     *
     * @param string $property The property to query on.
     * @param mixed $value The value to query for.
     *
     * @return array The matching models.
     */
    public static function queryNotEquals(string $property, $value): array
    {
        return self::querySimpleComparison($property, "<>", $value);
    }

    /**
     * Query for models where a property is like a given value.
     *
     * The value supports the SQL wildcards '%' and '_'.
     *
     * @param string $property The property to query on.
     * @param mixed $value The value to query for.
     *
     * @return array The matching models.
     */
    public static function queryLike(string $property, $value): array
    {
        return self::querySimpleComparison($property, "LIKE", $value);
    }

    /**
     * Query for models where a property is not like a given value.
     *
     * The value supports the SQL wildcards '%' and '_'.
     *
     * @param string $property The property to query on.
     * @param mixed $value The value to query for.
     *
     * @return array The matching models.
     */
    public static function queryNotLike(string $property, $value): array
    {
        return self::querySimpleComparison($property, "NOT LIKE", $value);
    }

    /**
     * Query for models where a property is in a set of values.
     *
     * @param string $property The property to query on.
     * @param mixed $values The set of values to query for.
     *
     * @return array The matching models.
     */
    public static function queryIn(string $property, array $values): array
    {
        $stmt = static::defaultConnection()->prepare("SELECT " . static::buildSelectList() . " FROM `" . static::$table . "` WHERE `{$property}` IN " . static::buildInOperand($values));
        static::bindValues($stmt, $values);
        $stmt->execute();
        return static::makeModelsFromQuery($stmt);
    }

    /**
     * Query for models where a property is not in a set of values.
     *
     * @param string $property The property to query on.
     * @param mixed $values The set of values to query for.
     *
     * @return array The matching models.
     */
    public static function queryNotIn(string $property, array $values): array
    {
        $stmt = static::defaultConnection()->prepare("SELECT " . static::buildSelectList() . " FROM `" . static::$table . "` WHERE `{$property}` NOT IN " . static::buildInOperand($values));
        static::bindValues($stmt, $values);
        $stmt->execute();
        return static::makeModelsFromQuery($stmt);
    }

    /**
     * Retrieve a set of model instances based on a query.
     *
     * Queries can use either an array of properties and values to match (in which case the matching models must meet
     * all of the terms) or a single property and value to match. In the latter case, you can optionally provide an
     * operator, or use the default operator of equality matching.
     *
     * @param string|array<string,mixed> $properties The property name or array of properties and values to match.
     * @param string|mixed|null $operator The operator to use to compare the value to the property.
     * @param mixed|null $value The value to match.
     *
     * @return array
     * @throws UnrecognisedQueryOperatorException
     */
    public static function query($properties, $operator = null, $value = null): array
    {
        if (is_array($properties)) {
            return static::queryAll($properties);
        }

        if (!isset($value)) {
            $value = $operator;
            $operator = "=";
        }

        switch (strtolower($operator)) {
            case "=":
            case "==":
                return static::queryEquals($properties, $value);

            case "!=":
            case "<>":
                return static::queryNotEquals($properties, $value);

            case "like":
                return static::queryLike($properties, $value);

            case "not like":
                return static::queryNotLike($properties, $value);

            case "in":
                return static::queryIn($properties, $value);

            case "not in":
                return static::queryNotIn($properties, $value);
        }

        throw new UnrecognisedQueryOperatorException($operator, "The operator {$operator} is not supported.");
    }

    /**
     * Helper to obtain a list of all the relation names.
     *
     * @return array The property names for the defined relations.
     */
    protected static function relationPropertyNames(): array
    {
        static $names = null;

        if (!isset($names)) {
            $names = array_unique([...array_keys(static::$relations["has"]), ...array_keys(static::$relations["belongs"]), ...array_keys(static::$relations["many"])]);
        }

        return $names;
    }

    /**
     * Fetch the definition of a named relation.
     *
     * @param string $name The relation name.
     *
     * @return array|null The relation definition, or `null` if the relation does not exist.
     */
    protected static function relation(string $name): ?array
    {
        static $cache = [];

        if (isset($cache[$name])) {
            return $cache[$name];
        }

        foreach (static::$relations as $type => $relations) {
            if (isset($relations[$name])) {
                $relation = $relations[$name];
                $relation["type"] = $type;
                $cache[$name] = $relation;
                return $relation;
            }
        }

        return null;
    }

    /**
     * Helper to fetch the related model(s) for a given relation.
     *
     * @param string|array<string, string> $relation The name or relation definition.
     *
     * @return null|Model|array<Model> The related model(s), or null if there is no related model to which this belongs.
     * @throws UnknownRelationException if the named relation does not exist.
     */
    protected function relatedModels($relation)
    {
        if (is_string($relation)) {
            $relationDefinition = static::relation($relation);

            if (!isset($relationDefinition)) {
                throw new UnknownRelationException(static::class, $relation, "The relation {$relation} is not defined for the " . static::class . " model.");
            }

            $relation = $relationDefinition;
        }

        switch ($relation["type"]) {
            case "has":
                return $this->relatedHasModels($relation);

            case "belongs":
                return $this->relatedBelongsModel($relation);

            case "many":
                return $this->relatedManyModels($relation);
        }

        throw new LogicException("Model class " . static::class . " accessed a relation of type {$relation["type"]} which is not valid.");
    }

    /**
     * Helper to fetch the related models for a "has" relation.
     *
     * @param array $relation The relation definition.
     *
     * @return array The related models. Will be an empty array if there are no related models.
     */
    protected function relatedHasModels(array $relation): array
    {
        // TODO special case where $this->$key is `null`?
        $key = $relation["key"] ?? static::$primaryKey;
        return [$relation["model"], "queryEquals"]($relation["related_key"], $this->{$key});
    }

    /**
     * Helper to fetch the related model for a "belongs to" relation.
     *
     * @param array $relation The relation definition.
     *
     * @return Model|null The related model, or `null` if it's not found.
     */
    protected function relatedBelongsModel(array $relation): ?Model
    {
        $relatedKey = $relation["related_key"] ?? ($relation["model"])::$primaryKey;
        return [$relation["model"], "queryEquals"]($relatedKey, $this->{$relation["key"]})[0] ?? null;
    }

    /**
     * Helper to fetch the related models for a many-to-many relation.
     *
     * @param array $relation The definition of the relation.
     *
     * @return array The models.
     */
    protected function relatedManyModels(array $relation): array
    {
        // TODO special case where $this->$key is `null`?
        $key = $relation["key"] ?? static::$primaryKey;
        $relatedTable = ($relation["model"])::$table;
        $pivotTable = ($relation["pivot"])::$table;
        $relatedKey = $relation["related_key"] ?? $relatedTable::$primaryKey;
        $stmt = static::defaultConnection()->prepare("SELECT " . $relatedTable::buildSelectList() . " FROM `{$relatedTable}`, `{$pivotTable}` AS `p` WHERE `p`.`{$relation["pivot_related_key"]}` = `{$relatedTable}`.`{$relatedKey}` AND `p`.`{$relation["pivot_key"]}` = ?");
        $stmt->execute([$this->$key]);
        return ($relation["model"])::makeModelsFromQuery($stmt);
    }
}
