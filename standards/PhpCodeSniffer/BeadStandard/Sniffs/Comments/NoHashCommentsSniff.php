<?php

declare(strict_types=1);

namespace BeadStandards\PhpCodeSniffer\BeadStandard\Sniffs\Comments;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class NoHashCommentsSniff implements Sniff
{

    /**
     * @inheritDoc
     */
    public function register(): array
    {
        return [
            T_COMMENT,
        ];
    }

    /**
     * @inheritDoc
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
