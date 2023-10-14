<?php

/**
 * Checks that arguments in function declarations are spaced correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace BeadStandards\PhpCodeSniffer\BeadStandard\Sniffs\Functions;

use LogicException;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class FunctionDeclarationArgumentSpacingSniff implements Sniff
{

    /**
     * How many spaces should surround the equals signs.
     *
     * @var integer
     */
    public $equalsSpacing = 1;

    /**
     * How many spaces should follow the opening bracket.
     *
     * @var integer
     */
    public $requiredSpacesBeforeOpen = 0;

    /**
     * How many spaces should follow the opening bracket.
     *
     * @var integer
     */
    public $requiredSpacesAfterOpen = 0;

    /**
     * How many spaces should precede the closing bracket.
     *
     * @var integer
     */
    public $requiredSpacesBeforeClose = 0;

    /**
     * How many spaces should precede the closing bracket.
     *
     * @var integer
     */
    public $requiredSpacesAfterClose = 0;

    /**
     * How many spaces should follow the reference operator in by-reference parameter declarations.
     *
     * @var integer
     */
    public $requiredSpacesAfterReferenceOperator = 1;

    /**
     * How many spaces should follow the variadic operator in variadic parameter declarations.
     *
     * @var integer
     */
    public $requiredSpacesAfterVariadicOperator = 1;

    /**
     * @var array<string,array<string,int>>
     */
    private array $parenthesisSpacing = [
        "opening" => [
            "before" => 0,
            "after" => 0,
        ],
        "closing" => [
            "before" => 0,
            "after" => 0,
        ],
    ];

    private array $parameterDeclarationSpacing = [
        "reference" => 1,
        "variadic" => 1,
    ];

    /**
     * The tokens the sniff is listening for.
     *
     * @return string[]
     */
    public function register()
    {
        return [
            T_FUNCTION,
            T_CLOSURE,
            T_FN,
        ];
    }


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]["parenthesis_opener"]) === false
            || isset($tokens[$stackPtr]["parenthesis_closer"]) === false
            || $tokens[$stackPtr]["parenthesis_opener"] === null
            || $tokens[$stackPtr]["parenthesis_closer"] === null
        ) {
            return;
        }

        $this->equalsSpacing = (int) $this->equalsSpacing;
        $this->requiredSpacesAfterReferenceOperator = (int) $this->requiredSpacesAfterReferenceOperator;
        $this->requiredSpacesAfterVariadicOperator = (int) $this->requiredSpacesAfterVariadicOperator;

        $this->parenthesisSpacing = [
            "opening" => [
                "before" => (int) $this->requiredSpacesBeforeOpen,
                "after" => (int) $this->requiredSpacesAfterOpen,
            ],
            "closing" => [
                "before" => (int) $this->requiredSpacesBeforeClose,
                "after" => (int) $this->requiredSpacesAfterClose,
            ],
        ];

        $openingParenthesis = $tokens[$stackPtr]["parenthesis_opener"];
        $closingParenthesis = $tokens[$openingParenthesis]["parenthesis_closer"];
        $this->processParenthesisedList($phpcsFile, $openingParenthesis, $closingParenthesis);

        if ($tokens[$stackPtr]["code"] === T_CLOSURE) {
            $use = $phpcsFile->findNext(T_USE, ($tokens[$stackPtr]["parenthesis_closer"] + 1), $tokens[$stackPtr]["scope_opener"]);
            if ($use !== false) {
                $openBracket = $phpcsFile->findNext(T_OPEN_PARENTHESIS, ($use + 1), null);
                $this->processParenthesisedList($phpcsFile, $openBracket);
            }
        }
    }

    /**
     * Processes the contents of a single parenthesised list.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int $openingParenthesis The position of the opening parenthesis in the stack.
     */
    private function processParenthesisedList(File $phpcsFile, int $openingParenthesis, int $closingParenthesis): void
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$openingParenthesis]["parenthesis_owner"])) {
            $stackPtr = $tokens[$openingParenthesis]["parenthesis_owner"];
        } else {
            $stackPtr = $phpcsFile->findPrevious(T_USE, ($openingParenthesis - 1));
        }

        $params = $phpcsFile->getMethodParameters($stackPtr);

        if (empty($params)) {
            // Check spacing around opening parenthesis
            $next = $phpcsFile->findNext(T_WHITESPACE, ($openingParenthesis + 1), $closingParenthesis, true);

            if (false === $next) {
                if (1 !== $closingParenthesis - $openingParenthesis) {
                    if ($tokens[$openingParenthesis]["line"] !== $tokens[$closingParenthesis]["line"]) {
                        $found = "newline";
                    } else {
                        $found = $tokens[($openingParenthesis + 1)]["length"];
                    }

                    $fix = $phpcsFile->addFixableError(
                        "Expected 0 spaces between parenthesis of function declaration; %s found",
                        $openingParenthesis,
                        "SpacingBetween",
                        [$found]
                    );

                    if ($fix) {
                        $phpcsFile->fixer->replaceToken(($openingParenthesis + 1), "");
                    }
                }

                // no params, so no need to check spacing between them
                return;
            }
        }

        $this->processParameters($phpcsFile, $params, $openingParenthesis, $closingParenthesis);

        // only check spacing around closing parenthesis for single line definitions
        if (($tokens[$openingParenthesis]["line"] !== $tokens[$closingParenthesis]["line"])) {
            return;
        }

        $this->processParenthesis($phpcsFile, $closingParenthesis, "closing");
    }

    private function processParenthesis(File $phpcsFile, int $parenthesisStackPtr, string $parenthesisType): void
    {
        $tokens = $phpcsFile->getTokens();

        // check spacing before and after parenthesis ($offset is token ptr offset from $parenthesisStackPtr
        foreach (["before" => -1, "after" => 1,] as $where => $offset) {
            $gap = 0;

            if ($tokens[$parenthesisStackPtr + $offset]["code"] === T_WHITESPACE) {
                $gap = $tokens[$parenthesisStackPtr + $offset]["length"];
            }

            if ($gap !== $this->parenthesisSpacing[$parenthesisType][$where]) {
                $fix = $phpcsFile->addFixableError(
                    "Expected %d spaces %s %s parenthesis; %d found",
                    $parenthesisStackPtr,
                    "SpacingBefore{$parenthesisType}",
                    [
                        $this->parenthesisSpacing[$parenthesisType][$where],
                        $where,
                        $parenthesisType,
                        $gap,
                    ]
                );

                if ($fix) {
                    $padding = str_repeat(" ", $this->requiredSpacesBeforeClose);

                    if (0 === $gap) {
                        $phpcsFile->fixer->addContentBefore($parenthesisStackPtr, $padding);
                    } else {
                        $phpcsFile->fixer->replaceToken(($parenthesisStackPtr - 1), $padding);
                    }
                }
            }
        }
    }

    /**
     * The helper that actually does the work of checking the spacing around a parameter declaration part.
     *
     * The stack pointer offset must not be 0. If it's positive, the token immediately after the declaration component
     * will be checked; if it's negative the token immediately before is checked. The magnitute of the int is of no
     * significance - it's trimmed to +1 or -1 internally.
     *
     * @param File $file The file being scanned.
     * @param array $param The parameter data.
     * @param int $tokenStackPtr The token stack pointer of the declaration component (e.g. type, reference operator, variadic operator).
     * @param int $tokenStackPtrOffset A +ve int for checking the spacing after, -ve for before.
     * @param int $spacingRequired How much spacing there should be.
     * @param string $label How to refer to the component in an error notice.
     */
    private function processParameterDeclarationComponent(File $file, array $param, int $tokenStackPtr, int $tokenStackPtrOffset, int $spacingRequired, string $label): void
    {
        assert(0 !== $tokenStackPtrOffset, new LogicException("\$tokenStackPtrOffset must be positive or negative, not 0."));

        if (0 < $tokenStackPtrOffset) {
            $tokenStackPtrOffset = 1;
            $where = "after";
        } else {
            $tokenStackPtrOffset = -1;
            $where = "before";
        }

        $tokens = $file->getTokens();
        $spacingFound = 0;

        if ($tokens[$tokenStackPtr + $tokenStackPtrOffset]["code"] === T_WHITESPACE) {
            $spacingFound = $tokens[$tokenStackPtr + $tokenStackPtrOffset]["length"];
        }

        if ($spacingFound !== $spacingRequired) {
            $fix = $file->addFixableError(
                "Expected %d %s %s %s for argument '%s'; %d found",
                $tokenStackPtr,
                "Spacing" . mb_convert_case($where, MB_CASE_TITLE, "UTF-8") . str_replace(" ", "", mb_convert_case($label, MB_CASE_TITLE, "UTF-8")),
                [
                    $spacingRequired,
                    (1 === $spacingRequired ? "space" : "spaces"),
                    $where,
                    $label,
                    $param["name"],
                    $spacingFound,
                ]
            );

            if ($fix) {
                $padding = str_repeat(" ", $spacingRequired);

                if (0 === $spacingFound) {
                    // if we have to insert padding where none exists ...
                    if (0 > $tokenStackPtrOffset) {
                        // ... put it before the current token if we're looking before
                        $file->fixer->addContentBefore($tokenStackPtr, $padding);
                    } else {
                        // ... or put it before the next token if we're looking after
                        $file->fixer->addContentBefore($tokenStackPtr + 1, $padding);
                    }
                } else {
                    // replace the found whitespace with the correct amount
                    $file->fixer->replaceToken($tokenStackPtr + $tokenStackPtrOffset, $padding);
                }
            }
        }
    }

    /**
     * Helper to check the correct spacing after part of a parameter declaration.
     *
     * @param File $file The file being scanned.
     * @param array $param The parameter data.
     * @param int $tokenStackPtr The token stack pointer of the declaration component (e.g. type, reference operator, variadic operator).
     * @param int $spacingRequired How much spacing there should be.
     * @param string $label How to refer to the component in an error notice.
     */
    private function processParameterDeclarationComponentAfter(File $file, array $param, int $tokenStackPtr, int $spacingRequired, string $label): void
    {
        $this->processParameterDeclarationComponent($file, $param, $tokenStackPtr, 1, $spacingRequired, $label);
    }

    /**
     * Helper to check the correct spacing before part of a parameter declaration.
     *
     * @param File $file The file being scanned.
     * @param array $param The parameter data.
     * @param int $tokenStackPtr The token stack pointer of the declaration component (e.g. type, reference operator, variadic operator).
     * @param int $spacingRequired How much spacing there should be.
     * @param string $label How to refer to the component in an error notice.
     */
    private function processParameterDeclarationComponentBefore(File $file, array $param, int $tokenStackPtr, int $spacingRequired, string $label): void
    {
        $this->processParameterDeclarationComponent($file, $param, $tokenStackPtr, -1, $spacingRequired, $label);
    }

    /**
     * @param File $phpcsFile The file being scanned.
     * @param array $params The parameters.
     * @param int $parameterListStartPtr The token stack pointer for the opening parenthesis of the parameter list.
     * @param int $parameterListEndPtr The token stack pointer for the closing parenthesis of the parameter list.
     *
     * @throws \PHP_CodeSniffer\Exceptions\RuntimeException
     */
    private function processParameters(File $phpcsFile, array $params, int $parameterListStartPtr, int $parameterListEndPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        foreach ($params as $idx => $param) {
            // check spacing after reference operator
            if (true === $param["pass_by_reference"]) {
                $this->processParameterDeclarationComponentAfter($phpcsFile, $param, $param["reference_token"], $this->requiredSpacesAfterReferenceOperator, "reference operator");
            }

            // check spacing after variadic operator
            if (true === $param["variable_length"]) {
                $this->processParameterDeclarationComponentAfter($phpcsFile, $param, $param["variadic_token"], $this->requiredSpacesAfterReferenceOperator, "variadic operator");
            }

            // check spacing around = for default value
            if (isset($param["default_equal_token"])) {
                $this->processParameterDeclarationComponentBefore($phpcsFile, $param, $param["default_equal_token"], 1, "equals sign");
                $this->processParameterDeclarationComponentAfter($phpcsFile, $param, $param["default_equal_token"], 1, "equals sign");
            }

            // check spacing after type declaration
            if ($param["type_hint_token"] !== false) {
                $this->processParameterDeclarationComponentAfter($phpcsFile, $param, $param["type_hint_end_token"], 1, "type declaration");
            }

            $commaToken = false;

            if ($idx > 0 && $params[($idx - 1)]["comma_token"] !== false) {
                $commaToken = $params[($idx - 1)]["comma_token"];
            }

            if (false !== $commaToken) {
                if ($tokens[($commaToken - 1)]["code"] === T_WHITESPACE) {
                    $fix = $phpcsFile->addFixableError(
                        "Expected 0 spaces between argument '%s' and comma; %d found",
                        $commaToken,
                        "SpaceBeforeComma",
                        [
                            $params[($idx - 1)]["name"],
                            $tokens[($commaToken - 1)]["length"],
                        ]
                    );

                    if ($fix) {
                        $phpcsFile->fixer->replaceToken(($commaToken - 1), "");
                    }
                }

                // Don't check spacing after the comma if it is the last content on the line.
                $checkComma = true;

                if (($tokens[$parameterListStartPtr]["line"] !== $tokens[$parameterListEndPtr]["line"])) {
                    $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($commaToken + 1), $parameterListEndPtr, true);

                    if ($tokens[$next]["line"] !== $tokens[$commaToken]["line"]) {
                        $checkComma = false;
                    }
                }

                if ($checkComma) {
                    if ($param["type_hint_token"] === false) {
                        $spacesAfter = 0;

                        if ($tokens[($commaToken + 1)]["code"] === T_WHITESPACE) {
                            $spacesAfter = $tokens[($commaToken + 1)]["length"];
                        }

                        if ($spacesAfter === 0) {
                            $fix = $phpcsFile->addFixableError(
                                "Expected 1 space between comma and argument '%s'; 0 found",
                                $commaToken,
                                "NoSpaceBeforeArg",
                                [$param["name"]]
                            );

                            if ($fix) {
                                $phpcsFile->fixer->addContent($commaToken, " ");
                            }
                        } else if ($spacesAfter !== 1) {
                            $error = "Expected 1 space between comma and argument '%s'; %s found";
                            $data  = [
                                $param["name"],
                                $spacesAfter,
                            ];

                            $fix = $phpcsFile->addFixableError($error, $commaToken, "SpacingBeforeArg", $data);
                            if ($fix === true) {
                                $phpcsFile->fixer->replaceToken(($commaToken + 1), " ");
                            }
                        }
                    } else {
                        $hint = $phpcsFile->getTokensAsString($param["type_hint_token"], (($param["type_hint_end_token"] - $param["type_hint_token"]) + 1));

                        if ($param["nullable_type"] === true) {
                            $hint = "?{$hint}";
                        }

                        if ($tokens[($commaToken + 1)]["code"] !== T_WHITESPACE) {
                            $fix = $phpcsFile->addFixableError(
                                "Expected 1 space between comma and type hint '%s'; 0 found",
                                $commaToken,
                                "NoSpaceBeforeHint",
                                [$hint]
                            );

                            if ($fix) {
                                $phpcsFile->fixer->addContent($commaToken, " ");
                            }
                        } else {
                            $spacingFound = $tokens[($commaToken + 1)]["length"];

                            if (1 !== $spacingFound) {
                                $fix = $phpcsFile->addFixableError(
                                    "Expected 1 space between comma and type hint '%s'; %d found",
                                    $commaToken,
                                    "SpacingBeforeHint",
                                    [
                                        $hint,
                                        $spacingFound,
                                    ]
                                );

                                if ($fix === true) {
                                    $phpcsFile->fixer->replaceToken(($commaToken + 1), " ");
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
