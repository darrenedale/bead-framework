<?php

namespace Bead\Polyfill
{
    function array_is_list(array $arr): bool
    {
        $idx = 0;

        foreach (array_keys($arr) as $key) {
            if ($key !== $idx) {
                return false;
            }

            ++$idx;
        }

        return true;
    }
}

namespace
{
    if (!function_exists("array_is_list")) {
        function array_is_list(array $arr): bool
        {
            return Bead\Polyfill\array_is_list($arr);
        }
    }
}
