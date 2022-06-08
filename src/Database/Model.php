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
use PDOException;
use PDOStatement;
use ReflectionException;
use ReflectionMethod;
use TypeError;

/**
 * Base class for database model classes.
 *
 * Subclasses **must** have a default constructor.
 */
abstract class Model
{
    /** @var string The database table the models' data lives in. */
    protected static string $table = "";

    /** @var string The name of the column containing the primary key. */
    protected static string $primaryKey = "id";

    /**
     * @var array The model's properties.
     *
     * Property names (columns) are keys; values are the column type. The type indicates how the value in the field will
     * be converted when accessed. The value will be converted in both directions. Types are:
     * - timestamp
     * - date
     * - datetime
     * - time
     * - int
     * - float
     * - string
     * - bool
     * - json
     */
    protected static array $properties = [];

    /** @var PDO The database connection the model uses. */
    private PDO $connection;

    /** @var array The model instance's data. Always stored as it comes out of the database. */
    private array $data = [];

    /** @var array The loaded related models. */
    private array $related = [];

    /**
     * Initialise a new instance of the model.
     */
    public function __construct()
    {
        $this->connection = static::defaultConnection();
    }

    /**
     * @return string The database table containing the data for the model.
     */
    public static function table(): string
    {
        return static::$table;
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
        $model = new static();
        $model->connection = static::defaultConnection();
        $model->data[static::$primaryKey] = $primaryKey;
        return $model->reload() ? $model : null;
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
     * @return PDO The default connection.
     */
    protected static function defaultConnection(): PDO
    {
        return Application::instance()->dataController();
    }

    /**
     * Build the list of columns for an UPDATE/INSERT/REPLACE query.
     *
     * @param array $except Properties to exclude.
     *
     * @return string The SET clause.
     */
    protected function buildColumnList(array $except = []): string
    {
        $columns = [];

        foreach (array_filter(static::propertyNames(), function(string $property) use ($except): bool {
            return !in_array($property, $except);
        }) as $property) {
            $columns[] = "`{$property}`";
        }

        return implode(",", $columns);
    }

    /**
     * Build a list of query placeholders for the model properties.
     *
     * A comma-separated list of "?" placeholders will be generated, with as many placeholders as there are properties
     * in the model.
     *
     * @param array $except Don't include these named properties when determining how many placeholders to generate.
     *
     * @return string The list of placeholders.
     */
    protected function buildPropertyPlaceholderList(array $except = []): string
    {
        return self::buildPlaceholderList(count(array_filter(static::propertyNames(), function(string $property) use ($except): bool {
            return !in_array($property, $except);
        })));
    }

    /**
     * Build a string containing a given number of comma-separated placeholders.
     *
     * @param int $n The number of placeholders.
     *
     * @return string The string with the placeholders.
     */
    protected static function buildPlaceholderList(int $n): string
    {
        return implode(",", array_fill(0, $n, "?"));
    }

    /**
     * Helper to create a OneToMany relation between this model and several others of a given type.
     *
     * This is most commonly a "has-many" relation between a model and several others of a different type that consider
     * this model to be their "parent".
     *
     * @param string $related The related model class.
     * @param string $relatedKey The property on the related model that links to this.
     * @param string|null $localKey The property on this model that links to the others. Defaults to the primary key.
     *
     * @return OneToMany The relation.
     */
    protected function oneToMany(string $related, string $relatedKey, ?string $localKey = null): OneToMany
    {
        return new OneToMany($this, $related, $relatedKey, $localKey ?? static::$primaryKey);
    }

    /**
     * Helper to create a ManyToOne relation between this model and another of a given type.
     *
     * This is most commonly a "belongs-to" relation between a model and another of a different type that is considered
     * its "parent".
     *
     * @param string $related The related model class.
     * @param string $localKey The property on this model that links to the other.
     * @param string|null $relatedKey The property on the related model that links to this. Defaults to the primary key
     * of the related model.
     *
     * @return ManyToOne The relation.
     */
    protected function manyToOne(string $related, string $localKey, ?string $relatedKey = null): ManyToOne
    {
        return new ManyToOne($this, $related, $relatedKey ?? $related::$primaryKey, $localKey);
    }

    /**
     * Helper to create a ManyToMany relation between this model and several others of a given type.
     *
     * This type of relation is mediated by a pivot table which enables multiple instances of the models on either side
     * of the relation to be arbitrarily liked together.
     *
     * @param string $related
     * @param string $pivot
     * @param string $pivotLocalKey
     * @param string $pivotRelatedKey
     * @param string|null $localKey
     * @param string|null $relatedKey
     *
     * @return ManyToMany The relation.
     */
    protected function manyToMany(string $related, string $pivot, string $pivotLocalKey, string $pivotRelatedKey, ?string $localKey = null, ?string $relatedKey = null): ManyToMany
    {
        return new ManyToMany($this, $related, $pivot, $pivotLocalKey, $pivotRelatedKey, $localKey ?? static::$primaryKey, $relatedKey ?? $related::$primaryKey);
    }

    /**
     * Fetch the model's database connection.
     *
     * @return PDO The connection.
     */
    public function connection(): PDO
    {
        return $this->connection;
    }

    /**
     * Determine whether the model exists in the database.
     *
     * This is only as up-to-date as the last time the model was loaded from or saved to the database.
     *
     * @return bool `true` if the model exists in the database, `false` otherwise.
     */
    public function exists(): bool
    {
        return isset($this->{static::$primaryKey});
    }

    /**
     * Reload the model data from the database.
     *
     * The properties will be reset to the values for the record in the database and the in-memory cache of related
     * models will be cleared, ensuring that they are fetched from the database the next time they are accessed.
     *
     * If the record can't be located in the database, the existing state of the model instance is untouched.
     *
     * @return bool `true` if the model was reloaded, `false` if it could not be found in the database.
     */
    public function reload(): bool
    {
        $stmt = $this->connection->prepare("SELECT " . static::buildSelectList() . " FROM `" . static::$table . "` WHERE `" . static::$primaryKey . "` = :primary_key LIMIT 1");
        $stmt->execute([":primary_key" => $this->data[static::$primaryKey]]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (false === $data) {
            return false;
        }

        $this->data = $data;
        $this->related = [];
        return true;
    }

    /**
     * Store the model.
     *
     * If it's already in the database it's updated; otherwise it's inserted. All related models are also
     *
     * @return bool `true` if the model was saved, `false` otherwise.
     */
    public function save(): bool
    {
        return $this->update();
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
            ->prepare("INSERT INTO `" . static::$table . "` ({$this->buildColumnList([static::$primaryKey])}) VALUES ({$this->buildPropertyPlaceholderList([static::$primaryKey])})")
            ->execute(array_filter($this->data, fn(string $key): bool => ($key !== static::$primaryKey)))) {
            $this->data[static::$primaryKey] = $this->connection->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Update the model in the database, optionally with updated properties.
     *
     * If the model exists in the database, it will be updated; otherwise it will be inserted and its primary key will
     * be set.
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

        if ($this->connection
            ->prepare("REPLACE INTO `" . static::$table . "` ({$this->buildColumnList()}) VALUES ({$this->buildPropertyPlaceholderList()})")
            ->execute(array_values($this->data))) {
            $id = $this->connection->lastInsertId();

            if (isset($id)) {
                $this->data[static::$primaryKey] = $id;
            }

            return true;
        }

        return false;
    }

    /**
     * Helper to cast a value from a Timestamp column to a PHP int.
     *
     * @param string|int $value The database value.
     * @param string $property The name of the property.
     *
     * @return int The timestamp.
     * @throws ModelPropertyCastException
     */
    protected static function castFromTimestampColumn($value, string $property): int
    {
        $intValue = filter_var($value, FILTER_VALIDATE_INT);

        if (false === $intValue) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Could not create a timestamp from the value {$value}.");
        }

        return $intValue;
    }

    /**
     * Helper to cast a value from a Date column to a PHP DateTime.
     *
     * @param string $value The database value.
     * @param string $property The name of the property.
     *
     * @return DateTime The DateTime.
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
     * Helper to cast a value from a DateTime column to a PHP DateTime.
     *
     * @param string $value The database value.
     * @param string $property The name of the property.
     *
     * @return DateTime The DateTime.
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
     * Helper to cast a value from a Time column to a PHP DateTime.
     *
     * @param string $value The database value.
     * @param string $property The name of the property.
     *
     * @return DateTime The DateTime.
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
     * Helper to cast a value from an int-type column to a PHP int.
     *
     * @param mixed $value The database value.
     * @param string $property The name of the property.
     *
     * @return int The int value.
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
     * @param string $property The property name.
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
     * Helper to cast a value from a JSON column to a PHP object.
     *
     * @param string $value The database value.
     * @param string $property The name of the property.
     *
     * @return object The PHP object representation of the JSON.
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
	 * Helper to cast a value from a boolean column to a PHP bool value.
	 *
	 * Booleans and numeric values will be converted to true/false based on an equality comparison with 0. Anything that
	 * won't pass that is an error. Values that come out of the database as strings are accepted as long as they are
	 * numeric strings (i.e. the column is numeric but the db driver provides the data as a string). Strings like "true"
	 * and "false" are not accepted. Databases generally don't store boolean values as strings like this, more often
	 * they are stored as ints.
	 *
	 * @param string $value The database value.
	 * @param string $property The name of the property.
	 *
	 * @return object The PHP object representation of the JSON.
	 * @throws ModelPropertyCastException
	 */
	protected static function castFromBoolColumn($value, string $property): bool
	{
		if (is_bool($value)) {
			return $value;
		} else if (is_int($value)) {
			return 0 !== $value;
		} else if (is_float($value)) {
			return 0.0 != $value;
		} else if (is_string($value)) {
			if (false !== ($validatedValue = filter_var($value, FILTER_VALIDATE_INT))) {
				return 0 !== $validatedValue;
			} else if (false !== ($validatedValue = filter_var($value, FILTER_VALIDATE_FLOAT))) {
				return 0.0 != $validatedValue;
			} else if (!is_null($validatedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ["flags" => FILTER_NULL_ON_FAILURE,]))) {
				return $validatedValue;
			}
		}

		throw new ModelPropertyCastException(static::class, $property, $value, "Could not cast the value {$value} to a boolean.", 0, $err);
	}

    /**
     * Cast a PHP value to a database char/varchar/text column value.
     *
     * @param mixed $value The PHP value.
     * @param string $property The property name.
     *
     * @return string The database value.
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
     * Cast a PHP value to a database timestamp column value.
     *
     * @param DateTime|int $value The PHP value.
     * @param string $property The property name.
     *
     * @return int The database timestamp value.
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
     * @param string|DateTime $value The PHP value.
     * @param string $property The column name.
     *
     * @return string The database representation of the date.
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
     * @param DateTime|string $value The PHP value.
     * @param string $property The column name.
     *
     * @return string The database representation of the date-time value.
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
     * @param DateTime|string $value The PHP value.
     * @param string $property The column name.
     *
     * @return string The database representation for the time column.
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
     * @param mixed $value The PHP value.
     * @param string $property The column name.
     *
     * @return int The database value.
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
     * @param mixed $value The PHP value.
     * @param string $property The colum name.
     *
     * @return float The database value.
     * @throws ModelPropertyCastException
     */
    protected static function castToFloatColumn($value, string $property): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new ModelPropertyCastException(static::class, $property, $value, "Values for float columns must be int or float values.");
        }

        return (float) $value;
    }

    /**
     * Helper to cast a set property value to the database representation for JSON columns.
     *
     * @param mixed $value The PHP value.
     * @param string $property The column name.
     *
     * @return string The database representation of the JSON.
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
	 * Helper to cast a set property value to the database representation for bool columns.
	 *
	 * For convenience, the string values of "true", "yes" and "on" will be accepted as `true` and "false", "no" and
	 * "off" will be accepted as `false`. The value will be cast to an int since database engines often represent bools
	 * as ints, or can cast from them. This also helps avoid the issue with PDO where it treats bound values as strings
	 * by default, which coerces `false` to an empty string, which in turn is not recognised (e.g. by Postgres) as a
	 * boolean `false`. Using 1 and 0, PDO will never convert a bool property value to an empty string.
	 *
	 * @param mixed $value The value set for the property.
	 * @param string $property The property name.
	 *
	 * @return int 0 if the property represents `false`, 1 if it represents `true`.
	 *
	 * @throws ModelPropertyCastException
	 */
	protected static function castToBoolColumn($value, string $property): int
	{
		if (is_bool($value)) {
			return $value ? 1 : 0;
		} else if (is_int($value)) {
			return (0 == $value ? 0 : 1);
		} else if (is_float($value)) {
			return (0.0 == $value ? 0 : 1);
		} else if (is_string($value)) {
			if (false !== ($validatedValue = filter_var($value, FILTER_VALIDATE_INT))) {
				return (0 === $validatedValue ? 0 : 1);
			} else if (false !== ($validatedValue = filter_var($value, FILTER_VALIDATE_FLOAT))) {
				return (0.0 == $validatedValue ? 0 : 1);
			} else if (!is_null($validatedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ["flags" => FILTER_NULL_ON_FAILURE,]))) {
				return ($validatedValue ? 1 : 0);
			} else if (in_array(strtolower($value), ["true", "on", "yes"])) {
				return 1;
			} else if (in_array(strtolower($value), ["false", "off", "no"])) {
				return 0;
			}
		}

		throw new ModelPropertyCastException(static::class, $property, $value, "The provided value cannot be represented as a database boolean.", 0, $err);
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

                case "bool":
                    return static::castFromBoolColumn($this->data[$property], $property);

                case "json":
                    return static::castFromJsonColumn($this->data[$property], $property);
            }
        } else {
            try {
                if (!isset($this->related[$property])) {
                    $method = new ReflectionMethod($this, $property);

                    if ($method->hasReturnType() && is_a($method->getReturnType()->getName(), Relation::class, true)) {
                        $this->related[$property] = $method->invoke($this);
                    }
                }

                $this->related[$property]->relatedModels();
            } catch (ReflectionException $err) {
                // requested property is not a relation method
            }
        }
        
        throw new LogicException("The property {$property} does not exist on class " . static::class . ".");
    }

    /**
     * Set a property for the model.
     *
     * @param string $property The property to assign.
     * @param mixed $value The value to assign to the property.
     *
     * @throws TypeError if the provided value is not a valid type for the property.
     */
    public function __set(string $property, $value): void
    {
        if (in_array($property, static::propertyNames())) {
            if (!isset($value)) {
                $this->data[$property] = null;
                return;
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

                    case "bool":
                        $this->data[$property] = static::castToBoolColumn($value, $property);
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

            return;
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
        } else {
            try {
                if (!array_key_exists($property, $this->related)) {
                    $method = new ReflectionMethod($this, $property);

                    if ($method->hasReturnType() && is_a($method->getReturnType()->getName(), Relation::class, true)) {
                        $this->related[$property] = $method->invoke($this);
                    }
                }

                return !is_null($this->related[$property]->relatedModels());
            } catch (ReflectionException $err) {
                // requested property is not a relation method
            }
        }

        return false;
    }

    /**
     * Bulk populate (a subset of) the model's properties.
     *
     * @param array $data The data to use to populate the model instance.
     *
     * @throws LogicException if any of the properties in the array does not exist.
     * @throws TypeError if any of the property values provided is not of the correct type for the property.
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
            $only = array_intersect($only, static::propertyNames());
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
        return "(" . self::buildPlaceholderList(count($values)) . ")";
    }

    /**
     * Helper to do a type-aware binding of values to a prepared statement.
     *
     * @param PDOStatement $stmt The statement to bind to.
     * @param array $values The values to bind.
     *
     * @throws PDOException if the number of values in the array is greater than the number of placeholders in the
     * statement.
     */
    public static function bindValues(PDOStatement $stmt, array $values): void
    {
        $stmt->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
     * Helper to query for rows that match any of a given set of terms.
     *
     * The search terms parameter expects an associative array of column => value pairs. A row must match one or more of
     * the terms in order to be included in the result. The operator for each of the terms is equals.
     *
     * @param array $terms The search terms.
     *
     * @return array An array of matching models.
     */
    protected static function queryAny(array $terms): array
    {
        $stmt = static::defaultConnection()->prepare(
            "SELECT " .
            static::buildSelectList() . " FROM `" . static::$table . "` WHERE " .
            implode(
                " OR ",
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
}
