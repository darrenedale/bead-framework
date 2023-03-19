<?php

namespace Bead\Session;

use InvalidArgumentException;
use TypeError;
use function Bead\Helpers\Iterable\all;

/**
 * Data accessor for session data that delegates to a parent accessor with all keys prefixed.
 *
 * TODO unit test
 */
class PrefixedAccessor implements DataAccessor
{
    use CanPrefix;
    use ImplementsArrayAccess;

    /** @var string The prefix to apply to all keys. */
    private string $m_prefix;

    /** @var DataAccessor The accessor to which to delegate. */
    private DataAccessor $m_parent;

    /**
     * Initialise a new prefixed accesor.
     *
     * @param string $prefix The prefix to apply to all keys.
     * @param DataAccessor $parent The accessor to delegate to.
     */
    public function __construct(string $prefix, DataAccessor $parent)
    {
        $this->m_prefix = $prefix;
        $this->m_parent = $parent;
    }

    /**
     * Helper to apply the prefix to a key.
     *
     * @param string $key The key to prefix.
     *
     * @return string The prefixed key.
     */
    protected function prefixedKey(string $key): string
    {
        return "{$this->m_prefix}{$key}";
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return $this->m_parent->has($this->prefixedKey($key));
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, $default = null)
    {
        return $this->m_parent->get($this->prefixedKey($key), $default);
    }

    /**
     * @inheritDoc
     */
    public function extract($keys)
    {
        if (is_string($keys)) {
            return $this->m_parent->extract($this->prefixedKey($keys));
        }

        if (!is_array($keys)) {
            throw new TypeError("Parameter \$keys expects a string or array of strings, " . gettype($keys) . " given.");
        }

        if (!all($keys, "is_string")) {
            throw new InvalidArgumentException("Keys for session data must be strings.");
        }

        array_walk($keys, function(string & $key): void {
            $key = $this->prefixedKey($key);
        });

        return $this->m_parent->extract($keys);
    }

    /**
     * @inheritDoc
     */
    public function set($keyOrData, $data = null): void
    {
        if (is_string($keyOrData)) {
            $this->m_parent->set($this->prefixedKey($keyOrData), $data);
            return;
        }

        if (!is_array($keyOrData)) {
            throw new TypeError("Parameter \$keys expects a string or an array of strings.");
        }

        foreach ($keyOrData as $key => $data) {
            $this->m_parent->set($this->prefixedKey($key), $data);
        }
    }

    /**
     * @inheritDoc
     */
    public function push(string $key, $data): void
    {
        $this->m_parent->push($this->prefixedKey($key), $data);
    }

    /**
     * @inheritDoc
     */
    public function pushAll(string $key, array $data): void
    {
        $this->m_parent->pushAll($this->prefixedKey($key), $data);
    }

    /**
     * @inheritDoc
     */
    public function pop(string $key, int $n = 1)
    {
        return $this->m_parent->pop($this->prefixedKey($key), $n);
    }

    /**
     * @inheritDoc
     */
    public function transientSet($keyOrData, $data = null): void
    {
        if (is_string($keyOrData)) {
            $this->m_parent->transientSet($this->prefixedKey($keyOrData), $data);
            return;
        }

        if (!is_array($keyOrData)) {
            throw new TypeError("Parameter \$keys expects a string or an array of strings.");
        }

        foreach ($keyOrData as $key => $data) {
            $this->m_parent->transientSet($this->prefixedKey($key), $data);
        }
    }

    /**
     * @inheritDoc
     */
    public function remove($keys): void
    {
        if (is_string($keys)) {
            $this->m_parent->remove($this->prefixedKey($keys));
            return;
        }

        if (!is_array($keys) || !all($keys, "is_string")) {
            throw new TypeError("Parameter \$keys expects a string or an array of strings.");
        }

        foreach ($keys as $key) {
            $this->m_parent->remove($this->prefixedKey($key));
        }
    }

    /**
     * Fetches all session data in the underlying Session whose key starts with the prefix.
     * @inheritDoc
     */
    public function all(): array
    {
        return array_filter($this->m_parent->all(), function(string $key): bool {
            return str_starts_with($key, $this->m_prefix);
        }, ARRAY_FILTER_USE_KEY);
    }
}
