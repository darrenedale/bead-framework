<?php

namespace Equit\Session;

// TODO look into whether we can support PHP8 and PHP7 without this duplication - currently PHP8 complains with an
//  E_NOTICE if the trait is defined without a return type of mixed for offsetGet(), which PHP7.4 won't recognise.
if (PHP_MAJOR_VERSION >= 8) {
    /**
     * Shared implementation of ArrayAccess interface for session DataAccessors
     */
    trait ImplementsArrayAccess
    {
        /**
         * Constrain trait users to provide a has() method.
         */
        public abstract function has(string $key): bool;

        /**
         * Constrain trait users to provide a get() method.
         */
        public abstract function get(string $key);

        /**
         * Constrain trait users to provide a set() method.
         */
        public abstract function set(string $key, mixed $value): void;

        /**
         * Constrain trait users to provide a remove() method.
         */
        public abstract function remove(string $key): void;

        /**
         * Check whether an offset exists.
         *
         * This is part of the ArrayAccess interface.
         *
         * @param mixed $offset The offset to check.
         *
         * @return bool `true` if the session key exists, `false` otherwise.
         */
        public function offsetExists(mixed $offset): bool
        {
            return is_string($offset) && $this->has($offset);
        }

        /**
         * Fetch the value for an offset.
         *
         * This is part of the ArrayAccess interface.
         *
         * @param mixed $offset The offset to retrieve.
         *
         * @return mixed The value at the offset.
         */
        public function offsetGet(mixed $offset): mixed
        {
            return (is_string($offset) ? $this->get($offset) : null);
        }

        /**
         * Set the value for an offset.
         *
         * This is part of the ArrayAccess interface.
         *
         * @param mixed $offset The offset to set.
         * @param mixed $value The value to set.
         */
        public function offsetSet(mixed $offset, mixed $value): void
        {
            if (!is_string($offset)) {
                return;
            }

            $this->set($offset, $value);
        }

        /**
         * Unset the value for an offset.
         *
         * This is part of the ArrayAccess interface.
         *
         * @param mixed $offset The offset to unset.
         */
        public function offsetUnset(mixed $offset): void
        {
            if (!is_string($offset)) {
                return;
            }

            $this->remove($offset);
        }
    }
} else {
    /**
     * Shared implementation of ArrayAccess interface for session DataAccessors
     */
    trait ImplementsArrayAccess
    {
        /**
         * Constrain trait users to provide a has() method.
         */
        public abstract function has(string $key): bool;

        /**
         * Constrain trait users to provide a get() method.
         */
        public abstract function get(string $key);

        /**
         * Constrain trait users to provide a set() method.
         */
        public abstract function set(string $key, $value): void;

        /**
         * Constrain trait users to provide a remove() method.
         */
        public abstract function remove(string $key): void;

        /**
         * Check whether an offset exists.
         *
         * This is part of the ArrayAccess interface.
         *
         * @param mixed $offset The offset to check.
         *
         * @return bool `true` if the session key exists, `false` otherwise.
         */
        public function offsetExists($offset): bool
        {
            return is_string($offset) && $this->has($offset);
        }

        /**
         * Fetch the value for an offset.
         *
         * This is part of the ArrayAccess interface.
         *
         * @param mixed $offset The offset to retrieve.
         *
         * @return mixed The value at the offset.
         */
        public function offsetGet($offset)
        {
            return (is_string($offset) ? $this->get($offset) : null);
        }

        /**
         * Set the value for an offset.
         *
         * This is part of the ArrayAccess interface.
         *
         * @param mixed $offset The offset to set.
         * @param mixed $value The value to set.
         */
        public function offsetSet($offset, $value): void
        {
            if (!is_string($offset)) {
                return;
            }

            $this->set($offset, $value);
        }

        /**
         * Unset the value for an offset.
         *
         * This is part of the ArrayAccess interface.
         *
         * @param mixed $offset The offset to unset.
         */
        public function offsetUnset($offset): void
        {
            if (!is_string($offset)) {
                return;
            }

            $this->remove($offset);
        }
    }
}
