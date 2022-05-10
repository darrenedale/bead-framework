<?php

namespace Equit\Util;

interface TimestampInterval {
    /** Representation of the interval in seconds. */
    public function inSeconds(): int;
}
