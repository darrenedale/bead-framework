<?php

declare(strict_types=1);

namespace BeadTests\Web;

use SplFileInfo;

/** A mock to use to replace SplFileInfo for UploadedFiletest. */
class MockSplFileInfo extends SplFileInfo
{
    public function __construct($filename)
    {
        parent::__construct($filename);
    }

    public function getSize(): int
    {
        if (UploadedFileTest::TempFileName === $this->getPathname()) {
            return UploadedFileTest::TempFileSize;
        }

        return parent::getSize();
    }
}
