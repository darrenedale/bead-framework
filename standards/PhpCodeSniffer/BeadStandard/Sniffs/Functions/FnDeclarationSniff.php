<?php

declare(strict_types=1);

namespace BeadStandards\PhpCodeSniffer\BeadStandard\Sniffs\Functions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

use function str_repeat;
use function strlen;
use function strtolower;

class FnDeclarationSniff implements Sniff
{
    /**
     * The number of spaces code should be indented.
     *
     * @var integer
     */
    public $indent = 4;

    public $requiredSpacesAfterKeyword = 1;

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register(): array
    {
        return [T_FN,];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $this->requiredSpacesAfterKeyword = (int) $this->requiredSpacesAfterKeyword;
        $tokens = $phpcsFile->getTokens();

        if (
            isset($tokens[$stackPtr]["parenthesis_opener"]) === false
            || isset($tokens[$stackPtr]["parenthesis_closer"]) === false
            || $tokens[$stackPtr]["parenthesis_opener"] === null
            || $tokens[$stackPtr]["parenthesis_closer"] === null
        ) {
            return;
        }

        $openBracket  = $tokens[$stackPtr]["parenthesis_opener"];

        if (strtolower($tokens[$stackPtr]["content"]) === "fn") {
            // Must be one space after the FUNCTION keyword.
            if ($tokens[($stackPtr + 1)]["content"] === $phpcsFile->eolChar) {
                $spaces = "newline";
            } elseif ($tokens[($stackPtr + 1)]["code"] === T_WHITESPACE) {
                $spaces = $tokens[($stackPtr + 1)]["length"];
            } else {
                $spaces = 0;
            }

            if ($spaces !== $this->requiredSpacesAfterKeyword) {
                $fix = $phpcsFile->addFixableError(
                    "Expected %d %s after FN keyword; %d found",
                    $stackPtr,
                    "SpaceAfterFn",
                    [
                        $this->requiredSpacesAfterKeyword,
                        (1 === $this->requiredSpacesAfterKeyword ? "space" : "spaces"),
                        $spaces,
                    ]
                );

                if (true === $fix) {
                    $padding = str_repeat(" ", $this->requiredSpacesAfterKeyword);

                    if ($spaces === 0) {
                        $phpcsFile->fixer->addContent($stackPtr, $padding);
                    } else {
                        $phpcsFile->fixer->replaceToken(($stackPtr + 1), $padding);
                    }
                }
            }
        }

        if ($this->isMultiLineDeclaration($phpcsFile, $stackPtr, $openBracket, $tokens) === true) {
            $this->processMultiLineDeclaration($phpcsFile, $stackPtr, $tokens);
        } else {
            $this->processSingleLineDeclaration($phpcsFile, $stackPtr, $tokens);
        }
    }

    /**
     * Determine if this is a multi-line function declaration.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile   The file being scanned.
     * @param int                         $stackPtr    The position of the current token
     *                                                 in the stack passed in $tokens.
     * @param int                         $openBracket The position of the opening bracket
     *                                                 in the stack passed in $tokens.
     * @param array                       $tokens      The stack of tokens that make up
     *                                                 the file.
     *
     * @return bool
     */
    private function isMultiLineDeclaration($phpcsFile, $stackPtr, $openBracket, $tokens)
    {
        $closeBracket = $tokens[$openBracket]["parenthesis_closer"];
        if ($tokens[$openBracket]["line"] !== $tokens[$closeBracket]["line"]) {
            return true;
        }

        // might use the USE keyword and so be multi-line in this way.
        $use = $phpcsFile->findNext(T_USE, ($closeBracket + 1), $tokens[$stackPtr]["scope_opener"]);
        if ($use !== false) {
            // If the opening and closing parenthesis of the use statement
            // are also on the same line, this is a single line declaration.
            $open  = $phpcsFile->findNext(T_OPEN_PARENTHESIS, ($use + 1));
            $close = $tokens[$open]["parenthesis_closer"];
            if ($tokens[$open]["line"] !== $tokens[$close]["line"]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Processes single-line declarations.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param array                       $tokens    The stack of tokens that make up
     *                                               the file.
     *
     * @return void
     */
    private function processSingleLineDeclaration($phpcsFile, $stackPtr, $tokens): void
    {
        // TODO can't use the K&R or BSD sniffs because they expect braces and FNs don't have them
    }

    /**
     * Processes multi-line declarations.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param array                       $tokens    The stack of tokens that make up
     *                                               the file.
     *
     * @return void
     */
    private function processMultiLineDeclaration($phpcsFile, $stackPtr, $tokens): void
    {
        $this->processArgumentList($phpcsFile, $stackPtr, $this->indent);

        $closeBracket = $tokens[$stackPtr]["parenthesis_closer"];
        $use = $phpcsFile->findNext(T_USE, ($closeBracket + 1), $tokens[$stackPtr]["scope_opener"]);
        if ($use !== false) {
            $open         = $phpcsFile->findNext(T_OPEN_PARENTHESIS, ($use + 1));
            $closeBracket = $tokens[$open]["parenthesis_closer"];
        }

        // TODO examine the stack and look for the arrow (must be one space away from closing paren)

        if (!isset($tokens[$stackPtr]["scope_opener"])) {
            return;
        }

//        // The opening brace needs to be one space away from the closing parenthesis.
//        $opener = $tokens[$stackPtr]["scope_opener"];
//        if ($tokens[$opener]["line"] !== $tokens[$closeBracket]["line"]) {
//            $error = "The closing parenthesis and the opening brace of a multi-line function declaration must be on the same line";
//            $fix   = $phpcsFile->addFixableError($error, $opener, "NewlineBeforeOpenBrace");
//            if ($fix === true) {
//                $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($opener - 1), $closeBracket, true);
//                $phpcsFile->fixer->beginChangeset();
//                $phpcsFile->fixer->addContent($prev, " {");
//
//                // If the opener is on a line by itself, removing it will create
//                // an empty line, so just remove the entire line instead.
//                $prev = $phpcsFile->findPrevious(T_WHITESPACE, ($opener - 1), $closeBracket, true);
//                $next = $phpcsFile->findNext(T_WHITESPACE, ($opener + 1), null, true);
//
//                if (
//                    $tokens[$prev]["line"] < $tokens[$opener]["line"]
//                    && $tokens[$next]["line"] > $tokens[$opener]["line"]
//                ) {
//                    // Clear the whole line.
//                    for ($i = ($prev + 1); $i < $next; $i++) {
//                        if ($tokens[$i]["line"] === $tokens[$opener]["line"]) {
//                            $phpcsFile->fixer->replaceToken($i, "");
//                        }
//                    }
//                } else {
//                    // Just remove the opener.
//                    $phpcsFile->fixer->replaceToken($opener, "");
//                    if ($tokens[$next]["line"] === $tokens[$opener]["line"]) {
//                        $phpcsFile->fixer->replaceToken(($opener + 1), "");
//                    }
//                }
//
//                $phpcsFile->fixer->endChangeset();
//            }
//        } else {
//            $prev = $tokens[($opener - 1)];
//            if ($prev["code"] !== T_WHITESPACE) {
//                $length = 0;
//            } else {
//                $length = strlen($prev["content"]);
//            }
//
//            if ($length !== 1) {
//                $error = "There must be a single space between the closing parenthesis and the opening brace of a multi-line function declaration; found %d spaces";
//                $fix   = $phpcsFile->addFixableError($error, ($opener - 1), "SpaceBeforeOpenBrace", [$length]);
//                if ($fix === true) {
//                    if ($length === 0) {
//                        $phpcsFile->fixer->addContentBefore($opener, " ");
//                    } else {
//                        $phpcsFile->fixer->replaceToken(($opener - 1), " ");
//                    }
//                }
//
//                return;
//            }
//        }
    }

    /**
     * Processes multi-line argument list declarations.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     * @param int                         $indent    The number of spaces code should be indented.
     * @param string                      $type      The type of the token the brackets
     *                                               belong to.
     *
     * @return void
     */
    public function processArgumentList($phpcsFile, $stackPtr, $indent, $type = "function"): void
    {
        $tokens = $phpcsFile->getTokens();

        // We need to work out how far indented the function
        // declaration itself is, so we can work out how far to
        // indent parameters.
        $functionIndent = 0;
        for ($i = ($stackPtr - 1); $i >= 0; $i--) {
            if ($tokens[$i]["line"] !== $tokens[$stackPtr]["line"]) {
                break;
            }
        }

        // Move $i back to the line the function is or to 0.
        $i++;

        if ($tokens[$i]["code"] === T_WHITESPACE) {
            $functionIndent = $tokens[$i]["length"];
        }

        // The closing parenthesis must be on a new line, even
        // when checking abstract function definitions.
        $closeBracket = $tokens[$stackPtr]["parenthesis_closer"];
        $prev         = $phpcsFile->findPrevious(
            T_WHITESPACE,
            ($closeBracket - 1),
            null,
            true
        );

        if (
            $tokens[$closeBracket]["line"] !== $tokens[$tokens[$closeBracket]["parenthesis_opener"]]["line"]
            && $tokens[$prev]["line"] === $tokens[$closeBracket]["line"]
        ) {
            $error = "The closing parenthesis of a multi-line {$type} declaration must be on a new line";
            $fix   = $phpcsFile->addFixableError($error, $closeBracket, "CloseBracketLine");

            if ($fix === true) {
                $phpcsFile->fixer->addNewlineBefore($closeBracket);
            }
        }

        // If this is a closure and is using a USE statement, the closing
        // parenthesis we need to look at from now on is the closing parenthesis
        // of the USE statement.
        if ($tokens[$stackPtr]["code"] === T_CLOSURE) {
            $use = $phpcsFile->findNext(T_USE, ($closeBracket + 1), $tokens[$stackPtr]["scope_opener"]);

            if ($use !== false) {
                $open         = $phpcsFile->findNext(T_OPEN_PARENTHESIS, ($use + 1));
                $closeBracket = $tokens[$open]["parenthesis_closer"];

                $prev = $phpcsFile->findPrevious(
                    T_WHITESPACE,
                    ($closeBracket - 1),
                    null,
                    true
                );

                if (
                    $tokens[$closeBracket]["line"] !== $tokens[$tokens[$closeBracket]["parenthesis_opener"]]["line"]
                    && $tokens[$prev]["line"] === $tokens[$closeBracket]["line"]
                ) {
                    $error = "The closing parenthesis of a multi-line use declaration must be on a new line";
                    $fix   = $phpcsFile->addFixableError($error, $closeBracket, "UseCloseBracketLine");
                    if ($fix === true) {
                        $phpcsFile->fixer->addNewlineBefore($closeBracket);
                    }
                }
            }
        }

        // Each line between the parenthesis should be indented 4 spaces.
        $openBracket = $tokens[$stackPtr]["parenthesis_opener"];
        $lastLine    = $tokens[$openBracket]["line"];
        for ($i = ($openBracket + 1); $i < $closeBracket; $i++) {
            if ($tokens[$i]["line"] !== $lastLine) {
                if (
                    $i === $tokens[$stackPtr]["parenthesis_closer"]
                    || ($tokens[$i]["code"] === T_WHITESPACE
                        && (($i + 1) === $closeBracket
                            || ($i + 1) === $tokens[$stackPtr]["parenthesis_closer"]))
                ) {
                    // Closing braces need to be indented to the same level
                    // as the function.
                    $expectedIndent = $functionIndent;
                } else {
                    $expectedIndent = ($functionIndent + $indent);
                }

                // We changed lines, so this should be a whitespace indent token.
                $foundIndent = 0;

                if (
                    $tokens[$i]["code"] === T_WHITESPACE
                    && $tokens[$i]["line"] !== $tokens[($i + 1)]["line"]
                ) {
                    $error = "Blank lines are not allowed in a multi-line {$type} declaration";
                    $fix   = $phpcsFile->addFixableError($error, $i, "EmptyLine");
                    if ($fix === true) {
                        $phpcsFile->fixer->replaceToken($i, "");
                    }

                    // This is an empty line, so don't check the indent.
                    continue;
                } elseif ($tokens[$i]["code"] === T_WHITESPACE) {
                    $foundIndent = $tokens[$i]["length"];
                } elseif ($tokens[$i]["code"] === T_DOC_COMMENT_WHITESPACE) {
                    $foundIndent = $tokens[$i]["length"];
                    ++$expectedIndent;
                }

                if ($expectedIndent !== $foundIndent) {
                    $error = "Multi-line {$type} declaration not indented correctly; expected %s spaces but found %s";
                    $data  = [
                        $expectedIndent,
                        $foundIndent,
                    ];

                    $fix = $phpcsFile->addFixableError($error, $i, "Indent", $data);
                    if ($fix === true) {
                        $spaces = str_repeat(" ", $expectedIndent);
                        if ($foundIndent === 0) {
                            $phpcsFile->fixer->addContentBefore($i, $spaces);
                        } else {
                            $phpcsFile->fixer->replaceToken($i, $spaces);
                        }
                    }
                }

                $lastLine = $tokens[$i]["line"];
            }//end if

            if ($tokens[$i]["code"] === T_OPEN_PARENTHESIS && isset($tokens[$i]["parenthesis_closer"]) === true) {
                $prevNonEmpty = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($i - 1), null, true);
                if ($tokens[$prevNonEmpty]["code"] !== T_USE) {
                    // Since PHP 8.1, a default value can contain a class instantiation.
                    // Skip over these "function calls" as they have their own indentation rules.
                    $i        = $tokens[$i]["parenthesis_closer"];
                    $lastLine = $tokens[$i]["line"];
                    continue;
                }
            }

            if ($tokens[$i]["code"] === T_ARRAY || $tokens[$i]["code"] === T_OPEN_SHORT_ARRAY) {
                // Skip arrays as they have their own indentation rules.
                if ($tokens[$i]["code"] === T_OPEN_SHORT_ARRAY) {
                    $i = $tokens[$i]["bracket_closer"];
                } else {
                    $i = $tokens[$i]["parenthesis_closer"];
                }

                $lastLine = $tokens[$i]["line"];
                continue;
            }

            if ($tokens[$i]["code"] === T_ATTRIBUTE) {
                // Skip attributes as they have their own indentation rules.
                $i        = $tokens[$i]["attribute_closer"];
                $lastLine = $tokens[$i]["line"];
                continue;
            }
        }
    }
}
