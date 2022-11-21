<?php

namespace Bead\Session\Handlers;

use Bead\Application;
use Bead\AppLog;
use Bead\Contracts\SessionHandler;
use Bead\Exceptions\Session\InvalidSessionDirectoryException;
use Bead\Exceptions\Session\InvalidSessionFileException;
use Bead\Exceptions\Session\SessionDestroyedException;
use Bead\Exceptions\Session\SessionFileSaveException;
use Bead\Exceptions\Session\SessionNotFoundException;
use DirectoryIterator;
use Exception;
use SplFileInfo;

/**
 * Session handler that uses files to store session data persistently.
 */
class File implements SessionHandler
{
    /** @var string Default session storage location, relative to application root directory. */
    private const DefaultSessionDirectory = "data/session";

    /** @var string The ID of the session. */
    private string $m_id;

    /** @var int The timestamp of the session's initial creation. */
    private int $m_createdAt;

    /** @var int The timestamp of the session's last use.  */
    private int $m_lastUsedAt;

    /** @var int The timestamp at which the session's current ID was generated. */
    private int $m_idCreatedAt;

    /** @var int|null The timestamp at which the session's ID was regenerated, if it has been. */
    private ?int $m_idExpiredAt = null;

    /** @var string|null The ID with which the session's (now old) ID was replaced. */
    private ?string $m_replacementId = null;

    /** @var array The session data. */
    private array $m_data = [];

    /** @var bool Flag indicating that the session has been destroyed. */
    private bool $m_destroyed = false;

    /**
     * @inheritDoc
     *
     * @param string|null $id The ID of the session to load.
     *
     * @throws InvalidSessionDirectoryException if the configured session directory is not valid.
     * @throws SessionNotFoundException if the session file for the given ID is not accessible.
     * @throws InvalidSessionFileException if the session file contains invalid content.
     * @throws SessionFileSaveException if the session requires ID regeneration and the new session file can't be
     * written.
     * @noinspection PhpDocMissingThrowsInspection Can't throw SessionDestroyedException.
     */
    public function __construct(?string $id = null)
    {
        if (!isset($id)) {
            $this->m_id = self::createId();
            $this->m_createdAt = $this->m_idCreatedAt = $this->m_lastUsedAt = time();
            /** @noinspection PhpUnhandledExceptionInspection Can't throw SessionDestroyedException. */
            $this->commit();
        } else {
            $info = new SplFileInfo(self::sessionDirectory() . "/{$id}");

            if (!$info->isFile()) {
                throw new SessionNotFoundException($id, "The session file for {$id} does not exist or is not a file.");
            }

            if ($info->isLink()) {
                throw new SessionNotFoundException($id, "The session file for {$id} is a link - links are not supported for security.");
            }

            if (!$info->isReadable()) {
                throw new SessionNotFoundException($id, "The session file for {$id} is not readable.");
            }

            $this->m_id = $id;
            /** @noinspection PhpUnhandledExceptionInspection Can't throw SessionDestroyedException. */
            $this->reload();
        }
    }

    /**
     * Commit the session data to disk on destruction.
     *
     * @throws InvalidSessionDirectoryException if the configured session directory is not valid.
     * @throws SessionFileSaveException if the session cannot be committed either to the file for the old ID or the
     * file for the new ID.
     * @noinspection PhpDocMissingThrowsInspection Can't throw SessionDestroyedException.
     */
    public function __destruct()
    {
        if (!$this->m_destroyed) {
            /** @noinspection PhpUnhandledExceptionInspection Can't throw SessionDestroyedException. */
            $this->commit();
        }
    }

    /**
     * Check whether a configured session directory is valid.
     *
     * @param string $dir The directory to check.
     *
     * @return bool `true` if the directory is valid, `false` if not.
     */
    protected static final function isValidSessionDirectory(string $dir): bool
    {
        return false === strpos($dir, ".");
    }

    /**
     * Fetch the configured session file storage directory.
     *
     * @return string The directory.
     * @throws InvalidSessionDirectoryException if the configured session directory is not valid.
     */
    protected static function sessionDirectory(): string
    {
        $dir = Application::instance()->config("session.handlers.file.directory", self::DefaultSessionDirectory);

        if (!self::isValidSessionDirectory($dir)) {
            throw new InvalidSessionDirectoryException($dir);
        }

        return Application::instance()->rootDir() . DIRECTORY_SEPARATOR . $dir;
    }

    /**
     * Create a new session ID.
     *
     * The session ID is guaranteed not to clash with an existing session and the session file for the ID is guaranteed
     * to be present but empty.
     *
     * @return string The ID.
     * @throws InvalidSessionDirectoryException if the configured session directory is not valid.
     */
    protected static function createId(): string
    {
        $dir = self::sessionDirectory();

        do {
            $id = randomString(64);
        } while (file_exists("{$dir}/{$id}"));

        touch("{$dir}/{$id}");
        return $id;
    }

    /**
     * Helper to ensure that IDs stored in session files haven't been tampered with.
     *
     * @param string $id
     *
     * @return bool `true` if the ID is valid, `false` if not.
     */
    protected static function isValidId(string $id): bool
    {
        return 64 === strlen($id) && 64 === strspn($id, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_");
    }

    /**
     * Helper to throw an exception when an attempt is made to use the session after it has been destroyed.
     * @throws SessionDestroyedException if the session has been destroyed.
     */
    protected function throwIfDestroyed(): void
    {
        if ($this->m_destroyed) {
            throw new SessionDestroyedException($this->m_id, "The session {$this->m_id} has been destroyed and cannot be used.");
        }
    }

    /**
     * @inheritDoc
     * @throws SessionDestroyedException if the session has been destroyed.
     */
    public function id(): string
    {
        $this->throwIfDestroyed();
        return $this->m_id;
    }

    /** @inheritDoc */
    public function createdAt(): int
    {
        return $this->m_createdAt;
    }

    /** @inheritDoc */
    public function idGeneratedAt(): int
    {
        return $this->m_idCreatedAt;
    }

    /** @inheritDoc */
    public function idExpiredAt(): ?int
    {
        return $this->m_idExpiredAt;
    }

    /** @inheritDoc */
    public function lastUsedAt(): int
    {
        return $this->m_lastUsedAt;
    }

    /** @inheritDoc */
    public function touch(?int $time = null): void
    {
        $this->m_lastUsedAt = $time ?? time();
    }

    /**
     * @inheritDoc
     * @throws SessionDestroyedException if the session has been destroyed.
     */
    public function get(string $key)
    {
        $this->throwIfDestroyed();
        return $this->m_data[$key] ?? null;
    }

    /**
     * @inheritDoc
     * @throws SessionDestroyedException if the session has been destroyed.
     */
    public function all(): array
    {
    $this->throwIfDestroyed();
        return $this->m_data;
    }

    /**
     * @inheritDoc
     * @throws SessionDestroyedException if the session has been destroyed.
     */
    public function set(string $key, $data)
    {
        $this->throwIfDestroyed();
        $this->m_data[$key] = $data;
    }

    /**
     * @inheritDoc
     * @throws SessionDestroyedException if the session has been destroyed.
     */
    public function remove(string $key): void
    {
        $this->throwIfDestroyed();
        unset($this->m_data[$key]);
    }

    /**
     * @inheritDoc
     * @throws SessionDestroyedException
     */
    public function clear(): void
    {
        $this->throwIfDestroyed();
        $this->m_data = [];
    }

    /**
     * @inheritDoc
     *
     * Regenerating the ID with this session handler commits the session.
     * @throws InvalidSessionDirectoryException if the storage directory configured for session data is not usable.
     * @throws SessionDestroyedException if the session has been destroyed
     * @throws SessionFileSaveException if the session cannot be committed either to the file for the old ID or the
     * file for the new ID.
     */
    public function regenerateId(): string
    {
        $this->throwIfDestroyed();
        $newId = self::createId();
        $this->m_idExpiredAt = time();
        $this->m_replacementId = $newId;
        $this->commit();
        $this->m_id = $newId;
        $this->m_idExpiredAt = null;
        $this->m_idCreatedAt = time();
        $this->commit();
        return $newId;
    }

    /**
     * @inheritDoc
     * @throws SessionDestroyedException if the session has been destroyed.
     */
    public function idHasExpired(): bool
    {
        $this->throwIfDestroyed();
        return isset($this->m_expiredAt);
    }

    /**
     * @inheritDoc
     * @throws SessionDestroyedException if the session has been destroyed.
     */
    public function replacementId(): ?string
    {
        $this->throwIfDestroyed();
        return ($this->idHasExpired() ? $this->m_replacementId : null);
    }

    /**
     * @inheritDoc
     * @throws InvalidSessionDirectoryException if the configured storage directory for session files is not valid.
     * @throws SessionFileSaveException if the session file cannot be written.
     * @throws SessionDestroyedException if the session has been destroyed.
     */
    public function commit(): void
    {
        $this->throwIfDestroyed();
        $this->m_lastUsedAt = time();

        if (false === file_put_contents(self::sessionDirectory() . "/{$this->id()}",
            serialize([
                "created_at" => $this->m_createdAt,
                "last_used_at" => $this->m_lastUsedAt,
                "id_created_at" => $this->m_idCreatedAt,
                "id_expired_at" => $this->m_idExpiredAt,
                "replacement_id" => $this->m_replacementId,
                "data" => $this->m_data,
            ]))) {
            throw new SessionFileSaveException(self::sessionDirectory() . "/{$this->id()}", "Failed to commit the session to the file '" . self::sessionDirectory() . "/{$this->id()}'");
        }
    }

    /**
     * @inheritDoc
     * @throws InvalidSessionDirectoryException if the configured storage directory for session files is not valid.
     * @throws InvalidSessionFileException if a problem is found with the data stored in the session file.
     * @throws SessionDestroyedException if the session has been destroyed.
     */
    public function reload(): void
    {
        $this->throwIfDestroyed();
        $session = unserialize(file_get_contents(self::sessionDirectory() . "/{$this->id()}"));

        if (!is_int($session["created_at"] ?? null)) {
            throw new InvalidSessionFileException(self::sessionDirectory() . "/{$this->id()}", "The session file '" . self::sessionDirectory() . "/{$this->id()}' contains an invalid created-at timestamp.");
        }

        if (!is_int($session["last_used_at"] ?? null)) {
            throw new InvalidSessionFileException(self::sessionDirectory() . "/{$this->id()}", "The session file '" . self::sessionDirectory() . "/{$this->id()}' contains an invalid last-used-at timestamp.");
        }

        if (!is_int($session["id_created_at"] ?? null)) {
            throw new InvalidSessionFileException(self::sessionDirectory() . "/{$this->id()}", "The session file '" . self::sessionDirectory() . "/{$this->id()}' contains an invalid id-created-at timestamp.");
        }

        if (isset($session["id_expired_at"]) && !is_int($session["id_expired_at"])) {
            throw new InvalidSessionFileException(self::sessionDirectory() . "/{$this->id()}", "The session file '" . self::sessionDirectory() . "/{$this->id()}' contains an invalid expired-at timestamp.");
        }

        if (isset($session["replacement_id"]) && !self::isValidId($session["replacement_id"])) {
            throw new InvalidSessionFileException(self::sessionDirectory() . "/{$this->id()}", "The session file '" . self::sessionDirectory() . "/{$this->id()}' contains an invalid replacement ID.");
        }

        if (!is_array($session["data"] ?? null)) {
            throw new InvalidSessionFileException(self::sessionDirectory() . "/{$this->id()}", "The session file '" . self::sessionDirectory() . "/{$this->id()}' contains an invalid data array.");
        }

        $this->m_createdAt = $session["created_at"];
        $this->m_lastUsedAt = $session["last_used_at"];
        $this->m_idCreatedAt = $session["id_created_at"];
        $this->m_idExpiredAt = $session["id_expired_at"];
        $this->m_replacementId = $session["replacement_id"];
        $this->m_data = $session["data"];
        $this->m_destroyed = false;
    }

    /**
     * @inheritDoc
     * @throws InvalidSessionDirectoryException if the configured storage directory for session files is not valid.
     * @throws SessionDestroyedException if the session has been destroyed.
     */
    public function destroy(): void
    {
        $this->throwIfDestroyed();
        unlink(self::sessionDirectory() . "/{$this->id()}");
        $this->m_destroyed = true;
        $this->m_data = [];
        $this->m_createdAt = 0;
        $this->m_idExpiredAt = null;
    }

    /**
     * Check whether a session can be purged.
     *
     * A session can be purged if it expired more than 3 minutes ago.
     *
     * @return bool `true` if it can be purged, `false` otherwise.
     */
    protected function canBePurged(): bool
    {
        $now = time();
        return $this->m_lastUsedAt < ($now - Session::sessionIdleTimeoutPeriod()) ||
            (isset($this->m_idExpiredAt) && $this->m_idExpiredAt < $now - Session::expiredSessionGracePeriod());
    }

    /**
     * @inheritDoc
     * @throws InvalidSessionDirectoryException if the configured storage directory for session files is not valid.
     */
    public static function prune(): void
    {
        foreach (new DirectoryIterator(self::sessionDirectory()) as $file) {
            if ($file->isDot()) {
                continue;
            }

            if (!$file->isFile() || !$file->isReadable()) {
                AppLog::warning("Session directory entry {$file->getRealPath()} is not a file or is not readable when purging session directory.");
                continue;
            }

            try {
                $session = new static($file->getFilename());

                if ($session->canBePurged()) {
                    $session->destroy();
                }
            } catch (Exception $err) {
                AppLog::error("Exception reading session file {$file->getRealPath()} when purging session directory: {$err->getMessage()}");
            }
        }
    }
}
