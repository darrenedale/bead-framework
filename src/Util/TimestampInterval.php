<?php

namespace Bead\Util;

interface TimestampInterval
{
    /** Representation of the interval in seconds. */
    public function inSeconds(): int;
}
