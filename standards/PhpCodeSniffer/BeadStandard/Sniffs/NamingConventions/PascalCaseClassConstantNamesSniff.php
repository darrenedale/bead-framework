<?php

declare(strict_types=1);

namespace BeadStandards\PhpCodeSniffer\BeadStandard\Sniffs\NamingConventions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Check that class constants use PascalCase naming convention.
 *
 * The convention is that all names should capitalise the first letter of each word, with the rest in lower case.
 * Acronyms should equally have only the first character in upper-case (e.g. Php not PHP).
 *
 * The sniff is imperfect as it's not possible to detect what a word actually is, and there remain some circumstances
 * where two capital letters in succession is valid (e.g. ThisIsAConstantName is valid). For this reason, it only
 * issues warnings.
 */
class PascalCaseClassConstantNamesSniff implements Sniff
{
    private const Pattern = "^([A-Z]|([A-Z][a-z]+)+)\$";

    private const RegExOptions = "d";

    private const TestInvalidName = null;

    public string $characterEncoding = "UTF-8";

    /**
     * The tokens the sniff is listening for.
     *
     * @return string[]
     */
    public function register(): array
    {
        return [
            T_CONST,
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

        // the next non-whitespace
        $tConstantName = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($tConstantName === false) {
            $phpcsFile->addError(
                "Expected constant name, found nothing",
                $stackPtr,
                "MissingClassConstantName"
            );

            return;
        }

        $constantName = $tokens[$tConstantName]["content"];
        $oldEncoding = mb_regex_encoding();
        mb_regex_encoding($this->characterEncoding);

        if (!mb_ereg_match(self::Pattern, $constantName, self::RegExOptions)) {
            $phpcsFile->addWarning(
                "Expected pascal-case constant name, found '%s'",
                $stackPtr,
                "PascalCaseClassConstantName",
                [$constantName]
            );
        }

        mb_regex_encoding($oldEncoding);
    }
}
