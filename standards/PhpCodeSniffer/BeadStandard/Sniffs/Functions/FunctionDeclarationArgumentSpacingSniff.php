<?php

declare(strict_types=1);

namespace BeadStandards\PhpCodeSniffer\BeadStandard\Sniffs\Functions;

use Bead\Util\ScopeGuard;
use LogicException;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * PHPCS sniff to check the formatting of function declaration parameters.
 *
 * Both parameters and used variables from the parent scope are checked. Its default configuration is compatible with
 * PSR12, but all aspects of its checks can be configured in the XML file.
 */
class FunctionDeclarationArgumentSpacingSniff implements Sniff
{
    /** @var int When checking spacing, check before the token. */
    protected const LocationBefore = -1;

    /** @var int When checking spacing, check after the token. */
    protected const LocationAfter = 1;

    /**
     * How many spaces between the parentheses when there are no parameters.
     *
     * @var int
     */
    public $requiredSpacesInEmptyParameterList = 0;

    /**
     * How many spaces should follow the opening bracket.
     *
     * @var int
     */
    public $requiredSpacesAfterOpen = 0;

    /**
     * How many spaces should precede the closing bracket.
     *
     * @var int
     */
    public $requiredSpacesBeforeClose = 0;

    /**
     * How many spaces should follow the parameter type.
     *
     * @var int
     */
    public $requiredSpacesAfterType = 1;

    /**
     * How many spaces should follow the reference operator in by-reference parameter declarations.
     *
     * @var int
     */
    public $requiredSpacesAfterReferenceOperator = 0;

    /**
     * How many spaces should follow the variadic operator in variadic parameter declarations.
     *
     * @var int
     */
    public $requiredSpacesAfterVariadicOperator = 0;

    /**
     * How many spaces should precede the equals sign for default values.
     *
     * @var int
     */
    public $requiredSpacesBeforeEquals = 1;

    /**
     * How many spaces should follow the equals sign for default values.
     *
     * @var int
     */
    public $requiredSpacesAfterEquals = 1;

    /**
     * How many spaces should precede each comma in the parameter list.
     *
     * @var int
     */
    public $requiredSpacesBeforeComma = 0;

    /**
     * How many spaces should follow each comma in the parameter list.
     *
     * @var int
     */
    public $requiredSpacesAfterComma = 1;

    /** @var array|null Transient state, token storage while the sniff is processing a token. */
    private ?array $tokens = null;

    /** @var File|null Transient state, the file the sniff is currently processing. */
    private ?File $file = null;

    private function resetTransientState(): void
    {
        $this->tokens = null;
        $this->file = null;
    }

    /** Ensure all values that might have been updated from the XML config file are typed correctly. */
    private function sanitiseProperties(): void
    {
        $this->requiredSpacesBeforeEquals = (int) $this->requiredSpacesBeforeEquals;
        $this->requiredSpacesAfterEquals  = (int) $this->requiredSpacesAfterEquals;
        $this->requiredSpacesInEmptyParameterList = (int) $this->requiredSpacesInEmptyParameterList;
        $this->requiredSpacesAfterReferenceOperator = (int) $this->requiredSpacesAfterReferenceOperator;
        $this->requiredSpacesAfterVariadicOperator = (int) $this->requiredSpacesAfterVariadicOperator;
    }

    private function checkSpacing(int $stackPtr, int $location, int $spacingRequired, string $errorCode, string $errorMessage, array $errorParams): void
    {
        assert(self::LocationBefore === $location || self::LocationAfter === $location, new LogicException("\$location must be one of LocationBefore or LocationAfter class constants."));
        $spacingFound = 0;

        if ($this->tokens[$stackPtr + $location]["code"] === T_WHITESPACE) {
            $spacingFound = $this->tokens[$stackPtr + $location]["length"];
        }

        if ($spacingFound !== $spacingRequired) {
            foreach ($errorParams as & $errorParam) {
                $errorParam = match ($errorParam) {
                    "{required}" => $spacingRequired,
                    "{found}" => $spacingFound,
                    default => $errorParam,
                };
            }

            $shouldFix = $this->file->addFixableError($errorMessage, $stackPtr, $errorCode, $errorParams);

            if ($shouldFix) {
                $padding = str_repeat(" ", $spacingRequired);

                if (0 === $spacingFound) {
                    // if we have to insert padding where none exists ...
                    if (self::LocationBefore > $location) {
                        // ... put it before the current token if we're looking before
                        $this->file->fixer->addContentBefore($stackPtr, $padding);
                    } else {
                        // ... or put it before the next token if we're looking after
                        $this->file->fixer->addContentBefore($stackPtr + 1, $padding);
                    }
                } else {
                    // replace the found whitespace with the correct amount
                    $this->file->fixer->replaceToken($stackPtr + $location, $padding);
                }
            }
        }
    }

    private function checkSpacingBefore(int $stackPtr, int $spacingRequired, string $errorCode, string $errorMessage = "Require %d space(s) before token, found %d", array $errorParams = ["{required}", "{found}",]): void
    {
        $this->checkSpacing($stackPtr, self::LocationBefore, $spacingRequired, $errorCode, $errorMessage, $errorParams);
    }

    private function checkSpacingAfter(int $stackPtr, int $spacingRequired, string $errorCode, string $errorMessage = "Require %d space(s) after token, found %d", array $errorParams = ["{required}", "{found}",]): void
    {
        $this->checkSpacing($stackPtr, self::LocationAfter, $spacingRequired, $errorCode, $errorMessage, $errorParams);
    }

    private function findUseStatement(int $fromPtr, int $toPtr): ?int
    {
        $use = $this->file->findNext(T_USE, $this->tokens[$fromPtr], $this->tokens[$toPtr]);
        return (false === $use ? null : $use);
    }

    /**
     * Processes the contents of a single parenthesised list.
     *
     * @param int $openingParenthesis The position of the opening parenthesis in the stack.
     */
    private function checkParameterList(int $openingParenthesis, int $closingParenthesis): void
    {
        $ownerStackPtr = $this->tokens[$openingParenthesis]["parenthesis_owner"] ?? $this->file->findPrevious(T_USE, $openingParenthesis - 1);
        $params = $this->file->getMethodParameters($ownerStackPtr);

        if (empty($params)) {
            // check spacing between parentheses
            $this->checkSpacingAfter(
                $openingParenthesis,
                $this->requiredSpacesInEmptyParameterList,
                "SpacingBetween",
                "Require %d space(s) between parentheses of function declaration with no parameters; %d found",
                ["{required}", "{found}"]
            );

            // no params so no need to check their spacing
            return;
        }

        $this->checkSpacingAfter(
            $openingParenthesis,
            $this->requiredSpacesAfterOpen,
            "SpacingAfterOpen",
            "Require %d space(s) after the opening parenthesis of function declaration; %d found",
            ["{required}", "{found}"]
        );

        $this->checkParameters($params);

        // only check spacing before closing parenthesis for single-line declarations
        if (($this->tokens[$openingParenthesis]["line"] !== $this->tokens[$closingParenthesis]["line"])) {
            return;
        }

        $this->checkSpacingBefore(
            $closingParenthesis,
            $this->requiredSpacesBeforeClose,
            "SpacingBeforeClose",
            "Require %d space(s) before the closing parenthesis of function declaration; %d found",
            ["{required}", "{found}"]
        );
    }

    /**
     * @param array $params The parameters.
     *
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException
     */
    private function checkParameters(array $params): void
    {
        $previousParam =  null;

        foreach ($params as $param) {
            // check spacing after reference operator
            if (true === $param["pass_by_reference"]) {
                $this->checkSpacingAfter(
                    $param["reference_token"],
                    $this->requiredSpacesAfterReferenceOperator,
                    "SpacingAfterReference",
                    "Require %d space(s) after reference operator for parameter '%s', found %d",
                    ["{required}", $param["name"], "{found}",]
                );
            }

            // check spacing after variadic operator
            if (true === $param["variable_length"]) {
                $this->checkSpacingAfter(
                    $param["variadic_token"],
                    $this->requiredSpacesAfterVariadicOperator,
                    "SpacingAfterVariadic",
                    "Require %d space(s) after variadic operator for parameter '%s', found %d",
                    ["{required}", $param["name"], "{found}",]
                );
            }

            // check spacing around = for default value
            if (isset($param["default_equal_token"])) {
                $this->checkSpacingBefore(
                    $param["default_equal_token"],
                    $this->requiredSpacesBeforeEquals,
                    "SpacingBeforeDefaultEquals",
                    "Require %d space(s) before the equals sign for default value for parameter '%s', found %d",
                    ["{required}", $param["name"], "{found}",]
                );
                $this->checkSpacingAfter(
                    $param["default_equal_token"],
                    $this->requiredSpacesAfterEquals,
                    "SpacingAfterDefaultEquals",
                    "Require %d space(s) after the equals sign for default value for parameter '%s', found %d",
                    ["{required}", $param["name"], "{found}",]
                );
            }

            // check spacing after type declaration
            if ($param["type_hint_token"] !== false) {
                $this->checkSpacingAfter(
                    $param["type_hint_end_token"],
                    $this->requiredSpacesAfterType,
                    "SpacingAfterDefaultEquals",
                    "Require %d space(s) after the type declaration for parameter '%s', found %d",
                    ["{required}", $param["name"], "{found}",]
                );
            }

            if (isset($previousParam) && $previousParam["comma_token"] !== false) {
                $this->checkSpacingBefore(
                    $previousParam["comma_token"],
                    $this->requiredSpacesBeforeComma,
                    "SpacingBeforeComma",
                    "Require %d space(s) between parameter '%s' and comma; %d found",
                    ["{required}", $previousParam["name"], "{found}",]
                );

                // find the next token that isn't whitespace
                $next = $this->file->findNext(Tokens::$emptyTokens, $previousParam["comma_token"] + 1, null, true);

                // if it's on the same line, check the spacing after the comma
                if ($this->tokens[$next]["line"] === $this->tokens[$previousParam["comma_token"]]["line"]) {
                    $typeDeclaration = "";

                    if ($param["type_hint_token"]) {
                        $typeDeclaration =
                            " type declaration '"
                            . ($param["nullable_type"] ? "?" : "")
                            . $this->file->getTokensAsString($param["type_hint_token"], $param["type_hint_end_token"] - $param["type_hint_token"] + 1)
                            . "' for";
                    }

                    $this->checkSpacingAfter(
                        $previousParam["comma_token"],
                        $this->requiredSpacesAfterComma,
                        "SpacingAfterComma",
                        "Require %d space(s) between comma and%s parameter '%s', %d found",
                        ["{required}", $typeDeclaration, $param["name"], "{found}",]
                    );
                }
            }

            $previousParam = $param;
        }
    }

    /**
     * The tokens the sniff is listening for.
     *
     * @return string[]
     */
    public function register(): array
    {
        return [T_FUNCTION, T_CLOSURE, T_FN,];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int $stackPtr  The position of the current token in the stack.
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        assert(is_int($stackPtr), new LogicException("Invalid stack pointer provided to process()"));

        // ensure we always reset the state however we leave this method
        $guard = new ScopeGuard(fn () => $this->resetTransientState());
        $this->sanitiseProperties();

        $this->file = $phpcsFile;
        $this->tokens = $phpcsFile->getTokens();
        $openingParenthesis = $this->tokens[$stackPtr]["parenthesis_opener"];
        $closingParenthesis = $this->tokens[$openingParenthesis]["parenthesis_closer"];

        $this->checkParameterList($openingParenthesis, $closingParenthesis);

        if (
            (
                $this->tokens[$stackPtr]["code"] === T_CLOSURE
                || $this->tokens[$stackPtr]["code"] === T_FN
            )
            && is_int($use = $this->findUseStatement($this->tokens[$stackPtr]["parenthesis_closer"] + 1, $this->tokens[$stackPtr]["scope_opener"]))
        ) {
            $openingParenthesis = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $use + 1, null);
            $closingParenthesis = $this->tokens[$openingParenthesis]["parenthesis_closer"];
            $this->checkParameterList($openingParenthesis, $closingParenthesis);
        }
    }
}
