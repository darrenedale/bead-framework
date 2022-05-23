<?php

namespace Equit\Session;

/**
 * Default implementation of session prefix handling.
 */
trait CanPrefix
{
    public function prefixed(string $prefix): DataAccessor
    {
        /** @var $this DataAccessor */
        return new PrefixedAccessor($prefix, $this);
    }
}
