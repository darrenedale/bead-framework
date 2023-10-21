<?php

declare(strict_types=1);

namespace BeadStandards\PhpCodeSniffer\BeadStandard\Sniffs\Strings;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Sniff to report and fix string literals that use single-quotes instead of double-quotes.
 */
class StringLiteralQuoteTypeSniff implements Sniff
{
    private const ActionNone = 0;

    private const ActionEscape = 1;

    private const ActionUnescape = -1;

    /**
     * Convert a single-quoted string to its double-quoted equivalent.
     *
     * The string provided must contain only the content of the string literal - the opening and closing single quotes
     * must not be present.
     *
     * @param string $str The string to convert.
     *
     * @return string The double-quoted equivalent of the provided single-quoted string content.
     */
    private static function singleToDoubleQuotedString(string $str): string
    {
        for ($idx = 0; $idx < strlen($str); ++$idx) {
            $ch = $str[$idx];
            $action = self::ActionNone;

            if ("\\" === $ch) {
                // peek forward and see what this backslash means
                switch ($str[$idx + 1] ?? "") {
                    // if it's an escaped single-quote, un-escape it
                    case "'":
                        $action = self::ActionUnescape;
                        break;

                    // if it's an escaped backslash, leave it alone (and skip the escaped backslash we've peeked at)
                    case "\\":
                        ++$idx;
                        break;

                    // if it's a backslash not escaping anything it's a literal backslash, so escape it
                    default:
                        $action = self::ActionEscape;
                        break;
                }
            } elseif ("\"" === $ch) {
                // an unescaped double-quote, escape it
                $action = self::ActionEscape;
            }

            switch ($action) {
                case self::ActionEscape:
                    $str = substr($str, 0, $idx) . "\\" . substr($str, $idx);
                    ++$idx;     // we don't want to look at it again
                    break;

                case self::ActionUnescape:
                    $str = substr($str, 0, $idx) . substr($str, $idx + 1);
                    /*
                     * NOTE if we're unescaping, we know that the next char is valid on its own, so no need to decrement
                     * $idx and look at it again
                     */
                    break;
            }
        }

        return $str;
    }

    /**
     * The tokens the sniff is listening for.
     *
     * @return string[]
     */
    public function register(): array
    {
        return [
            T_CONSTANT_ENCAPSED_STRING,
        ];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int $stackPtr  The position of the current token in the stack.
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $quoteType = $tokens[$stackPtr]["content"][0] ?? null;

        if (null === $quoteType) {
            // unexpected token
            return;
        }

        if ("'" === $quoteType) {
            $fix = $phpcsFile->addFixableError(
                "Expecting string literal enclosed with double-quotes [\"], found single-quotes [']",
                $stackPtr,
                "StringSingleQuoted"
            );

            if ($fix) {
                $content = self::singleToDoubleQuotedString(substr($tokens[$stackPtr]["content"], 1, -1));
                $phpcsFile->fixer->replaceToken($stackPtr, "\"{$content}\"");
            }
        }
    }
}
