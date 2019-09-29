<?php

namespace Equit;

/**#
 * Class to help clean up directories based on various rules.
 */
class DirectoryCleaner {
    /**
     * Initialise a new cleaner.
     */
    public function __construct() {
        $this->m_defaultCallback = function(DirectoryIterator $item) {
            // check the file's basename is a match for a provided pattern - no patterns means don't check
            if(!$this->matchesPattern($item->getBasename())) {
                return false;
            }

            // check the file's ctime matches the ctime conditions
            if(!$this->matchesCTime($item->getCTime())) {
                return false;
            }

            // check the file's mtime matches the mtime conditions
            if(!$this->matchesMTime($item->getMTime())) {
                return false;
            }

            // check the file's atime matches the atime conditions
            if(!$this->matchesATime($item->getATime())) {
                return false;
            }

            // file meets all the conditions for deletion
            return true;
        };

        $this->m_callback = $this->m_defaultCallback;
    }

    /**
     * Provide a custom callback to determine what files are deletable.
     * 
     * Callable signature:
     * `function(DirectoryIterator $item): bool`
     * 
     * If the callback returns `true` the file is deletable; `false` means it should be kept.
     *
     * @param callable $fn The callable to use to determine whether any given file can be deleted.
     */
    public function setCallback(?callable $fn) {
        if(isset($fn)) {
            $this->m_callback = $fn;
            return;
        }

        $this->m_callback = $this->m_defaultCallback;
    }

    public function callback(): ?callable {
        return ($this->m_defaultCallback == $this->m_callback ? null $this->m_callback);
    }

    /**
     * Check what would be deleted by the cleaner in the given path.
     *
     * The check only applies the rules to the files it finds - it doesn't check whether the deletion would
     * actually succeed (e.g. because of lack of permissions, etc.).
     *
     * @param string $path The path to the directory to check.
     * 
     * @return array An array of strings, the full paths to every file that would be deleted.
     */
    public function check(string $path): array {
        $this->m_currentTime = time();
        $dirIter = new DirectoryIterator($path);
        $ret = [];

        // could just use filter()
        foreach($dirIter as $item) {
            if($this->m_callback($item)) {
                $ret[] = $item->getPathname();
            }
        }

        $this->m_currentTime = null;
        return $ret;
    }

    /**
     * Check what would be deleted by the cleaner in the given path.
     *
     * The check only applies the rules to the files it finds - it doesn't check whether the deletion would
     * actually succeed (e.g. because of lack of permissions, etc.).
     *
     * @param string $path The path to the directory to check.
     * 
     * @return array A tuple of two arrays: the full paths to the deleted files, and the full paths to the files that
     * should have been deleted but failed.
     */
    public function clean(string $path): array {
        $this->m_currentTime = time();
        $dirIter = new DirectoryIterator($path);
        $succeeded = [];
        $failed = [];

        // could just use filter()
        foreach($dirIter as $item) {
            if($this->m_callback($item)) {
                $path = $item->getPathname();

                if(unlink($path)) {
                    $succeeded[] = $path;
                }
                else {
                    $failed[] = $path;
                }
            }
        }

        $this->m_currentTime = null;
        return [$succeeded, $failed];
    }

    /**
     * Helper to check whether a given file basename matches one of the configured patterns.
     * 
     * @return bool
     */
    private function matchesPattern(string $baseName) {
        if(empty($this->m_patterns)) {
            return true;
        }

        foreach($this->m_patterns as $pattern) {
            if(preg_match("/{$pattern}/", $basename)) {
                return true;
            }
        }

        return false;
    }

    /// Helper to check a time against an age limit
    private function checkAge(?int $age, int $time): bool {
        return isset($age) && $this->checkDate($this->m_currentTime - $age, $time);
    }

    /// Helper to check a time against a threshold time
    private function checkDate(?int $threshold, int $time): bool {
        return isset($threshold) && $time < $threshold;
    }

    /// Helper to check a ctime agains the configured ctime limits (if any)
    private function matchesCTime(int $fileTime): bool {
        return $this->checkAge($this->m_ctimeAge, $fileTime) && $this->checkDate($this->m_ctimeAbsolute, $fileTime);
    }

    /// Helper to check an mtime agains the configured mtime limits (if any)
    private function matchesMTime(int $fileTime): bool {
        return $this->checkAge($this->m_mtimeAge, $fileTime) && $this->checkDate($this->m_mtimeAbsolute, $fileTime);
    }

    /// Helper to check an atime agains the configured atime limits (if any)
    private function matchesATime(int $fileTime): bool {
        return $this->checkAge($this->m_atimeAge, $fileTime) && $this->checkDate($this->m_atimeAbsolute, $fileTime);
    }

    /// While checking/deleting, stores the current time so that C/M/A-time checks use a consistent basis.
    private $m_currentTime;

    /// The default callback that implements the rules described by the patterns, access times, etc., in the following properties.
    private $m_defaultCallback;

    /// The callback to use to determine whether a file can be deleted. By default, this is set to the default callback but can be
    /// set to a custom callback to implement more complex rules.
    private $m_callback;

    /// File name must match one of these patterns if it is to be deleted.
    private $m_patterns = [];

    /// Operate recursively - check subdir entries as well as entries in the provided path.
    private $m_recursive;

    /// File must have been created at least this long ago if it is to be deleted.
    private $m_ctimeAge;

    /// File must have been created before this time if it is to be deleted.
    private $m_ctimeAbsolute;

    /// File must have been last updated at least this long ago if it is to be deleted.
    private $m_mtimeAge;

    /// File must have been last updated before this time if it is to be deleted.
    private $m_mtimeAbsolute;

    /// File must have been last accessed at least this long ago if it is to be deleted.
    private $m_atimeAge;

    /// File must have been last accessed before this time if it is to be deleted.
    private $m_atimeAbsolute;
}
