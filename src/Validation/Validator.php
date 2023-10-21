<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation;

use ArgumentCountError;
use DateTime;
use Bead\Exceptions\ValidationException;
use Bead\Validation\Rules\After;
use Bead\Validation\Rules\Alpha;
use Bead\Validation\Rules\Alphanumeric;
use Bead\Validation\Rules\Before;
use Bead\Validation\Rules\Between;
use Bead\Validation\Rules\Date;
use Bead\Validation\Rules\DateFormat;
use Bead\Validation\Rules\Different;
use Bead\Validation\Rules\Email;
use Bead\Validation\Rules\EqualTo;
use Bead\Validation\Rules\Filled;
use Bead\Validation\Rules\GreaterThan;
use Bead\Validation\Rules\GreaterThanOrEqual;
use Bead\Validation\Rules\In;
use Bead\Validation\Rules\Integer;
use Bead\Validation\Rules\Ip;
use Bead\Validation\Rules\IsArray;
use Bead\Validation\Rules\IsBoolean;
use Bead\Validation\Rules\IsFalse;
use Bead\Validation\Rules\IsString;
use Bead\Validation\Rules\IsTrue;
use Bead\Validation\Rules\Json;
use Bead\Validation\Rules\Length;
use Bead\Validation\Rules\LessThan;
use Bead\Validation\Rules\LessThanOrEqual;
use Bead\Validation\Rules\Max;
use Bead\Validation\Rules\MaxLength;
use Bead\Validation\Rules\Min;
use Bead\Validation\Rules\MinLength;
use Bead\Validation\Rules\NotEqualTo;
use Bead\Validation\Rules\NotIn;
use Bead\Validation\Rules\Number;
use Bead\Validation\Rules\Optional;
use Bead\Validation\Rules\Present;
use Bead\Validation\Rules\RegEx;
use Bead\Validation\Rules\RequiredIf;
use Bead\Validation\Rules\RequiredUnless;
use Bead\Validation\Rules\RequiredWith;
use Bead\Validation\Rules\RequiredWithAll;
use Bead\Validation\Rules\RequiredWithout;
use Bead\Validation\Rules\RequiredWithoutAll;
use Bead\Validation\Rules\Same;
use Bead\Validation\Rules\Url;
use Exception;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use RuntimeException;
use TypeError;

/**
 * A class that validates datasets.
 *
 * A Validator contains a collection of rules for one or more fields in a dataset. When validate() is called, each
 * rule is applied to the appropriate value in the array of data. For any rule that does not pass, the error message for
 * that rule is collected. If all rules pass, validation is padded and validated() can be called to fetch the validated
 * data; otherwise a validation exception is thrown and the collected errors are available by calling errors(). The
 * errors are keyed by field.
 */
class Validator
{
    /** @var int state when validate() has yet to be called for the current data and rules. */
    private const StateNotValidated = 0;

    /** @var int state when validate() has been called but has not finished. */
    private const StateValidating = 1;

    /** @var int state when validate() has been completed with the current data and rules. */
    private const StateValidationDone = 2;

    /**
     * Aliases for rules that can be defined using strings.
     * @var array|string[]
     */
    private static array $s_ruleAliases = [
        "filled" => Filled::class,
        "present" => Present::class,
        "int" => Integer::class,
        "integer" => Integer::class,
        "number" => Number::class,
        "string" => IsString::class,
        "array" => IsArray::class,
        "bool" => IsBoolean::class,
        "true" => IsTrue::class,
        "false" => IsFalse::class,
        "alpha" => Alpha::class,
        "alphanumeric" => Alphanumeric::class,
        "date" => Date::class,
        "date-format" => DateFormat::class,
        "time-format" => DateFormat::class,
        "json" => Json::class,
        "min" => Min::class,
        "max" => Max::class,
        "between" => Between::class,
        "before" => Before::class,
        "after" => After::class,
        "equals" => EqualTo::class,
        "===" => EqualTo::class,
        "not-equal-to" => NotEqualTo::class,
        "!==" => NotEqualTo::class,
        "less-than" => LessThan::class,
        "lt" => LessThan::class,
        "less-than-or-equals" => LessThanOrEqual::class,
        "lte" => LessThanOrEqual::class,
        "greater-than" => GreaterThan::class,
        "gt" => GreaterThan::class,
        "greater-than-or-equals" => GreaterThanOrEqual::class,
        "gte" => GreaterThanOrEqual::class,
        "length" => Length::class,
        "min-length" => MinLength::class,
        "max-length" => MaxLength::class,
        "regex" => RegEx::class,
        "regexp" => RegEx::class,
        "required-if" => RequiredIf::class,
        "required-unless" => RequiredUnless::class,
        "required-with" => RequiredWith::class,
        "required-without" => RequiredWithout::class,
        "required-with-all" => RequiredWithAll::class,
        "required-without-all" => RequiredWithoutAll::class,
        "same" => Same::class,
        "different" => Different::class,
        "in" => In::class,
        "not-in" => NotIn::class,
        "email" => Email::class,
        "url" => Url::class,
        "ip" => Ip::class,
        "optional" => Optional::class,
    ];

    /** @var array The data to validate. */
    private array $m_originalData = [];

    /** @var array|null The validated data. */
    private ?array $m_validatedData = null;

    /**
     * @var array The rules that have been added to the validator.
     */
    private array $m_rules = [];

    /** @var int The validator state: validation not attempted, currently validating or validation complete. */
    private int $m_state = self::StateNotValidated;

    /**
     * Fields whose (remaining) rules should be skipped.
     * @var array
     */
    private array $m_skips = [];

    /**
     * Flag to indicate all (remaining) rules should be skipped.
     * @var bool
     */
    private bool $m_skipAll = false;

    /**
     * @var array The errors generated by the last call to check(), if any.
     */
    private array $m_errors = [];

    /**
     * Create a new Validator, optionally with a set of rules.
     *
     * Any rules provided must be an associative array keyed by field. Each element in the array must be either a single
     * rule or an array of rules for that field. Each rule can be supplied either as a Rule instance or as a string with
     * the rule's alias and arguments (e.g. "integer:0:10" for a rule requiring an int value between 0 and 10).
     *
     * @param array $data The data to validate.
     * @param array $rules The optional set of rules.
     */
    public function __construct(array $data, array $rules = [])
    {
        $this->setData($data);

        foreach ($rules as $field => $fieldRules) {
            if (!is_array($fieldRules)) {
                $fieldRules = [$fieldRules];
            }

            foreach ($fieldRules as $fieldRule) {
                $this->addRule($field, $fieldRule);
            }
        }
    }

    /**
     * Fetch the current state of the validator.
     *
     * The validator is always in one of three states:
     * - `self::StateNotValidated` the current data has not yet undergone validation according to the current ruleset.
     *   This can be because `validate()` hasn't been called yet or because the ruleset or data has changed since it
     *   was last called. This is the initial state.
     * - `self::StateValidating` validation is currently underway (i.e. `validate()` has been called but has not
     *   returned.)
     * - `self::StateValidationDone` the current data has undergone validation according to the current ruleset
     *   successfully or otherwise).
     * @return int The state.
     */
    protected function state(): int
    {
        return $this->m_state;
    }

    /**
     * Check whether the validator is currently validating the data.
     *
     * @return bool `true` if it is, `false` otherwise.
     */
    public function isValidating(): bool
    {
        return self::StateValidating == $this->state();
    }

    /**
     * Check whether the current rules have been used to validate the current data.
     *
     * @return bool `true` if the current data has been subjected to validation with the current rules, `false`
     * otherwise.
     */
    protected function hasValidated(): bool
    {
        return self::StateValidationDone == $this->state();
    }

    /**
     * Set the data to validate.
     *
     * Setting fresh data clears any previous errors and validated data.
     *
     * @param array $data The data to validate.
     */
    public function setData(array $data): void
    {
        assert(!$this->isValidating(), (
            8 <= PHP_MAJOR_VERSION
                ? new LogicException("Cannot set a validator's data while it's validating the data.")
                : "Cannot set a validator's data while it's validating the data."
        ));

        $this->clearErrors();
        $this->clearSkips();
        $this->clearValidated();
        $this->m_originalData = $data;
    }

    /**
     * Fetch the data under validation.
     *
     * @return array The data.
     */
    public function data(): array
    {
        return $this->m_originalData;
    }

    /**
     * Fetch the rules the Validator will use to validate the dataset.
     *
     * @param string|null $field The field whose rules are sought. Defaults to `null` to return all the rules, keyed by
     * field.
     *
     * @return array The rules (for the requested field), or `null` if the requested field is not under validation.
     */
    public function rules(?string $field = null): ?array
    {
        return (isset($field) ? ($this->m_rules[$field] ?? null) : $this->m_rules);
    }

    /**
     * Fetch the fields under validation.
     *
     * @return array<string> The fields for which the validator contains rules.
     */
    public function fieldsUnderValidation(): array
    {
        return array_keys($this->rules());
    }

    /**
     * Internal helper to add an error to the list.
     *
     * @param string $field The field that failed a validation rule.
     * @param string $message The error message to add.
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->m_errors[$field])) {
            $this->m_errors[$field] = [$message];
        } else {
            $this->m_errors[$field][] = $message;
        }
    }

    /**
     * Reset the list of errors (for a given field).
     *
     * If not field is specified, all errors are cleared.
     *
     * @param string|null $field The field to reset.
     */
    public function clearErrors(?string $field = null): void
    {
        if (isset($field)) {
            unset($this->m_errors[$field]);
        } else {
            $this->m_errors = [];
        }
    }

    /**
     * Tell the validator to skip any remaining rules (optionally for a given field).
     *
     * If no field is given, all the remaining rules for all fields are skipped.
     *
     * @param string|null $field The field whose rules should be skipped.
     */
    public function skipRemainingRules(?string $field = null): void
    {
        if (isset($field)) {
            $this->m_skips[] = $field;
        } else {
            $this->m_skipAll = true;
        }
    }

    /**
     * Clear the skip flags ready for a new iteration of the validator rules.
     */
    protected function clearSkips(): void
    {
        $this->m_skips = [];
        $this->m_skipAll = false;
    }

    /**
     * Validate the data.
     *
     * If the data does not pass an exception is thrown. After validating, errors() provides the error messages for the
     * rules that failed, keyed by field, while validated() will provide the validated data if the validation passed. If
     * validation passes errors() will return an empty array; if validation fails, validated() will throw.
     *
     * @throws ValidationException If the data does not pass validation.
     * @throws LogicException if called while validation is already taking place.
     */
    public function validate(): bool
    {
        assert(!$this->isValidating(), (
        8 <= PHP_MAJOR_VERSION
            ? new LogicException("Recursive call to Validator::validate()")
            : "Recursive call to Validator::validate()"
        ));
        $this->m_state = self::StateValidating;
        $this->clearErrors();
        $this->clearSkips();
        $this->clearValidated();
        $passes = true;

        // validated data contains only the data under validation - data for which there are no rules is not validated
        $fieldsUnderValidation = $this->fieldsUnderValidation();
        $validatedData = array_filter($this->data(), fn (string $key): bool => in_array($key, $fieldsUnderValidation), ARRAY_FILTER_USE_KEY);

        foreach ($this->rules() as $field => $rules) {
            if ($this->m_skipAll) {
                break;
            }

            // all rules always receive the original data so that rules that reference other fields in the data always
            // work with the original data. $validatedData will be updated with converted values for rules that
            // implement TypeConvertingRule that pass
            $fieldData = $this->data()[$field] ?? null;

            /** @var Rule $rule */
            foreach ($rules as $rule) {
                if (in_array($field, $this->m_skips) || $this->m_skipAll) {
                    break;
                }

                if ($rule instanceof ValidatorAwareRule) {
                    $rule->setValidator($this);
                }

                if ($rule instanceof DatasetAwareRule) {
                    $rule->setDataset($this->m_originalData);
                }

                if ($rule->passes($field, $fieldData)) {
                    if ($rule instanceof TypeConvertingRule) {
                        $validatedData[$field] = $rule->convert($fieldData);
                    }
                } else {
                    $passes = false;
                    $this->addError($field, $rule->message($field));
                }
            }
        }

        $this->m_state = self::StateValidationDone;

        if (!$passes) {
            throw new ValidationException($this, "The data failed validation.");
        }

        $this->setValidated($validatedData);
        return true;
    }

    /**
     * Check whether the data passes validation.
     *
     * @return bool true if the data passes, false otherwise.
     * @throws LogicException if called while validation is taking place.
     */
    public function passes(): bool
    {
        assert(!$this->isValidating(), (
        8 <= PHP_MAJOR_VERSION
            ? new LogicException("Can't call passes() while the validator is validating the data.")
            : "Can't call passes() while the validator is validating the data."
        ));
        if (!$this->hasValidated()) {
            try {
                $this->validate();
            } catch (ValidationException $err) {
                return false;
            }
        }

        return isset($this->m_validatedData);
    }

    /**
     * Check whether the validator fails.
     *
     * @return bool true if the original data fails validation, false if it passes.
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Set the validated data.
     *
     * Setting the validated data implies that the original data has passed validation.
     *
     * @param array $data The validated data.
     */
    protected function setValidated(array $data): void
    {
        $this->m_validatedData = $data;
    }

    /**
     * Clear the validated data.
     */
    protected function clearValidated(): void
    {
        $this->m_validatedData = null;
    }

    /**
     * Fetch the validated data.
     *
     * @return array<string, mixed> The validated data.
     *
     * @throws ValidationException if the data is not valid.
     * @throws LogicException if called while validation is taking place.
     */
    public function validated(): array
    {
        assert(!$this->isValidating(), (
        8 <= PHP_MAJOR_VERSION
            ? new LogicException("Can't call validated() while the validator is validating the data.")
            : "Can't call validated() while the validator is validating the data."
        ));

        if (!$this->hasValidated()) {
            $this->validate();
        }

        if (!isset($this->m_validatedData)) {
            throw new ValidationException($this, "The data failed validation.");
        }

        return $this->m_validatedData;
    }

    /**
     * If the validator has not passed, fetch the errors.
     *
     * The errors are keyed by field. There can be multiple errors per field. If the data has not yet been subjected to
     * validation, the error messages will be empty.
     *
     * @return array<string, array<string>> The messages.
     */
    public function errors(): array
    {
        return $this->m_errors;
    }

    /**
     * Takes the args extracted from a rule expressed as a string and converts them to the appropriate types for the
     * rule constructor, if possible.
     *
     * @param array $args
     * @param string $ruleClass
     *
     * @return array
     * @throws ArgumentCountError if there are not enough args to cover the non-optional constructor parameters
     * @throws InvalidArgumentException if an argument cannot be converted to the required type
     * @noinspection PhpDocMissingThrowsInspection ReflectionClass constructor won't throw.
     */
    private static function convertRuleConstructorArgs(array $args, string $ruleClass): array
    {
        /** @noinspection PhpUnhandledExceptionInspection internal helper - we know the clas name is valid. */
        $constructor = (new ReflectionClass($ruleClass))->getConstructor();

        if (!$constructor) {
            return $args;
        }

        $constructorParams = $constructor->getParameters();

        for ($idx = 0; $idx < count($constructorParams); ++$idx) {
            if (!$constructorParams[$idx]->hasType()) {
                continue;
            }

            if ($idx >= count($args)) {
                if (!$constructorParams[$idx]->isOptional()) {
                    throw new ArgumentCountError("Not enough arguments for the constructor for {$ruleClass}.");
                }

                break;
            }

            switch ($constructorParams[$idx]->getType()->getName()) {
                case "int":
                    $args[$idx] = filter_var($args[$idx], FILTER_VALIDATE_INT);

                    if (false === $args[$idx]) {
                        throw new InvalidArgumentException("The argument for the {$constructorParams[$idx]->getName()} parameter must be an int.");
                    }
                    break;

                case "float":
                case "double":
                    $args[$idx] = filter_var($args[$idx], FILTER_VALIDATE_FLOAT);

                    if (false === $args[$idx]) {
                        throw new InvalidArgumentException("The argument for the {$constructorParams[$idx]->getName()} parameter must be a float.");
                    }
                    break;

                case "bool":
                    $args[$idx] = filter_var($args[$idx], FILTER_VALIDATE_BOOLEAN, ["flags" => FILTER_NULL_ON_FAILURE,]);

                    if (!isset($args[$idx])) {
                        throw new InvalidArgumentException("The argument for the {$constructorParams[$idx]->getName()} parameter must be a bool.");
                    }
                    break;

                case "string":
                    break;

                case "array":
                    $args[$idx] = explode(",", $args[$idx]);
                    break;

                case "DateTime":
                    try {
                        $args[$idx] = new DateTime($args[$idx]);
                    } catch (Exception $err) {
                        throw new InvalidArgumentException("The argument for the {$constructorParams[$idx]->getName()} parameter is not a valid DateTime.", 0, $err);
                    }
                    break;

                default:
                    throw new InvalidArgumentException("The {$constructorParams[$idx]->getName()} parameter cannot be provided using a rule alias because its type is {$constructorParams[$idx]->getType()->getName()}.");
            }
        }

        return $args;
    }

    /**
     * Add a rule to the validator.
     *
     * @param string $field The field for which the rule applies.
     * @param Rule|string $rule The rule.
     *
     * @throws LogicException if called while the data is being validated.
     */
    public function addRule(string $field, $rule): void
    {
        assert(!$this->isValidating(), (
        8 <= PHP_MAJOR_VERSION
            ? new LogicException("Can't add rules while the validator is validating the data.")
            : "Can't add rules while the validator is validating the data."
        ));

        $this->clearValidated();
        $this->clearSkips();
        $this->clearErrors();

        if (is_string($rule)) {
            // extract args, delimited by unescaped : chars
            $args = preg_split("/(?<!\\\):/", $rule);
            $rule = array_shift($args);

            // unescape any escaped : chars in each arg
            array_walk($args, function (string & $arg): void {
                $arg = str_replace("\\:", ":", $arg);
            });

            if (!isset(self::$s_ruleAliases[$rule])) {
                throw new InvalidArgumentException("Validation rule {$rule} is not recognised.");
            }

            $ruleClass = self::$s_ruleAliases[$rule];

            if (!class_exists($ruleClass)) {
                throw new RuntimeException("Class {$ruleClass} for rule {$rule} does not exist.");
            }

            if (!is_subclass_of($ruleClass, Rule::class, true)) {
                throw new RuntimeException("Class {$ruleClass} for rule {$rule} does not implement the Rule interface.");
            }

            $rule = new $ruleClass(...self::convertRuleConstructorArgs($args, $ruleClass));
        } elseif (!($rule instanceof Rule)) {
            throw new TypeError("Argument 2 \$rule must be a string or a Rule object.");
        }

        if (!isset($this->m_rules[$field])) {
            $this->m_rules[$field] = [$rule,];
        } else {
            $this->m_rules[$field][] = $rule;
        }
    }

    /**
     * Register an alias for a rule.
     *
     * When an alias is registered, the rule can be referred to by its string alias. This can make building validators
     * easier and more readable. Using rules by their aliases you can provide a string for the rule definition to the
     * Validator rather than having to instantiate the rule. For example:
     *
     * ```php
     * new Validator(["quantity" => ["int", "min:1", "max:10",]]);
     * ```
     *
     * instead of
     *
     * ```php
     * use Bead\Validation\Rules\Integer;
     * new Validator(["quantity" => [new Integer(), new Min(1), new Max(10)],]);
     * ```
     *
     * Your code is marginally faster if you don't use aliases, but other than that there's no difference.
     *
     * @param string $ruleName The name for the rule (its alias).
     * @param string $ruleClass The class name of the Rule object it represents.
     */
    public static function registerRuleAlias(string $ruleName, string $ruleClass): void
    {
        if (isset(self::$s_ruleAliases[$ruleName])) {
            throw new InvalidArgumentException("The rule name {$ruleName} is already in use.");
        }

        self::$s_ruleAliases[$ruleName] = $ruleClass;
    }
}
