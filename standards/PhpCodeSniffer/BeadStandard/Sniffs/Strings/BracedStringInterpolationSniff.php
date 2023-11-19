<?php

declare(strict_types=1);

namespace BeadStandards\PhpCodeSniffer\BeadStandard\Sniffs\Strings;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Sniff to report and fix interpolated expressions in double-quoted string literals that aren't enclosed in braces.
 */
class BracedStringInterpolationSniff implements Sniff
{
    private const ValidSymbolFirstCharacters = [
        "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m",
        "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z",
        "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M",
        "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z",
        "\x80", "\x81", "\x82", "\x83", "\x84", "\x85", "\x86", "\x87",
        "\x88", "\x89", "\x8a", "\x8b", "\x8c", "\x8d", "\x8e", "\x8f",
        "\x90", "\x91", "\x92", "\x93", "\x94", "\x95", "\x96", "\x97",
        "\x98", "\x99", "\x9a", "\x9b", "\x9c", "\x9d", "\x9e", "\x9f",
        "\xa0", "\xa1", "\xa2", "\xa3", "\xa4", "\xa5", "\xa6", "\xa7",
        "\xa8", "\xa9", "\xaa", "\xab", "\xac", "\xad", "\xae", "\xaf",
        "\xb0", "\xb1", "\xb2", "\xb3", "\xb4", "\xb5", "\xb6", "\xb7",
        "\xb8", "\xb9", "\xba", "\xbb", "\xbc", "\xbd", "\xbe", "\xbf",
        "\xc0", "\xc1", "\xc2", "\xc3", "\xc4", "\xc5", "\xc6", "\xc7",
        "\xc8", "\xc9", "\xca", "\xcb", "\xcc", "\xcd", "\xce", "\xcf",
        "\xd0", "\xd1", "\xd2", "\xd3", "\xd4", "\xd5", "\xd6", "\xd7",
        "\xd8", "\xd9", "\xda", "\xdb", "\xdc", "\xdd", "\xde", "\xdf",
        "\xe0", "\xe1", "\xe2", "\xe3", "\xe4", "\xe5", "\xe6", "\xe7",
        "\xe8", "\xe9", "\xea", "\xeb", "\xec", "\xed", "\xee", "\xef",
        "\xf0", "\xf1", "\xf2", "\xf3", "\xf4", "\xf5", "\xf6", "\xf7",
        "\xf8", "\xf9", "\xfa", "\xfb", "\xfc", "\xfd", "\xfe", "\xff",
        "_",
    ];

    private const ValidSymbolCharacters = [
        ... self::ValidSymbolFirstCharacters,
        "0", "1", "2", "3", "4", "5", "6", "7", "8", "9",
    ];

    private const InvalidInterpolatedExpression = -1;

    /** @var File|null Transient state, the file the sniff is currently processing. */
    private ?File $file = null;

    /** @var File|null Transient state, the stack pointer of the token the sniff was asked to process. */
    private ?int $stackPtr =  null;

    /** Check whether a character is a valid character for the first in a PHP symbol name. */
    private static function isSymbolNameFirstCharacter(string $ch): bool
    {
        return in_array($ch, self::ValidSymbolFirstCharacters);
    }

    /** Check whether a character is a valid character a PHP symbol name. */
    private static function isSymbolNameCharacter(string $ch): bool
    {
        return in_array($ch, self::ValidSymbolCharacters);
    }

    /** Reset the internal state that tracks what we're currently processing. */
    private function resetTransientState(): void
    {
        $this->file = null;
        $this->stackPtr = null;
    }

    /** Read and return a symbol from a given point in the content we're processing. */
    private static function readSymbolName(string $str, int $from): ?string
    {
        $to = $from;

        if (!self::isSymbolNameFirstCharacter($str[$to] ?? "")) {
            return null;
        }

        while (self::isSymbolNameCharacter($str[++$to] ?? "")) {
        }

        return substr($str, $from, $to - $from);
    }

    /** Read an array subscript from a given point in the content we're processing. */
    private static function readArraySubscript(string $str, int $start): ?string
    {
        $prefix = "";

        if ("\$" === $str[$start]) {
            // array subscript is a variable
            $prefix = "\$";
            $index = self::readSymbolName($str, $start + 1);
        } else {
            // array subscript is a literal (must be an int)
            $end = $start;

            if ("-" === $str[$start]) {
                $prefix = "-";
                ++$start;
                ++$end;
            }

            while (ctype_digit($str[$end] ?? "")) {
                ++$end;
            }

            $index = substr($str, $start, $end - $start);
        }

        return (empty($index) ? null : "{$prefix}{$index}");
    }

    /**
     * Read an unbraced interpolated expression from a string.
     *
     * If the offset does not start an interpolated expression, 0 is returned.
     *
     * For clarity, the start index should be the index in the string of the $ character that introduces the (potential)
     * interpolated expression.
     *
     * @param string $str The string to read from
     * @param int $start The offset from which to start reading.
     *
     * @return int The length of the interpolated expression, including the leading $. 0 means it's not an interpolated
     * expression (i.e. it's just a solo $); negative means it's an invalid expression.
     */
    private static function readInterpolatedExpression(string $str, int $start): int
    {
        $end = $start + 1;
        $symbol = self::readSymbolName($str, $end);

        if (null === $symbol) {
            // just a solo $
            return 0;
        }

        $end += strlen($symbol);

        // you can have just a variable, or one object dereference, or one array subscript
        if ("->" === substr($str, $end, 2)) {
            // potential object dereference
            $symbol = self::readSymbolName($str, $end + 2);

            if (null !== $symbol) {
                $end += 2 + strlen($symbol);
            }
        } elseif ("[" === ($str[$end] ?? "")) {
            $subscript = self::readArraySubscript($str, $end + 1);

            if (null === $subscript) {
                // subscript is required
                return self::InvalidInterpolatedExpression;
            }

            $end += 2 + strlen($subscript);
        }

        return $end - $start;
    }

    /**
     * The tokens the sniff is listening for.
     *
     * @return string[]
     */
    public function register(): array
    {
        return [
            T_DOUBLE_QUOTED_STRING,
        ];
    }

    /**
     * Find the opening brace for an interpolated expression in a string.
     *
     * @param string $str The string to search.
     * @param int $backFrom The first character of the interpolated expression whose opening brace we're looking for.
     *
     * @return int The location of the opening brace, or null if none was found.
     */
    private static function findOpeningBrace(string $str, int $backFrom): ?int
    {
        // locate the first non-whitespace character that precedes the starting point
        while ($backFrom > 0 && ctype_space($str[--$backFrom])) {
        }

        // if it's a brace return its location, otherwise there's no opening brace for the specified location
        return ("{" !== $str[$backFrom] ? null : $backFrom);
    }

    /**
     * Find the closing brace that follows an interpolated expression in a string.
     *
     * @param string $str The string to search.
     * @param int $backFrom The index of a character in the interpolocated expression (typically its opening brace).
     *
     * @return int The location of the closing brace, or null if none was found.
     */
    private static function findClosingBrace(string $str, int $from): ?int
    {
        // locate the first non-whitespace character that precedes the starting point
        while ($from < (strlen($str) - 1) && "}" !== $str[++$from]) {
        }

        // if it's a brace return its location, otherwise there's no opening brace for the specified location
        return ("}" !== $str[$from] ? null : $from);
    }

    /**
     * Run a check over an interpolated expression.
     *
     * The provided string *might* be updated in-place, if fixing is in effect.
     *
     * @param string $content The content being processed.
     * @param int $idx The location of the start of the interpolated expression.
     *
     * @return int|null The length of the processed content, or null if an error was found.
     */
    private function checkUnbracedInterpolatedExpression(string & $content, int $idx): ?int
    {
        // found possible interpolated expression that's not brace-enclosed (could still be just a solo $)
        $length = self::readInterpolatedExpression($content, $idx);

        if (0 < $length) {
            $expression = substr($content, $idx, $length);

            $fix = $this->file->addFixableError(
                "Expression '%s' in double-quoted string should be enclosed with {} braces",
                $this->stackPtr,
                "UnbracedInterpolatedExpression",
                [$expression,]
            );

            if ($fix) {
                $content = substr($content, 0, $idx) . "{{$expression}}" . substr($content, $idx + strlen($expression));
                $this->file->fixer->replaceToken($this->stackPtr, $content);
                $idx += 2;
            }

            return $idx + $length;
        } elseif (0 === $length) {
            // just a solo $, nothing to do
            return $idx;
        }

        $this->file->addError(
            "Invalid interpolated expression at character %d in string.",
            $this->stackPtr,
            "InvalidInterpolatedExpression",
            [$idx,]
        );

        return null;
    }

    /**
     * Run a check over a braced interpolated expression.
     *
     * @param string $content The content being processed.
     * @param int $idx The location of the start of the braced interpolated expression.
     *
     * @return int|null The length of the processed content, or null if an error was found.
     */
    private function checkBracedInterpolatedExpression(string $content, int $idx): ?int
    {
        $closingBrace = self::findClosingBrace($content, $idx);

        if (null === $closingBrace) {
            $this->file->addError(
                "Unclosed braced interpolated expression at character %d in string.",
                $this->stackPtr,
                "UnclosedBracedInterpolatedExpression",
                [$idx,]
            );

            return null;
        }

        return $closingBrace;
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int $stackPtr  The position of the current token in the stack.
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $this->file = $phpcsFile;
        $this->stackPtr = $stackPtr;
        $tokens = $this->file->getTokens();

        // track how many fixes have been applied so that subsequent fixes can accommodate the inserted {} braces
        $content = $tokens[$stackPtr]["content"];

        for ($idx = 0; $idx < strlen($content); ++$idx) {
            $ch = $content[$idx];

            if ("\\" === $ch) {
                // ignore anything escaped - it's not an interpolated expression
                ++$idx;
                continue;
            }

            if ("\$" === $ch) {
                $openingBrace = self::findOpeningBrace($content, $idx);

                if (null === $openingBrace) {
                    $idx = $this->checkUnbracedInterpolatedExpression($content, $idx);
                } else {
                    $idx = $this->checkBracedInterpolatedExpression($content, $idx);
                }

                if (null === $idx) {
                    break;
                }
            }
        }

        $this->resetTransientState();
    }
}
