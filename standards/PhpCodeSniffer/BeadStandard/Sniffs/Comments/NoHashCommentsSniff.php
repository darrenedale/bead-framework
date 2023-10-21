<?php

declare(strict_types=1);

namespace BeadStandards\PhpCodeSniffer\BeadStandard\Sniffs\Comments;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Sniff to locate # comments, and fix them with // comments
 */
class NoHashCommentsSniff implements Sniff
{
    /**
     * The tokens the sniff is listening for.
     *
     * @return string[]
     */
    public function register(): array
    {
        return [
            T_COMMENT,
        ];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int $stackPtr  The position of the current token in the stack.
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if ("#" === $tokens[$stackPtr]["content"][0]) {
            $fix = $phpcsFile->addFixableError(
                "Don't use # comments, use // for line comments, /* ... */ for block comments",
                $stackPtr,
                "NoHashComments",
                []
            );

            if ($fix) {
                $content = substr($tokens[$stackPtr]["content"], 1);
                $phpcsFile->fixer->replaceToken($stackPtr, "//{$content}");
            }
        }
    }
}
