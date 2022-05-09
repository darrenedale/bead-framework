<?php

/**
 * @file traversable.php
 * @author Darren Edale
 * @version 1.2.0
 * @date Jan 2018
 * @ingroup generic-includes
 *
 * @brief Polyfills and other useful functions to work with Traversable objects.
 *
 * These functions replciate and complement the array processing functions PHP provides.
 *
 * @package libequit
 */

namespace Equit\Traversable;

use Traversable;

/**
 * Transform the entries in a traversable collection using a function.
 *
 * The function is applied to each entry in the Traversable collection. The item is modified in-place - the Traversable
 * will contain the transformed items after the call. The Traversable is also returned.
 *
 * @param \Traversable $collection The collection to transform.
 * @param callable $fn The function to use to transform the entries.
 *
 * @return \Traversable The transformed Traversable.
 */
function & transform(Traversable &$collection, callable $fn): Traversable
{
    foreach ($collection as & $item) {
        $item = $fn($item);
    }

    return $collection;
}

/**
 * Reduce a Traversable collection by successive application of a function.
 *
 * The function receives each item in the traversable, along with the current reduced value. The reduced value is
 * updated to the return value of each call, and the final return value is the final reduced value.
 *
 * @param \Traversable $collection
 * @param callable $fn
 * @param $init
 *
 * @return mixed|null
 */
function reduce(Traversable $collection, callable $fn, $init = null)
{
    $ret = $init;

    foreach ($collection as $item) {
        $ret = $fn($item, $ret);
    }

    return $ret;
}

/**
 * Calculate the accumulation of a Traversable collection.
 *
 * The function receives each item in the traversable, along with the current accumulated value. YOu can provide a
 * callable to do the accumulation; if not, arithmetic addition is used.
 *
 * @param \Traversable $collection The collection to accumulate.
 * @param callable|null $fn An optional function to perform the accumulation. Defaults to arithmetic addition.
 * @param mixed $init The initial value to start the accumulation. Defaults to 0.
 *
 * @return mixed|null
 */
function accumulate(Traversable $collection, callable $fn = null, $init = 0)
{
    static $add = null;

    if (!isset($fn)) {
        if (!isset($add)) {
            $add = function ($a, $b) {
                return $a + $b;
            };
        }

        $fn = $add;
    }

    $ret = $init;

    foreach ($collection as $item) {
        $ret = $fn($item, $ret);
    }

    return $ret;
}

