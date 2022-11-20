<?php

namespace Bead\Session;

/**
 * Default implementation of session prefix handling.
 */
trait CanPrefix
{
    /**
     * Fetch a proxy session accessor that can be used to fetch data with a given prefix.
     *
     * When accessing lots of session data with a common prefix, call this and fetch the data from the returned object
     * instead to save typing, and to ease maintenance and code readability. Reads from and writes to the session will
     * update the underlying session data from which the prefix proxy was sourced - for all intents and purposes, the
     * proxy object is the same as the original session object.
     *
     * @param string $prefix The prefix.
     *
     * @return DataAccessor The proxy session accessor.
     */
    public function prefixed(string $prefix): DataAccessor
    {
        /** @var $this DataAccessor */
        return new PrefixedAccessor($prefix, $this);
    }
}
