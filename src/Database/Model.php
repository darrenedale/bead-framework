<?php

namespace Equit\Database;

use DateTime;
use Equit\Application;
use Equit\Contracts\SoftDeletableModel;
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

use function Equit\Helpers\Iterable\all;
use function Equit\Helpers\String\snakeToCamel;

/**
 * Base class for database model classes.
 *
 * Your model classes should derive from this class. Subclasses **must** have a default constructor. Each model class
 * you create should define the properties of the model (i.e. the database columns) in the static `$properties` member.
 * This is an associative array with column names as keys and column types as values. The following types are supported
 * and will automatically be cast by the magic method that provides model property access:
 * - timestamp (PHP DateTime)
 * - date (DateTime)
 * - datetime (DateTime)
 * - time (DateTime)
 * - int (int)
 * - float (float)
 * - string (string)
 * - bool (bool)
 * - json (object)
 *
 * Properties defined in this way can be accessed as properties of the Model instance. For example, if you define
 * $properties as `["date_of_birth" => "date",]`, you can access the property on an instance using
 * `$model->date_of_birth`. The Model base class will take care of casting the value from the database representation to
 * a PHP `DateTime` object. All properties are nullable, albeit you are likely to encounter database exceptions if you
 * set a property to null and its column in the database is not nullable.
 *
 * You can exert more control over the reading and writing of properties by providing custom property accessors and
 * mutators in your model classes. By convention database columns are named using `snake_case` while class methods are
 * named using `camelCase()`. The `Model` class will look for a camelCase equivalent of the snake_case property name
 * prefixed with `get` for the accessor and `set` for the mutator, and suffixed with `Property`. So for example, to
 * provide a custom accessor and mutuator for a column named `first_name` you would implement methods named
 * `getFirstNameProperty()` and `setFirstNameProperty(mixed $value): void`. As a matter of good practice you should
 * appropriately type-hint the return type of the accessor; the mutator should have its sole parameter type hinted
 * `mixed` for PHP8+ or not type hinted for earlier PHP versions, since the `__set()` method could provide data of any
 * type.
 *
 * If you implement custom accessors and/or mutators your methods are entirely responsible for converting the data
 * between the database representation and your app's representation - none of the internal type casting provided
 * by the Model base class will be performed, including setting to null. If your custom mutator is unable to accept the
 * value provided, it must throw a `ModelPropertyCastException`. This will be caught by `__set()` which will throw a
 * `TypeError` with the `ModelPropertyCastException` as its previous. This is done so that contextual information can
 * be provided about property casting while remaining consistent with the exception PHP throws when a typed property is
 * set to a value of an incorrect type.
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
     * Property names (columns) are keys; values are the column type.
     */
    protected static array $properties = [];

    /** @var PDO The database connection the model uses. */
    private PDO $connection;

    /**
	 * @var array The model instance's data. Always stored as the type comes out of the database, always contains keys
     * for every property of the model and always in the order in which the properties are defined in `$properties`
	 */
    protected array $data;

    /** @var array The loaded related models. */
    private array $related = [];

    /**
     * Initialise a new instance of the model.
     *
     * The model instance will be initialised with null for each of its defined properties.
     */
    public function __construct()
    {
        $this->connection = static::defaultConnection();
        $this->data = array_combine(array_keys(static::$properties), array_fill(0, count(static::$properties), null));
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
     * Fetch the database table containing the data for the model.
     *
     * @return string The database table.
     */
    public static function table(): string
    {
        return static::$table;
    }

    /**
     * Fetch the name of the primary key property.
     *
     * @return string The primary key.
     */
    public static function primaryKey(): string
    {
        return static::$primaryKey;
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
        $model->data[static::primaryKey()] = $primaryKey;
        return $model->reload() && (!($model instanceof SoftDeletableModel) || static::deletedModelsIncluded() || !$model->isDeleted()) ? $model : null;
    }

    /**
     * The properties for this type of model.
     *
     * @return array<string> The properties.
     */
    protected static function propertyNames(): array
    {
        return array_keys(static::$properties);
    }

    /**
     * The default connection for models of this type.
     *
     * @return PDO The default connection.
     */
    protected static function defaultConnection(): PDO
    {
        return Application::instance()->database();
    }

    /**
     * Build the list of columns for an UPDATE/INSERT/REPLACE query.
     *
     * @param array $except Properties to exclude.
     *
     * @return string The list of columns.
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
     * @param class-string $related The related model class.
     * @param string $relatedKey The property on the related model that links to this.
     * @param string|null $localKey The property on this model that links to the others. Defaults to the primary key.
     *
     * @return OneToMany The relation.
     */
    protected function oneToMany(string $related, string $relatedKey, ?string $localKey = null): OneToMany
    {
        return new OneToMany($this, $related, $relatedKey, $localKey ?? static::primaryKey());
    }

    /**
     * Helper to create a ManyToOne relation between this model and another of a given type.
     *
     * This is most commonly a "belongs-to" relation between a model and another of a different type that is considered
     * its "parent".
     *
     * @param class-string $related The related model class.
     * @param string $localKey The property on this model that links to the other.
     * @param string|null $relatedKey The property on the related model that links to this. Defaults to the primary key
     * of the related model.
     *
     * @return ManyToOne The relation.
     */
    protected function manyToOne(string $related, string $localKey, ?string $relatedKey = null): ManyToOne
    {
        return new ManyToOne($this, $related, $relatedKey ?? $related::primaryKey(), $localKey);
    }

    /**
     * Helper to create a ManyToMany relation between this model and several others of a given type.
     *
     * This type of relation is mediated by a pivot table which enables multiple instances of the models on either side
     * of the relation to be arbitrarily liked together.
     *
     * @param class-string $related
     * @param class-string $pivot
     * @param string $pivotLocalKey
     * @param string $pivotRelatedKey
     * @param string|null $localKey
     * @param string|null $relatedKey
     *
     * @return ManyToMany The relation.
     */
    protected function manyToMany(string $related, string $pivot, string $pivotLocalKey, string $pivotRelatedKey, ?string $localKey = null, ?string $relatedKey = null): ManyToMany
    {
        return new ManyToMany($this, $related, $pivot, $pivotLocalKey, $pivotRelatedKey, $localKey ?? static::primaryKey(), $relatedKey ?? $related::primaryKey());
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
        return isset($this->{static::primaryKey()}) && (!($this instanceof SoftDeletableModel) || static::deletedModelsIncluded() || !$this->isDeleted());
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
        $stmt = $this->connection()->prepare("SELECT " . static::buildSelectList() . " FROM `" . static::table() . "` WHERE `" . static::primaryKey() . "` = :primary_key LIMIT 1");
        $stmt->execute([":primary_key" => $this->data[static::primaryKey()]]);
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
        if (isset($this->{static::primaryKey()})) {
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
		$primaryKeyProperty = static::primaryKey();

        if ($this->connection()
            ->prepare("INSERT INTO `" . static::table() . "` ({$this->buildColumnList([$primaryKeyProperty])}) VALUES ({$this->buildPropertyPlaceholderList([$primaryKeyProperty])})")
            ->execute(array_values(array_filter($this->data, fn(string $key): bool => ($key !== $primaryKeyProperty), ARRAY_FILTER_USE_KEY)))) {
            $this->data[$primaryKeyProperty] = static::castInsertedKeyToPrimaryKey($this->connection()->lastInsertId());
            return true;
        }

        return false;
    }

    /**
     * Update the model in the database, optionally with updated properties.
     *
     * @return bool `true` if the record was updated successfully, `false` otherwise.
     */
    public function update(?array $data = null): bool
    {
        if (isset($data)) {
            foreach ($data as $property => $value) {
                $this->$property = $value;
            }
        }

        // don't update the primary key
        $properties = array_diff(array_keys(static::$properties), [static::primaryKey()]);

        // arrange the data in the order required for the prepared statement
        $data = array_values(array_diff_key($this->data, [static::primaryKey() => ""]));
        $data[] = $this->data[static::primaryKey()];

        return $this->connection()
            ->prepare("UPDATE `" . static::table() . "` SET `" . implode("` = ?, `", $properties) . "` = ? WHERE `" . static::primaryKey() . "` = ? LIMIT 1")
            ->execute($data);
    }

    /**
     * Delete the model from the database.
     *
     * @return bool `true` if the model was deleted, `false` if not.
     */
    public function delete(): bool
    {
        return $this->connection()
            ->prepare("DELETE FROM `" . static::table() . "` WHERE `" . static::primaryKey() . "` = ? LIMIT 1")
            ->execute([$this->{static::primaryKey()}]);
    }

	/**
	 * Create one or more instances of the model from provided data.
	 *
	 * Provide either the properties for a single model or an array containing the properties for several models. If
	 * the properties for a single model are provided, a single model is returned; if an array of sets of properties is
	 * provided, an array of models is returned (even if the array of sets of properties contains only one set of
	 * properties).
	 *
	 * For example, `User::create(["username" => "darren",])` will return a single model, whereas
	 * `User::create([["username" => "darren",], ["username" => "susan",]])` and
	 * `User::create([["username" => "darren",],])` will both return an array of models. (The first call will return an
	 * array of 2 models, the second an array with just one model in it.)
	 *
	 * The models returned will have been inserted into the database.
	 *
	 * @param array $data The data for the instance(s) to create.
	 *
	 * @return Model|array<Model> The created model(s).
	 */
	public static function create(array $data)
	{
		if (!all(array_keys($data), fn($key): bool => is_int($key))) {
			$model = new static();
			$model->populate($data);
			$model->insert();
			return $model;
		}

		$modelClass = static::class;

		return array_map(function(array $data) use ($modelClass) {
			$model = new $modelClass();
			$model->populate($data);
			$model->insert();
			return $model;
		}, $data);
	}

	/**
	 * Make one or more instances of the model from provided data.
	 *
	 * Provide either the properties for a single model or an array containing the properties for several models. If
	 * the properties for a single model are provided, a single model is returned; if an array of sets of properties is
	 * provided, an array of models is returned (even if the array of sets of properties contains only one set of
	 * properties).
	 *
	 * For example, `User::make(["username" => "darren",])` will return a single model, whereas
	 * `User::make([["username" => "darren",], ["username" => "susan",]])` and
	 * `User::make([["username" => "darren",],])` will both return an array of models. (The first call will return an
	 * array of 2 models, the second an array with just one model in it.)
	 *
	 * The models returned will NOT have been inserted into the database.
	 *
	 * @param array $data The data for the instance(s) to create.
	 *
	 * @return Model|array<Model> The created model(s).
	 */
	public static function make(array $data)
	{
		if (!all(array_keys($data), fn($key): bool => is_int($key))) {
			$model = new static();
			$model->populate($data);
			return $model;
		}

		$modelClass = static::class;

		return array_map(function(array $data) use ($modelClass) {
			$model = new $modelClass();
			$model->populate($data);
			return $model;
		}, $data);
	}

	/**
	 * Cast a value provided by the database connection from an INSERT statuement to the correct primary key type.
	 *
	 * PDO provides `lastInsertId()` as a string. This method can be used to cast the provided last insert ID to the
	 * correct type for the primary column. The default implementation converts strings to ints if required. Reimplement
	 * in your model classes if you need conversion to other types.
	 *
	 * @param $value The value to cast.
	 *
	 * @return mixed The value to store in the model for the primary key.
	 */
	protected static function castInsertedKeyToPrimaryKey($value)
	{
		$primaryKeyProperty = static::primaryKey();

		if ("int" === static::$properties[$primaryKeyProperty]) {
			$castValue = filter_var($value, FILTER_VALIDATE_INT, ["flags" => FILTER_NULL_ON_FAILURE,]);

			if (!isset($castValue)) {
				throw new ModelPropertyCastException(static::class, static::primaryKey(), "The value {$value} provided by the database for the primary key when inserting a " . static::table() . " row is not a valid int.");
			}

			return $castValue;
		}

		return $value;
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
     * Helper to cast a value from a boolean column to a PHP bool value.
     *
     * Booleans and numeric values will be converted to true/false based on an equality comparison with 0. Anything that
     * won't pass that is an error. Values that come out of the database as strings are accepted as long as they are
     * numeric strings (i.e. the column is numeric but the db driver provides the data as a string). Strings like "true"
     * and "false" are not accepted. Databases generally don't store boolean values as strings like this, more often
     * they are stored as ints.
     *
     * @param mixed $value The database value.
     * @param string $property The name of the property.
     *
     * @return bool The PHP boolean value.
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
            if (!is_null($validatedValue = filter_var($value, FILTER_VALIDATE_INT, ["flags" => FILTER_NULL_ON_FAILURE]))) {
                return 0 !== $validatedValue;
            } else if (!is_null($validatedValue = filter_var($value, FILTER_VALIDATE_FLOAT, ["flags" => FILTER_NULL_ON_FAILURE]))) {
                return 0.0 != $validatedValue;
            } else if (!is_null($validatedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ["flags" => FILTER_NULL_ON_FAILURE]))){
                return $validatedValue;
            }
        }

        throw new ModelPropertyCastException(static::class, $property, $value, "Could not cast the value {$value} to a boolean.");
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

		throw new ModelPropertyCastException(static::class, $property, $value, "The provided value cannot be represented as a database boolean.");
	}

    /**
     * Fetch the value of a property or a relation for the model.
     *
     * If the provided property identifies a property of the model from the database, the value of that property is
     * returned. If it's one of the model's defined relations, that relation is loaded and the related models are
     * returned. Otherwise, an exception is thrown.
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
		static $accessors = [];

        if (in_array($property, static::propertyNames())) {
			$cacheKey = static::class . "::{$property}";

			if (!isset($accessors[$cacheKey])) {
				$accessors[$cacheKey] = snakeToCamel("get_{$property}_property");
			}

			if (method_exists($this, $accessors[$cacheKey])) {
				return $this->{$accessors[$cacheKey]}();
			}

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

                return $this->related[$property]->relatedModels();
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
		static $mutators = [];

        if (in_array($property, static::propertyNames())) {
			$cacheKey = static::class . "::{$property}";

			if (!isset($mutators[$cacheKey])) {
				$mutators[$cacheKey] = snakeToCamel("set_{$property}_property");
			}

			try {
				if (method_exists($this, $mutators[$cacheKey])) {
					$this->{$mutators[$cacheKey]}($value);
					return;
				}

				if (!isset($value)) {
					$this->data[$property] = null;
					return;
				}

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
     * is returned. Otherwise, `false` is returned.
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
     * Helper to build a list of columns to select for a SELECT clause.
     *
     * @param array|null $only Only these columns. `null` means all columns.
     *
     * @return string The list of columns.
     */
    protected static function buildSelectList(?array $only = null): string
    {
        if (isset($only)) {
            $only = array_intersect($only, static::propertyNames());
        } else {
            $only = static::propertyNames();
        }

        return "`" . static::table() . "`.`" . implode("`, `" . static::table() . "`.`", $only) . "`";
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
		$stmt->setFetchMode(PDO::FETCH_ASSOC);

        foreach ($stmt as $data) {
            $model = new static();
            $model->data = $data;
            $models[] = $model;
        }

        return $models;
    }

    /**
     * Helper to provide WHERE expressions that should be applied to all queries.
     *
     * This is useful as a customisation point to inject fixed conditions into queries, for example to exclude soft-
     * deleted records.
     *
     * @return array<string> The expressions.
     */
    protected static function fixedWhereExpressions(string $tableAlias = null): array
    {
        return [];
    }

    /**
     * Helper to provide a single string of WHERE expressions built from the fixed expressions.
     *
     * This can be used internally to append the expressions to SQL for prepared statements. The expressions are
     * concatenated using the AND operator, and each expression is wrapped in its own parentheses. It is recommended
     * that any client-supplied expression is wrapped in a pair of parentheses also so that the fixed expressions are
     * appended in the expected manner.
     *
     * @return string The concatenated expressions, or an empty string if there are none.
     */
    protected static final function fixedWhereExpressionsSql(string $tableAlias = null): string
    {
        $fixedExpressions = static::fixedWhereExpressions($tableAlias);

        if (empty($fixedExpressions)) {
            return "";
        }

        return " AND (" . implode(") AND (", $fixedExpressions) . ")";
    }

    /**
     * Helper to query for rows that match all of a given set of terms.
     *
     * The search terms parameter expects an associative array of column => value pairs. A row must match all the terms
     * in order to be included in the result. The operator for each of the terms is equals.
     *
     * @param array $terms The search terms.
     *
     * @return array An array of matching models.
     */
    protected static function queryAll(array $terms): array
    {
        $stmt = static::defaultConnection()->prepare(
            "SELECT " .
            static::buildSelectList() . " FROM `" . static::table() . "` WHERE " .
            implode(
                " AND ",
                array_map(
                    function(string $property): string {
                        return "`{$property}` = ?";
                    },
                    array_keys($terms)
                )
            ) .
            static::fixedWhereExpressionsSql()
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
            static::buildSelectList() . " FROM `" . static::table() . "` WHERE (" .
            implode(
                " OR ",
                array_map(
                    function(string $property): string {
                        return "`{$property}` = ?";
                    },
                    array_keys($terms)
                )
            ) .
            ")" . static::fixedWhereExpressionsSql()
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
    protected static final function querySimpleComparison(string $property, string $operator, $value): array
    {
        $stmt = static::defaultConnection()->prepare("SELECT " . static::buildSelectList() . " FROM `" . static::table() . "` WHERE (`{$property}` {$operator} ?)" . static::fixedWhereExpressionsSql());
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
        $stmt = static::defaultConnection()->prepare("SELECT " . static::buildSelectList() . " FROM `" . static::table() . "` WHERE (`{$property}` IN " . static::buildInOperand($values) . ")" . static::fixedWhereExpressionsSql());
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
        $stmt = static::defaultConnection()->prepare("SELECT " . static::buildSelectList() . " FROM `" . static::table() . "` WHERE (`{$property}` NOT IN " . static::buildInOperand($values) . ")" . static::fixedWhereExpressionsSql());
        static::bindValues($stmt, $values);
        $stmt->execute();
        return static::makeModelsFromQuery($stmt);
    }

    /**
     * Retrieve a set of model instances based on a query.
     *
     * Queries can use either an array of properties and values to match (in which case the matching models must meet
     * all the terms) or a single property and value to match. In the latter case, you can optionally provide an
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
	 * Helper to remove rows that match all of a given set of terms.
	 *
	 * The search terms parameter expects an associative array of column => value pairs. A row must match all the terms
     * in order to be removed. The operator for each of the terms is equals.
	 *
	 * WARNING This is a destructive operation - the matched rows will be deleted from the database.
	 *
	 * @param array $terms The search terms.
	 *
	 * @return bool `true` if the removal was successful, `false` otherwise.
	 */
	protected static function removeWhereAll(array $terms): bool
	{
		$stmt = static::defaultConnection()->prepare(
			"DELETE FROM `" . static::$table . "` WHERE " .
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

		return $stmt->execute(array_values($terms));
	}

	/**
	 * Helper to remove rows based on a simple comparison operator for a single property.
	 *
	 * WARNING This is a destructive operation - the matched rows will be deleted from the database.
	 *
	 * @param string $property The property to query on.
	 * @param string $operator The operator. Must be a valid SQL operator.
	 * @param mixed $value The value to query for.
	 *
	 * @return bool `true` if the removal was successful, `false` otherwise.
	 */
	protected static final function removeSimpleComparison(string $property, string $operator, $value): bool
	{
		$stmt = static::defaultConnection()->prepare("DELETE FROM `" . static::$table . "` WHERE (`{$property}` {$operator} ?)");
		return $stmt->execute([$value]);
	}

	/**
	 * Remove models where a property is equal to a given value.
	 *
	 * WARNING This is a destructive operation - the matched rows will be deleted from the database.
	 *
	 * @param string $property The property to query on.
	 * @param mixed $value The value to query for.
	 *
	 * @return bool `true` if the removal was successful, `false` otherwise.
	 */
	public static function removeEquals(string $property, $value): bool
	{
		return self::removeSimpleComparison($property, "=", $value);
	}

	/**
	 * Remove models where a property is not equal to a given value.
	 *
	 * WARNING This is a destructive operation - the matched rows will be deleted from the database.
	 *
	 * @param string $property The property to query on.
	 * @param mixed $value The value to query for.
	 *
	 * @return bool `true` if the removal was successful, `false` otherwise.
	 */
	public static function removeNotEquals(string $property, $value): bool
	{
		return self::removeSimpleComparison($property, "<>", $value);
	}

	/**
	 * Remove models where a property is like a given value.
	 *
	 * The value supports the SQL wildcards '%' and '_'.
	 *
	 * WARNING This is a destructive operation - the matched rows will be deleted from the database.
	 *
	 * @param string $property The property to query on.
	 * @param mixed $value The value to query for.
	 *
	 * @return bool `true` if the removal was successful, `false` otherwise.
	 */
	public static function removeLike(string $property, $value): bool
	{
		return self::removeSimpleComparison($property, "LIKE", $value);
	}

	/**
	 * Remove models where a property is not like a given value.
	 *
	 * The value supports the SQL wildcards '%' and '_'.
	 *
	 * WARNING This is a destructive operation - the matched rows will be deleted from the database.
	 *
	 * @param string $property The property to query on.
	 * @param mixed $value The value to query for.
	 *
	 * @return bool Whether the removal succeeded.
	 */
	public static function removeNotLike(string $property, $value): bool
	{
		return self::removeSimpleComparison($property, "NOT LIKE", $value);
	}

	/**
	 * Remove models where a property is in a set of values.
	 *
	 * WARNING This is a destructive operation - the matched rows will be deleted from the database.
	 *
	 * @param string $property The property to query on.
	 * @param mixed $values The set of values to query for.
	 *
	 * @return bool `true` if the removal was successful, `false` otherwise.
	 */
	public static function removeIn(string $property, array $values): bool
	{
		$stmt = static::defaultConnection()->prepare("DELETE FROM `" . static::$table . "` WHERE (`{$property}` IN " . static::buildInOperand($values) . ")");
		static::bindValues($stmt, $values);
		return $stmt->execute();
	}

	/**
	 * Remove models where a property is not in a set of values.
	 *
	 * WARNING This is a destructive operation - the matched rows will be deleted from the database.
	 *
	 * @param string $property The property to query on.
	 * @param mixed $values The set of values to query for.
	 *
	 * @return bool `true` if the removal was successful, `false` otherwise.
	 */
	public static function removeNotIn(string $property, array $values): bool
	{
		$stmt = static::defaultConnection()->prepare("DELETE FROM `" . static::$table . "` WHERE (`{$property}` NOT IN " . static::buildInOperand($values) . ")");
		static::bindValues($stmt, $values);
		return $stmt->execute();
	}

	/**
	 * Remove a set of model instances based on a query.
	 *
	 * Queries can use either an array of properties and values to match (in which case the matching models must meet
	 * all the terms) or a single property and value to match. In the latter case, you can optionally provide an
	 * operator, or use the default operator of equality matching.
	 *
	 * WARNING This is a destructive operation - the matched rows will be deleted from the database.
	 *
	 * @param string|array<string,mixed> $properties The property name or array of properties and values to match.
	 * @param string|mixed|null $operator The operator to use to compare the value to the property.
	 * @param mixed|null $value The value to match.
	 *
	 * @return bool `true` if the removal was successful, `false` otherwise.
	 * @throws UnrecognisedQueryOperatorException
	 */
	public static function remove($properties, $operator = null, $value = null): bool
	{
		if (is_array($properties)) {
			return static::removeWhereAll($properties);
		}

		if (!isset($value)) {
			$value = $operator;
			$operator = "=";
		}

		switch (strtolower($operator)) {
			case "=":
			case "==":
				return static::removeEquals($properties, $value);

			case "!=":
			case "<>":
				return static::removeNotEquals($properties, $value);

			case "like":
				return static::removeLike($properties, $value);

			case "not like":
				return static::removeNotLike($properties, $value);

			case "in":
				return static::removeIn($properties, $value);

			case "not in":
				return static::removeNotIn($properties, $value);
		}

		throw new UnrecognisedQueryOperatorException($operator, "The operator {$operator} is not supported.");
	}
}
